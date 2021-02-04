<?php

namespace App\Models\IncentiveCalculationSystem;

use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class IncentivePolicy extends BaseValidator
{
    protected $table = 'inc_incentive_policy';
    protected $primaryKey = 'inc_inc_policy_id';
    public $incrementing = false;
    protected $keyType = 'string';
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';

    protected $fillable = ['inc_policy_paid_rate'];

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
        'inc_policy_paid_rate' => [
          'required',
          'unique:inc_incentive_policy,inc_policy_paid_rate,'.$data['inc_inc_policy_id'].',inc_inc_policy_id',
        ],
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
