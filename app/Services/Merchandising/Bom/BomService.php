<?php

namespace App\Services\Merchandising\Bom;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

use App\Models\Merchandising\BOMHeader;
use App\Models\Merchandising\BOMDetails;

use App\Models\Merchandising\Costing\Costing;

use App\Models\Merchandising\ShopOrderHeader;
use App\Models\Merchandising\ShopOrderDetail;

class BomService
{

  private function calculate_fabric_cost($bom_id){
    $cost = BOMDetails::join('item_master', 'item_master.master_id', '=', 'bom_details.inventory_part_id')
    ->join('item_category', 'item_category.category_id', '=', 'item_master.category_id')
    ->where('bom_details.bom_id', '=', $bom_id)
    ->where('item_category.category_code', '=', 'FAB')
    ->sum('bom_details.total_cost');
    return $cost;
  }

  private function calculate_trim_cost($bom_id){
    $cost = BOMDetails::join('item_master', 'item_master.master_id', '=', 'bom_details.inventory_part_id')
    ->join('item_category', 'item_category.category_id', '=', 'item_master.category_id')
    ->where('bom_details.bom_id', '=', $bom_id)
    ->where('item_category.category_code', '=', 'TRM')
    ->sum('bom_details.total_cost');
    return $cost;
  }

  private function calculate_elastic_cost($bom_id){
    $cost = BOMDetails::join('item_master', 'item_master.master_id', '=', 'bom_details.inventory_part_id')
    ->join('item_category', 'item_category.category_id', '=', 'item_master.category_id')
    ->where('bom_details.bom_id', '=', $bom_id)
    ->where('item_category.category_code', '=', 'ELA')
    ->sum('bom_details.total_cost');
    return $cost;
  }

  private function calculate_packing_cost($bom_id){
    $cost = BOMDetails::join('item_master', 'item_master.master_id', '=', 'bom_details.inventory_part_id')
    ->join('item_category', 'item_category.category_id', '=', 'item_master.category_id')
    ->where('bom_details.bom_id', '=', $bom_id)
    ->where('item_category.category_code', '=', 'PAC')
    ->sum('bom_details.total_cost');
    return $cost;
  }

  private function calculate_other_cost($bom_id){
    $cost = BOMDetails::join('item_master', 'item_master.master_id', '=', 'bom_details.inventory_part_id')
    ->join('item_category', 'item_category.category_id', '=', 'item_master.category_id')
    ->where('bom_details.bom_id', '=', $bom_id)
    ->where('item_category.category_code', '=', 'OTHER')
    ->sum('bom_details.total_cost');
    return $cost;
  }

  private function calculate_rm_cost($bom_id){
    $cost = BOMDetails::where('bom_id', '=', $bom_id)
    ->sum('total_cost');
    return $cost;
  }


  public function calculate_epm($fob, $total_rm_cost, $smv){
    $epm = ($smv == 0) ? 0 : ($fob - $total_rm_cost) / $smv; //(fob - rm cost) / smv
    return round($epm, 4, PHP_ROUND_HALF_UP ); //round and return
  }

  public function calculate_np($fob, $total_cost){
    $np = ($fob == 0) ? 0 : ($fob - $total_cost) / $fob; //(total cost - fob) / total cost
    return round($np, 4, PHP_ROUND_HALF_UP ); //round and return
  }


  public function update_bom_summary($bom_id){
    //$costing_item = CostingItem::find($costing_item_id);
    $bom = BOMHeader::find($bom_id);

    $fabric_cost = $this->calculate_fabric_cost($bom_id);
    $trim_cost = $this->calculate_trim_cost($bom_id);
    $packing_cost = $this->calculate_packing_cost($bom_id);
    $elastic_cost = $this->calculate_elastic_cost($bom_id);
    $other_cost = $this->calculate_other_cost($bom_id);

    $total_rm_cost = $this->calculate_rm_cost($bom_id);
    $finance_cost = ($total_rm_cost * $bom->finance_charges) / 100;
    $total_cost = $total_rm_cost + $bom->labour_cost + $finance_cost + $bom->coperate_cost;//rm cost + labour cost + finance cost + coperate cost
    $epm = $this->calculate_epm($bom->fob, $total_rm_cost, $bom->total_smv);//calculate fg epm
    $np = $this->calculate_np($bom->fob, $total_cost); //calculate fg np value

    $bom->total_rm_cost = round($total_rm_cost, 4, PHP_ROUND_HALF_UP ); //round and assign
    $bom->finance_cost = round($finance_cost, 4, PHP_ROUND_HALF_UP ); //round and assign
    $bom->fabric_cost = round($fabric_cost, 4, PHP_ROUND_HALF_UP ); //round and assign
    $bom->trim_cost = round($trim_cost, 4, PHP_ROUND_HALF_UP ); //round and assign
    $bom->packing_cost = round($packing_cost, 4, PHP_ROUND_HALF_UP ); //round and assign
    $bom->elastic_cost = round($elastic_cost, 4, PHP_ROUND_HALF_UP ); //round and assign
    $bom->other_cost = round($other_cost, 4, PHP_ROUND_HALF_UP ); //round and assign
    $bom->total_cost = round($total_cost, 4, PHP_ROUND_HALF_UP ); //round and assign
    $bom->epm = $epm;
    $bom->np_margin = $np;
    $bom->save();
  }


  public function save_bom_revision($bom_id){
    $bom = BOMHeader::find($bom_id);
    $bom->revision_no = $bom->revision_no + 1;
    $bom->save();
    //save bom header revision
    DB::insert("INSERT INTO bom_header_history SELECT 0 AS history_id, bom_header.* FROM bom_header WHERE bom_header.bom_id = ?", [$bom_id]);
    //save bom details history_id
    DB::insert("INSERT INTO bom_details_history SELECT 0 AS history_id, bom_details.* FROM bom_details WHERE bom_details.bom_id = ?", [$bom_id]);
  }


  public function is_bom_need_approval($bom_id){
    $bom = BOMHeader::find($bom_id);
    $costing = Costing::find($bom->costing_id);

    $data = DB::select("SELECT cust_division.epm_level_1,cust_division.np_level_1 FROM style_creation
      INNER JOIN cust_division ON cust_division.division_id = style_creation.division_id
      WHERE style_id = ?", [$costing->style_id]);
    $need_approval = false;

    if($data != null && sizeof($data) > 0){
      if($bom->epm > $data[0]->epm_level_1 && $bom->np_margin > $data[0]->np_level_1){
        $need_approval = false;
      }
      else {
        $need_approval = true;
      }
    }
    return $need_approval;
  }


  public function create_shop_order_items($bom_id){
    try {
      $bom = BOMHeader::find($bom_id);
      $shop_order = ShopOrderHeader::where('fg_id', '=', $bom->fng_id)->first();

      if($shop_order == null){//no shop order for this bom
        return false;
      }

      //delete items not in the bom and only exists in the shop order
     $bom_deleted_item = ShopOrderDetail::leftjoin('bom_details', 'bom_details.bom_detail_id', '=', 'merc_shop_order_detail.bom_detail_id')
     ->where('merc_shop_order_detail.bom_id', '=', $bom_id)
     ->whereNull('bom_details.bom_detail_id')->delete();

     //get items only in bom and insert items to shop order
      $items = DB::select("SELECT bom_details.* FROM bom_details
        LEFT JOIN merc_shop_order_detail ON merc_shop_order_detail.bom_detail_id = bom_details.bom_detail_id
        WHERE bom_details.bom_id = ? AND merc_shop_order_detail.shop_order_detail_id IS NULL", [$bom_id]);

      if(sizeof($items) > 0){
        for($x = 0 ; $x < sizeof($items) ; $x++)
        {
            $shoporder_detail= new ShopOrderDetail();
            $shoporder_detail->shop_order_id = $shop_order->shop_order_id;
            $shoporder_detail->bom_id = $items[$x]->bom_id;
            $shoporder_detail->bom_detail_id = $items[$x]->bom_detail_id;
            $shoporder_detail->costing_item_id = $items[$x]->costing_item_id;
            $shoporder_detail->costing_id = $items[$x]->costing_id;
            $shoporder_detail->component_id = $items[$x]->product_component_id;
            $shoporder_detail->inventory_part_id = $items[$x]->inventory_part_id;
            $shoporder_detail->supplier = $items[$x]->supplier_id;
            $shoporder_detail->purchase_price = $items[$x]->purchase_price;
            $shoporder_detail->postion_id = $items[$x]->position_id;
            $shoporder_detail->purchase_uom = $items[$x]->purchase_uom_id;
            $shoporder_detail->orign_type_id = $items[$x]->origin_type_id;
            $shoporder_detail->garment_option_id = $items[$x]->garment_options_id;
            $shoporder_detail->unit_price = $items[$x]->bom_unit_price;
            $shoporder_detail->net_consumption = $items[$x]->net_consumption;
            $shoporder_detail->wastage = $items[$x]->wastage;
            $shoporder_detail->gross_consumption = $items[$x]->gross_consumption;
            $shoporder_detail->material_type = $items[$x]->meterial_type;
            $shoporder_detail->freight_charges = $items[$x]->freight_charges;
            $shoporder_detail->surcharge = $items[$x]->surcharge;
            $shoporder_detail->total_cost = $items[$x]->total_cost;
            $shoporder_detail->ship_mode = $items[$x]->ship_mode;
            $shoporder_detail->ship_term = $items[$x]->ship_term_id;
            $shoporder_detail->lead_time = $items[$x]->lead_time;
            $shoporder_detail->country_id = $items[$x]->country_id;
            $shoporder_detail->comments = $items[$x]->comments;
            $shoporder_detail->status = '1';
            $shoporder_detail->save();
        }
      }
      return true;
    }
    catch(Exception $e){
      echo $e;
      return false;
    }
  }


  public function update_shop_order_item($bom_detail){
    try {

      $shoporder_detail= ShopOrderDetail::where('bom_detail_id', '=', $bom_detail->bom_detail_id)->first();

      if($shoporder_detail == null || $shoporder_detail->po_con == 'CREATE'){//no shop order for this bom
        return false;
      }

      //$shoporder_detail->shop_order_id = $shop_order->shop_order_id;
      //$shoporder_detail->bom_id = $items[$x]->bom_id;
      //$shoporder_detail->bom_detail_id = $items[$x]->bom_detail_id;
      //$shoporder_detail->costing_item_id = $items[$x]->costing_item_id;
      //$shoporder_detail->costing_id = $items[$x]->costing_id;
      $shoporder_detail->component_id = $bom_detail->product_component_id;
      $shoporder_detail->inventory_part_id = $bom_detail->inventory_part_id;
      $shoporder_detail->supplier = $bom_detail->supplier_id;
      $shoporder_detail->purchase_price = $bom_detail->purchase_price;
      $shoporder_detail->postion_id = $bom_detail->position_id;
      $shoporder_detail->purchase_uom = $bom_detail->purchase_uom_id;
      $shoporder_detail->orign_type_id = $bom_detail->origin_type_id;
      $shoporder_detail->garment_option_id = $bom_detail->garment_options_id;
      $shoporder_detail->unit_price = $bom_detail->bom_unit_price;
      $shoporder_detail->net_consumption = $bom_detail->net_consumption;
      $shoporder_detail->wastage = $bom_detail->wastage;
      $shoporder_detail->gross_consumption = $bom_detail->gross_consumption;
      $shoporder_detail->material_type = $bom_detail->meterial_type;
      $shoporder_detail->freight_charges = $bom_detail->freight_charges;
      $shoporder_detail->surcharge = $bom_detail->surcharge;
      $shoporder_detail->total_cost = $bom_detail->total_cost;
      $shoporder_detail->ship_mode = $bom_detail->ship_mode;
      $shoporder_detail->ship_term = $bom_detail->ship_term_id;
      $shoporder_detail->lead_time = $bom_detail->lead_time;
      $shoporder_detail->country_id = $bom_detail->country_id;
      $shoporder_detail->comments = $bom_detail->comments;
      $shoporder_detail->status = '1';
      $shoporder_detail->save();
      return true;
    }
    catch(Exception $e){
      echo $e;
      return false;
    }
  }


  //chek bom can edit
  public function is_bom_can_edit($bom, $costing, $user_id){
    //in edit mode
    if($bom->edit_status == 1){
      //costing in edit mode
      if($bom->edit_user == null){
        return [
          'status' => 'error',
          'message' => "Cannot edit bom. Costing is in edit mode."
        ];
      }
      else if($bom->edit_user == $user_id){ //bom in edit mode
        //check costing status
        if($costing->status != 'APPROVED'){
          return [
            'status' => 'error',
            'message' => "Cannot edit bom. Costing is not approved"
          ];
        }
        else {
          return [
            'status' => 'success',
            'message' => "You can edit costing"
          ];
        }
      }
      else {
        return [
          'status' => 'error',
          'message' => "You cannot edit bom. Another user already making changes"
        ];
      }
    }
    else {
      if($costing->status == 'APPROVED'){

        if($costing->edit_status == 1){
          return [
            'status' => 'error',
            'message' => "Costing is in edit mode."
          ];
        }
        else {
          if($bom->created_by == $user_id) {//costing created user and can edit
             return [
               'status' => 'success',
               'message' => "You can edit bom"
             ];
           }
           else {
             return [
               'status' => 'error',
               'message' => "Only Bom created user can edit the Bom"
             ];
           }
        }

      }
      else {
        return [
          'status' => 'error',
          'message' => "Cannot edit bom. Costing is not approved"
        ];
      }
    }

    /*if($bom->edit_status == 1 && $bom->created_by == $user_id){//already in edit mode
      //chek costing

      if($costing->edit_status == 1){ //costing is in edit mode, cannot update bom
        return response([
          'status' => 'error',
          'message' => "Cannot edit bom. Costing is in edit mode."
        ]);
      }
      else if($costing->status != 'APPROVED'){
        return response([
          'status' => 'error',
          'message' => "Cannot edit bom. Costing is not approved"
        ]);
      }
      else {
        return response([
          'status' => 'success',
          'message' => "You can edit costing"
        ]);
      }
    }
    else if($bom->edit_status == 1 && $bom->created_by != $user_id){
      return response([
        'status' => 'error',
        'message' => "You cannot edit bom. It's already in edit mode"
      ]);
    }
    else {
      if($costing->status == 'APPROVED'){
        if($bom->created_by == $user_id) {//costing created user and can edit
          $bom->edit_status = 1;
          $bom->edit_user = $user_id;
          $bom->save();
          //add costing to edit mode
          //costing->edit_status = 1;
          //$costing->edit_user = $user_id;
          //$costing->save();

          return response([
            'status' => 'success',
            'message' => "You can edit bom"
          ]);
        }
        else {
          return response([
            'status' => 'error',
            'message' => "Only Bom created user can edit the Bom"
          ]);
        }
      }
      else {
        return response([
          'status' => 'error',
          'message' => "Cannot edit bom. Costing is not approved"
        ]);
      }
    }*/
  }


  public function get_bom_items($bom_id){
    $items = BOMDetails::leftjoin('item_master', 'item_master.master_id', '=', 'bom_details.inventory_part_id')
    ->leftjoin('item_master as item_master_sfg', 'item_master_sfg.master_id', '=', 'bom_details.sfg_id')
    ->leftjoin('item_category', 'item_category.category_id', '=', 'item_master.category_id')
    ->leftjoin('merc_position', 'merc_position.position_id', '=', 'bom_details.position_id')
    ->leftjoin('org_uom', 'org_uom.uom_id', '=', 'bom_details.purchase_uom_id')
    ->leftjoin('org_color', 'org_color.color_id', '=', 'item_master.color_id')
    ->leftjoin('org_color as org_color_sfg', 'org_color_sfg.color_id', '=', 'item_master_sfg.color_id')
    ->leftjoin('org_supplier', 'org_supplier.supplier_id', '=', 'bom_details.supplier_id')
    ->leftjoin('org_origin_type', 'org_origin_type.origin_type_id', '=', 'bom_details.origin_type_id')
    ->leftjoin('org_garment_options', 'org_garment_options.garment_options_id', '=', 'bom_details.garment_options_id')
    ->leftjoin('fin_shipment_term', 'fin_shipment_term.ship_term_id', '=', 'bom_details.ship_term_id')
    ->leftjoin('org_country', 'org_country.country_id', '=', 'bom_details.country_id')
    ->leftjoin('product_component', 'product_component.product_component_id', '=', 'bom_details.product_component_id')
    ->leftjoin('product_silhouette', 'product_silhouette.product_silhouette_id', '=', 'bom_details.product_silhouette_id')
    ->select('bom_details.*','item_master.supplier_reference', 'item_master.master_code','item_master.master_description',
      'item_category.category_name','item_category.category_code', 'merc_position.position', 'org_uom.uom_code', 'org_color.color_code','org_color.color_name',
      'org_supplier.supplier_name', 'org_origin_type.origin_type', 'org_garment_options.garment_options_description', 'fin_shipment_term.ship_term_description',
      'org_country.country_description','product_component.product_component_description','product_silhouette.product_silhouette_description',
      'org_color_sfg.color_code as sfg_color_code', 'org_color_sfg.color_name as sfg_color_name', 'org_uom.is_decimal_allowed')
      ->where('bom_details.bom_id', '=', $bom_id)->orderBy('bom_details.sfg_code', 'ASC')
      ->orderBy('bom_details.sfg_code', 'ASC')
      ->orderBy('bom_details.product_component_id', 'ASC')
      ->orderBy('bom_details.product_silhouette_id', 'ASC')
      ->orderBy('item_category.category_name', 'ASC')->get();
      //echo json_encode($item);die();
      return $items;
  }


}
