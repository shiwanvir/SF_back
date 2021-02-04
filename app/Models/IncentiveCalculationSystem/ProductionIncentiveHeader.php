<?php

namespace App\Models\IncentiveCalculationSystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\BaseValidator;
use App\Libraries\ICS_UniqueIdGenerator;

class ProductionIncentiveHeader extends BaseValidator
{
    protected $table = 'inc_production_incentive_header';
    protected $primaryKey = 'production_incentive_header_id';
    //public $incrementing = false;
    //protected $keyType = 'string';
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';

    protected $fillable = ['incentive_date','incentive_status'];

    protected function getValidationRules($data) {
      return [
        'incentive_date' => [
          'required',
          'unique:inc_production_incentive_header,incentive_date,'.$data['production_incentive_header_id'].',production_incentive_header_id',
        ],
          'incentive_status' => 'required',
      ];
    }

    public function __construct() {
        parent::__construct();
    }

}
