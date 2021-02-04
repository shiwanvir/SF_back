<?php

namespace App\Models\Store;
use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class ReturnToSupplierDetails extends BaseValidator
{
    protected $table='store_return_to_supplier_detail';
    protected $primaryKey='return_detail_id';

    const UPDATED_AT = 'updated_date';
    const CREATED_AT = 'created_date';

    protected $fillable=[
        'return_id',
        'item_id',
        'inv_uom',
        'request_uom',
        'grn_qty',
        'return_qty',
        'return_inv_qty',
        'status',
        'location_id',
        'store_id',
        'sub_store_id',
        'bin',
        'roll_box',
        'batch_no',
        /*'shade' => 'required',*/
        'rm_plan_id',
        'stock_detail_id',
        'comments',
        'purchase_price',
        'grn_detail_id',
		'grn_id',
		'style_id'
        ];


    //Validation functions......................................................
    /**
    *unique:table,column,except,idColumn
    *The field under validation must not exist within the given database table
    */
    protected function getValidationRules($data) {
      return [
        'return_id' => 'required',
        'item_id' => 'required',
        'inv_uom' => 'required',
        'grn_qty' => 'required',
        'return_qty' => 'required',
        'status' => 'required',
        'location_id' => 'required',
        'store_id' => 'required',
        'sub_store_id' => 'required',
        'bin' => 'required',
        'roll_box' => 'required',
        'batch_no' => 'required',
        /*'shade' => 'required',*/
        'rm_plan_id' => 'required',
        'grn_id' => 'required',
        'grn_detail_id' => 'required',
        'style_id' => 'required',
        'return_inv_qty' => 'required'
      ];
    }


    public function __construct()
    {
    	parent::__construct();
    }


}
