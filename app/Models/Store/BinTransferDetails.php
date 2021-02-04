<?php

namespace App\Models\Store;
use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class BinTransferDetails extends BaseValidator
{
    protected $table='store_bin_transfer_detail';
    protected $primaryKey='transfer_detail_id';
    const UPDATED_AT = 'updated_date';
    const CREATED_AT = 'created_date';

    protected $fillable=[
        'transfer_id',
        'stock_detail_id',
        'shop_order_id',
        'shop_order_detail_id',
        'style_id',
        'size',
        'color',
        'uom',
        'standard_price',
        'purchase_price',
        'item_id',
        'store_id',
        'substore_id',
        'bin',
        'transfer_qty',
        'status',
        'comments',
        'rm_plan_id'
    ];
//
    //Validation functions......................................................
    /**
    *unique:table,column,except,idColumn
    *The field under validation must not exist within the given database table
    */
    protected function getValidationRules($data) {
      return [
        'transfer_id' => 'required',
        'stock_detail_id' => 'required',
        //'shop_order_id' => 'required',
        //'shop_order_detail_id' => 'required',
        //'style_id' => 'required',
        'uom' => 'required',
        'standard_price' => 'required',
        'purchase_price' => 'required',
        'item_id' => 'required',
        'store_id' => 'required',
        'substore_id' => 'required',
        'bin' => 'required',
        'transfer_qty' => 'required',
        'status' => 'required',
        'rm_plan_id' => 'required'
      ];
    }

    public function __construct()
    {
    	parent::__construct();
    }


}
