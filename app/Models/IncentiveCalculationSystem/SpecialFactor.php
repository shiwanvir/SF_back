<?php

namespace App\Models\IncentiveCalculationSystem;

use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class SpecialFactor extends BaseValidator
{
    protected $table = 'inc_special_factor';
    protected $primaryKey = 'inc_special_factor_id';
    public $incrementing = false;
    protected $keyType = 'string';
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';

    protected $fillable = ['special_factor','paid_rate'];

    // protected $rules = array(
    //     'prod_cat_description' => 'required',
    // );

    //Validation Functions
    /**
    *unique:table,column,except,idColumn
    *The field under validation must not exist within the given database table
    **/
    protected function getValidationRules($data) {
      return [
        'special_factor' => [
          'required',
          'unique:inc_special_factor,special_factor,'.$data['inc_special_factor_id'].',inc_special_factor_id',
        ],
          'paid_rate' => 'required',
      ];
    }

    public function __construct()
    {
        parent::__construct();
        $this->attributes = array(
            'updated_by' => 2//Session::get("user_id")
          );
    }


}
