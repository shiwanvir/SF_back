<?php

namespace App\Models\Merchandising;

use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class ProductAverageEfficiencyHistory extends BaseValidator
{
    protected $table = 'product_average_efficiency_history';
    protected $primaryKey = 'history_id';
    protected $primaryKey2 = 'efficiency';
    const UPDATED_AT = 'updated_date';
    const CREATED_AT = 'created_date';

    protected $fillable = ['history_id', 'prod_cat_id', 'product_silhouette_id', 'version', 'qty_from', 'qty_to', 'efficiency', 'status'];

    protected $rules = array(
        'prod_cat_id' => 'required',
        'product_silhouette_id' => 'required',
        'qty_from' => 'required',
        'qty_to' => 'required',
        'efficiency' => 'required'
    );
}
