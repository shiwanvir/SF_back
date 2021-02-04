<?php


namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;

use App\BaseValidator;



class StockTransactionDetail extends BaseValidator
{
    protected $table='store_stock_transaction_detail';
    protected $primaryKey='transaction_detail_id';
    //public $timestamps = false;
    //const UPDATED_AT='updated_date';
    const CREATED_AT='created_date';
      const UPDATED_AT='updated_date';

    protected $fillable=['transaction_id','stock_id','location', 'store_id','substore_id','stock_detail_id' ,'bin','item_id', 'qty', 'financial_year', 'finacial_month', 'rm_plan_id', 'uom','qty', 'uom'];

    protected $rules=array(
        ////'color_code'=>'required',
        //'color_name'=>'required'
    );

    public function __construct() {
        parent::__construct();
    }

}
