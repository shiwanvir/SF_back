<?php

namespace App\Models\IncentiveCalculationSystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\BaseValidator;
use App\Libraries\ICS_UniqueIdGenerator;

class ProductionIncentiveLine extends BaseValidator
{
    protected $table = 'inc_production_incentive_line';
    protected $primaryKey = 'inc_production_incentive_line_id';
    //public $incrementing = false;
    //protected $keyType = 'string';
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';

    protected $fillable = ['inc_production_incentive_id','emp_no','line_no','incentive_date','from_line_no','to_line_no','work_duration','shift_duration'
  ,'incentive_payment'];

    protected function getValidationRules($data) {
      return [
        'inc_production_incentive_id' => [
          'required',
          'unique:inc_production_incentive,inc_production_incentive_id,'.$data['inc_production_incentive_line_id'].',inc_production_incentive_line_id',
        ],
          'emp_no' => 'required',
          'line_no' => 'required',
          'incentive_date' => 'required',
          'from_line_no' => 'required',
          'to_line_no' => 'required',
          'work_duration' => 'required',
          'shift_duration' => 'required',
          'incentive_payment' => 'required'
      ];
    }

    public function __construct() {
        parent::__construct();
    }

}
