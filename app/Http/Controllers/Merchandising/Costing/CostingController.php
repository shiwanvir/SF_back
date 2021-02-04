<?php

namespace App\Http\Controllers\Merchandising\Costing;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

use App\Models\Merchandising\Costing\Costing;
use App\Models\Merchandising\Costing\CostingSizeChart;
use App\Models\Merchandising\Costing\CostingFngColor;
use App\Models\Merchandising\Costing\CostingSfgColor;
use App\Models\Merchandising\Costing\CostingCountry;
use App\Models\Merchandising\Costing\CostingItem;

use App\Models\Org\SizeChart;
//use App\Models\Merchandising\Costing\BulkCostingApproval;
//use App\Models\MerchandisBulkCostingDetailsing\Costing\BulkCostingDetails;
//use App\Models\Merchandising\Costing\BulkCostingFeatureDetails;
//use App\Models\Merchandising\Costing\CostingBulkRevision;
//use App\Models\Merchandising\Costing\CostingFinishGood;
//use App\Models\Merchandising\Costing\CostingFinishGoodComponent;
use App\Models\Finance\Cost\FinanceCost;
use App\Models\Merchandising\StyleCreation;
use App\Models\Merchandising\ProductFeature;
use App\Models\Merchandising\ProductFeatureComponent;
//use App\Models\Merchandising\Costing\CostingFinishGoodComponentItem;
use App\Models\Org\UOM;
use App\Models\Org\Color;
use App\Models\Merchandising\CustomerOrderDetails;
use App\Models\Merchandising\Item\Item;
use App\Models\Merchandising\Item\Category;
use App\Models\Merchandising\Item\SubCategory;
use App\Models\Merchandising\ProductComponent;
use App\Models\Merchandising\ProductSilhouette;
use App\Models\Org\Division;
use App\Models\Org\Season;
use App\Models\Merchandising\Costing\CostingFngItem;
use App\Models\Merchandising\Costing\CostingSfgItem;

use App\Models\Merchandising\BOMHeader;
use App\Models\Merchandising\BOMDetails;

use App\Libraries\Approval;
use App\Services\Merchandising\Costing\CostingService;
use App\Jobs\MailSendJob;

use App\Libraries\AppAuthorize;

class CostingController extends Controller {
  var $authorize = null;

  public function __construct()
  {
    //add functions names to 'except' paramert to skip authentication
    $this->middleware('jwt.verify', ['except' => ['index']]);
    $this->authorize = new AppAuthorize();
  }

    public function index(Request $request) {
        $type = $request->type;
        if ($type == 'getStyleData') {
            return response($this->getStyleData($request->style_id));
        }
        elseif ($type == 'getColorForDivision'){
            $division_id = $request->division_id;
            $query = $request->query;
            return response($this->getColorForDivision($division_id,$request->get('query')));
        }
        /*elseif ($type == 'getFinishGood') {
            return response($this->getFinishGood($request->id));
        }*/
        elseif ($type == 'finance_cost'){
            return response($this->finance_cost());
        }
        elseif ($type == 'total_smv'){
            return response([
              'data' => $this->total_smv($request->style_id, $request->bom_stage_id, $request->color_type_id, $request->buy_id)
            ]);
        }
        elseif ($type == 'artical_numbers') {
          $search = $request->search;
          return response([
            'data' => $this->get_artical_numbers($search)
          ]);
        }
        elseif ($type == 'item_uom') {
          $item_description = $request->item_description;
          return response([
            'data' => $this->get_item_uom($item_description)
          ]);
        }
        else if($type == 'datatable') {
            $data = $request->all();
            $this->datatable_search($data);
        }
        else if($type == 'auto') {
          $search = $request->search;
          return response($this->autocomplete_search($search));
        }
        else if($type == 'item_from_article_no') {
          $article_no = $request->article_no;
          return response([
            'data' => $this->get_item_from_article_no($article_no)
          ]);
        }
        else if($type == 'get_finish_good_color'){
            return response([
              'data' => $this->get_finish_good_color($request->style_id)
            ]);
        }
        else if($type == 'costing_colors'){
            $costing_id = $request->costing_id;
            $feature_component_count = $request->feature_component_count;
            return response([
              'data' => $this->get_saved_finish_good_colors($costing_id, $feature_component_count)
            ]);
        }
        else if($type == 'get_product_feature_components'){
          $style_id = $request->style_id;
          return response([
            'data' => $this->get_product_feature_components($style_id)
          ]);
        }
        else if($type == 'get_saved_size_chart'){
          $costing_id = $request->costing_id;
          return response([
            'data' => $this->get_saved_size_chart($costing_id)
          ]);
        }
        else if($type == 'get_saved_countries'){
          $costing_id = $request->costing_id;
          return response([
            'data' => $this->get_saved_countries($costing_id)
          ]);
        }
        else if($type == 'costing_finish_goods'){
          $costing_id = $request->costing_id;
          return response([
            'data' => $this->get_costing_finish_goods($costing_id)
          ]);
        }
        else if($type == 'costing_smv_details'){
          $costing_id = $request->costing_id;
          return response([
            'data' => $this->get_costing_smv_details($costing_id)
          ]);
        }
        elseif($type == 'order_qty_efficiency'){
            return response([
              'data' => $this->get_order_qty_efficiency($request->style_id, $request->order_qty)
            ]);
        }
        elseif ($type == 'get_bom_stages') {
            return response([
              'data' => $this->get_bom_stages($request->style)
            ]);
        }
        elseif ($type == 'get_color_types'){
          return response([
            'data' => $this->get_color_types($request->style)
          ]);
        }
        elseif ($type == 'get_buy_names'){
          return response([
            'data' => $this->get_buy_names($request->style)
          ]);
        }
        else if ($type == 'get_items_for_yy_update'){
            return response([
              'data' => $this->get_items_for_yy_update($request)
            ]);
        }
        /*elseif ($type == 'apv'){
            $this->Approval($request);
        }
        /*elseif ($type == 'report-balk'){
            $this->reportBalk($request);
        }*/
        /*elseif ($type == 'report-flash'){
            $this->reportFlash($request);
        }*/
        //new functions

    }


    public function store(Request $request) {

      if($this->authorize->hasPermission('COSTING_CREATE'))//check permission
      {
        $query = Costing::where('style_id', '=', $request->style_id)->where('bom_stage_id', '=', $request->bom_stage_id)
        ->where('season_id', '=', $request->season_id)->where('color_type_id', '=', $request->color_type_id)
        ->where('lot_no', '=', $request->lot_no);

        if($request->buy_id != null && $request->buy_id != ''){
          $query->where('buy_id', '=', $request->buy_id);
        }

        $costing_count = $query->count();
        if($costing_count > 0){ //chek costing already exixts for same style, bom stage, season and color type
          return response(['data' => [
              'status' => 'error',
              'message' => 'Duplicate costing'
            ]
          ]);
        }
        else
        {
          $costing = new Costing();

          if ($costing->validate($request->all())) { //validate costing details
             //fill data -> style_id, bom_stage_id, season_id, color_type_id, total_order_qty, fob, planned_efficiency, cost_per_std_min,
             //pcd, cost_per_std_min, upcharge, upcharge_reason

              $costing->fill($request->except(['upcharge_reason_description', 'division', 'style_description', 'style_remarks', 'customer', 'status']));
              if($costing->pcd != null && $costing->pcd != ''){
                $pcd_date = date_create($costing->pcd);
                $costing->pcd = date_format($pcd_date,"Y-m-d");//change pcd date format to save in database
              }

              //chek finance details
              $current_timestamp = date("Y-m-d H:i:s");
              $finance_details = FinanceCost::where('effective_from', '<=', $current_timestamp)
              ->where('effective_to', '>=', $current_timestamp)
              ->where('status', 1)->first();

              $finance_charges = 0;
              $cpm_front_end = 0;
              $cpum = 0;
              $cpm_factory = 0;

              if ($finance_details != false && $finance_details != null) { //if has finance details
                 $finance_charges = $finance_details['finance_cost'];
                 $cpm_front_end = $finance_details['cpmfront_end'];
                 $cpum = $finance_details['cpum'];
                 $cpm_factory = ($cpum * $costing->planned_efficiency) / 100;
                 $cpm_factory = round($cpm_factory, 4, PHP_ROUND_HALF_UP);
              }

              $total_smv = $this->get_total_smv($costing->style_id, $costing->bom_stage_id, $costing->color_type_id, $costing->buy_id);//get smv details
              $labour_cost = round(($total_smv * $cpm_factory), 4, PHP_ROUND_HALF_UP);//calculate labour cost
              $coperate_cost = round(($total_smv * $cpm_front_end), 4, PHP_ROUND_HALF_UP);//calculate coperate cost

              $costing->finance_charges = $finance_charges;
              $costing->finance_cost = 0; //finance cost = total rm cost * finance charges
              $costing->fabric_cost = 0;
              $costing->elastic_cost = 0;
              $costing->trim_cost = 0;
              $costing->packing_cost = 0;
              $costing->other_cost = 0;
              $costing->cpm_front_end = $cpm_front_end;
              $costing->cost_per_utilised_min = $cpum;
              $costing->total_smv = $total_smv;
              $costing->cpm_factory = $cpm_factory;
              $costing->labour_cost = $labour_cost;
              $costing->coperate_cost = $coperate_cost;
              $costing->revision_no = 0;
              $costing->status = 'CREATE';
              $costing->edit_status = 1;
              $costing->edit_user = auth()->user()->user_id;
              $costing->consumption_required_notification_status = 0;
              $costing->consumption_added_notification_status = 0;
              $costing->consumption_required_notification_date = null;
              $costing->save();

              $costing->sc_no = str_pad($costing->id, 5, '0', STR_PAD_LEFT);
              $costing->save();//generate sc no and update

              //get product feature components from style
              //$finish_goods = $this->get_finish_good($costing->style_id, $costing->bom_stage_id, $costing->color_type_id);
              $costing = Costing::with(['style'])->find($costing->id);
              $feature_component_count = ProductFeature::find($costing->style->product_feature_id)->count;
              //send response
              return response(['data' => [
                  'status' => 'success',
                  'message' => 'Costing saved successfully',
                  'costing' => $costing,
                  'feature_component_count' => $feature_component_count,
                  //'finish_goods' => $finish_goods
                ]
              ], Response::HTTP_CREATED);
          }
          else {
              $errors = $costing->errors(); // failure, get errors
              return response(['errors' => ['validationErrors' => $errors]], Response::HTTP_UNPROCESSABLE_ENTITY);
          }
          }
        }
        else {
          return response($this->authorize->error_response(), 401);
        }
    }


    public function show($id) {
      if($this->authorize->hasPermission('COSTING_VIEW'))//check permission
      {

       $costing = Costing::with(['style', 'bom_stage', 'season', 'color_type','upcharge_reason','buy','design_source','pack_type', 'currency'])->find($id);
       //$finish_goods_count = CostingFinishGood::where('costing_id', '=', $costing->id)->count();
       //$finish_goods = [];

       /*if($finish_goods_count > 0){ //chek already saved finishgood
         $finish_goods = $this->get_saved_finish_good($id); //get saved finish good details
       }
       else{
         $finish_goods = $this->get_finish_good($costing->style_id, $costing->bom_stage_id, $costing->color_type_id); //get finishgood data from smv table
       }*/

      $feature_component_count = ProductFeature::find($costing->style->product_feature_id)->count;

      return response([
        'data' => [
          'costing' => $costing,
          //'finish_goods' => $finish_goods,
          'feature_component_count' => $feature_component_count,
          ]
      ]);
    }
    else{
      return response($this->authorize->error_response(), 401);
    }
  }


    public function update(Request $request, $id){
      if($this->authorize->hasPermission('COSTING_EDIT'))//check permission
      {

      $costing = Costing::find($id);
      //check costing status. cannot update PENDING and REJECTED costings
      if($costing->status == 'PENDING' || $costing->status == 'REJECTED') {
        return response([
          'data' => [
            'status' => 'error',
            'message' => 'Cannot update '. $costing->status .' costing'
          ]
        ]);
      }
      else {
        $style = StyleCreation::find($costing->style_id);
        $product_feature = ProductFeature::find($style->product_feature_id);
        //$finish_goods = $request->finish_goods;//get finisg goods list

        if($costing->status == 'APPROVED'){ //if costing is an APPROVED one, add revision data to history table and save new data as a new revision
          $this->save_costing_revision($costing->id, $costing->revision_no, $request->revision_reason);
          $costing->revision_no = ($costing->revision_no + 1); //update revision no
          $costing->status = 'CREATE'; //clear approval details and save as a new revision
          $costing->approval_user = null;
          $costing->approval_date = null;
          $costing->approval_sent_date = null;
          $costing->approval_sent_user = null;
          $costing->consumption_required_notification_status = 0;
          $costing->consumption_required_notification_date = null;
          $costing->consumption_added_notification_status = 0;
        }

       $costing->total_order_qty = $request->total_order_qty;
        $costing->fob = $request->fob;
        $costing->planned_efficiency = $request->planned_efficiency;
        $costing->cost_per_std_min = $request->cost_per_std_min;
        $costing->pcd = $request->pcd;
        $costing->upcharge = $request->upcharge;
        $costing->upcharge_reason = $request->upcharge_reason;
        $costing->style_type = $request->style_type;

        $cpm_factory = ($costing->cost_per_utilised_min * $costing->planned_efficiency) / 100;
        $cpm_factory = round($cpm_factory, 4, PHP_ROUND_HALF_UP );
        $labour_cost = round(($costing->total_smv * $cpm_factory), 4, PHP_ROUND_HALF_UP);
        // no need to update corperate cost. Because it will not change based on user inut data
        $costing->cpm_factory = $cpm_factory;
        $costing->labour_cost = $labour_cost;
        $costing->epm = $costing->calculate_epm($request->fob, $costing->total_rm_cost, $costing->total_smv);
        $costing->np_margine = $costing->calculate_np($request->fob, $costing->total_cost);
        //$costing->consumption_required_notification_status = 0;
        //$costing->consumption_required_notification_date = null;
        //$costing->consumption_added_notification_status = 0;
        $costing->save();

          return response([
            'data' => [
              'status' => 'success',
              'message' => 'Costing updated successfully',
              'costing' => $costing,
              //'finish_goods' => $this->get_saved_finish_good($costing->id),
              'feature_component_count' => $product_feature->count,
            ]
          ]);
      }
    }
        else{
          return response($this->authorize->error_response(), 401);
        }
    }


    public function destroy($id) {

    }


  /*  public function approve_costing(Request $request) {
      $costing_id = $request->costing_id;
      $costing = Costing::find($costing_id);
      if($costing->status != 'APPROVED'){
        $costing->status = 'APPROVED';
        $costing->save();
        $this->generate_bom_for_costing($costing_id);//generate boms for all coonected deliveries
      }
    }*/


    private function getStyleData($style_id) {
        $dataArr = array();
        $styleData = StyleCreation::find($style_id);
        //$hader = Costing::where('style_id', $style_id)->get()->toArray();
        //$country = \App\Models\Org\Country::find($styleData->customer->customer_country);

        $dataArr['remark_style'] = $styleData->remark_style;
        $dataArr['division_name'] = $styleData->division->division_description;
        $dataArr['division_id'] = $styleData->division->division_id;
        $dataArr['style_desc'] = $styleData->style_description;
        $dataArr['cust_name'] = $styleData->customer->customer_name;
        $dataArr['style_desc'] = $styleData->style_description;
        $dataArr['style_id'] = $styleData->style_id;
        $dataArr['style_no'] = $styleData->style_no;
        $dataArr['image'] = $styleData->image;

      /*  if(count($hader)>0){
            $costed_smv = 0;
            $blkCostFea = [];

            if(count($blkCostFea)>0){
                $sum=0;
                foreach ($blkCostFea AS $CostFea ){
                    $sum+=$CostFea['smv'];
                }
                $costed_smv=$sum;
            }
            $hader[0]['pcd']=date_format(date_create($hader[0]['pcd']),"m/d/Y");
            $dataArr['blk_hader'] = $hader[0];
            $dataArr['blk_hader']['smv_received']='';
            $dataArr['blk_hader']['costed_smv_id']=$costed_smv;

        }else{
            $financeCost=\App\Models\Finance\Cost\FinanceCost::first();

            $dataArr['blk_hader']['updated_date']='';
            $dataArr['blk_hader']['total_cost']='';
            $dataArr['blk_hader']['season_id']='';
            $dataArr['blk_hader']['color_type_id']='';
            $dataArr['blk_hader']['created_date']='';
            $dataArr['blk_hader']['cost_per_std_min']=$financeCost->cpmfront_end;
            $dataArr['blk_hader']['epm']='';
            $dataArr['blk_hader']['np_margin']='';
            $dataArr['blk_hader']['plan_efficiency']='';
            $dataArr['blk_hader']['bulk_costing_id']='';
            $dataArr['blk_hader']['pcd']='';
            $dataArr['blk_hader']['finance_charges']=$financeCost->finance_cost;
            $dataArr['blk_hader']['cost_per_min']=$financeCost->cpum;

            $dataArr['blk_hader']['costed_smv_id']=0;

            $dataArr['blk_hader']['costing_status']=0;

        }*/
        return $dataArr;
    }




    /*private function getFinishGood($id) {
      return [
        'finish_goods' => $this->get_saved_finish_good($id)
      ];
    }*/

    public function send_to_approval(Request $request) {
        //check all finish goods have connected sales order deliveries
        $costing = Costing::find($request->costing_id);
        $user = auth()->user();

      //  $costingService = new CostingService();
      //  $res = $costingService->genarate_bom($costing->id);
      //  return;

        $fg_colors_count = CostingFngColor::where('costing_id', '=', $costing->id)->count();

        if($fg_colors_count > 0) {//has finish good colors
          //check has countries
          $country_count = CostingCountry::where('costing_id', '=', $costing->id)->count();
          if($country_count > 0){//check has countries
            //check for items
            $item_count = CostingItem::where('costing_id', '=', $costing->id)->count();
            if($item_count > 0){//has item

              //has entered consumptions for all items
              $consumption_item_count = CostingItem::where('costing_id', '=', $costing->id)
              ->where('net_consumption', '=', 0)->count();
              if($consumption_item_count == 0){ //has consumptions

                $this->remove_boms_from_edit_mode($costing->id);//remove boms from edit stats
                //change bom status to edit until costing approve
                DB::table('bom_header')->where('costing_id', $costing->id)->update(['status' => 'PENDING']);

                $costing->status = 'PENDING';
                $costing->approval_user = null;
                $costing->approval_sent_user = $user->user_id;
                $costing->approval_sent_date = date("Y-m-d H:i:s");
                $costing->edit_status = 0;
                $costing->edit_user = null;
                $costing->save();

                $approval = new Approval();
                $approval->start('COSTING', $costing->id, $costing->created_by);//start costing approval process

                /*if($costing->status == 'PENDING'){
                  $costing->status = 'APPROVED';
                  $costing->save();
                  //$costing = Costing::find($costing_id);
                  //if($costing != null && $costing->status == 'APPROVED'){
                    $costingService = new CostingService();
                    $res = $costingService->genarate_bom($costing->id);
                  //  echo json_encode($res);
                //  }
              }*/

                return response([
                  'data' => [
                    'status' => 'success',
                    'message' => 'Costing Sent For Approval',
                    'costing' => $costing
                  ]
                ]);
              }
              else {
                return response([
                  'data' => [
                    'status' => 'error',
                    'message' => "Cannot send for approval. Some items dont't have net consumptions",
                    'costing' => $costing
                  ]
                ]);
              }
            }
            else {//no item
              return response([
                'data' => [
                  'status' => 'error',
                  'message' => 'Cannot send for approval. There are no items.',
                  'costing' => $costing
                ]
              ]);
            }
          }
          else {
            return response([
              'data' => [
                'status' => 'error',
                'message' => 'Cannot send for approval. There are no delivery countries',
                'costing' => $costing
              ]
            ]);
          }
        }
        else { //no finish goods
          return response([
            'data' => [
              'status' => 'error',
              'message' => 'Cannot send for approval. There are no colors.',
              'costing' => $costing
            ]
          ]);
        }
    }


    public  function getColorForDivision($division_id,$query){
      //$color=\App\Models\Org\Color::where([['division_id','=',$division_id]])->pluck('color_name')->toArray();
        $color=\App\Models\Org\Color::where('status', '=', 1)->where('color_code', 'like', $query.'%')->pluck('color_code')->toArray();
       return json_encode($color);
    }




    /*public function copy_finish_good(Request $request){
      $fg_id = $request->fg_id;
      $proceed_without_warning = ($request->proceed_without_warning == null) ? false : $request->proceed_without_warning;
      //count finish good items
      $components_item_count = CostingFinishGoodComponent::select("costing_finish_good_components.id",
        DB::raw("(SELECT count(costing_finish_good_component_items.id) FROM costing_finish_good_component_items
        WHERE costing_finish_good_component_items.fg_component_id = costing_finish_good_components.id) as item_count")
      )->where('costing_finish_good_components.fg_id', '=', $fg_id)->get();

      $has_error = 0;
      for($x = 0 ; $x < sizeof($components_item_count) ; $x++){
        if($components_item_count[$x]->item_count <= 0){
          $has_error++;
        }
      }

      if($has_error >= sizeof($components_item_count)){ //no item for every component
        return response([
          'data' => [
            'status' => 'error',
            'message' => 'You must add items before copy finish good'
          ]
        ]);
      }
      else if($has_error > 0 && $has_error < sizeof($components_item_count) && $proceed_without_warning == false){//some components don't have items and need show warring
        return response([
          'data' => [
            'status' => 'warning',
            'message' => "Some components don't have items."
          ]
        ]);
      }*/
      /*$fg_item_count = CostingFinishGoodComponentItem::join('costing_finish_good_components', 'costing_finish_good_components.id', '=', 'costing_finish_good_component_items.fg_component_id')
      ->where('costing_finish_good_components.fg_id', '=', $fg_id)
      ->count();*/
      //if($fg_item_count == null || $fg_item_count <= 0) {
      /*else {
        $finish_good = CostingFinishGood::find($fg_id);
        $finish_good_copy = $finish_good->replicate();
        $finish_good_copy->pack_no = DB::table('costing_finish_goods')->where('costing_id', '=', $finish_good->costing_id)->max('pack_no') + 1;
        $finish_good_copy->pack_no_code = 'FG'.str_pad($finish_good_copy->pack_no, 3, '0', STR_PAD_LEFT);
        $finish_good_copy->combo_color_id = null;
        $finish_good_copy->save();

        $components = CostingFinishGoodComponent::where('fg_id', '=', $finish_good->fg_id)->get();
        for($x = 0 ; $x < sizeof($components) ; $x++){
          $component_copy = $components[$x]->replicate();
          $component_copy->fg_id = $finish_good_copy->fg_id;
          $component_copy->color_id = null;
          $component_copy->save();

          $component_items = CostingFinishGoodComponentItem::where('fg_component_id', '=', $components[$x]['id'])->get();
          for($y = 0 ; $y < sizeof($component_items) ; $y++){
            $component_item_copy = $component_items[$y]->replicate();
            $component_item_copy->fg_component_id = $component_copy->id;
            $component_item_copy->save();
          }
       }

        $finish_goods = $this->get_saved_finish_good($finish_good->costing_id);
        return response([
          'data' => [
            'status' => 'success',
            'message' => 'Finish good copied successfully',
            'feature_component_count' => sizeof($components),
            'finish_goods' => $finish_goods
          ]
        ]);
      }
    }*/


    public function copy(Request $request){
      $costing = Costing::find($request->costing_id);
      $bom_stage_id = $request->bom_stage_id;
      $season_id = $request->season_id;
      $color_type_id = $request->color_type_id;
      $buy_id = $request->buy_id;
      $lot_no = $request->lot_no;

      $arr_where = [['style_id', '=', $costing->style_id], ['bom_stage_id', '=', $bom_stage_id], ['season_id', '=', $season_id], ['color_type_id', '=', $color_type_id]];
    //  echo $bom_stage_id;die();

      if($buy_id != null && $buy_id != ''){
        array_push($arr_where, ['buy_id', '=', $buy_id]);
      }

      if($lot_no != null && $lot_no != '' && $lot_no != 0){
        array_push($arr_where, ['lot_no', '=', $lot_no]);
      }

      $costing_count =  Costing::where($arr_where)->count();

      if($costing_count > 0){
        return response(['data' => [
            'status' => 'error',
            'message' => 'Costing already exists'
          ]
        ]);
      }
      else {

        $new_total_smv = $this->get_total_smv($costing->style_id, $bom_stage_id, $color_type_id, $buy_id);
        if($new_total_smv <= 0) { //check total smv for new costing, if smv == 0, then send error message
          return response(['data' => [
              'status' => 'error',
              'message' => 'No SMV details avaliable for selected costing combination.'
            ]
          ]);
        }
        else {
          $date = gmdate('Y-m-d h:i:s');

          $costing_copy = $costing->replicate();
          $costing_copy->bom_stage_id = $bom_stage_id;
          $costing_copy->season_id = $season_id;
          $costing_copy->color_type_id = $color_type_id;
          $costing_copy->sc_no = null;
          $costing_copy->approval_user = null;
          $costing_copy->approval_date = null;
          $costing_copy->approval_sent_date = null;
          $costing_copy->approval_sent_user = null;
          $costing_copy->total_smv = $new_total_smv;
          $costing_copy->status = 'CREATE';
          $costing_copy->created_date = $date;
          $costing_copy->updated_date = $date;
          //$costing_copy->consumption_required_notification_status = 1;
          $costing_copy->consumption_required_notification_date = null;
          $costing_copy->consumption_added_notification_status = 0;
          $costing_copy->revision_no = 0;
          $costing_copy->buy_id = $buy_id;
          $costing_copy->lot_no = $lot_no;
          $costing_copy->edit_status = 0;
          $costing_copy->edit_user = null;
          $costing_copy->save();

          $costing_copy->sc_no = str_pad($costing_copy->id, 5, '0', STR_PAD_LEFT);
          $costing_copy->save();//generate sc no and update

          $countries = CostingCountry::where('costing_id', '=', $costing->id)->get();
          foreach($countries as $country){
            $country_copy = $country->replicate();
            $country_copy->costing_id = $costing_copy->id;
            $country_copy->created_by = null;
            $country_copy->created_date = $date;
            $country_copy->updated_by = null;
            $country_copy->updated_date = $date;
            $country_copy->save();
          }

          $fng_colors = CostingFngColor::where('costing_id', '=', $costing->id)->get();
          foreach($fng_colors as $fng_color){
            $fng_color_copy = $fng_color->replicate();
            $fng_color_copy->costing_id = $costing_copy->id;
            $fng_color_copy->created_by = null;
            $fng_color_copy->created_date = $date;
            $fng_color_copy->updated_by = null;
            $fng_color_copy->updated_date = $date;
            $fng_color_copy->save();

            $sfg_colors = CostingSfgColor::where('fng_color_id', '=', $fng_color->fng_color_id)->get();
            foreach($sfg_colors as $sfg_color){
              $sfg_color_copy = $sfg_color->replicate();
              $sfg_color_copy->fng_color_id = $fng_color_copy->fng_color_id;
              $sfg_color_copy->created_by = null;
              $sfg_color_copy->created_date = $date;
              $sfg_color_copy->updated_by = null;
              $sfg_color_copy->updated_date = $date;
              $sfg_color_copy->save();
            }
          }

          $costing_items = CostingItem::where('costing_id', '=', $costing->id)->get();
          foreach($costing_items as $costing_item){
            $costing_item_copy = $costing_item->replicate();
            $costing_item_copy->costing_id = $costing_copy->id;
            $costing_item_copy->created_by = null;
            $costing_item_copy->created_date = $date;
            $costing_item_copy->updated_by = null;
            $costing_item_copy->updated_date = $date;
            $costing_item_copy->save();
          }

          return response([
            'data' => [
              'status' => 'success',
              'message' => 'Costing Copied Successfully'
            ]
          ]);
        }
      }
    }


    public function edit_mode(Request $request){
      $costing_id = $request->costing_id;
      $edit_status = $request->edit_status;
      $costing = Costing::find($costing_id);

      if($costing != null){ //has a costing
        if($edit_status == 1){//put to edit status
            $user_id = auth()->user()->user_id;

            if($costing->edit_status == 1 && $costing->created_by == $user_id){//already in edit mode
              //chek costings boms has shop orders
              //$has_shop_orders = $this->has_shop_orders($costing_id);
              //if($has_shop_orders == true){ //already have shop orders, cannot edit costing
              //  return response([
              //    'status' => 'error',
              //    'message' => "Cannot edit costing. Shop orders are already connected to this costing."
              //  ]);
              //  }
              //else {
                return response([
                  'status' => 'success',
                  'message' => "You can edit costing",
                  'costing' => $costing
                ]);
              //}
            }
            else if($costing->edit_status == 1 && $costing->created_by != $user_id){
              return response([
                'status' => 'error',
                'message' => "You cannot edit costing. It's already in edit mode"
              ]);
            }
            else {
              if($costing->created_by == $user_id) {//costing created user and can edit
                //chek costings boms has shop orders
                /*$has_shop_orders = $this->has_shop_orders($costing_id);
                if($has_shop_orders == true){ //already have shop orders, cannot edit costing
                  return response([
                    'status' => 'error',
                    'message' => "Cannot edit costing. Shop orders are already connected to this costing."
                  ]);
                }
                else {*/

                //chek all the boms are not in the edit status
                $bom_status_count = BOMHeader::where('costing_id', '=', $costing_id)->where('edit_status', '=', 1)->count();
                if($bom_status_count > 0){
                  return response([
                    'status' => 'error',
                    'message' => "You cannot edit costing. BOM(s) are in edit mode."
                  ]);
                }
                else {
                  $costing->edit_status = 1;
                  $costing->edit_user = $user_id;
                  $costing->save();
                  $this->add_boms_to_edit_mode($costing_id);

                  return response([
                    'status' => 'success',
                    'message' => "You can edit costing",
                    'costing' => $costing
                  ]);
                }
                //}
              }
              else {
                return response([
                  'status' => 'error',
                  'message' => "Only costing created user can edit the costing"
                ]);
              }
            }
        }
        else {//remove edit status
          $user_id = auth()->user()->user_id;
          if($costing->edit_status == 1 && $costing->edit_user == $user_id){//can edit
            $costing->edit_status = 0;
            $costing->edit_user = null;
            $costing->save();
            $this->remove_boms_from_edit_mode($costing_id);

            return response([
              'status' => 'success',
              'message' => "Costing removed from edit status",
              'costing' => $costing
            ]);
          }
          else {
            return response([
              'status' => 'error',
              'message' => "Costing is not in the edit status or user don't have permissions to edit costing"
            ]);
          }
        }
      }
      else {//no costing
        return response([
          'status' => 'error',
          'message' => "Incorrect costing"
        ]);
      }
    }


    private function has_shop_orders($costing_id){
      $shop_order_list = DB::select("SELECT merc_shop_order_header.shop_order_id FROM merc_shop_order_header
          INNER JOIN costing_fng_item ON costing_fng_item.fng_id = merc_shop_order_header.fg_id
          WHERE costing_fng_item.costing_id = ?", [$costing_id]);

      if(sizeof($shop_order_list) > 0){ //already have shop orders
        return true;
      }
      else{
        return false;
      }
    }


    private function add_boms_to_edit_mode($costing_id){
      $user_id = auth()->user()->user_id;
      DB::table('bom_header')->where('costing_id', $costing_id)
          ->update(['edit_status' => 1, 'edit_user' => null]);
  //  DB::table('bom_header')->where('costing_id', $costing_id)
  //      ->update(['edit_status' => 0, 'edit_user' => null, 'status' => 'PLANNED']);
    }


    private function remove_boms_from_edit_mode($costing_id){
      $user_id = auth()->user()->user_id;
      DB::table('bom_header')->where('costing_id', $costing_id)
          ->update(['edit_status' => 0, 'edit_user' => null]);
    }


    public function delete_finish_good(Request $request){
      $fg_id = $request->fg_id;
      $finish_good = CostingFinishGood::find($fg_id);

      $components = CostingFinishGoodComponent::where('fg_id', '=', $finish_good->fg_id)->get();
      for($x = 0 ; $x < sizeof($components) ; $x++){
        CostingFinishGoodComponentItem::where('fg_component_id', '=', $components[$x]['id'])->delete();
      }

      CostingFinishGoodComponent::where('fg_id', '=', $finish_good->fg_id)->delete();
      $finish_good->delete();

      return response([
        'data' => [
          'message' => 'Finish good deleted successfully.',
          'feature_component_count' => sizeof($components),
          'finish_goods' => $this->get_saved_finish_good($finish_good->costing_id)
        ]
      ] , Response::HTTP_OK);
    }


    public function send_consumption_required_notification(Request $request){
      $costing_id = $request->costing_id;
      $currentDate = date("Y-m-d H:i:s");

      //count items
      $items_count = CostingItem::where('costing_id', '=', $costing_id)->count();
      if($items_count <= 0){
          return ['status' => 'error', 'message' => 'Must add at least one item.'];
      }

      $costing = DB::table('costing')
          ->join('style_creation', 'style_creation.style_id', '=', 'costing.style_id')
          ->join('usr_login', 'usr_login.user_id', '=', 'costing.created_by')
          ->select('costing.*', 'style_creation.style_no', 'usr_login.user_name')
          ->where('costing.id', '=', $costing_id)
          ->first();

      if($costing->consumption_required_notification_status == 1){
          //calculate and set due date
           $due_date = date("jS F Y, g:i a", strtotime($currentDate. ' + 1 days'));

           $to_users = DB::select("SELECT usr_profile.email, '' AS name FROM app_notification_assign
           INNER JOIN usr_profile ON usr_profile.user_id = app_notification_assign.user_id
           WHERE app_notification_assign.type = 'COSTING CONSUMPTION CAD'");

           $data = [
             'type' => 'COSTING_CONSUMPTION_CAD',
             'data' => [
               'costing' => $costing,
               'due_date' => $due_date
             ],
             'mail_data' => [
               'subject' => 'Consumption for Costing Required',
               'to' => $to_users
             ]
           ];
           $job = new MailSendJob($data);//dispatch mail to the queue
           dispatch($job);

           $costing = Costing::find($costing_id);
           $costing->consumption_required_notification_status = 0;//update notification status
           $costing->consumption_required_notification_date = $currentDate;
           $costing->consumption_added_notification_status = 1;
           $costing->saveOrFail();

           return ['status' => 'success', 'message' => 'Notification Message Sent Successfully.'];
      }
      else {
        return ['status' => 'error', 'message' => 'Notification Message Already Sent.'];
      }
    }


    public function send_consumption_add_notification(Request $request){
      $costing_id_list = $request->costing_id_list;
      $success_list = [];
      $error_list = [];

      for($x = 0 ; $x < sizeof($costing_id_list); $x++){

        $consumption_item_count = CostingItem::where('costing_id', '=', $costing_id_list[$x])
        ->where('purchase_uom_id', '!=', 'pcs')
        ->where('net_consumption', '<=', 0)->count();
        if($consumption_item_count > 0){
          array_push($error_list, $costing_id_list[$x]);
        }
        else {
          $costing = DB::table('costing')
              ->join('style_creation', 'style_creation.style_id', '=', 'costing.style_id')
              ->join('usr_login', 'usr_login.user_id', '=', 'costing.updated_by')
              ->join('usr_profile', 'usr_profile.user_id', '=', 'costing.created_by')
              ->select('costing.*', 'style_creation.style_no', 'usr_login.user_name', 'usr_profile.email')
              ->where('costing.id', '=', $costing_id_list[$x])
              ->first();

          if($costing->consumption_added_notification_status == 1){
              //calculate and set due date
               $due_date = date("F jS, Y, g:i a", strtotime($costing->created_date. ' + 1 days'));
               $user = auth()->userOrFail();
               $user = DB::table('usr_login')->where('user_id', '=', $user->user_id)->first();

               $data = [
                 'type' => 'COSTING_CONSUMPTION_ADD',
                 'data' => [
                   'costing' => $costing,
                   'user_name' => $costing->user_name,
                   'cad_user' => $user->user_name
                 ],
                 'mail_data' => [
                   'subject' => 'Consumption Added to Costing',
                   'to' => $costing->email
                 ]
               ];
              // echo json_encode($data);die();
               $job = new MailSendJob($data);//dispatch mail to the queue
               dispatch($job);

               $costing = Costing::find($costing_id_list[$x]);
               $costing->consumption_added_notification_status = 0;
               $costing->saveOrFail();

               array_push($success_list, $costing_id_list[$x]);
               //return ['status' => 'success', 'message' => 'Notification message sent successfully.'];
          }
          else {
            array_push($error_list, $costing_id_list[$x]);
            //return ['status' => 'error', 'message' => 'Notification message already sent.'];
          }
        }
      }

      if(sizeof($success_list) <= 0){
        return ['status' => 'error', 'message' => "Did Not Send Notification For Any Costing."];
      }
      else if(sizeof($success_list) > 0 && sizeof($error_list) > 0){
        return ['status' => 'warning', 'message' => "Notifications successfully sent for costing (". implode(',', $success_list) .") and costing (". implode(',', $error_list) .") failed."];
      }
      else {
        return ['status' => 'success', 'message' => 'Notification messages sent successfully.'];
      }
    }

    //************************* size chart section *****************************

    public function update_size_chart(Request $request){
      $costing_id = $request->costing_id;
      $size_chart_id = $request->size_chart_id;
      $sizes = $request->sizes;

      $costing = Costing::find($costing_id);
      $costing->size_chart_id = $size_chart_id;
      $costing->save();

      CostingSizeChart::where('costing_id', '=', $costing_id)->delete();
      $data = [];
      for($x = 0 ; $x < sizeof($sizes) ; $x++){
        array_push($data, [
          'costing_id' => $costing_id,
          'size_chart_id' => $size_chart_id,
          'size_id' => $sizes[$x]['size_id'],
          'status' => $sizes[$x]['status']
        ]);
      }
      CostingSizeChart::insert($data);

      return response([
        'data' => [
          'status' => 'success',
          'message' => 'Size chart saved successfully.'
        ]
      ]);
    }


    public function get_saved_size_chart($costing_id){
      $costing = Costing::find($costing_id);

      if($costing->size_chart_id != null){
        $size_chart = SizeChart::find($costing->size_chart_id);
        $sizes = DB::select("SELECT
          org_size_chart_sizes.size_chart_id,
          org_size_chart_sizes.size_id,
          org_size.size_name,
          IF
          (
               (
                               SELECT status FROM costing_size_chart WHERE costing_size_chart.costing_id = ? AND
                               costing_size_chart.size_chart_id = org_size_chart_sizes.size_chart_id AND costing_size_chart.size_id = org_size_chart_sizes.size_id
               ) = 1, 1, 0
          ) AS status
          FROM org_size_chart_sizes
          INNER JOIN org_size ON org_size.size_id = org_size_chart_sizes.size_id
          WHERE org_size_chart_sizes.size_chart_id = ?", [$costing_id, $costing->size_chart_id]);

          return [
            'size_chart' => $size_chart,
            'sizes' => $sizes
          ];

      }
      else {
        return [
          'size_chart' => null
        ];
      }
    }

    //************************** Costing Colors ********************************

    public function save_costing_colors(Request $request){
      $colors = $request->colors;
      $costing_id = $request->costing_id;
      $costing = Costing::find($costing_id);
      $style = StyleCreation::find($costing->style_id);
      $product_feature = ProductFeature::find($style->product_feature_id);

      for($x = 0 ; $x < sizeof($colors) ; ($x = $x + $product_feature->count)){

        $fng_color = null;
        if($colors[$x]['fng_color_id'] == 0){ //new fng color
          $fng_color = new CostingFngColor();
        }
        else {
          $fng_color = CostingFngColor::find($colors[$x]['fng_color_id']);
        }

        $fng_color->costing_id = $costing->id;
        $fng_color->color_id = $colors[$x]['fng_color'];
        $fng_color->product_feature_id = $colors[$x]['product_feature_id'];
        $fng_color->save();


          for($y = $x ; $y < ($x + $product_feature->count) ; $y++) {
            $sfg_color = null;
            if($colors[$y]['sfg_color_id'] == 0){ //new sfg color
              $sfg_color = new CostingSfgColor();
            }
            else {
              $sfg_color = CostingSfgColor::find($colors[$y]['sfg_color_id']);
            }

            $sfg_color->fng_color_id = $fng_color->fng_color_id;

            if($product_feature->count > 1){//has semi finish goods
              $sfg_color->color_id = $colors[$y]['sfg_color'];
            }
            else {//no semi finish goods
              $sfg_color->color_id = $fng_color->color_id;//set sfg color to fng color
            }

            $sfg_color->product_component_id = $colors[$y]['product_component_id'];
            $sfg_color->product_silhouette_id = $colors[$y]['product_silhouette_id'];
            $sfg_color->product_component_line_no = $colors[$y]['product_component_line_no'];
            $sfg_color->save();
          }

      }

      return response(['data' => [
        'status' => 'success',
        'message' => 'Colors Saved Successfully',
        'colors' => $this->get_saved_finish_good_colors($costing->id, $product_feature->count),
        'component_count' => $product_feature->count
      ]]);
    }


    public function remove_costing_color(Request $request){
      $fng_color_id = $request->fng_color_id;
      $fng_color = CostingFngColor::find($fng_color_id);
      $costing = Costing::find($fng_color->costing_id);
      $style = StyleCreation::find($costing->style_id);
      $product_feature = ProductFeature::find($style->product_feature_id);

      if($costing->edit_status == 1){
        $user_id = auth()->user()->user_id;

        if($user_id == $costing->edit_user) {

          //check for created shop orders
          $has_shop_orders = $this->has_shop_orders_for_fng_color($costing->id, $fng_color->color_id);
          if($has_shop_orders == true) {//has shop orders, cannot remove color
            return response(['data' => [
              'status' => 'error',
              'message' => 'Cannot remove color. Shop orders were already created.'
            ]]);
          }

            $fng_items = CostingFngItem::where('costing_id', '=', $costing->id)->where('fng_color_id', '=', $fng_color->color_id)->get();
            //chek finish goods are created for this color
            if(sizeof($fng_items) > 0) {//has generated finisg goods
                $fng_item_ids = [];
                foreach($fng_items as $fng_item) {
                  $bom = BOMHeader::where('costing_id', '=', $costing->id)->where('fng_id', '=', $fng_item->fng_id)->first();
                  //remove bom items
                  BOMDetails::where('bom_id', '=', $bom->bom_id)->delete();
                  $bom->delete(); //remove bom

                  //get afg ids
                  $sfg_items = CostingSfgItem::where('costing_fng_id', '=', $fng_item->costing_fng_id)->pluck('sfg_id');
                  //deactivate sfg items in item master
                  DB::table('item_master')->whereIn('master_id', $sfg_items)->update(['status' => 0]);
                  //remove costing sfg items
                  CostingSfgItem::where('costing_fng_id', '=', $fng_item->costing_fng_id)->delete();

                  array_push($fng_item_ids, $fng_item->fng_id);//add removed fng id
                  //remove costing fng items
                  $fng_item->delete();
                }
                //deactivate all fng items in item master
                DB::table('item_master')->whereIn('master_id', $fng_item_ids)->update(['status' => 0]);
            }
            //remove all sfg colors
            CostingSfgColor::where('fng_color_id', '=', $fng_color_id)->delete();
            //remove selected fng color
            $fng_color->delete();

            return response(['data' => [
              'status' => 'success',
              'message' => 'Color removed successfully',
              'colors' => $this->get_saved_finish_good_colors($fng_color->costing_id, $product_feature->coun),
            ]]);
        }
        else {
          return response(['data' => [
            'status' => 'error',
            'message' => "You don't have permissions to remove color"
          ]]);
        }
      }
      else {
        return response(['data' => [
          'status' => 'error',
          'message' => 'Costing is not in the edit mode'
        ]]);
      }
    }


    private function has_shop_orders_for_fng_color($costing_id, $fng_color_id){

      $fng_ids = CostingFngItem::where('costing_id', '=', $costing_id)->where('fng_color_id', '=', $fng_color_id)->pluck('fng_id');
      //$shop_order_count = DB::table('merc_shop_order_header')
      //->join('costing_fng_item', 'costing_fng_item.fng_id', '=', 'merc_shop_order_header.fg_id')
      //->whereNotIn('costing_fng_item.fng_id', $fng_ids)->count();
      $shop_order_count = DB::table('merc_shop_order_header')->whereIn('costing_fng_item.fng_id', $fng_ids)->count();
      //echo json_encode($shop_order_count);
      if($shop_order_count > 0){ //already have shop orders
        return true;
      }
      else{
        return false;
      }
    }

    //********************** Costing Countries *********************************

    public function save_costing_countries(Request $request){
      $countries = $request->countries;
      $costing_id = $request->costing_id;
      $costing = Costing::find($costing_id);

      for($x = 0 ; $x < sizeof($countries) ; $x++){

        $country = null;
        if($countries[$x]['costing_country_id'] == 0){ //new fng color
          $country = new CostingCountry();
        }
        else {
          $country = CostingCountry::find($countries[$x]['costing_country_id']);
        }

        $country->costing_id = $costing->id;
        $country->country_id = $countries[$x]['country_id'];
        $country->fob = $countries[$x]['fob'];
        $country->save();

      }

      return response(['data' => [
        'status' => 'success',
        'message' => 'Country Saved Successfully',
        'countries' => $this->get_saved_countries($costing->id)
      ]]);
    }


    public function remove_costing_country(Request $request){
      $costing_country_id = $request->costing_country_id;
      $costing_country = CostingCountry::find($costing_country_id);
      $costing = Costing::find($costing_country->costing_id);

      if($costing->edit_status == 1){
        $user_id = auth()->user()->user_id;

        if($user_id == $costing->edit_user) {

          //check for created shop orders
          $has_shop_orders = $this->has_shop_orders_for_country($costing->id, $costing_country->country_id);
          if($has_shop_orders == true) {//has shop orders, cannot remove color
            return response(['data' => [
              'status' => 'error',
              'message' => 'Cannot remove country. Shop orders were already created.'
            ]]);
          }

          $fng_items = CostingFngItem::where('costing_id', '=', $costing->id)->where('country_id', '=', $costing_country->country_id)->get();
          //chek finish goods are created for this country
          if(sizeof($fng_items) > 0) {//has generated finisg goods
              $fng_item_ids = [];
              foreach($fng_items as $fng_item) {
                $bom = BOMHeader::where('costing_id', '=', $costing->id)->where('fng_id', '=', $fng_item->fng_id)->first();
                //remove bom items
                BOMDetails::where('bom_id', '=', $bom->bom_id)->delete();
                $bom->delete(); //remove bom

                //get afg ids
                $sfg_items = CostingSfgItem::where('costing_fng_id', '=', $fng_item->costing_fng_id)->pluck('sfg_id');
                //deactivate sfg items in item master
                DB::table('item_master')->whereIn('master_id', $sfg_items)->update(['status' => 0]);
                //remove costing sfg items
                CostingSfgItem::where('costing_fng_id', '=', $fng_item->costing_fng_id)->delete();

                array_push($fng_item_ids, $fng_item->fng_id);//add removed fng id
                //remove costing fng items
                $fng_item->delete();
              }
              //deactivate all fng items in item master
              DB::table('item_master')->whereIn('master_id', $fng_item_ids)->update(['status' => 0]);
          }
          //remove selected country
          $costing_country->delete();

          return response(['data' => [
            'status' => 'success',
            'message' => 'Country removed successfully',
            'countries' => $this->get_saved_countries($costing_country->costing_id),
          ]]);
        }
        else {
          return response(['data' => [
            'status' => 'error',
            'message' => "You don't have permissions to remove color"
          ]]);
        }
      }
      else {
        return response(['data' => [
          'status' => 'error',
          'message' => 'Costing is not in the edit mode'
        ]]);
      }



    }


    private function get_saved_countries($costing_id){
      $list = DB::select("SELECT
        costing_country.*,
        org_country.country_description,
        org_country.country_code
        FROM costing_country
        INNER JOIN org_country ON org_country.country_id = costing_country.country_id
        WHERE costing_country.costing_id = ?", [$costing_id]);

        return $list;
    }


    private function has_shop_orders_for_country($costing_id, $country_id){
      $fng_ids = CostingFngItem::where('costing_id', '=', $costing_id)->where('country_id', '=', $country_id)->pluck('fng_id');
      //$shop_order_count = DB::table('merc_shop_order_header')
      //->join('costing_fng_item', 'costing_fng_item.fng_id', '=', 'merc_shop_order_header.fg_id')
      //->whereNotIn('costing_fng_item.fng_id', $fng_ids)->count();
      $shop_order_count = DB::table('merc_shop_order_header')->whereIn('costing_fng_item.fng_id', $fng_ids)->count();

      if($shop_order_count > 0){ //already have shop orders
        return true;
      }
      else{
        return false;
      }
    }

    //*********************** Costing finish goods and bom *********************

    private function get_costing_finish_goods($costing_id){
      $costing = Costing::with(['style'])->find($costing_id);
      $product_feature = ProductFeature::find($costing->style->product_feature_id);
      $item = [];

      if($product_feature->count > 1){//has sfg items
        $list = DB::select("SELECT
            costing_sfg_item.costing_id,
            costing_fng_item.costing_fng_id,
            costing_fng_item.fng_id,
            item_master_fng.master_code AS fng_code,
            item_master_fng.master_description AS fng_description,
            org_color_fng.color_code AS fng_color_code,
            org_color_fng.color_name AS fng_color_name,
            costing_sfg_item.costing_sfg_id,
            costing_sfg_item.sfg_id,
            item_master_sfg.master_code AS sfg_code,
            item_master_sfg.master_description AS sfg_description,
            org_color_sfg.color_code AS sfg_color_code,
            org_color_sfg.color_name AS sfg_color_name,
            org_country.country_description
            FROM
            costing_sfg_item
            INNER JOIN costing_fng_item ON costing_fng_item.costing_fng_id = costing_sfg_item.costing_fng_id
            INNER JOIN item_master AS item_master_sfg ON item_master_sfg.master_id = costing_sfg_item.sfg_id
            INNER JOIN item_master AS item_master_fng ON item_master_fng.master_id = costing_fng_item.fng_id
            INNER JOIN org_country ON org_country.country_id = costing_sfg_item.country_id
            INNER JOIN org_color AS org_color_fng ON org_color_fng.color_id = item_master_fng.color_id
            INNEr JOIN org_color AS org_color_sfg ON org_color_sfg.color_id = item_master_sfg.color_id
            WHERE costing_sfg_item.costing_id = ?", [$costing_id]);
      }
      else {//no sfg items
        $list = DB::select("SELECT
            costing_fng_item.costing_id,
            costing_fng_item.costing_fng_id,
            costing_fng_item.fng_id,
            item_master.master_code AS fng_code,
            item_master.master_description AS fng_description,
            org_color.color_code AS fng_color_code,
            org_color.color_name AS fng_color_name,
            0 AS costing_sfg_id,
            0 AS sfg_id,
            '' AS sfg_code,
            ''  AS sfg_description,
            '' AS sfg_color_code,
            '' AS sfg_color_name,
            org_country.country_description
            FROM
            costing_fng_item
            INNER JOIN item_master ON item_master.master_id = costing_fng_item.fng_id
            INNER JOIN org_country ON org_country.country_id = costing_fng_item.country_id
            INNER JOIN org_color ON org_color.color_id = costing_fng_item.fng_color_id
            WHERE costing_fng_item.costing_id = ?", [$costing_id]);
      }

          return $list;
    }

    //*************** Costing SMV details **************************************

    private function get_costing_smv_details($costing_id){

      $costing = Costing::find($costing_id);
      $have_smv_for_buy = false;

      $query = DB::table('ie_component_smv_header')
      ->where('status', '=', 1)
      ->where('style_id', '=', $costing->style_id)
      ->where('bom_stage_id', '=', $costing->bom_stage_id)
      ->where('col_opt_id', '=', $costing->color_type_id);

      if($costing->buy_id != null && $costing->buy_id != '' && $costing->buy_id != "0"){
        $query->where('buy_id', '=', $costing->buy_id);
        $total_smv = $query->first();

        if($total_smv == null || $total_smv == false){
          $have_smv_for_buy = false;
        }
        else {
          $have_smv_for_buy = true;
        }
      }
      else {
        $have_smv_for_buy = false;
      }

      $sql = "SELECT
        ie_component_smv_details.details_id,
        ie_component_smv_details.product_component_id,
        product_component.product_component_description,
        ie_component_smv_details.product_silhouette_id,
        product_silhouette.product_silhouette_description,
        ie_garment_operation_master.garment_operation_name,
        ie_component_smv_details.smv
        FROM
        ie_component_smv_details
        INNER JOIN ie_component_smv_header ON ie_component_smv_header.smv_component_header_id = ie_component_smv_details.smv_component_header_id
        INNER JOIN product_component ON product_component.product_component_id = ie_component_smv_details.product_component_id
        INNER JOIN product_silhouette ON product_silhouette.product_silhouette_id = ie_component_smv_details.product_silhouette_id
        INNER JOIN ie_garment_operation_master ON ie_garment_operation_master.garment_operation_id = ie_component_smv_details.garment_operation_id
        INNER JOIN costing ON costing.style_id = ie_component_smv_header.style_id
               AND costing.bom_stage_id = ie_component_smv_header.bom_stage_id
               AND costing.color_type_id = ie_component_smv_header.col_opt_id
        WHERE costing.id = ?";

        $arr = [$costing_id];
        if($have_smv_for_buy == true){
          $sql .= " AND ie_component_smv_header.buy_id = ?";
          array_push($arr, $costing->buy_id);
        }
        else {
          $sql .= " AND ie_component_smv_header.buy_id IS NULL";
        }

      $list = DB::select($sql, $arr);
      return $list;
    }

    //***********************************************************

    private function finance_cost(){
      $current_timestamp = date("Y-m-d H:i:s");
      $finance_details = FinanceCost::where('effective_from', '<=', $current_timestamp)
      ->where('effective_to', '>=', $current_timestamp)
      ->where('status', 1)->first();

      if($finance_details != null && $finance_details != false){
        return [
          'status' => 'success',
          'finance_details' => $finance_details
        ];
      }
      else{
        return [
          'status' => 'error',
          'message' => 'Finance details not avaliable. Contact finace team.'
        ];
      }
    }


    private function total_smv($style_id, $bom_stage_id, $color_type_id, $buy_id) {
      $total_smv = null;

      if($buy_id == "0"){//get smv without buy
       $total_smv = DB::table('ie_component_smv_header')->where('style_id', '=', $style_id)->where('bom_stage_id', '=', $bom_stage_id)
        ->where('col_opt_id', '=', $color_type_id)->where('status', '=', 1)->first();
      }
      else {
        $total_smv = DB::table('ie_component_smv_header')->where('style_id', '=', $style_id)->where('bom_stage_id', '=', $bom_stage_id)
        ->where('col_opt_id', '=', $color_type_id)->where('buy_id', '=', $buy_id)->where('status', '=', 1)->first();

        if($total_smv == null) {
          $total_smv = DB::table('ie_component_smv_header')->where('style_id', '=', $style_id)->where('bom_stage_id', '=', $bom_stage_id)
          ->where('col_opt_id', '=', $color_type_id)->where('status', '=', 1)->first();
        }
      }

      if($total_smv == null || $total_smv == false){
        return [
          'status' => 'error',
          'message' => 'SMV details not avaliable. Contact IE team.'
        ];
      }
      else{
        return [
          'status' => 'success',
          'total_smv' => $total_smv->total_smv
        ];
      }
    }


    private function get_total_smv($style_id, $bom_stage_id, $color_type_id, $buy_id){
      $query = DB::table('ie_component_smv_header')
      ->where('status', '=', 1)
      ->where('style_id', '=', $style_id)
      ->where('bom_stage_id', '=', $bom_stage_id)
      ->where('col_opt_id', '=', $color_type_id);

      if($buy_id != null && $buy_id != '' && $buy_id != "0"){
        $query->where('buy_id', '=', $buy_id);
      }

      $total_smv = $query->first();

      if($total_smv == null || $total_smv == false){
          //no smv for selected buy, then check the smv without buy id
          if($buy_id != null && $buy_id != '' && $buy_id != 0){
            $total_smv = DB::table('ie_component_smv_header')
            ->where('status', '=', 1)
            ->where('style_id', '=', $style_id)
            ->where('bom_stage_id', '=', $bom_stage_id)
            ->where('col_opt_id', '=', $color_type_id)
            ->first();

            if($total_smv == null || $total_smv == false){
              return 0;
            }
            else {
              return $total_smv->total_smv;
            }
          }
      }
      else{
        return $total_smv->total_smv;
      }
    }

    /*private function calculate_fg_component_rm_cost($fg_component_id){
      $cost = CostingFinishGoodComponentItem::where('fg_component_id', '=', $fg_component_id)->sum('total_cost');
      return $cost;
    }*/

    /*private function calculate_fg_rm_cost($fg_id){
      $cost = CostingFinishGoodComponentItem::join('costing_finish_good_components', 'costing_finish_good_components.id', '=', 'costing_finish_good_component_items.fg_component_id')
      ->where('costing_finish_good_components.id', '=', $fg_id)->sum('costing_finish_good_component_items.total_cost');
      return $cost;
    }*/


    /*private function get_finish_good($style_id, $bom_stage, $color_type) {
      $product_feature_components = DB::select("SELECT
        ie_component_smv_summary.product_component_id,
        ie_component_smv_summary.product_silhouette_id,
        ie_component_smv_summary.line_no,
        ie_component_smv_summary.total_smv AS smv,
        product_feature.product_feature_id,
        product_component.product_component_description,
        product_silhouette.product_silhouette_description,
        product_feature.product_feature_description,
        '0' AS mcq,
        '0' AS surcharge,
        '0' AS epm,
        '0' AS np,
        '0' AS id,
        '1' AS pack_no,
        'FG001' AS pack_no_code,
        '' AS combo_color,
        '' AS color,
        '0' AS fg_id
        FROM ie_component_smv_summary
        INNER JOIN ie_component_smv_header ON ie_component_smv_header.smv_component_header_id = ie_component_smv_summary.smv_component_header_id
        INNER JOIN product_component ON product_component.product_component_id = ie_component_smv_summary.product_component_id
        INNER JOIN product_silhouette ON product_silhouette.product_silhouette_id = ie_component_smv_summary.product_silhouette_id
        INNER JOIN product_feature ON product_feature.product_feature_id = ie_component_smv_header.product_feature_id
        WHERE ie_component_smv_header.style_id = ?
        AND ie_component_smv_header.bom_stage_id = ?
        AND ie_component_smv_header.col_opt_id = ?
        AND ie_component_smv_header.status = ? ", [$style_id, $bom_stage, $color_type, 1]);

        return $product_feature_components;
    }*/

    private function get_finish_good_color($style_id) {
      $product_feature_components = DB::select("SELECT
        product_feature.product_feature_id,
        product_component.product_component_description,
        product_silhouette.product_silhouette_description,
        product_feature.product_feature_description,
        product_component.product_component_id,
        product_silhouette.product_silhouette_id,
        product_feature.product_feature_id,
        product_feature_component.line_no AS product_component_line_no,
        0 AS fng_color,
        0 AS sfg_color,
        '' AS fng_color_code,
        '' AS fng_color_name,
        '' AS sfg_color_code,
        '' AS sfg_color_name,
        0 AS fng_color_id,
        0 AS sfg_color_id,
        1 AS edited
        FROM product_feature_component
        INNER JOIN product_feature ON product_feature.product_feature_id = product_feature_component.product_feature_id
        INNER JOIN product_silhouette ON product_silhouette.product_silhouette_id = product_feature_component.product_silhouette_id
        INNER JOIN product_component ON product_component.product_component_id = product_feature_component.product_component_id
        INNER JOIN style_creation ON style_creation.product_feature_id = product_feature.product_feature_id
        WHERE style_creation.style_id = ? AND product_feature_component.status = 1", [$style_id]);

        return $product_feature_components;
    }


    private function get_saved_finish_good_colors($costing_id, $feature_component_count) {

      $sql = "SELECT
        product_component.product_component_description,
        product_silhouette.product_silhouette_description,
        product_feature.product_feature_description,
        product_feature.product_feature_id,
        product_component.product_component_id,
        product_silhouette.product_silhouette_id,
        costing_sfg_color.product_component_line_no,
        org_color_fng.color_id AS fng_color,
        org_color_fng.color_code AS fng_color_code,
        org_color_fng.color_name AS fng_color_name,
        org_color_sfg.color_id AS sfg_color,";

      if($feature_component_count > 1){//has semi finish goods, show sfg colors
        $sql .= "org_color_sfg.color_code AS sfg_color_code,
          org_color_sfg.color_name AS sfg_color_name,";
      }
      else {//no semi finish goods, no nned to show sfg colors
        $sql .= "'' AS sfg_color_code,
          '' AS sfg_color_name,";
      }
      $sql .= "costing_fng_color.fng_color_id,
        costing_sfg_color.sfg_color_id,
        0 AS edited
        FROM
        costing_sfg_color
        INNER JOIN costing_fng_color ON costing_fng_color.fng_color_id = costing_sfg_color.fng_color_id
        INNER JOIN costing ON costing.id = costing_fng_color.costing_id
        INNER JOIN product_feature ON product_feature.product_feature_id = costing_fng_color.product_feature_id
        INNER JOIN product_silhouette ON product_silhouette.product_silhouette_id = costing_sfg_color.product_silhouette_id
        INNER JOIN product_component ON product_component.product_component_id = costing_sfg_color.product_component_id
        INNER JOIN org_color AS org_color_fng ON org_color_fng.color_id = costing_fng_color.color_id
        INNER JOIN org_color AS org_color_sfg ON org_color_sfg.color_id = costing_sfg_color.color_id
        WHERE costing.id = ? ORDER BY costing_sfg_color.fng_color_id,costing_sfg_color.product_component_id,
        costing_sfg_color.product_silhouette_id,costing_sfg_color.product_component_line_no";

        $list = DB::select($sql, [$costing_id]);
        return $list;
    }


    /*private function get_saved_finish_good($id){
      $costing = Costing::find($id);
      //$style = StyleCreation::find($costing->style_id);

      $product_feature_components = DB::select("SELECT
        costing_finish_good_components.*,
        costing_finish_goods.pack_no,
        costing_finish_goods.pack_no_code,
        costing_finish_goods.combo_color_id,
        costing_finish_goods.epm,
        costing_finish_goods.np,
        product_feature.product_feature_id,
        product_component.product_component_description,
        product_silhouette.product_silhouette_description,
        product_feature.product_feature_description,
        color1.color_code AS combo_color,
        color1.color_code AS combo_color2,
        color2.color_code AS color
        FROM costing_finish_good_components
        INNER JOIN costing_finish_goods ON costing_finish_goods.fg_id = costing_finish_good_components.fg_id
        INNER JOIN product_component ON product_component.product_component_id = costing_finish_good_components.product_component_id
        INNER JOIN product_silhouette ON product_silhouette.product_silhouette_id = costing_finish_good_components.product_silhouette_id
        INNER JOIN product_feature ON product_feature.product_feature_id = costing_finish_goods.product_feature
        LEFT JOIN org_color AS color1 ON color1.color_id = costing_finish_goods.combo_color_id
        LEFT JOIN org_color AS color2 ON color2.color_id = costing_finish_good_components.color_id
        WHERE costing_finish_goods.costing_id = ?
        ORDER BY costing_finish_good_components.fg_id, costing_finish_good_components.id", [$id]);

        return $product_feature_components;
    }*/


    private function datatable_search($data)
    {
          $start = $data['start'];
          $length = $data['length'];
          $draw = $data['draw'];
          $search = $data['search']['value'];
          $order = $data['order'][0];
          $order_column = $data['columns'][$order['column']]['data'];
          $order_type = $order['dir'];
          $user_id = auth()->user()->user_id;
//echo $user_id;die();
          $costing_list = Costing::select('costing.*','style_creation.style_no','merc_bom_stage.bom_stage_description',
            'org_season.season_name', 'merc_color_options.color_option')
          ->join('style_creation', 'style_creation.style_id', '=', 'costing.style_id')
          ->join('merc_bom_stage', 'merc_bom_stage.bom_stage_id', '=', 'costing.bom_stage_id')
          ->join('org_season', 'org_season.season_id', '=', 'costing.season_id')
          ->join('merc_color_options', 'merc_color_options.col_opt_id', '=', 'costing.color_type_id')
          ->where('costing.created_by', '=', $user_id)
          ->where(function ($query) use ($search) {
              $query->orWhere('costing.id', 'like', $search.'%' )
              ->orWhere('style_creation.style_no'  , 'like', $search.'%' )
              ->orWhere('merc_bom_stage.bom_stage_description','like',$search.'%')
              ->orWhere('org_season.season_name','like',$search.'%')
              ->orWhere('merc_color_options.color_option','like',$search.'%');
          })

          /*->orWhere('style_creation.style_no'  , 'like', $search.'%' )
          ->orWhere('merc_bom_stage.bom_stage_description','like',$search.'%')
          ->orWhere('org_season.season_name','like',$search.'%')
          ->orWhere('merc_color_options.color_option','like',$search.'%')*/
          ->orderBy($order_column, $order_type)
          ->offset($start)->limit($length)->get();

          $costing_count = Costing::join('style_creation', 'style_creation.style_id', '=', 'costing.style_id')
          ->join('merc_bom_stage', 'merc_bom_stage.bom_stage_id', '=', 'costing.bom_stage_id')
          ->join('org_season', 'org_season.season_id', '=', 'costing.season_id')
          ->join('merc_color_options', 'merc_color_options.col_opt_id', '=', 'costing.color_type_id')
          ->where('costing.created_by', '=', $user_id)
          ->where(function ($query) use ($search) {
              $query->orWhere('costing.id', 'like', $search.'%' )
              ->orWhere('style_creation.style_no'  , 'like', $search.'%' )
              ->orWhere('merc_bom_stage.bom_stage_description','like',$search.'%')
              ->orWhere('org_season.season_name','like',$search.'%')
              ->orWhere('merc_color_options.color_option','like',$search.'%');
          })
          ->count();

          echo json_encode([
              "draw" => $draw,
              "recordsTotal" => $costing_count,
              "recordsFiltered" => $costing_count,
              "data" => $costing_list
          ]);
    }


    private function get_artical_numbers($search){
      $list = DB::table('costing_finish_good_component_items')->where('article_no', 'like', $search . '%')
      ->distinct()->select('article_no')->get()->pluck('article_no');
      return $list;
    }

    private function get_item_uom($item_description){
      $list = DB::table('item_uom')
      ->join('org_uom', 'org_uom.uom_id', '=', 'item_uom.uom_id')
      ->join('item_master', 'item_master.master_id', '=', 'item_uom.master_id')
      ->where('item_master.master_description', '=', $item_description)->get()->pluck('uom_code');
      return $list;
    }


    private function save_costing_revision($id, $revision_no, $revision_reason){

      $costing = (array)DB::table('costing')->where('id', '=', $id)->first();
      $costing = json_decode( json_encode($costing), true);//convert resullset to array
      $costing['revision_reason'] = $revision_reason;
      DB::table('costing_history')->insert($costing);

      $countries = DB::table('costing_country')->where('costing_id', '=', $id)->get();
      $countries = json_decode( json_encode($countries), true);//convert resullset to array
      //$fg_id_arr = [];
      for($x = 0 ; $x < sizeof($countries); $x++){
       $countries[$x]['revision_no'] = $revision_no;
        //array_push($fg_id_arr, $finish_goods[$x]['fg_id']);
      }
      DB::table('costing_country_history')->insert($countries);

      $fng_colors = DB::table('costing_fng_color')->where('costing_id', '=', $id)->get();
      $fng_colors = json_decode( json_encode($fng_colors), true);//convert resullset to array
      //$fg_id_arr = [];
      for($x = 0 ; $x < sizeof($fng_colors); $x++){
        $fng_colors[$x]['revision_no'] = $revision_no;
        //array_push($fg_id_arr, $finish_goods[$x]['fg_id']);
        $sfg_colors = DB::table('costing_sfg_color')->where('fng_color_id', '=', $fng_colors[$x]['fng_color_id'])->get();
        $sfg_colors = json_decode( json_encode($sfg_colors), true);//convert resullset to array
        for($y = 0 ; $y < sizeof($sfg_colors); $y++){
          $sfg_colors[$y]['revision_no'] = $revision_no;
        }
		//echo json_encode($sfg_colors[$x]);die();
        DB::table('costing_sfg_color_history')->insert($sfg_colors);
      }
      DB::table('costing_fng_color_history')->insert($fng_colors);

      $fng_items = DB::table('costing_fng_item')->where('costing_id', '=', $id)->get();
      $fng_items = json_decode( json_encode($fng_items), true);//convert resullset to array
      //$fg_id_arr = [];
      for($x = 0 ; $x < sizeof($fng_items); $x++){
        $fng_items[$x]['revision_no'] = $revision_no;
        //array_push($fg_id_arr, $finish_goods[$x]['fg_id']);
        $sfg_items = DB::table('costing_sfg_item')->where('costing_fng_id', '=', $fng_items[$x]['costing_fng_id'])->get();
        $sfg_items = json_decode( json_encode($sfg_items), true);//convert resullset to array
        for($y = 0 ; $y < sizeof($sfg_items); $y++){
          $sfg_items[$y]['revision_no'] = $revision_no;
        }
        DB::table('costing_sfg_item_history')->insert($sfg_items);
      }
      DB::table('costing_fng_item_history')->insert($fng_items);


      $costing_items = DB::table('costing_items')->where('costing_id', '=', $id)->get();
      $costing_items = json_decode( json_encode($costing_items), true);//convert resullset to array
      for($x = 0 ; $x < sizeof($costing_items); $x++){
        $costing_items[$x]['revision_no'] = $revision_no;
      }
      DB::table('costing_items_history')->insert($costing_items);
    }


    private function get_color_id_from_name($color_name){
      if($color_name == null || $color_name == false || $color_name == ''){
        return null;
      }
      else{
         $color = Color::where('color_code', '=', $color_name)->first();
         return $color->color_id;
      }
    }


    private function autocomplete_search($search)            {
          $ists = Costing::select('id','sc_no')
          ->where([['sc_no', 'like', '%' . $search . '%']/*, ['status', '=', 'APPROVED']*/]) ->get();
          return $ists;
    }


    private function get_item_from_article_no($article_no){
      $component_item = CostingFinishGoodComponentItem::where('article_no', '=', $article_no)->first();
      if($component_item == null || $component_item == ''){
        return null;
      }
      else{
        $item = Item::find($component_item->master_id);
        return $item;
      }
    }

    private function get_product_feature_components($style_id){
      $product_feature_components = DB::select("SELECT
        product_feature.product_feature_id,
        product_feature.product_feature_description,
        product_component.product_component_id,
        product_component.product_component_description,
        product_silhouette.product_silhouette_id,
        product_silhouette.product_silhouette_description,
        product_feature_component.feature_component_id,
        product_feature_component.line_no
        FROM product_feature_component
        INNER JOIN product_feature ON product_feature.product_feature_id = product_feature_component.product_feature_id
        INNER JOIN product_silhouette ON product_silhouette.product_silhouette_id = product_feature_component.product_silhouette_id
        INNER JOIN product_component ON product_component.product_component_id = product_feature_component.product_component_id
        INNER JOIN style_creation ON style_creation.product_feature_id = product_feature.product_feature_id
        WHERE style_creation.style_id = ? AND product_feature_component.status = 1", [$style_id]);

        return $product_feature_components;
    }

    /*private function generate_bom_for_costing($costing_id) {
      $deliveries = CustomerOrderDetails::where('costing_id', '=', $costing_id)->get();
      $costing = Costing::find($costing_id);
      for($y = 0; $y < sizeof($deliveries); $y++) {
        $bom = new BOMHeader();
        $bom->costing_id = $deliveries[$y]->costing_id;
        $bom->delivery_id = $deliveries[$y]->details_id;
        $bom->sc_no = $costing->sc_no;
        $bom->status = 1;
        $bom->save();

        $components = CostingFinishGoodComponent::where('fg_id', '=', $deliveries[$y]->fg_id)->get()->pluck('id');
        $items = CostingFinishGoodComponentItem::whereIn('fg_component_id', $components)->get();
        $items = json_decode(json_encode($items), true); //conver to array
        for($x = 0 ; $x < sizeof($items); $x++) {
          $items[$x]['bom_id'] = $bom->bom_id;
          $items[$x]['costing_item_id'] = $items[$x]['id'];
          $items[$x]['id'] = 0; //clear id of previous data, will be auto generated
          $items[$x]['bom_unit_price'] = $items[$x]['unit_price'];
          $items[$x]['order_qty'] = $deliveries[$y]->order_qty * $items[$x]['gross_consumption'];
          $items[$x]['required_qty'] = $deliveries[$y]->order_qty * $items[$x]['gross_consumption'];
          $items[$x]['total_cost'] = (($items[$x]['unit_price'] * $items[$x]['gross_consumption'] * $deliveries[$y]->order_qty) + $items[$x]['freight_charges'] + $items[$x]['surcharge']);
          $items[$x]['created_date'] = null;
          $items[$x]['created_by'] = null;
          $items[$x]['updated_date'] = null;
          $items[$x]['updated_by'] = null;
        }
        DB::table('bom_details')->insert($items);
      }
    }*/

    private function get_order_qty_efficiency($style_id, $order_qty){
      $data = DB::table('product_average_efficiency')
      ->select(DB::raw( 'SUM(product_average_efficiency.efficiency) / product_feature.count as average_efficiency'))
      ->join('product_feature_component', 'product_feature_component.product_silhouette_id', '=', 'product_average_efficiency.product_silhouette_id')
      ->join('product_feature', 'product_feature.product_feature_id', '=', 'product_feature_component.product_feature_id')
      ->join('style_creation', function ($join) {
              $join->on('style_creation.product_feature_id', '=', 'product_feature.product_feature_id');
              $join->on('product_average_efficiency.prod_cat_id', 'style_creation.product_category_id');
          }
      )
      ->where('style_creation.style_id', '=', $style_id)
      ->where('product_average_efficiency.qty_from', '<=', $order_qty)
      ->where('product_average_efficiency.qty_to', '>=', $order_qty)->first();
      return round($data->average_efficiency);
    }


    private function get_bom_stages($style){
      $list = DB::table('merc_bom_stage')
      ->join('ie_component_smv_header', 'ie_component_smv_header.bom_stage_id', '=', 'merc_bom_stage.bom_stage_id')
      ->select('merc_bom_stage.bom_stage_id', 'merc_bom_stage.bom_stage_description')
      ->where('merc_bom_stage.status', '=', 1)
      ->where('ie_component_smv_header.status', '=', 1)
      ->where('ie_component_smv_header.style_id', '=', $style)
      ->groupBy('merc_bom_stage.bom_stage_id')
      ->get();
      return $list;
    }


    private function get_color_types($style){
      $list = DB::table('merc_color_options')
      ->join('ie_component_smv_header', 'ie_component_smv_header.col_opt_id', '=', 'merc_color_options.col_opt_id')
      ->select('merc_color_options.col_opt_id', 'merc_color_options.color_option')
      ->where('merc_color_options.status', '=', 1)
      ->where('ie_component_smv_header.status', '=', 1)
      ->where('ie_component_smv_header.style_id', '=', $style)
      ->groupBy('merc_color_options.col_opt_id')
      ->get();
      return $list;
    }


    private function get_buy_names($style){
      $list = DB::table('buy_master')
      ->join('ie_component_smv_header', 'ie_component_smv_header.buy_id', '=', 'buy_master.buy_id')
      ->select('buy_master.buy_id', 'buy_master.buy_name')
      ->where('buy_master.status', '=', 1)
      ->where('ie_component_smv_header.status', '=', 1)
      ->where('ie_component_smv_header.style_id', '=', $style)
      ->groupBy('buy_master.buy_id')
      ->get();
      return $list;
    }


    //Costing yy updates
    public function get_items_for_yy_update(Request $request){
      $style_id = $request->style_id;
      $bom_stage_id = $request->bom_stage_id;
      $division_id = $request->division_id;
      $color_type_id = $request->color_type_id;
      $buy_id = $request->buy_id;
      $product_component = $request->product_component;
      $item_category = $request->item_category;
      $merchant = $request->merchant;
      $customer_id = $request->customer_id;
      $lot_no = $request->lot_no;
      $costing_id = $request->costing_id;

      $sql = "SELECT
      	costing_items.*,
      	item_master.supplier_reference,
      	item_master.master_code,
      	item_master.master_description,
      	item_category.category_name,
      	item_category.category_code,
      	merc_position.position,
      	org_uom.uom_code,
        org_uom.is_decimal_allowed,
      	org_color.color_code,
      	org_color.color_name,
      	org_supplier.supplier_name,
      	org_origin_type.origin_type,
      	org_garment_options.garment_options_description,
      	fin_shipment_term.ship_term_description,
      	org_country.country_description,
      	style_creation.style_id,
        style_creation.style_description,
        costing.total_order_qty,
        costing.revision_no,
        DATE_FORMAT(costing.created_date, '%d-%b-%Y') AS costing_created_date,
        usr_login.user_name,
        '0' AS edited
      FROM
      	costing_items
      INNER JOIN costing ON costing.id = costing_items.costing_id
      INNER JOIN style_creation ON style_creation.style_id = costing.style_id
      LEFT JOIN item_master ON `item_master`.`master_id` = `costing_items`.`inventory_part_id`
      LEFT JOIN item_category ON `item_category`.`category_id` = `item_master`.`category_id`
      LEFT JOIN merc_position ON `merc_position`.`position_id` = `costing_items`.`position_id`
      LEFT JOIN org_uom ON `org_uom`.`uom_id` = `costing_items`.`purchase_uom_id`
      LEFT JOIN org_color ON `org_color`.`color_id` = `item_master`.`color_id`
      LEFT JOIN org_supplier ON `org_supplier`.`supplier_id` = `costing_items`.`supplier_id`
      LEFT JOIN org_origin_type ON `org_origin_type`.`origin_type_id` = `costing_items`.`origin_type_id`
      LEFT JOIN org_garment_options ON `org_garment_options`.`garment_options_id` = `costing_items`.`garment_options_id`
      LEFT JOIN fin_shipment_term ON `fin_shipment_term`.`ship_term_id` = `costing_items`.`ship_term_id`
      LEFT JOIN org_country ON `org_country`.`country_id` = `costing_items`.`country_id`
      LEFT JOIN usr_login ON usr_login.user_id = costing.created_by
      WHERE costing.consumption_added_notification_status = 1 AND org_uom.uom_code != 'PCS' AND style_creation.customer_id = ? ";
      $parameters = [$customer_id];

      if($lot_no == null || $lot_no == 'null' || $lot_no == ''){
        $sql .= "AND (costing.lot_no IS NULL || costing.lot_no LIKE '%') ";
      }
      else {
        $sql .= "AND costing.lot_no LIKE ? ";
        array_push($parameters, $lot_no."%");
      }

      if($merchant != null && $merchant != 'null' && $merchant != ''){
        $sql .= "AND usr_login.user_name LIKE ? ";
        array_push($parameters, $merchant."%");
      }

      if($costing_id != 0){
        $sql .= "AND costing_items.costing_id = ? ";
        array_push($parameters, $costing_id);
      }

      if($style_id != "0"){
        $sql .= "AND style_creation.style_id = ? ";
        array_push($parameters, $style_id);
      }

      if($bom_stage_id != "0"){
        $sql .= "AND costing.bom_stage_id = ? ";
        array_push($parameters, $bom_stage_id);
      }

      if($division_id != "0"){
        $sql .= "AND style_creation.division_id = ? ";
        array_push($parameters, $division_id);
      }

      if($color_type_id != "0"){
        $sql .= "AND costing.color_type_id = ? ";
        array_push($parameters, $color_type_id);
      }

      if($buy_id != "0"){
        $sql .= "AND costing.buy_id = ? ";
        array_push($parameters, $buy_id);
      }

      if($product_component != "0"){
        $sql .= "AND costing_items.product_component_id = ? ";
        array_push($parameters, $product_component);
      }

      if($item_category != "0"){
        $sql .= "AND item_master.category_id = ? ";
        array_push($parameters, $item_category);
      }

      $sql .= "ORDER BY item_category.category_name ASC";
      //echo $sql;die();
      $list =  DB::select($sql, $parameters);
      return $list;
    }

}
