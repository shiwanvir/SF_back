<?php

namespace App\Models\stores;
use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class StoreScarpDetails extends BaseValidator
{
    protected $table='store_inv_scarp_details';
    protected $primaryKey='scarp_detail_id';
    const UPDATED_AT='updated_date';
    const CREATED_AT='created_date';

	protected $fillable=['scarp_id','style','shop_order_id','shop_order_detail_id','item_id','inventory_uom',
	'bin_no','roll_box_no','batch','shade','inv_qty','scarp_qty','standard_price','purchase_price','rm_plan_id',
	'comments','status','grn_detail_id'];

    protected $rules=array();

    public function __construct() {
        parent::__construct();
    }

    protected function getValidationRules($data) {
      //dd($data);
      return [
          'scarp_id' => 'required',
          'style' => 'required',
          'shop_order_id' => 'required',
          'shop_order_detail_id' => 'required',
          'item_id' => 'required',
          'inventory_uom' => 'required',
          'bin_no' => 'required',
          'roll_box_no' => 'required',
          'batch' => 'required',
          //'shade' => 'required',
          'inv_qty' => 'required',
          'inventory_uom' => 'required',
          'scarp_qty' => 'required',
          'standard_price' => 'required',
          'purchase_price' => 'required',
          'rm_plan_id' => 'required',
          'status' => 'required',
          'grn_detail_id' => 'required'
      ];
    }


}
