<?php

namespace App\Models\Merchandising\Item;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\BaseValidator;

class SubCategory extends BaseValidator
{
    protected $table = 'item_subcategory';
    protected $primaryKey = 'subcategory_id';
    public $incrementing = false;
    protected $keyType = 'string';
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';

    protected $fillable = ['subcategory_id','subcategory_code','category_id','subcategory_name','category_id','is_inspectiion_allowed','is_display'];

  //  protected $casts = [ 'is_inspectiion_allowed' => 'int' ,  'is_display' => 'int'];

    protected $rules = array(
        'subcategory_code' => 'required',
        'subcategory_name'  => 'required'/*,
        'category_id'  => 'required'*/
    );

    public function __construct()
    {
        parent::__construct();
        $this->attributes = array(
            'updated_by' => 2//Session::get("user_id")
        );
    }

    public function scopeGetSubCategoryList(){
        //return DB::table('item_subcategory')->join('item_category','item_category.category_id','=','item_subcategory.category_id')->select('item_subcategory.*','item_category.category_name')->where('item_subcategory.status','=','1')->get();
        return DB::table('item_subcategory')->join('item_category','item_category.category_id','=','item_subcategory.category_id')->where('item_subcategory.status','=','1')->select('item_subcategory.*','item_category.category_name')->get();
    }

    public function scopeGetSubCategoryCount(){
        return DB::table('item_subcategory')->where('status','1')->count();
    }


}
