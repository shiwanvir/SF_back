<?php

namespace App\Models\IncentiveCalculationSystem;

use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class Designation extends BaseValidator
{
    protected $table = 'inc_designation_equation';
    protected $primaryKey = 'inc_designation_equation_id';
    public $incrementing = false;
    protected $keyType = 'string';
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';

    protected $fillable = ['emp_designation','inc_equation_id'];

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
        'emp_designation' => [
          'required',
          'unique:inc_designation_equation,emp_designation,'.$data['inc_designation_equation_id'].',inc_designation_equation_id',
        ],
          'inc_equation_id' => 'required',
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
