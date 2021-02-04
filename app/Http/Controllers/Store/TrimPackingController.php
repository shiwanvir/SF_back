<?php

namespace App\Http\Controllers\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Libraries\AppAuthorize;
use App\Libraries\CapitalizeAllFields;
use App\Models\Store\TrimPacking;
use App\Models\Store\GrnHeader;
use App\Models\Store\GrnDetail;
use App\Models\Store\Stock;
use App\Models\Store\StoreBin;
use App\Models\Org\UOM;
use App\Models\Merchandising\Item\Item;
use App\Models\Org\ConversionFactor;
use App\Models\stores\RMPlan;
use App\Models\stores\RMPlanHeader;
class TrimPackingController extends Controller{


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


  public function store(Request $request)
  {

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
              if($getItemCategory->inspection_allowed==1){
              //$newRmPlanHeader->inspection_status="PENDING";
              $newRmPlanHeader->confirm_status="PLANNED";
              }
              if($getItemCategory->inspection_allowed==0){
                $newRmPlanHeader->inspection_status="PASS";
                $newRmPlanHeader->confirm_status="PLANNED";
              }
              $newRmPlanHeader->save();
              $rmPlan->rm_plan_header_id=$newRmPlanHeader->rm_plan_header_id;
              }
             $rmPlan->lot_no=$data->lot_no;
             $rmPlan->batch_no=$data->batch_no;
             $rmPlan->roll_or_box_no=$data->box_no;
             $rmPlan->actual_qty=$data->received_qty;
             $rmPlan->received_qty=$data->received_qty;
             $rmPlan->bin=$binID->store_bin_id;
             //$rmPlan->width=$data->width;
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
                 'rollPlan' => $rollPlan
                 ]
               ], Response::HTTP_CREATED );
             }


       }

       return response([ 'data' => [
         'message' => 'Trim Packing Saved Successfully',
         ]
       ], Response::HTTP_CREATED );
  }





}
