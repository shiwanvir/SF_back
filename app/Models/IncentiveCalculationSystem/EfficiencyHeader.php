<?php

namespace App\Models\IncentiveCalculationSystem;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\BaseValidator;
use App\Libraries\ICS_UniqueIdGenerator;

class EfficiencyHeader extends BaseValidator
{
    protected $table = 'inc_efficiency_header';
    protected $primaryKey = 'eff_header_id';
    //public $incrementing = false;
    //protected $keyType = 'string';
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';

    protected $fillable = ['production_incentive_header_id','incentive_date','incentive_status'];

    protected function getValidationRules($data) {
      return [
        'production_incentive_header_id' => [
          'required',
          'unique:inc_efficiency_header,production_incentive_header_id,'.$data['eff_header_id'].',eff_header_id',
        ],
          'incentive_date' => 'required',
          'incentive_status' => 'required',

      ];
    }

    public function __construct() {
        parent::__construct();
    }



}
