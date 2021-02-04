<?php

namespace App\Models\IncentiveCalculationSystem;

use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class Equation extends BaseValidator
{
    protected $table = 'inc_equation';
    protected $primaryKey = 'inc_equation_id';
    public $incrementing = false;
    protected $keyType = 'string';
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';

    protected $fillable = ['equation','present_factor'];

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
        'equation' => [
          'required',
          'unique:inc_equation,equation,'.$data['inc_equation_id'].',inc_equation_id',
        ],
          'present_factor' => 'required',
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
