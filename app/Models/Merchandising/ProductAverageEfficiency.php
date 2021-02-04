<?php

namespace App\Models\Merchandising;

use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class ProductAverageEfficiency extends BaseValidator
{
    protected $table = 'product_average_efficiency';
    protected $primaryKey = 'id';
    protected $primaryKey2 = 'efficiency';
    const UPDATED_AT = 'updated_date';
    const CREATED_AT = 'created_date';

    protected $fillable = ['id', 'prod_cat_id', 'product_silhouette_id', 'version', 'qty_from', 'qty_to', 'efficiency', 'status'];

    protected $rules = array(
        'prod_cat_id' => 'required',
        'product_silhouette_id' => 'required',
        'qty_from' => 'required',
        'qty_to' => 'required',
        'efficiency' => 'required'
    );

    public function pro_category()
    {
        return $this->belongsTo('App\Models\Merchandising\ProductCategory', 'prod_cat_id')->select(['prod_cat_id', 'prod_cat_description']);
    }

    public function silhouette()
    {
        return $this->belongsTo('App\Models\Org\Silhouette', 'product_silhouette_id')->select(['product_silhouette_id', 'product_silhouette_description']);
    }
}
