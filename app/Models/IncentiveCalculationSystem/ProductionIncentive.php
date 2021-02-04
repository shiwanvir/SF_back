<?php

namespace App\Models\IncentiveCalculationSystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\BaseValidator;
use App\Libraries\ICS_UniqueIdGenerator;

class ProductionIncentive extends BaseValidator
{
    protected $table = 'inc_production_incentive';
    protected $primaryKey = 'inc_production_incentive_id';
    //public $incrementing = false;
    //protected $keyType = 'string';
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';

    protected $fillable = ['production_incentive_header_id','order_type','qco_date','line_no','incentive_date','efficiency_rate','aql','cni'
  ,'incentive_payment'];

    protected function getValidationRules($data) {
      return [
        'production_incentive_header_id' => [
          'required',
          'unique:inc_production_incentive,production_incentive_header_id,'.$data['inc_production_incentive_id'].',inc_production_incentive_id',
        ],
          'order_type' => 'required',
          'qco_date' => 'required',
          'line_no' => 'required',
          'incentive_date' => 'required',
          'efficiency_rate' => 'required',
          'aql' => 'required',
          'cni' => 'required',
          'incentive_payment' => 'required'
      ];
    }

    public function __construct() {
        parent::__construct();
    }

}
