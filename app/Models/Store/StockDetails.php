<?php


namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;

use App\BaseValidator;



class StockDetails extends BaseValidator
{
    protected $table='store_stock_details';
    protected $primaryKey='stock_detail_id';
    //public $timestamps = false;
    const UPDATED_AT='updated_date';
    const CREATED_AT='created_date';

    protected $fillable=['stock_id', 'bin','location', 'store_id', 'substore_id', 'item_id', 'financial_year', 'finacial_month', 'rm_plan_id','barcode'
    ,'parent_rm_plan_id','in_qty','out_qty','avaliable_qty','standard_price','purchase_price','financial_year','finacial_month','avaliable_qty','in_qty','out_qty'];

    protected $rules=array(
        ////'color_code'=>'required',
        //'color_name'=>'required'
    );

    public function __construct() {
        parent::__construct();
    }





}
