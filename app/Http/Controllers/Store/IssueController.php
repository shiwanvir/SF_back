<?php

namespace App\Http\Controllers\Store;
use App\Libraries\UniqueIdGenerator;
use App\Models\Store\IssueDetails;
use App\Models\Store\IssueHeader;
use App\Models\Store\IssueSummary;
use App\Models\Store\MRNHeader;
use App\Models\Store\MRNDetail;
use App\Models\Store\GrnDetail;
use App\Models\stores\RollPlan;
use App\Models\Merchandising\Item\Item;
use App\Models\Merchandising\Item\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Models\Org\ConversionFactor;
use App\Models\Org\UOM;
use App\Models\Finance\Transaction;
use App\Models\Store\StockTransaction;
use Illuminate\Support\Facades\DB;
use App\Models\Merchandising\ShopOrderDetail;
use App\Models\Store\TrimPacking;
use App\Models\Store\Stock;
use App\Models\stores\RMPlan;
use App\Models\Store\StockTransactionDetail;
use App\Models\Store\StockDetails;
class IssueController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $type = $request->type;
        $fields = $request->fields;
        $active = $request->status;
        if($type == 'datatable') {
            $data = $request->all();
            return response($this->datatable_search($data));
        }elseif($type == 'issue_details'){
            $id = $request->id;
            return response(['data' => $this->getIssueDetails($id)]);
        }else if($type == 'auto')    {
            $search = $request->search;
            return response($this->autocomplete_search($search));
        }else if($type == 'auto_batch')    {
            $search = $request->search;
            return response($this->autocomplete_batch_search($search));
        }else if($type == 'auto_ins_status')    {
            $search = $request->search;
            return response($this->autocomplete_ins_status_search($search));
        }else{
            $loc_id = $request->loc;
            return response(['data' => $this->list($active, $fields, $loc_id)]);
        }
    }

    /**
    * Store a newly created resource in storage.
    *
    *@param  \Illuminate\Http\Request  $request
    *@return \Illuminate\Http\Response
    */

    private function autocomplete_ins_status_search($search)
    {
      $query = DB::table('store_inspec_status')
      ->select('*')
      //->where([['store_inspec_status.status_name', 'like', '%' . $search . '%'],])
      ->get();
      return $query;
    }

    private function autocomplete_batch_search($search)
    {
      $roll_plan = RMPlan::select('batch_no')
      ->where([['batch_no', 'like', '%' . $search . '%'],])
      ->groupBy('batch_no')
      ->distinct()
      ->get();

      return $roll_plan;
    }

    //search Color for autocomplete
    private function autocomplete_search($search)
    {
      $lists = IssueHeader::join('store_grn_type','store_grn_type.grn_type_id','=','store_issue_header.issue_type')
      ->select('*')
      ->where([['issue_no', 'like', '%' . $search . '%'],])
      //->where('store_grn_type.grn_type_code','=','AUTO')
      ->where('store_issue_header.issue_status','=','CONFIRM')
       ->get();
      return $lists;
    }

    public function store(Request $request)
    {
      $header=$request->header;
      $details=$request->dataset;
      $locId=auth()->payload()['loc_id'];
      if($header['issue_no']!=0){
        $issueNo=$header['issue_no'];
        $issueHeader=IssueHeader::where('issue_no','=',$issueNo)->first();
      }
      else if($header['issue_no']==0){
      $issueHeader=new IssueHeader();
      $unId = UniqueIdGenerator::generateUniqueId('ISSUE', auth()->payload()['company_id']);
      $mrn_line=MRNHeader::find($header['mrn_no']['mrn_id']);
      $issueHeader->mrn_id=$header['mrn_no']['mrn_id'];
      $issueHeader->issue_no=$unId;
      $issueHeader->issue_type=$mrn_line->mrn_type;
      $issueHeader->status=1;
      $issueHeader->issue_status="PENDING";
      $issueHeader->save();
    }
      for($i=0;$i<sizeof($details);$i++){

          if(empty($details[$i]['isEdited'])==false&&$details[$i]['isEdited']==1){

              $issueDetails=new IssueDetails();
              $issueDetails->issue_id=$issueHeader->issue_id;
              $issueDetails->mrn_detail_id=$request->mrn_detail_id;
              $issueDetails->item_id=$details[$i]['item_id'];


              $issueDetails->qty=$details[$i]['issue_qty'];
              $issueDetails->location_id=$locId;
              $issueDetails->store_id=$details[$i]['store_id'];
              $issueDetails->sub_store_id=$details[$i]['substore_id'];
              $issueDetails->bin=$details[$i]['bin'];
              $issueDetails->status=1;
              $issueDetails->issue_status="PENDING";
              $issueDetails->qty=$details[$i]['issue_qty'];
              $issueDetails->balance_to_return_qty=$details[$i]['issue_qty'];
              $issueDetails->stock_id=$details[$i]['stock_id'];
              $issueDetails->stock_detail_id=$details[$i]['stock_detail_id'];
              $issueDetails->rm_plan_id=$details[$i]['rm_plan_id'];
              $issueDetails->uom=$details[$i]['uom'];
              $stockDetail=StockDetails::find($details[$i]['stock_detail_id']);
              $stockDetail->issue_status="PENDING";
              $stockDetail->save();
              $issueDetails->save();

          }


      }
      return response([ 'data' => [
        'message1'=>'Issue No ',
        'message2' => ' Saved Successfully',
        'status'=>1,
        'issueNo'=>$issueHeader->issue_no,
        'issueId'=>$issueHeader->issue_id
        ]
      ], Response::HTTP_CREATED );
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {






    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    //get searched Colors for datatable plugin format
    private function datatable_search($data)
    {
      //dd(csc);

          $start = $data['start'];
          $length = $data['length'];
          $draw = $data['draw'];
          $search = $data['search']['value'];
          $order = $data['order'][0];
          $order_column = $data['columns'][$order['column']]['data'];
          $order_type = $order['dir'];

          $issue_list = IssueHeader::join('store_issue_detail','store_issue_detail.issue_id','=','store_issue_header.issue_id')
                                    ->join('item_master','store_issue_detail.item_id','=','item_master.master_id')
                                    ->join('org_store','store_issue_detail.store_id','=','org_store.store_id')
                                    ->join('org_substore','store_issue_detail.sub_store_id','=','org_substore.substore_id')
                                    ->join('org_store_bin','store_issue_detail.bin','=','org_store_bin.store_bin_id')
                                    ->join('store_grn_type','store_issue_header.issue_type','=','store_grn_type.grn_type_id')


          ->select(DB::raw("DATE_FORMAT(store_issue_header.updated_date, '%d-%b-%Y') AS st_updated_date"),'store_issue_detail.*','store_issue_header.*','org_store.store_name','store_grn_type.grn_type_code','org_substore.substore_name','org_substore.substore_name','item_master.master_description')
          ->where('store_issue_header.issue_no'  , 'like', $search.'%' )
          ->orWhere('item_master.master_code'  , 'like', $search.'%' )
          ->orWhere('org_substore.substore_name','like',$search.'%')
          ->orWhere('org_store_bin.store_bin_description','like',$search.'%')
          ->orderBy($order_column, $order_type)
          ->offset($start)->limit($length)->get();
          //dd($issue_list);

          $issue_list_count = IssueHeader::join('store_issue_detail','store_issue_detail.issue_id','=','store_issue_header.issue_id')
                                    ->join('item_master','store_issue_detail.item_id','=','item_master.master_id')
                                    ->join('org_store','store_issue_detail.store_id','=','org_store.store_id')
                                    ->join('org_substore','store_issue_detail.sub_store_id','=','org_substore.substore_id')
                                    ->join('org_store_bin','store_issue_detail.bin','=','org_store_bin.store_bin_id')


          ->select(DB::raw("DATE_FORMAT(store_issue_header.updated_date, '%d-%b-%Y') AS st_updated_date"),'store_issue_detail.*','store_issue_header.*','org_store.store_name','org_substore.substore_name','org_substore.substore_name')
          ->where('store_issue_header.issue_no'  , 'like', $search.'%' )
          ->orWhere('item_master.master_code'  , 'like', $search.'%' )
          ->orWhere('org_substore.substore_name','like',$search.'%')
          ->orWhere('org_store_bin.store_bin_description','like',$search.'%')
          ->count();

          echo json_encode([
              "draw" => $draw,
              "recordsTotal" => $issue_list_count,
              "recordsFiltered" => $issue_list_count,
              "data" => $issue_list
          ]);

    }

    public function list($active, $fields, $loc){
        $query = null;
        if($fields == null || $fields == '') {
            $query = IssueHeader::select('*');
        }else{
            $fields = explode(',', $fields);
            $query = IssueHeader::select($fields);
            if($active != null && $active != ''){
                $payload = auth()->payload();
                $query->where([['status', '=', $active], ['location', '=', $loc]]);
            }

        }
        return $query->get();
    }

    public function getIssueDetails($id){
        return IssueDetails::getIssueDetailsForReturn($id);
    }

   public function loadMrnData(Request $request){
     //dd("sad");
     $mrnType=MRNHeader::join('store_grn_type','store_mrn_header.mrn_type','=','store_grn_type.grn_type_id')
                         ->where('store_mrn_header.mrn_id','=',$request->mrn_id)
                         ->select('store_grn_type.grn_type_code','store_mrn_header.*')->first();
    $mrn_type_code=$mrnType->grn_type_code;
    $mrndetails=null;
    $locId=auth()->payload()['loc_id'];
    $status=1;
    $inspect_status="PASS";

    if($mrn_type_code=="AUTO"){
     $inventory_type="AUTO";
     $mrndetails=MRNHeader::join('store_mrn_detail','store_mrn_header.mrn_id','=','store_mrn_detail.mrn_id')
                            ->join('item_master','store_mrn_detail.item_id','=','item_master.master_id')
                            ->leftjoin('org_color','store_mrn_detail.color_id','=','org_color.color_id')
                            ->leftJoin('org_size','store_mrn_detail.size_id','=','org_size.size_id')
                            ->join('merc_shop_order_detail','store_mrn_detail.shop_order_detail_id','=','merc_shop_order_detail.shop_order_detail_id')
                            ->join('merc_po_order_details','merc_shop_order_detail.shop_order_detail_id','=','merc_po_order_details.shop_order_detail_id')
                            ->join('org_uom as for_po_uom','merc_po_order_details.uom','=','for_po_uom.uom_id')
                            ->join('org_uom as for_inv_uom','item_master.inventory_uom','=','for_inv_uom.uom_id')
                            ->select('store_mrn_header.*','store_mrn_detail.*','item_master.master_code','item_master.master_description','org_color.color_name','merc_shop_order_detail.asign_qty','merc_shop_order_detail.balance_to_issue_qty','for_po_uom.uom_code',
                            'for_inv_uom.uom_code as inventory_uom','for_inv_uom.uom_id as inventory_uom_id'
                            )
                            ->where('store_mrn_header.mrn_id','=',$request->mrn_id)
                            ->get();
  $mrndetails=DB::SELECT("SELECT
store_mrn_header.*, store_mrn_detail.*, item_master.master_code,
item_master.master_description,
org_color.color_name,
merc_shop_order_detail.asign_qty,
merc_shop_order_detail.balance_to_issue_qty,
for_po_uom.uom_code,
for_inv_uom.uom_code AS inventory_uom,
for_inv_uom.uom_id AS inventory_uom_id,
(
select IFNULL(SUM(ISD.qty),0)
FROM
store_issue_detail as ISD
WHERE
ISD.mrn_detail_id=store_mrn_detail.mrn_detail_id
AND ISD.issue_status='CONFIRM'
)as total_issued_qty,
(select
  IFNULL(SUM(STK_DETAILS.avaliable_qty),0)
  from store_stock as STK_BALANCE
  join store_stock_details as STK_DETAILS on STK_BALANCE.stock_id=STK_DETAILS.stock_id
  where STK_BALANCE.item_id=item_master.master_id
  AND STK_BALANCE.location='$locId'
  AND STK_DETAILS.inspection_status='$inspect_status'
  AND STK_DETAILS.issue_status='ISSUABLE'
  AND STK_BALANCE.inventory_type='$inventory_type'
  GROUP By(item_master.master_id)
) as total_qty_
FROM
store_mrn_header
INNER JOIN store_mrn_detail ON store_mrn_header.mrn_id = store_mrn_detail.mrn_id
INNER JOIN item_master ON store_mrn_detail.item_id = item_master.master_id
LEFT JOIN org_color ON store_mrn_detail.color_id = org_color.color_id
LEFT JOIN org_size ON store_mrn_detail.size_id = org_size.size_id
INNER JOIN merc_shop_order_detail ON store_mrn_detail.shop_order_detail_id = merc_shop_order_detail.shop_order_detail_id
#INNER JOIN merc_po_order_details ON merc_shop_order_detail.shop_order_detail_id = merc_po_order_details.shop_order_detail_id
INNER JOIN org_uom AS for_po_uom ON store_mrn_detail.purchase_uom = for_po_uom.uom_id
INNER JOIN org_uom AS for_inv_uom ON item_master.inventory_uom = for_inv_uom.uom_id
WHERE
store_mrn_header.mrn_id = $request->mrn_id");
  }
else if($mrn_type_code=="MANUAL"){

    $inventory_type="MANUAL";
    $mrndetails=DB::SELECT(" SELECT
   store_mrn_header.*, store_mrn_detail.*, item_master.master_code,
   item_master.master_description,
   org_color.color_name,
   for_po_uom.uom_code,
   for_inv_uom.uom_code AS inventory_uom,
   for_inv_uom.uom_id AS inventory_uom_id,
   (
  select IFNULL(SUM(ISD.qty),0)
  FROM
  store_issue_detail as ISD
  WHERE
  ISD.mrn_detail_id=store_mrn_detail.mrn_detail_id
  AND ISD.issue_status='CONFIRM'
)as total_issued_qty,
(select
  IFNULL(SUM(STK_DETAILS.avaliable_qty),0)
  from store_stock as STK_BALANCE
  join store_stock_details as STK_DETAILS on STK_BALANCE.stock_id=STK_DETAILS.stock_id
  where STK_BALANCE.item_id=item_master.master_id
  AND STK_BALANCE.location='$locId'
  AND STK_DETAILS.inspection_status='$inspect_status'
  AND STK_DETAILS.issue_status='ISSUABLE'
  AND STK_BALANCE.inventory_type='$inventory_type'
  GROUP By(item_master.master_id)
) as total_qty_,
(
SELECT IFNULL (SUM(MD.requested_qty_ininventory_uom),0)
from store_mrn_header as MH
join store_mrn_detail as MD on MH.mrn_id=MD.mrn_id
WHERE MH.mrn_type='$inventory_type'
AND MH.mrn_id=store_mrn_header.mrn_id
AND MD.item_id=item_master.master_id
GROUP By(item_master.master_id)
) as total_mrn_qty_for_item,
(
SELECT IFNULL (SUM(ID.qty),0)
from store_issue_header as IH
join store_issue_detail as ID on IH.issue_id=ID.issue_id
WHERE ID.item_id=item_master.master_id
AND IH.mrn_id=store_mrn_header.mrn_id
GROUP By(item_master.master_id)
) as total_issued_qty_for_item,
IFNULL((SELECT(total_mrn_qty_for_item-total_issued_qty_for_item)),0)as balance_to_issue_qty
FROM
   store_mrn_header
  INNER JOIN store_mrn_detail ON store_mrn_header.mrn_id = store_mrn_detail.mrn_id
  INNER JOIN item_master ON store_mrn_detail.item_id = item_master.master_id
  LEFT JOIN org_color ON store_mrn_detail.color_id = org_color.color_id
  LEFT JOIN org_size ON store_mrn_detail.size_id = org_size.size_id
  INNER JOIN org_uom AS for_po_uom ON store_mrn_detail.purchase_uom = for_po_uom.uom_id
  INNER JOIN org_uom AS for_inv_uom ON item_master.inventory_uom = for_inv_uom.uom_id
  WHERE
   store_mrn_header.mrn_id = $request->mrn_id");

        }
                           return response([
                               'data' => $mrndetails
                           ]);
    }


     public function loadBinDetails (Request $request){
       $stockDetails=null;
       $pendingIssueQty=null;
       $locId=auth()->payload()['loc_id'];
       $mrnType=MRNHeader::join('store_grn_type','store_mrn_header.mrn_type','=','store_grn_type.grn_type_id')
                           ->where('store_mrn_header.mrn_id','=',$request->mrn_id)
                           ->select('store_grn_type.grn_type_code','store_mrn_header.*')->first();
      $mrn_type_code=$mrnType->grn_type_code;
      //dd($mrn_type_code);
      if($mrn_type_code=="AUTO"){
       $pendingIssueQty=DB::SELECT("SELECT SUM(store_issue_detail.qty) as pendindg_qty

         From
         store_issue_header
         INNER JOIN store_issue_detail on store_issue_header.issue_id=store_issue_detail.issue_id
         INNER JOIN store_mrn_detail on store_issue_detail.mrn_detail_id=store_mrn_detail.mrn_detail_id
         INNER JOIN store_mrn_header on store_mrn_detail.mrn_id=store_mrn_header.mrn_id
         INNER JOIN store_grn_type on store_mrn_header.mrn_type=store_grn_type.grn_type_code
         where store_mrn_detail.shop_order_detail_id=?
         AND store_mrn_detail.item_id=?
         AND store_grn_type.grn_type_code=?",[$request->shop_order_detail_id,$request->item_id,$mrn_type_code]
       );
       $locId=auth()->payload()['loc_id'];


                  $stockDetails=StockDetails::join('store_stock','store_stock.stock_id','=','store_stock_details.stock_id')
                                        ->join('store_rm_plan','store_stock_details.rm_plan_id','=','store_rm_plan.rm_plan_id')
                                       ->join('org_store_bin','store_rm_plan.bin','=','org_store_bin.store_bin_id')
                                       ->select('store_stock_details.*','store_stock.store_id','store_stock.uom','store_stock.substore_id','org_store_bin.store_bin_name','store_rm_plan.*')
                                       ->where('store_stock.shop_order_detail_id','=',$request->shop_order_detail_id)
                                       ->where('store_stock.location','=',$locId)
                                       ->where('store_stock_details.inspection_status','=',"PASS")
                                       ->where('store_stock_details.issue_status','=',"ISSUABLE")
                                       ->where('store_stock.inventory_type','=',$mrn_type_code)
                                       ->where('store_stock_details.avaliable_qty','>',0)
                                       ->get();


              if($pendingIssueQty[0]->pendindg_qty==null){
               $pendingIssueQty[0]->pendindg_qty=0;
              }
            }
            else if($mrn_type_code=="MANUAL"){

              $pendingIssueQty=DB::SELECT("SELECT SUM(store_issue_detail.qty) as pendindg_qty

                From
                store_issue_header
                INNER JOIN store_issue_detail on store_issue_header.issue_id=store_issue_detail.issue_id
                INNER JOIN store_mrn_detail on store_issue_detail.mrn_detail_id=store_mrn_detail.mrn_detail_id
                INNER JOIN store_mrn_header on store_mrn_detail.mrn_id=store_mrn_header.mrn_id
                INNER JOIN store_grn_type on store_mrn_header.mrn_type=store_grn_type.grn_type_code
                AND store_mrn_detail.item_id=?
                AND store_grn_type.grn_type_code=?"

              ,[$request->item_id,$mrn_type_code]);



                         $stockDetails=StockDetails::join('store_stock','store_stock.stock_id','=','store_stock_details.stock_id')
                                              ->join('store_rm_plan','store_stock_details.rm_plan_id','=','store_rm_plan.rm_plan_id')
                                              ->join('org_store_bin','store_rm_plan.bin','=','org_store_bin.store_bin_id')
                                              ->select('store_stock_details.*','store_stock.store_id','store_stock.uom','store_stock.substore_id','org_store_bin.store_bin_name','store_rm_plan.*')
                                               ->where('store_stock.item_id','=',$request->item_id)
                                              ->where('store_stock.location','=',$locId)
                                              ->where('store_stock_details.inspection_status','=',"PASS")
                                              ->where('store_stock_details.issue_status','=',"ISSUABLE")
                                               ->where('store_stock.inventory_type','=',$mrn_type_code)
                                              ->where('store_stock_details.avaliable_qty','>',0)
                                              ->get();

                                        //dd($locId);
                     if($pendingIssueQty[0]->pendindg_qty==null){
                      $pendingIssueQty[0]->pendindg_qty=0;
                     }

            }



              return response([ 'data' => [
                'data' => $stockDetails,
                'status'=>1,
                'pending_qty'=>$pendingIssueQty[0]->pendindg_qty
                ]
              ], Response::HTTP_CREATED );
     }


     public function loadBinDetailsfromBarcode(Request $request){
      // dd($request);
       $stockDetails=null;
       $pendingIssueQty=null;
       $barCode=$request->barCode;
       $locId=auth()->payload()['loc_id'];
       $mrnType=MRNHeader::join('store_grn_type','store_mrn_header.mrn_type','=','store_grn_type.grn_type_id')
                           ->where('store_mrn_header.mrn_id','=',$request->mrn_id)
                           ->select('store_grn_type.grn_type_code','store_mrn_header.*')->first();
      $mrn_type_code=$mrnType->grn_type_code;
      //dd($mrn_type_code);
      if($mrn_type_code=="AUTO"){
       $pendingIssueQty=DB::SELECT("SELECT SUM(store_issue_detail.qty) as pendindg_qty

         From
         store_issue_header
         INNER JOIN store_issue_detail on store_issue_header.issue_id=store_issue_detail.issue_id
         INNER JOIN store_mrn_detail on store_issue_detail.mrn_detail_id=store_mrn_detail.mrn_detail_id
         INNER JOIN store_mrn_header on store_mrn_detail.mrn_id=store_mrn_header.mrn_id
         INNER JOIN store_grn_type on store_mrn_header.mrn_type=store_grn_type.grn_type_code
         where store_mrn_detail.shop_order_detail_id=?
         AND store_mrn_detail.item_id=?
         AND store_grn_type.grn_type_code=?",[$request->shop_order_detail_id,$request->item_id,$mrn_type_code]
       );
       $locId=auth()->payload()['loc_id'];


                  $stockDetails=StockDetails::join('store_stock','store_stock.stock_id','=','store_stock_details.stock_id')
                                        ->join('store_rm_plan','store_stock_details.rm_plan_id','=','store_rm_plan.rm_plan_id')
                                       ->join('org_store_bin','store_rm_plan.bin','=','org_store_bin.store_bin_id')
                                       ->select('store_stock_details.*','store_stock_details.avaliable_qty as issue_qty','store_stock.store_id','store_stock.uom','store_stock.substore_id','org_store_bin.store_bin_name','store_rm_plan.*')
                                       ->where('store_stock.shop_order_detail_id','=',$request->shop_order_detail_id)
                                       ->where('store_stock.location','=',$locId)
                                       ->where('store_stock_details.inspection_status','=',"PASS")
                                       ->where('store_stock_details.issue_status','=',"ISSUABLE")
                                       ->where('store_stock.inventory_type','=',$mrn_type_code)
                                       ->where('store_stock_details.avaliable_qty','>',0)
                                       ->where('store_rm_plan.barcode','=',$barCode)
                                       ->first();


              if($pendingIssueQty[0]->pendindg_qty==null){
               $pendingIssueQty[0]->pendindg_qty=0;
              }
            }
            else if($mrn_type_code=="MANUAL"){

              $pendingIssueQty=DB::SELECT("SELECT SUM(store_issue_detail.qty) as pendindg_qty

                From
                store_issue_header
                INNER JOIN store_issue_detail on store_issue_header.issue_id=store_issue_detail.issue_id
                INNER JOIN store_mrn_detail on store_issue_detail.mrn_detail_id=store_mrn_detail.mrn_detail_id
                INNER JOIN store_mrn_header on store_mrn_detail.mrn_id=store_mrn_header.mrn_id
                INNER JOIN store_grn_type on store_mrn_header.mrn_type=store_grn_type.grn_type_code
                AND store_mrn_detail.item_id=?
                AND store_grn_type.grn_type_code=?"

              ,[$request->item_id,$mrn_type_code]);



                         $stockDetails=StockDetails::join('store_stock','store_stock.stock_id','=','store_stock_details.stock_id')
                                              ->join('store_rm_plan','store_stock_details.rm_plan_id','=','store_rm_plan.rm_plan_id')
                                              ->join('org_store_bin','store_rm_plan.bin','=','org_store_bin.store_bin_id')
                                              ->select('store_stock_details.*','store_stock_details.avaliable_qty as issue_qty','store_stock.store_id','store_stock.uom','store_stock.substore_id','org_store_bin.store_bin_name','store_rm_plan.*')
                                               ->where('store_stock.item_id','=',$request->item_id)
                                              ->where('store_stock.location','=',$locId)
                                              ->where('store_stock_details.inspection_status','=',"PASS")
                                              ->where('store_stock_details.issue_status','=',"ISSUABLE")
                                               ->where('store_stock.inventory_type','=',$mrn_type_code)
                                               ->where('store_stock_details.avaliable_qty','>',0)
                                               ->where('store_rm_plan.barcode','=',$barCode)
                                              ->first();

                                       //dd($barCode);
                     if($pendingIssueQty[0]->pendindg_qty==null){
                      $pendingIssueQty[0]->pendindg_qty=0;
                     }

            }



              return response([ 'data' => [
                'data' => $stockDetails,
                'status'=>1,
                'pending_qty'=>$pendingIssueQty[0]->pendindg_qty
                ]
              ], Response::HTTP_CREATED );

     }

     public function confirmIssueData(Request $request) {

      $headerData=IssueHeader::join('store_grn_type','store_issue_header.issue_type','=','store_grn_type.grn_type_id')
      ->where('issue_id','=',$request->header['issue_id'])->first();
      //dd($headerData);
      $issue_type=$headerData->grn_type_code;

       $headerData->issue_status="CONFIRM";
       $headerData->save();
       $storeWisaeStockLines= $issueHeader=IssueHeader::join('store_issue_detail','store_issue_header.issue_id','=','store_issue_detail.issue_id')
                               ->join('store_mrn_detail','store_issue_detail.mrn_detail_id','=','store_mrn_detail.mrn_detail_id')
                               ->join('store_mrn_header','store_mrn_detail.mrn_id','=','store_mrn_header.mrn_id')
                              ->select('store_issue_detail.*','store_issue_header.*','store_mrn_detail.uom','store_mrn_header.style_id','store_mrn_detail.shop_order_id','store_mrn_detail.shop_order_detail_id','store_mrn_detail.cust_order_detail_id','store_mrn_detail.color_id','store_mrn_detail.purchase_uom',
                                DB::raw("SUM(store_issue_detail.qty)as total_qty"),'store_mrn_detail.uom as inventory_uom')
                                ->where('store_issue_header.issue_id','=',$request->header['issue_id'])
                                ->Where('store_issue_header.mrn_id','=',$request->header['mrn_id'])
                               ->groupBy('store_issue_detail.stock_id')
                               ->get();

       $issueDetailLines=IssueHeader::join('store_issue_detail','store_issue_header.issue_id','=','store_issue_detail.issue_id')
                              ->join('store_mrn_detail','store_issue_detail.mrn_detail_id','=','store_mrn_detail.mrn_detail_id')
                              ->join('store_mrn_header','store_mrn_detail.mrn_id','=','store_mrn_header.mrn_id')
                              ->where('store_issue_header.issue_id','=',$request->header['issue_id'])
                              ->Where('store_issue_header.mrn_id','=',$request->header['mrn_id'])
                              ->select('store_issue_detail.*','store_issue_header.*','store_mrn_detail.uom','store_mrn_header.style_id','store_mrn_detail.shop_order_id','store_mrn_detail.shop_order_detail_id','store_mrn_detail.cust_order_detail_id','store_mrn_detail.color_id','store_mrn_detail.uom as inventory_uom','store_mrn_detail.purchase_uom')
                              ->get();


                      $this->updateDetails($storeWisaeStockLines,$issueDetailLines,$issue_type);

                    for($i=0;$i<count($issueDetailLines);$i++){
                          $issueDetails=new IssueDetails();
                          $issueDetails=IssueDetails::find($issueDetailLines[$i]['issue_detail_id']);
                          $issueDetails->issue_status="CONFIRM";
                          //$issueDetails->qty=$issueDetails->qty+$issueHeader[$i]['qty'];
                          $issueDetails->save();
                          //$this->stockDetailsUpdate($issueHeader[$i]);

                          }

                 return response([ 'data' => [
                      'data' => $issueHeader,
                          'status'=>1,
                           'message'=>"Issue Confirmed sucessfully!"
  ]
], Response::HTTP_CREATED );


     }


     public function updateDetails($stockHeaderDetails,$stockDetails,$issue_type){
      // dd("asdad");
       $year=DB::select("SELECT YEAR(CURDATE())AS current_d");
       $month=DB::select("SELECT MONTHNAME(CURDATE()) as current_month");
       $current_year=$year["0"]->current_d;
       $current_month=$month['0']->current_month;
       foreach ($stockHeaderDetails as $headervalue) {
          $qtyforShoporder=0;
          $stockUpdate=Stock::find($headervalue['stock_id']);
          $stockUpdate->avaliable_qty=$stockUpdate->avaliable_qty-$headervalue['total_qty'];
          $stockUpdate->out_qty=$stockUpdate->out_qty+$headervalue['total_qty'];
          $stockUpdate->save();
          //dd($headervalue);
          if($stockUpdate->uom!=$headervalue['purchase_uom']){
             //$storeUpdate->uom = $dataset[$i]['inventory_uom'];
             $_uom_unit_code=UOM::where('uom_id','=',$headervalue['purchase_uom'])->pluck('uom_code');
             $_uom_base_unit_code=UOM::where('uom_id','=',$stockUpdate->uom)->pluck('uom_code');
             $ConversionFactor=ConversionFactor::select('*')
                                                 ->where('unit_code','=',$_uom_unit_code[0])
                                                 ->where('base_unit','=',$_uom_base_unit_code[0])
                                                 ->first();
                                                 // convert values according to the convertion rate
                                                 $qtyforShoporder=(double)($headervalue['total_qty']*$ConversionFactor->present_factor);


           }
            if($stockUpdate->uom==$headervalue['purchase_uom']){
           $qtyforShoporder=$headervalue['total_qty'];
           }
           if($issue_type=="AUTO"){
          $findShopOrderline=ShopOrderDetail::find($stockUpdate->shop_order_detail_id);
          $findShopOrderline->asign_qty=$findShopOrderline->asign_qty-$qtyforShoporder;
          $findShopOrderline->issue_qty=$findShopOrderline->issue_qty+$qtyforShoporder;
          $findShopOrderline->balance_to_issue_qty=$findShopOrderline->balance_to_issue_qty-$qtyforShoporder;
          $findShopOrderline->save();
         }
         }

         foreach ($stockDetails as  $value) {
           $findStockDetailLine=StockDetails::find($value['stock_detail_id']);
           $stockheaderLine=Stock::find($value['stock_id']);
           $findStockDetailLine->avaliable_qty=$findStockDetailLine->avaliable_qty-$value['qty'];
           $findStockDetailLine->out_qty=$findStockDetailLine->out_qty+$value['qty'];
           $findStockDetailLine->issue_status="ISSUABLE";
           $findStockDetailLine->save();
           $poDetails=RMPlan::join('store_grn_detail','store_rm_plan.grn_detail_id','=','store_grn_detail.grn_detail_id')->where('store_rm_plan.rm_plan_id','=',$value['rm_plan_id'])->first();
           $stockTransaction=new StockTransaction();
           $stockTransaction->stock_id=$value['stock_id'];
           $stockTransaction->stock_detail_id=$findStockDetailLine->stock_detail_id;
           $stockTransaction->doc_header_id=$value['issue_id'];
           $stockTransaction->doc_detail_id=$value['issue_detail_id'];
           $stockTransaction->doc_type="ISSUE";
           $stockTransaction->style_id=$stockheaderLine->style_id;
           $stockTransaction->item_id=$stockheaderLine->item_id;
           $stockTransaction->uom=$stockheaderLine->uom;
           $stockTransaction->color=$poDetails->color;
           $stockTransaction->qty=$value['qty'];
           $stockTransaction->main_store=$value['store_id'];
           $stockTransaction->bin=$findStockDetailLine->bin;
           $stockTransaction->sub_store=$stockheaderLine->substore_id;
           $stockTransaction->status=$stockheaderLine->substore_id;
           $stockTransaction->location= $stockheaderLine->location;
           $stockTransaction->shop_order_id=$stockheaderLine->shop_order_id;
           $stockTransaction->shop_order_detail_id=$stockheaderLine->shop_order_detail_id;
           $stockTransaction->sup_po_header_id=$poDetails->po_number;
           $stockTransaction->sup_po_details_id=$poDetails->po_details_id;
           $stockTransaction->financial_year=$current_year;
           $stockTransaction->financial_month=$current_month;
           $stockTransaction->standard_price=$poDetails->standard_price;
           $stockTransaction->purchase_price=$poDetails->purchase_price;
           $stockTransaction->rm_plan_id=$findStockDetailLine->rm_plan_id;
           $stockTransaction->direction="-";
           $stockTransaction->save();
         }



     }




     public function stockHeaderUpdate($storeWisaeStockLines){

       foreach ($storeWisaeStockLines as  $value) {
        $qtyforShoporder=0;
        $updateStockLine=Stock::find($value['stock_id']);
        $updateStockLine->avaliable_qty=$updateStockLine->avaliable_qty-$value['total_qty'];
        $updateStockLine->out_qty=$updateStockLine->out_qty+$value['total_qty'];
        $updateStockLine->save();
      //$this->pushTranscationHeader($storeWisaeStockLines,$storeWisaeStockLines,$issueSummary);
        if($value['uom']!=$updateStockLine->uom){
           //$storeUpdate->uom = $dataset[$i]['inventory_uom'];
           $_uom_unit_code=UOM::where('uom_id','=',$updateStockLine->uom)->pluck('uom_code');
           $_uom_base_unit_code=UOM::where('uom_id','=',$value['uom'])->pluck('uom_code');
           $ConversionFactor=ConversionFactor::select('*')
                                               ->where('unit_code','=',$_uom_unit_code[0])
                                               ->where('base_unit','=',$_uom_base_unit_code[0])
                                               ->first();
                                               // convert values according to the convertion rate
                                               $qtyforShoporder=(double)($value['total_qty']*$ConversionFactor->present_factor);


         }
         if($value['uom']==$updateStockLine->uom){
         $qtyforShoporder=$value['total_qty'];
         }

        $findShopOrderline=ShopOrderDetail::find($updateStockLine->shop_order_detail_id);
        $findShopOrderline->asign_qty=$findShopOrderline->asign_qty-$qtyforShoporder;
        $findShopOrderline->issue_qty=$findShopOrderline->issue_qty+$qtyforShoporder;
        $findShopOrderline->balance_to_issue_qty=$findShopOrderline->balance_to_issue_qty-$qtyforShoporder;
        $findShopOrderline->save();

        //$this->pushTranscationHeader($savedDetailLine,$updateStockLine,$issueDetails);
       }
     }



    public function stockDetailsUpdate($savedDetailLine){
      foreach ($savedDetailLine as $value) {
        // code...

        $stockDetail=StockDetails::find($value['stock_detail_id']);
        $stockheaderLine=Stock::find($value['stock_id']);
        $stockDetail->avaliable_qty=$stockDetail->avaliable_qty-$value['qty'];
        $stockDetail->out_qty=$stockDetail->out_qty+$value['qty'];
        $stockDetail->issue_status="ISSUABLE";
        $stockDetail->save();
        $poDetails::join('store_rm_plan','store_grn_detail.grn_detail_id','=','store_rm_plan.grn_detail_id')->where('store_rm_plan.rm_plan_id','=',$value['rm_plan_id'])->first();
        $stockTransaction=new StockTransaction();
        $stockTransaction->stock_id=$value['stock_id'];
        $stockTransaction->doc_header_id=$value['issue_id'];
        $stockTransaction->doc_detail_id=$value['issue_detail_id'];
        $stockTransaction->doc_type="ISSUE";
        $stockTransaction->style_id=$stockheaderLine->style_id;
        $stockTransaction->item_id=$stockheaderLine->item_id;
        $stockTransaction->uom=$stockheaderLine->uom;
        $stockTransaction->color=$poDetails->color;
        $stockTransaction->qty=$value['qty'];
        $stockTransaction->main_store=$value['store_id'];
        $stockTransaction->sub_store=$value['substore_id'];
        $stockTransaction->location= $stockheaderLine->location;
        $stockTransaction->shop_order_id=$stockheaderLine->shop_order_id;
        $stockTransaction->shop_order_detail_id=$stockheaderLine->shop_order_detail_id;
        $stockTransaction->sup_po_header_id=$poDetails->po_number;
        $stockTransaction->sup_po_details_id=$poDetails->po_details_id;
        $stockTransaction->standard_price=$poDetails->standard_price;
        $stockTransaction->purchase_price=$poDetails->purchase_price;
        $stockTransaction->direction="-";
        $stockTransaction->save();
      }

      //$this->pushTranscationHeader($savedDetailLine,$stock);


    }
    public function pushTranscationHeader($storeWisaeStockLines,$issueDetailLines){

      foreach ($storeWisaeStockLines as  $value) {
        $stockTransaction=new StockTransaction();
        $stockTransaction->stock_id=$stock->stock_id;
        $stockTransaction->doc_header_id=$savedDetailLine['issue_id'];
        $stockTransaction->doc_detail_id=$issueSummary->summary_id;
        $stockTransaction->doc_type="ISSUE";
        $stockTransaction->style_id=$stock->style_id;
        $stockTransaction->item_id=$stock->item_id;
        $stockTransaction->uom=$stock->uom;
        $stockTransaction->qty=$savedDetailLine['qty'];
        $stockTransaction->main_store=$stock->store_id;
        $stockTransaction->sub_store=$stock->substore_id;
        $stockTransaction->location= $locId;
        $stockTransaction->shop_order_id=$stock->shop_order_id;
        $stockTransaction->shop_order_detail_id=$stock->shop_order_detail_id;
        $stockTransaction->sup_po_header_id=$poDetails->po_number;
        $stockTransaction->shop_order_id=$poDetails->po_details_id;
        $stockTransaction->standard_price=$poDetails->standard_price;
        $stockTransaction->purchase_price=$poDetails->purchase_price;
        $stockTransaction->direction="-";
        $stockTransaction->save();
      }





      $rmPlanId=$savedDetailLine['rm_plan_id'];
      $locId=auth()->payload()['loc_id'];
      $poDetails=RMPlan::join('store_issue_detail','store_rm_plan.rm_plan_id','=','store_issue_detail.rm_plan_id')
                              ->join('store_grn_detail','store_rm_plan.grn_detail_id','=','store_grn_detail.grn_detail_id')
                              ->where('store_issue_detail.rm_plan_id','=',$rmPlanId)
                              ->first();
        $stockTransaction=new StockTransaction();
        $stockTransaction->stock_id=$stock->stock_id;
        $stockTransaction->doc_header_id=$savedDetailLine['issue_id'];
        $stockTransaction->doc_detail_id=$issueSummary->summary_id;
        $stockTransaction->doc_type="ISSUE";
        $stockTransaction->style_id=$stock->style_id;
        $stockTransaction->item_id=$stock->item_id;
        $stockTransaction->uom=$stock->uom;
        $stockTransaction->qty=$savedDetailLine['qty'];
        $stockTransaction->main_store=$stock->store_id;
        $stockTransaction->sub_store=$stock->substore_id;
        $stockTransaction->location= $locId;
        $stockTransaction->shop_order_id=$stock->shop_order_id;
        $stockTransaction->shop_order_detail_id=$stock->shop_order_detail_id;
        $stockTransaction->sup_po_header_id=$poDetails->po_number;
        $stockTransaction->shop_order_id=$poDetails->po_details_id;
        $stockTransaction->standard_price=$poDetails->standard_price;
        $stockTransaction->purchase_price=$poDetails->purchase_price;
        $stockTransaction->direction="-";
        $stockTransaction->save();
        $stockDetail=$stockTransaction->replicate();
        $stockDetail->save();

    }


}
