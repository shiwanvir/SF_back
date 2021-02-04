<?php

namespace App\Http\Controllers\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Controllers\Controller;
use App\Models\stores\PoOrderDetails;
use App\Models\stores\PoOrderHeader;
use App\Models\stores\PoOrderType;
use App\Models\stores\RollPlan;
use App\Models\stores\RMPlan;
use App\Models\stores\RMPlanHeader;
use App\Models\Store\GrnDetail;
use Illuminate\Support\Facades\DB;
use App\Models\Merchandising\Item\Item;
use Exception;
use App\Libraries\AppAuthorize;
use App\Libraries\CapitalizeAllFields;
use App\Libraries\InventoryValidation;
class RollPlanController extends Controller
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
    else if($type == 'auto')    {
      $search = $request->search;
      return response($this->autocomplete_search($search));
    }

    else {
    $this->store($request);
    }
  }
//create roll plan
  public function store(Request $request)
  {
    foreach ($request->dataset as $value) {
      $bin_validation=InventoryValidation::store_sub_store_bin_validation("BIN",$value['store_bin_name']);
      if($bin_validation==false){
        return response([ 'data' => [
         'message' => 'Incorrect Bin name',
         'status'=>0

         ]
       ], Response::HTTP_CREATED);
      }
    }


      for($i=0;$i<count($request->dataset);$i++)
            {
               $rmPlan= new RMPlan();
               $data=$request->dataset[$i];
               $data=(object)$data;
               $binData=$data->bin;
               $getItemCategory=GrnDetail::join('item_master','store_grn_detail.item_code','=','item_master.master_id')
                                          ->join('item_category','item_master.category_id','=','item_category.category_id')
                                          ->where('store_grn_detail.grn_detail_id','=',$request->grn_detail_id)->first();

               $binID=DB::table('org_store_bin')->where('store_bin_name','=',$data->store_bin_name)
               ->where('store_id','=',$binData['store_id'])
               ->where('substore_id','=',$binData['substore_id'])
               ->select('store_bin_id')->first();
               $grnHeader=GrnDetail::find($request->grn_detail_id);
               $rmPlanHeader=RMPlanHeader::where('invoice_no','=',$request->invoiceNo)
                                        ->where('grn_id','=',$grnHeader->grn_id)
                                       ->where('batch_no','=',$data->batch_no)
                                       ->where('item_code','=',$getItemCategory->master_id)->first();
              if($rmPlanHeader!=null){
                $rmPlan->rm_plan_header_id=$rmPlanHeader->rm_plan_header_id;
              }
              else if($rmPlanHeader==null){
                $newRmPlanHeader=new RMPlanHeader();
                $newRmPlanHeader->invoice_no=$request->invoiceNo;
                $newRmPlanHeader->batch_no=$data->batch_no;
                $newRmPlanHeader->item_code=$getItemCategory->master_id;
                $newRmPlanHeader->grn_id=$grnHeader->grn_id;
                $newRmPlanHeader->status=1;
                if($getItemCategory->inspection_allowed==1){
                //$newRmPlanHeader->inspection_status="PENDING";
                $newRmPlanHeader->confirm_status="PLANNED";
                }
                if($getItemCategory->inspection_allowed==0){
                  //$newRmPlanHeader->inspection_status="PASS";
                  $newRmPlanHeader->confirm_status="PLANNED";
                }
                CapitalizeAllFields::setCapitalAll($newRmPlanHeader);
                $newRmPlanHeader->save();

                $rmPlan->rm_plan_header_id=$newRmPlanHeader->rm_plan_header_id;
                }
               $rmPlan->lot_no=$data->lot_no;
               $rmPlan->batch_no=$data->batch_no;
               $rmPlan->roll_or_box_no=$data->roll_no;
               $rmPlan->actual_qty=$data->received_qty;
               $rmPlan->received_qty=$data->received_qty;
               $rmPlan->bin=$binID->store_bin_id;
               $rmPlan->width=$data->width;
               $rmPlan->shade=$data->shade;
               $rmPlan->rm_comment=$data->comment;
               $rmPlan->category_id=$getItemCategory->category_id;
               $rmPlan->invoice_no=$request->invoiceNo;
               $rmPlan->grn_detail_id=$request->grn_detail_id;
               $rmPlan->status = 1;
               $rmPlan->is_excess=$data->is_excess;
               if($getItemCategory->inspection_allowed==1){
               $rmPlan->inspection_status="PENDING";
               $rmPlan->confirm_status="PENDING";
             }
             if($getItemCategory->inspection_allowed==0){
             $rmPlan->inspection_status="PASS";
             $rmPlan->confirm_status="CONFIRMED";
             $rmPlan->actual_width=$data->width;
             //$grn_detail_line_update=GrnDetail::find($request->grn_detail_id);
             //$grn_detail_line_update->inspection_pass_qty=$grn_detail_line_update->inspection_pass_qty+$data->received_qty;
             }

               $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($rmPlan);
               $rmPlan->save();
               if(!$rmPlan){
                  return response([ 'data' => [
                   'message' => 'Roll Plan Not Saved successfully',
                   'rollPlan' => $rmPlan,
                   'status'=>0
                   ]
                 ], Response::HTTP_CREATED );
               }





            }

            return response([ 'data' => [
              'message' => 'Roll Plan Saved Successfully',
              'status'=>1
              ]
            ], Response::HTTP_CREATED );

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

        $roll_plan_list =RMPlan::join('store_grn_detail','store_rm_plan.grn_detail_id','=','store_grn_detail.grn_detail_id')
                                ->join('store_grn_header','store_grn_detail.grn_id','=','store_grn_header.grn_id')
                                ->join('item_master','store_grn_detail.item_code','=','item_master.master_id')
                                ->join('org_color','item_master.color_id','=','org_color.color_id')
                                ->select('store_rm_plan.*','store_grn_header.grn_number','org_color.color_name','item_master.master_description','store_grn_detail.excess_qty','store_grn_detail.arrival_status','store_grn_detail.i_rec_qty')
                                ->where('store_grn_header.grn_number'  , 'like', $search.'%' )
                                ->orWhere('org_color.color_name'  , 'like', $search.'%' )
                                ->orWhere('item_master.master_description','like',$search.'%')
                                ->orWhere('store_grn_detail.i_rec_qty','like',$search.'%')
                                ->groupBy('store_rm_plan.grn_detail_id')
                                ->orderBy($order_column, $order_type)
                                ->offset($start)->limit($length)->get();

        $roll_plan_count=RMPlan::join('store_grn_detail','store_rm_plan.grn_detail_id','=','store_grn_detail.grn_detail_id')
                                ->join('store_grn_header','store_grn_detail.grn_id','=','store_grn_header.grn_id')
                                ->join('item_master','store_grn_detail.item_code','=','item_master.master_id')
                                ->join('org_color','item_master.color_id','=','org_color.color_id')
                                ->where('store_grn_header.grn_number'  , 'like', $search.'%' )
                                ->orWhere('org_color.color_name'  , 'like', $search.'%' )
                                ->orWhere('item_master.master_description','like',$search.'%')
                                ->orWhere('store_grn_detail.i_rec_qty','like',$search.'%')
                                ->groupBy('store_rm_plan.grn_detail_id')->get()
                                ->count();
        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => $roll_plan_count,
            "recordsFiltered" => $roll_plan_count,
            "data" => $roll_plan_list
        ]);

  }

 public function show($id){

   $roll_plan_data= RMPlan::select('store_rm_plan.*','org_store_bin.store_bin_name','store_grn_header.main_store','store_grn_header.sub_store','store_grn_header.grn_id','store_grn_header.inv_number')
   ->join('store_rm_plan_header','store_rm_plan.rm_plan_header_id','=','store_rm_plan_header.rm_plan_header_id')
   ->join('org_store_bin','store_rm_plan.bin','=','org_store_bin.store_bin_id')
   ->join('store_grn_detail','store_rm_plan.grn_detail_id','=','store_grn_detail.grn_detail_id')
   ->join('store_grn_header','store_grn_detail.grn_id','=','store_grn_header.grn_id')
   ->where('store_rm_plan.grn_detail_id','=',$id)->get();
   $grn_line_qty= GrnDetail::find($roll_plan_data[0]['grn_detail_id']);
   $store_id=$roll_plan_data[0]['main_store'];
   $sub_store_id=$roll_plan_data[0]['sub_store'];

   return response([ 'data' =>[
                      'data' => $roll_plan_data,
                      'sub_store_id'=>$sub_store_id,
                      'store_id'=>$store_id,
                      'status'=>1,
                       'grn_line_qty'=>$grn_line_qty->i_rec_qty,
                       'excess_qty'=>$grn_line_qty->excess_qty
                    ]]);

}

//update a Color
public function update(Request $request, $id){

//dd("dahdhadjadh");
  for($i=0;$i<count($request->dataset);$i++)
  {
    $dataset=$request->dataset;
    $rmPlan = RMPlan::find($dataset[$i]['rm_plan_id']);
    $data=$dataset[$i];
    $data=(object)$data;
    $getItemCategory=GrnDetail::join('item_master','store_grn_detail.item_code','=','item_master.master_id')
                               ->join('item_category','item_master.category_id','=','item_category.category_id')
                               ->where('store_grn_detail.grn_detail_id','=',$dataset[$i]['grn_detail_id'])->first();

    $grnHeader=GrnDetail::find($data->grn_detail_id);
    $binID=DB::table('org_store_bin')->where('store_bin_name','=',$data->store_bin_name)
    ->where('store_id','=',$data->main_store)
    ->where('substore_id','=',$data->sub_store)
    ->select('store_bin_id')->first();
    //dd($data->shade);
    $rmPlanHeader=RMPlanHeader::where('invoice_no','=',$data->inv_number)
                             ->where('grn_id','=',$data->grn_id)
                            ->where('batch_no','=',$data->batch_no)
                            ->where('item_code','=',$getItemCategory->master_id)->first();
                            if($rmPlanHeader!=null){
                              $rmPlan->rm_plan_header_id=$rmPlanHeader->rm_plan_header_id;
                            }
                            else if($rmPlanHeader==null){
                              $newRmPlanHeader=new RMPlanHeader();
                              $newRmPlanHeader->invoice_no=$data->inv_number;
                              $newRmPlanHeader->batch_no=$data->batch_no;
                              $newRmPlanHeader->item_code=$getItemCategory->master_id;
                              $newRmPlanHeader->grn_id=$data->grn_id;
                              $newRmPlanHeader->status=1;
                              if($getItemCategory->inspection_allowed==1){
                              //$newRmPlanHeader->inspection_status="PENDING";
                              $newRmPlanHeader->confirm_status="PLANNED";
                              }
                              if($getItemCategory->inspection_allowed==0){
                                $newRmPlanHeader->inspection_status="PASS";
                                $newRmPlanHeader->confirm_status="PLANNED";
                              }
                              $newRmPlanHeader->save();
                              CapitalizeAllFields::setCapitalAll($newRmPlanHeader);
                              $rmPlan->rm_plan_header_id=$newRmPlanHeader->rm_plan_header_id;
                              }

                              $rmPlan->lot_no=$data->lot_no;
                              $rmPlan->batch_no=$data->batch_no;
                              $rmPlan->roll_or_box_no=$data->roll_or_box_no;
                              $rmPlan->actual_qty=$data->received_qty;
                              $rmPlan->received_qty=$data->received_qty;
                              $rmPlan->bin=$binID->store_bin_id;
                              $rmPlan->width=$data->width;
                              $rmPlan->shade=$data->shade;
                              $rmPlan->rm_comment=$data->rm_comment;
                              $rmPlan->category_id=$getItemCategory->category_id;
                              $rmPlan->invoice_no=$data->inv_number;
                              $rmPlan->grn_detail_id=$data->grn_detail_id;
                              $rmPlan->status = 1;
                              $rmPlan->is_excess=$data->is_excess;
                              if($getItemCategory->inspection_allowed==1){
                              $rmPlan->inspection_status="PENDING";
                              $rmPlan->confirm_status="PENDING";
                            }
                            if($getItemCategory->inspection_allowed==0){
                            $rmPlan->inspection_status="PASS";
                            $rmPlan->confirm_status="CONFIRMED";
                            $rmPlan->actual_width=$data->width;
                            //$grn_detail_line_update=GrnDetail::find($request->grn_detail_id);
                            //$grn_detail_line_update->inspection_pass_qty=$grn_detail_line_update->inspection_pass_qty+$data->received_qty;
                            }

                              $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($rmPlan);
                              $rmPlan->save();
                          }

  return response([ 'data' => [
    'message' => 'Roll Plan Updated successfully',
    ]
  ], Response::HTTP_CREATED );


}

public function destroy($id){
//$rmPlanHeader=
$rmData=RMPlan::where('grn_detail_id','=',$id)->get();
foreach ($rmData as $value) {
  $rmPlanHeader=RMPlanHeader::find($value['rm_plan_header_id']);
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
  ] );
}



}
