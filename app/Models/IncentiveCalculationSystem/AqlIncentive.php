<?php

namespace App\Models\IncentiveCalculationSystem;

use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class AqlIncentive extends BaseValidator
{
    protected $table = 'inc_aql';
    protected $primaryKey = 'inc_aql_id';
    public $incrementing = false;
    protected $keyType = 'string';
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';

    protected $fillable = ['aql','paid_rate'];

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
        'aql' => [
          'required',
          'unique:inc_aql,aql,'.$data['inc_aql_id'].',inc_aql_id',
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
