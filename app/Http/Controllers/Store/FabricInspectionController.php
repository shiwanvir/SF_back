<?php

namespace App\Http\Controllers\Store;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Models\stores\StoRollDescription;
use App\Models\stores\PoOrderDetails;
use App\Models\store\StoRollFabricinSpection;
use App\Models\Stores\RollPlan;
use App\Models\Store\GrnHeader;
use App\Models\Store\GrnDetail;
use App\Models\Store\FabricInspection;
use App\Models\Finance\Transaction;
use App\Models\Store\StockTransaction;
use App\Models\Store\Stock;
use App\Models\Org\ConversionFactor;
use App\Models\Org\UOM;
use App\Models\Finance\PriceVariance;
use App\Models\Merchandising\ShopOrderHeader;
use App\Models\Merchandising\ShopOrderDetail;
use App\Models\Merchandising\Item\Item;
use App\Models\stores\RMPlan;
use App\Models\stores\RMPlanHeader;
use App\Libraries\InventoryValidation;
use Exception;
use Illuminate\Support\Facades\DB;

use App\Libraries\AppAuthorize;


class FabricInspectionController extends Controller
{
  var $authorize = null;
  public function __construct()
  {
    //add functions names to 'except' paramert to skip authentication
    $this->middleware('jwt.verify', ['except' => ['index']]);
    $this->authorize = new AppAuthorize();
  }

  public function index(Request $request)
  {
    $type = $request->type;
    if($type == 'datatable')   {
      $data = $request->all();
      return response($this->datatable_search($data));
    }
    else if($type == 'autoInvoice')    {
      $search = $request->search;
      return response($this->autocomplete_search_invoice($search));
    }

    else if($type == 'autoBatchNO'){
      $search = $request->search;
      return response($this->autocomplete_search_batchNo($search));
    }
    else if($type == 'autoBatchNoFilter'){
      $inv_no = $request->inv_no;
      $batch_no=$request->batch_no;
      return ($this->autocomplete_search_bacth_filter($inv_no,$batch_no));
    }
    else if($type == 'autoItemCodeFilter'){
      $inv_no = $request->inv_no;
      $batch_no=$request->batch_no;
      $item_code = $request->search;
      //dd($item_code);
      return ($this->autocomplete_search_item_code_filter($item_code,$inv_no,$batch_no));
    }
    else if($type=='autoStatusTypes'){
      $search = $request->search;
      return response($this->autocomplete_search_inspection_status($search));
    }

      else {
      $active = $request->active;
      $fields = $request->fields;
      return response([
        'data' => $this->list($active , $fields)
      ]);
    }
  }


  //get a Color
  public function show($id)
  {
    if($this->authorize->hasPermission('FABRIC_INSPECTION_VIEW'))//check permission
    {
      //$getHeaderData=RMPlan::join('store_grn_detail','store_rm_plan.grn_detail_id','=','store_grn_detail.grn_detail_id')->where('store_grn_detail.grn_detail_id','=',$id)->first();
      //dd($getHeaderData);
      $status=1;
      $isInspectionAllowed=1;
      $arival_status=['RECEIVED','INSPECTION_SAVED'];
      $grnDetails = GrnDetail::join('store_rm_plan','store_grn_detail.grn_detail_id','=','store_rm_plan.grn_detail_id')
      ->join('store_grn_header','store_grn_detail.grn_id','=','store_grn_header.grn_id')
      ->join('org_store_bin','store_rm_plan.bin','=','org_store_bin.store_bin_id')
      ->join('item_master','store_grn_detail.item_code','=','item_master.master_id')
      ->join('store_rm_plan_header','store_rm_plan.rm_plan_header_id','=','store_rm_plan_header.rm_plan_header_id')
       ->where(function($q) use($arival_status) {
        $q->where('store_rm_plan_header.confirm_status','=',$arival_status['0'])
        ->Orwhere('store_rm_plan_header.confirm_status','=',$arival_status['1']);
    })
      //->where('store_rm_plan.invoice_no','=',$getHeaderData->invoice_no)
      ->where('store_rm_plan.rm_plan_header_id','=',$id)
      ->where('store_grn_detail.status','=',$status)
      ->where('store_grn_detail.inspection_allowed','=',$isInspectionAllowed)
      ->select('store_rm_plan.*','store_rm_plan.inspection_status','org_store_bin.store_bin_name','store_rm_plan.actual_qty as previous_received_qty','store_rm_plan.grn_detail_id','store_rm_plan.inspection_status as previous_status_name','store_grn_detail.grn_qty','store_grn_detail.item_code','item_master.master_code','store_grn_header.grn_number')
      ->orderBy('roll_or_box_no', 'ASC')
      ->get();
      if(count($grnDetails) ==0){
            //dd($grnDetails);
      return response([ 'data'  => ['data'=>null]
                      ]);
      }
      else{
        $itemData=Item::select("*")->where('master_id','=',$grnDetails[0]['item_code'])->first();
        $invoice = GrnHeader::select('*')->where('inv_number','=',$grnDetails[0]['invoice_no'])->first();
        $batch= RMPlan::select('*')->where('batch_no','=',$grnDetails[0]['batch_no'])->first();
        $inspection_status=$grnDetails[0]['confirm_status'];
        $grn_detail_id=0;
        return response([ 'data'  => ['data'=>$grnDetails,
                                    'item'=>$itemData,
                                      'invoiceNo'=>$invoice,
                                      'batchNo'=>$batch,
                                      'grn_detail_id'=>$grn_detail_id,
                                      'inspection_status'=>$inspection_status
                                    ]
                            ]);
          }

      }
      else{
        return response($this->authorize->error_response(), 401);
      }
  }





  public function store(Request $request)
  {
    if($this->authorize->hasPermission('FABRIC_INSPECTION_EDIT'))//check permission
    {
        $data=$request->data;
        $status_validation=InventoryValidation::inspection_status_validation($data);
         if($status_validation==true){
            for($i=0;$i<sizeof($data);$i++){
              //save data on fabric inspection table
            $rmPlan=RMPlan::find($data[$i]['rm_plan_id']);
            $rmPlan->actual_qty=$data[$i]['actual_qty'];
            $rmPlan->received_qty=$data[$i]['received_qty'];
            $rmPlan->width=$data[$i]['width'];
            $rmPlan->actual_width=$data[$i]['actual_width'];
            $rmPlan->shade=$data[$i]['shade'];
            $rmPlan->inspection_status=$data[$i]['inspection_status'];
            $rmPlan->lab_comment=$data[$i]['lab_comment'];
            //$rmPlan->comment=$data[$i]['comment'];
            $rmPlan->status=1;
            $rmPlan->save();

          }

          $rmPlanHeader=RMPlanHeader::find($data[0]['rm_plan_header_id']);
          $rmPlanHeader->confirm_status="INSPECTION_SAVED";
          $rmPlanHeader->save();
            return response([ 'data' => [
              'message' => 'Fabric Inspection Saved Successfully',
              'status' => 1
              ]
            ], Response::HTTP_CREATED );

      }
      else if($status_validation==false){
        return response([ 'data' => [
          'message' => 'Incorrect Inspection status',
          'status' => 0
          ]
        ], Response::HTTP_CREATED );
      }
    }
    else{
      return response($this->authorize->error_response(), 401);
    }
  }

  private function setGrnQtytemparlyZero($data){
    for($i=0;$i<sizeof($data);$i++){
    $grnDetails=GrnDetail::find($data[$i]['grn_detail_id']);
    $grnDetails->grn_qty=0;
    $grnDetails->save();
    }
    //return true;
  }

  public function destroy($id){
  $rmData=RMPlan::where('grn_detail_id','=',$id)->get();
/*  foreach ($rmData as $value) {

    if($rmPlanHeader->confirm_status!="PLANNED"){
      return response([
        'data' => [
          'message' => 'Roll Plan deactivate failed',
          'status'=>0
        ]
        ] );
    }
  }

  foreach ($rmData as $value) {
    $value['status']=0;
    $rmPlanHeader=RMPlanHeader::find($value['rm_plan_header_id']);
    $rmPlanHeader->status=0;
    $rmPlanHeader->save();
    $value->save();
  }
  //$rmData->save();
  return response([
    'data' => [
      'message' => 'Roll Plan deactivate successfully.',
      'status'=>1
    ]
    ] );*/
  }
  private function autocomplete_search_invoice($search){

    $invoice_list = GrnHeader::select('inv_number')->distinct('inv_number')
    ->where([['inv_number', 'like', '%' . $search . '%'],]) ->get();
    return $invoice_list;



  }
  private function autocomplete_search_batchNo($search){
    $invoice_list = GrnHeader::select('batch_no')->distinct('batch_no')
    ->where([['batch_no', 'like', '%' . $search . '%'],]) ->get();
    return $invoice_list;

  }
  private function autocomplete_search_inspection_status($search){
    //dd($search);
    $inspectionStatus=DB::table('store_inspec_status')->where('status_name','like','%'.$search.'%')->pluck('status_name');
    return $inspectionStatus;

  }

  public function autocomplete_search_bacth_filter($inv_no,$batch_no){
    //dd("dadad");
    $batch_list = RMPlan::select('batch_no')->distinct('batch_no')
    ->where([['batch_no', 'like', '%' . $batch_no . '%'],])
    ->where('invoice_no', '=', $inv_no) ->get();
//dd($batch_list);
   return $batch_list;

  }

public function autocomplete_search_item_code_filter($item_code,$inv_no,$batch_no){
    $item_list = RMPlan::join('store_grn_detail','store_rm_plan.grn_detail_id','=','store_grn_detail.grn_detail_id')
    ->join('item_master','store_grn_detail.item_code','=','item_master.master_id')
    ->select('item_master.master_code','item_master.master_id','item_master.master_description')
    ->where('store_rm_plan.batch_no','=', $batch_no)
    ->where('invoice_no','=', $inv_no)
    ->where([['item_master.master_code', 'like', '%' . $item_code . '%'],])
    ->groupBy('item_master.master_code')
    ->get();
    return $item_list;

  }

public function confrim_inspection(Request $request){
  $data=$request->dataset;
  $status_validation=InventoryValidation::inspection_status_validation($data);
  if($status_validation==true){
  foreach ($data as $value) {
    $rmPlan=RMPlan::find($value['rm_plan_id']);
    $rmPlan->confirm_status="CONFIRMED";
    $rmPlan->save();
    $rmPlanHeader=RMPlanHeader::find($rmPlan->rm_plan_header_id);
    $rmPlanHeader->confirm_status="CONFIRMED";
    $rmPlanHeader->save();
  }
  return response([
    'data' => [
      'message' => 'Fabric Inspection Confirmed Successfully.',
      'status'=>1
    ]
  ] );
}
else if($status_validation==false){
  return response([ 'data' => [
    'message' => 'Incorrect Inspection status',
    'status' => 0
    ]
  ], Response::HTTP_CREATED );
}
}
    public function search_rollPlan_details(Request $request){
      $batch_no=$request->batchNo;
      $invoice_no=$request->invoiceNo;
      $item_code=$request->itemCode;
      $status=1;
      $grnSatatus="RECEIVED";
      $locId=auth()->payload()['loc_id'];
    /*  $checkInspection=FabricInspection::select('store_grn_detail.grn_detail_id')
      ->join('store_roll_plan','store_fabric_inspection.roll_plan_id','=','store_roll_plan.roll_plan_id')
      ->join('store_grn_detail','store_roll_plan.grn_detail_id','=','store_grn_detail.grn_detail_id')
      ->join('store_grn_header','store_grn_detail.grn_id','=','store_grn_header.grn_id')
      ->where('store_fabric_inspection.invoice_no','=',$invoice_no)
      ->where('store_fabric_inspection.batch_no','=',$batch_no)
      ->where('store_grn_detail.item_code','=',$item_code)->first();
      if($checkInspection['grn_detail_id']!=null){
          return $this->show($checkInspection['grn_detail_id']);
      }*/
      $rmPlanDetails=DB::SELECT("SELECT store_rm_plan.*,org_store_bin.store_bin_name,store_grn_detail.grn_qty,store_grn_header.grn_number
          From store_rm_plan
          INNER JOIN store_rm_plan_header on store_rm_plan.rm_plan_header_id=store_rm_plan_header.rm_plan_header_id
          INNER JOIN store_grn_detail on store_rm_plan.grn_detail_id=store_grn_detail.grn_detail_id
          INNER JOIN store_grn_header on store_grn_detail.grn_id=store_grn_header.grn_id
          INNER JOIN org_store_bin on store_rm_plan.bin=org_store_bin.store_bin_id
          WHERE store_rm_plan.invoice_no='".$invoice_no."'
          AND store_rm_plan.batch_no='".$batch_no."'
          AND store_grn_detail.item_code='".$item_code."'
          AND store_grn_detail.arrival_status='".$grnSatatus."'
          AND store_grn_header.location='".$locId."'
          ORDER BY roll_or_box_no ASC
          "
          );
        $grn_detail_id=0;
          return response([ 'data'  => ['data'=>$rmPlanDetails,
                                      'item'=>$item_code,
                                        'invoiceNo'=>$invoice_no,
                                        'batchNo'=>$batch_no,
                                        'grn_detail_id'=>$grn_detail_id
                                      ]
                              ]);
    }

    //get searched Colors for datatable plugin format
    private function datatable_search($data)
    {

          $start = $data['start'];
          $length = $data['length'];
          $draw = $data['draw'];
          $search = $data['search']['value'];
          $order = $data['order'][0];
          $order_column = $data['columns'][$order['column']]['data'];
          $order_type = $order['dir'];
          $itemCategoryCode="FAB";
          $isInspectionAllowed=1;
          $inspection_list=null;
          $inspection_count=null;
          $grn_type=$data['grn_type'];
          $rmHeaderstatus="PLANNED";
          if($grn_type=="AUTO"){
          $inspection_list = DB::table('store_rm_plan')->join('store_grn_detail','store_rm_plan.grn_detail_id','=','store_grn_detail.grn_detail_id')
          ->join('store_rm_plan_header','store_rm_plan.rm_plan_header_id','=','store_rm_plan_header.rm_plan_header_id')
          ->join('store_grn_header','store_grn_detail.grn_id','=','store_grn_header.grn_id')
          ->join('store_grn_type','store_grn_header.grn_type','store_grn_type.grn_type_id')
          ->join('merc_po_order_header','store_grn_header.po_number','=','merc_po_order_header.po_id')
          ->join('item_master','store_grn_detail.item_code','=','item_master.master_id')
          ->join('item_category','item_master.category_id','=','item_category.category_id')
          ->select('store_rm_plan.*','store_grn_header.grn_number','merc_po_order_header.po_number','item_master.master_code','store_rm_plan_header.confirm_status',DB::raw("SUM(actual_qty) as batch_wise_qty"))
          ->where('item_category.category_code','=',$itemCategoryCode)
          ->where('store_grn_detail.inspection_allowed','=',$isInspectionAllowed)
          ->where('store_grn_type.grn_type_code','=',$grn_type)
          ->where('store_rm_plan_header.confirm_status','!=',$rmHeaderstatus)
          ->where(function($q) use($search) {
              $q ->where('store_grn_header.grn_number'  , 'like', $search.'%' )
                ->orWhere('merc_po_order_header.po_number'  , 'like', $search.'%' )
                ->orWhere('item_master.master_code','like',$search.'%')
                ->orWhere('store_rm_plan.invoice_no','like',$search.'%')
                ->orWhere('store_rm_plan_header.confirm_status','like',$search.'%')
                ->orWhere('store_rm_plan.batch_no','like',$search.'%');
          })
          ->groupBy('store_rm_plan_header.rm_plan_header_id');
          if($order_column=="confirm_status"){
            $inspection_list->orderBy('store_rm_plan_header.confirm_status', $order_type);
          }
          else if($order_column=="status"){
            $inspection_list->orderBy('store_rm_plan_header.confirm_status', $order_type);

          }
          else {

            $inspection_list=$inspection_list->orderBy($order_column, $order_type);

          }
          $inspection_list=$inspection_list->offset($start)->limit($length)->get();

          $inspection_count = DB::table('store_rm_plan')
           ->join('store_grn_detail','store_rm_plan.grn_detail_id','=','store_grn_detail.grn_detail_id')
          ->join('store_rm_plan_header','store_rm_plan.rm_plan_header_id','=','store_rm_plan_header.rm_plan_header_id')
          ->join('store_grn_header','store_grn_detail.grn_id','=','store_grn_header.grn_id')
          ->join('store_grn_type','store_grn_header.grn_type','store_grn_type.grn_type_id')
          ->join('merc_po_order_header','store_grn_header.po_number','=','merc_po_order_header.po_id')
          ->join('item_master','store_grn_detail.item_code','=','item_master.master_id')
          ->join('item_category','item_master.category_id','=','item_category.category_id')
          ->select('store_rm_plan.*','store_grn_header.grn_number','merc_po_order_header.po_number','item_master.master_code','store_grn_detail.grn_qty')
          ->where('item_category.category_code','=',$itemCategoryCode)
          ->where('store_grn_detail.inspection_allowed','=',$isInspectionAllowed)
          ->where('store_grn_type.grn_type_code','=',$grn_type)
          ->where('store_rm_plan_header.confirm_status','!=',$rmHeaderstatus)
          ->where(function($q) use($search) {
              $q ->where('store_grn_header.grn_number'  , 'like', $search.'%' )
                ->orWhere('store_grn_header.po_number'  , 'like', $search.'%' )
                ->orWhere('item_master.master_code','like',$search.'%')
                ->orWhere('store_rm_plan_header.confirm_status','like',$search.'%')
                ->orWhere('store_rm_plan.invoice_no','like',$search.'%');
          })
          ->groupBy('store_rm_plan_header.rm_plan_header_id')->get()
          ->count();
        }
        else if($grn_type=="MANUAL"){

          $inspection_list = DB::table('store_rm_plan')->join('store_grn_detail','store_rm_plan.grn_detail_id','=','store_grn_detail.grn_detail_id')
          ->join('store_rm_plan_header','store_rm_plan.rm_plan_header_id','=','store_rm_plan_header.rm_plan_header_id')
          ->join('store_grn_header','store_grn_detail.grn_id','=','store_grn_header.grn_id')
          ->join('store_grn_type','store_grn_header.grn_type','store_grn_type.grn_type_id')
          ->join('merc_po_order_manual_header','store_grn_header.po_number','=','merc_po_order_manual_header.po_id')
          ->join('item_master','store_grn_detail.item_code','=','item_master.master_id')
          ->join('item_category','item_master.category_id','=','item_category.category_id')
          ->select('store_rm_plan.*','store_grn_header.grn_number','merc_po_order_manual_header.po_number','item_master.master_code','store_rm_plan_header.confirm_status',DB::raw("SUM(actual_qty) as batch_wise_qty"))
          ->where('item_category.category_code','=',$itemCategoryCode)
          ->where('store_grn_detail.inspection_allowed','=',$isInspectionAllowed)
          ->where('store_grn_type.grn_type_code','=',$grn_type)
          ->where('store_rm_plan_header.confirm_status','!=',$rmHeaderstatus)
          ->where(function($q) use($search) {
              $q ->where('store_grn_header.grn_number'  , 'like', $search.'%' )
                ->orWhere('store_grn_header.po_number'  , 'like', $search.'%' )
                ->orWhere('item_master.master_code','like',$search.'%')
                ->orWhere('store_rm_plan_header.confirm_status','like',$search.'%')
                ->orWhere('store_rm_plan.invoice_no','like',$search.'%');
          })
          ->groupBy('store_rm_plan_header.rm_plan_header_id');
          if($order_column=="confirm_status"){
            $inspection_list->orderBy('store_rm_plan_header.confirm_status', $order_type);
          }
          else if($order_column=="status"){
            $inspection_list->orderBy('store_rm_plan_header.confirm_status', $order_type);

          }
          else {

            $inspection_list=$inspection_list->orderBy($order_column, $order_type);

          }
          $inspection_list=$inspection_list->offset($start)->limit($length)->get();


          $inspection_count =DB::table('store_rm_plan')->join('store_grn_detail','store_rm_plan.grn_detail_id','=','store_grn_detail.grn_detail_id')
          ->join('store_rm_plan_header','store_rm_plan.rm_plan_header_id','=','store_rm_plan_header.rm_plan_header_id')
          ->join('store_grn_header','store_grn_detail.grn_id','=','store_grn_header.grn_id')
          ->join('store_grn_type','store_grn_header.grn_type','store_grn_type.grn_type_id')
          ->join('merc_po_order_manual_header','store_grn_header.po_number','=','merc_po_order_manual_header.po_id')
          ->join('item_master','store_grn_detail.item_code','=','item_master.master_id')
          ->join('item_category','item_master.category_id','=','item_category.category_id')
          ->select('store_rm_plan.*','store_grn_header.grn_number','merc_po_order_manual_header.po_number','item_master.master_code','store_grn_detail.grn_qty')
          ->where('item_category.category_code','=',$itemCategoryCode)
          ->where('store_grn_detail.inspection_allowed','=',$isInspectionAllowed)
          ->where('store_grn_type.grn_type_code','=',$grn_type)
          ->where('store_rm_plan_header.confirm_status','!=',$rmHeaderstatus)
         ->where(function($q) use($search) {
              $q ->where('store_grn_header.grn_number'  , 'like', $search.'%' )
                ->orWhere('store_grn_header.po_number'  , 'like', $search.'%' )
                ->orWhere('item_master.master_code','like',$search.'%')
                ->orWhere('store_rm_plan_header.confirm_status','like',$search.'%')
                ->orWhere('store_rm_plan.invoice_no','like',$search.'%');
          });
          $inspection_count=$inspection_count
          ->groupBy('store_rm_plan_header.rm_plan_header_id')->get()
          ->count();

        }

          //dd($inspection_count);
          echo json_encode([
              "draw" => $draw,
              "recordsTotal" => $inspection_count,
              "recordsFiltered" => $inspection_count,
              "data" => $inspection_list
          ]);

    }


    //update a Color
    public function update(Request $request, $id)
    {        //
          $data=$request->data;
          $this->setGrnQtytemparlyZero($data);
          //loop through data set
        for($i=0;$i<sizeof($data);$i++){
          //get related line
          $fabricInspection = FabricInspection::find($data[$i]['fab_inspection_id']);
          $fabricInspection->roll_plan_id=$data[$i]['roll_plan_id'];
          $fabricInspection->lot_no=$data[$i]['lot_no'];
          $fabricInspection->invoice_no=$data[$i]['invoice_no'];
          $fabricInspection->batch_no=$data[$i]['batch_no'];
          $fabricInspection->roll_no=$data[$i]['roll_no'];
          $fabricInspection->qty=$data[$i]['qty'];
          //get updated qty
          if($data[$i]['previous_status_name']=="PASS"){
          $updated_st_qty=$data[$i]['qty']-$data[$i]['previous_received_qty'];
          }
        else if($data[$i]['previous_status_name']!="PASS"){
          $updated_st_qty=$data[$i]['qty'];
        }
          //dd($fabricInspection->qty);
          $fabricInspection->received_qty=$data[$i]['received_qty'];
          $fabricInspection->bin=$data[$i]['bin'];
          $fabricInspection->width=$data[$i]['width'];
          $fabricInspection->shade=$data[$i]['shade'];
          $fabricInspection->inspection_status=$data[$i]['status_name'];
          $fabricInspection->lab_comment=$data[$i]['lab_comment'];
          $fabricInspection->comment=$data[$i]['comment'];
          $fabricInspection->status=1;
          //update inspection table
          $fabricInspection->save();
          //get inspection status pass line to update stock
          if($fabricInspection->inspection_status=='PASS'){
            //get transaction code
            $transaction = Transaction::where('trans_description', 'STOCKUPDATE')->first();
            //get roll Plan details for stock update
            $rollplanDetail=DB::SELECT("SELECT
                     store_roll_plan.grn_detail_id,
                      store_fabric_inspection.roll_plan_id,
                       store_grn_header.main_store,
                       store_grn_header.sub_store,
                       store_grn_detail.grn_detail_id,
                        store_grn_detail.grn_id,
                        store_grn_detail.grn_line_no,
                       store_grn_detail.style_id,
                      store_grn_detail.combine_id,
                      store_grn_detail.color,
                      store_grn_detail.size,
                      store_grn_detail.uom,
                      store_grn_detail.item_code,
                     store_grn_detail.po_qty,
                     store_grn_detail.standard_price,
                     store_grn_detail.purchase_price,
  store_grn_detail.grn_qty,
  store_grn_detail.bal_qty,
  store_grn_detail.original_bal_qty,
  store_grn_detail.po_details_id,
  store_grn_detail.po_number,
  store_grn_detail.maximum_tolarance,
  store_grn_detail.customer_po_id,
  item_master.standard_price,
  store_grn_detail.purchase_price,
  store_grn_detail.inventory_uom,
  store_grn_detail.shop_order_id,
  store_grn_detail.shop_order_detail_id,
  store_roll_plan.bin
  FROM
  store_fabric_inspection
  INNER JOIN store_roll_plan ON store_roll_plan.roll_plan_id = store_fabric_inspection.roll_plan_id
  INNER JOIN store_grn_detail ON store_grn_detail.grn_detail_id = store_roll_plan.grn_detail_id
  INNER JOIN item_master ON store_grn_detail.item_code=item_master.master_id
  INNER JOIN store_grn_header ON store_grn_header.grn_id = store_grn_detail.grn_id
  WHERE store_fabric_inspection.roll_plan_id=$fabricInspection->roll_plan_id");
              //update stock transaction table
            $st = new StockTransaction;
            $st->status = 'PASS';
            $st->doc_type = $transaction->trans_code;
            $st->doc_num = $rollplanDetail[0]->grn_id;
            $st->style_id = $rollplanDetail[0]->style_id;
            $st->main_store = $rollplanDetail[0]->main_store;
            $st->sub_store = $rollplanDetail[0]->sub_store;
            $st->item_code = $rollplanDetail[0]->item_code;
            $st->size = $rollplanDetail[0]->size;
            $st->color = $rollplanDetail[0]->color;
            $st->uom = $rollplanDetail[0]->uom;
            $st->customer_po_id=$rollplanDetail[0]->customer_po_id;
            $st->qty = $updated_st_qty;
            $st->location = auth()->payload()['loc_id'];
            $st->bin = $rollplanDetail[0]->bin;
            $st->created_by = auth()->payload()['user_id'];
            $st->shop_order_id=$rollplanDetail[0]->shop_order_id;
            $st->shop_order_detail_id=$rollplanDetail[0]->shop_order_detail_id;
            $st->standard_price = $rollplanDetail[0]->standard_price;
            $st->purchase_price = $rollplanDetail[0]->purchase_price;
            $st->direction="+";
            $st->save();
            $po_detail_id=$rollplanDetail[0]->po_details_id;
            $loc= auth()->payload()['loc_id'];
            $grnDetails=GrnDetail::find($data[$i]['grn_detail_id']);
            $grnDetails->grn_qty=$grnDetails->grn_qty+$fabricInspection->qty;
            $grnDetails->save();

            //*balance qty suould be get from shop order detail level*
            $balanceQty=DB::SELECT("SELECT min(bal_qty)
                          from store_grn_detail
                          where po_details_id=$po_detail_id");
            //find exact line of stock(old way)
            $cus_po=$rollplanDetail[0]->customer_po_id;
            $style_id=$rollplanDetail[0]->style_id;
            $item_code=$rollplanDetail[0]->item_code;
            $size=$rollplanDetail[0]->size;
          //  $size=1;
            $color=$rollplanDetail[0]->color;
            $main_store=$rollplanDetail[0]->main_store;
            $sub_store=$rollplanDetail[0]->sub_store;
            $bin=$rollplanDetail[0]->bin;
          /*  if($size==null){
              $size_serach=0;
            }
            else {
              $size_serach=$size;
            }*/
            //find exact line on stock table
            $findStoreStockLine=DB::SELECT ("SELECT * FROM store_stock
                                             where item_id=$st->item_code
                                             AND shop_order_id=$st->shop_order_id
                                             AND style_id=$st->style_id
                                             AND shop_order_detail_id=$st->shop_order_detail_id
                                             AND bin=$bin
                                             AND store=$main_store
                                             AND sub_store=$sub_store
                                             AND location=$st->location");
            //if related line is not available
          if($findStoreStockLine==null){
            //update the stock table
            $storeUpdate=new Stock();
            $storeUpdate->shop_order_id=$rollplanDetail[0]->shop_order_id;
            $storeUpdate->shop_order_detail_id=$rollplanDetail[0]->shop_order_detail_id;
            $storeUpdate->style_id = $rollplanDetail[0]->style_id;
            $storeUpdate->item_id= $rollplanDetail[0]->item_code;
            $storeUpdate->size = $rollplanDetail[0]->size;
            $storeUpdate->color = $rollplanDetail[0]->color;
            $storeUpdate->location = auth()->payload()['loc_id'];
            $storeUpdate->store = $rollplanDetail[0]->main_store;
            $storeUpdate->sub_store =$rollplanDetail[0]->sub_store;
            $storeUpdate->bin = $rollplanDetail[0]->bin;
            $storeUpdate->standard_price = $rollplanDetail[0]->standard_price;
            $storeUpdate->purchase_price = $rollplanDetail[0]->purchase_price;
            //check price variance
            if($storeUpdate->standard_price!=$storeUpdate->purchase_price){
              $priceVariance= new PriceVariance;
              $priceVariance->item_id=$rollplanDetail[0]->item_code;
              $priceVariance->standard_price=$rollplanDetail[0]->standard_price;
              $priceVariance->purchase_price =$rollplanDetail[0]->purchase_price;
              $priceVariance->shop_order_id =$rollplanDetail[0]->shop_order_id;
              $priceVariance->shop_order_detail_id =$rollplanDetail[0]->shop_order_detail_id;
              $priceVariance->status =1;
              //save price variance
              $priceVariance->save();
            }
            //if inventory uom varid
              if($rollplanDetail[0]->uom!=$rollplanDetail[0]->inventory_uom){
                $storeUpdate->uom = $rollplanDetail[0]->inventory_uom;
                $_uom_unit_code=UOM::where('uom_id','=',$rollplanDetail[0]->inventory_uom)->pluck('uom_code');
                $_uom_base_unit_code=UOM::where('uom_id','=',$rollplanDetail[0]->uom)->pluck('uom_code');
                $ConversionFactor=ConversionFactor::select('*')
                                                    ->where('unit_code','=',$_uom_unit_code[0])
                                                    ->where('base_unit','=',$_uom_base_unit_code[0])
                                                    ->first();

                                                    $storeUpdate->qty =(double)($fabricInspection->qty*$ConversionFactor->present_factor);
                                                    //$storeUpdate->total_qty = (double)($fabricInspection->qty*$ConversionFactor->present_factor);
                                                    //$storeUpdate->tolerance_qty = (double)($rollplanDetail[0]->maximum_tolarance*$ConversionFactor->present_factor);
            }
            //if inventory uom and purchase varid
            if($rollplanDetail[0]->uom==$rollplanDetail[0]->inventory_uom){
              $storeUpdate->uom = $rollplanDetail[0]->inventory_uom;
              $storeUpdate->qty =(double)($fabricInspection->qty);
              //$storeUpdate->total_qty = (double)($fabricInspection->qty);
              //$storeUpdate->tolerance_qty = $rollplanDetail[0]->maximum_tolarance;
            }


            //$storeUpdate->transfer_status="STOCKUPDATE";
            $storeUpdate->status=1;
            $shopOrder=ShopOrderDetail::find($rollplanDetail[0]->shop_order_detail_id);
            //if previous status pass
            if($data[$i]['previous_status_name']=="PASS"){
              $shopOrder->asign_qty=$fabricInspection->qty-(double)$data[$i]['previous_received_qty']+$shopOrder->asign_qty;
            }
            //if not
            else if ($data[$i]['previous_status_name']!="PASS"){

              $shopOrder->asign_qty=$fabricInspection->qty+$shopOrder->asign_qty;
            }



            $shopOrder->save();
            $storeUpdate->save();
          }

          //dd($findStoreStockLine);
          if($findStoreStockLine!=null){
              //dd($findStoreStockLine[0]->id);
              $stock=Stock::find($findStoreStockLine[0]->id);
              //if previous standerd price and new price is same
              if($stock->standard_price!=$rollplanDetail[0]->standard_price){
                $priceVariance= new PriceVariance;
                $priceVariance->item_id=$rollplanDetail[0]->item_code;
                $priceVariance->standard_price=$rollplanDetail[0]->standard_price;
                $priceVariance->purchase_price =$rollplanDetail[0]->purchase_price;
                $priceVariance->shop_order_id =$rollplanDetail[0]->shop_order_id;
                $priceVariance->shop_order_detail_id =$rollplanDetail[0]->shop_order_detail_id;
                $priceVariance->status =1;
                $priceVariance->save();
              }

              $stock->standard_price = $rollplanDetail[0]->standard_price;
              $stock->purchase_price = $rollplanDetail[0]->purchase_price;
              $shopOrder=ShopOrderDetail::find($rollplanDetail[0]->shop_order_detail_id);
              if($rollplanDetail[0]->uom!=$rollplanDetail[0]->inventory_uom){

                  //$stock->uom = $rollplanDetail[0]->inventory_uom;
                  $_uom_unit_code=UOM::where('uom_id','=',$rollplanDetail[0]->inventory_uom)->pluck('uom_code');
                  $_uom_base_unit_code=UOM::where('uom_id','=',$rollplanDetail[0]->uom)->pluck('uom_code');
                  $ConversionFactor=ConversionFactor::select('*')
                                                      ->where('unit_code','=',$_uom_unit_code[0])
                                                      ->where('base_unit','=',$_uom_base_unit_code[0])
                                                      ->first();
                                                      //dd((double)$stock->inv_qty-(double)$data[$i]['previous_received_qty']);
                                                      if($data[$i]['previous_status_name']=="PASS"){
                                                      $stock->qty =(double)$stock->qty-(double)$data[$i]['previous_received_qty']+(double)($fabricInspection->qty*$ConversionFactor->present_factor);
                                                      //$stock->total_qty = (double)$stock->total_qty-(double)$data[$i]['previous_received_qty']+(double)($fabricInspection->qty*$ConversionFactor->present_factor);
                                                      $shopOrder->asign_qty=$fabricInspection->qty-(double)$data[$i]['previous_received_qty']+$shopOrder->asign_qty;
                                                    }
                                                  else if ($data[$i]['previous_status_name']!="PASS"){

                                                      $stock->qty =(double)$stock->qty+(double)($fabricInspection->qty*$ConversionFactor->present_factor);
                                                      //$stock->total_qty = (double)$stock->total_qty+(double)($fabricInspection->qty*$ConversionFactor->present_factor);
                                                      $shopOrder->asign_qty=$fabricInspection->qty+$shopOrder->asign_qty;
                                                    }


                                                      //$stock->tolerance_qty = (double)($rollplanDetail[0]->maximum_tolarance*$ConversionFactor->present_factor);
                                                    //  if($i==1)


              }
              if($rollplanDetail[0]->uom==$rollplanDetail[0]->inventory_uom){
                //dd($fabricInspection->qty);
                if($data[$i]['previous_status_name']=="PASS"){
                $stock->qty = (double)$stock->qty-(double)$data[$i]['previous_received_qty']+(double)($fabricInspection->qty);
                //$stock->total_qty=(double)$stock->total_qty-(double)$data[$i]['previous_received_qty']+(double)($fabricInspection->qty);
                $shopOrder->asign_qty=$fabricInspection->qty-(double)$data[$i]['previous_received_qty']+$shopOrder->asign_qty;
              }
              else if ($data[$i]['previous_status_name']!="PASS"){
                $stock->qty = (double)$stock->inv_qty+(double)($fabricInspection->qty);
                //$stock->total_qty=(double)$stock->total_qty+(double)($fabricInspection->qty);
                $shopOrder->asign_qty=$fabricInspection->qty+$shopOrder->asign_qty;
              }
                //$stock->tolerance_qty = $rollplanDetail[0]->maximum_tolarance;


              }



              $shopOrder->save();
              //$stock->total_qty=$stock->total_qty+$fabricInspection->received_qty;
             //$stock->inv_qty = $stock->inv_qty+$fabricInspection->received_qty;
             $stock->save();
            }


        }


        }

        return response([ 'data' => [
          'message' => 'Fabric Inspection Updated  sucessfully',
          'status' => 1
          ]
        ], Response::HTTP_CREATED );
    }

}
