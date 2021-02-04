<?php

namespace App\Models\IncentiveCalculationSystem;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\BaseValidator;
use App\Libraries\ICS_UniqueIdGenerator;

class EfficiencyDetails extends BaseValidator
{
    protected $table = 'inc_efficiency';
    protected $primaryKey = 'inc_efficiency_id';
    //public $incrementing = false;
    //protected $keyType = 'string';
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';

    protected $fillable = ['eff_header_id','d2d_location','line_no','efficiency_date','efficiency_rate'];

    protected function getValidationRules($data) {
      return [
        'eff_header_id' => [
          'required',
          'unique:inc_efficiency,eff_header_id,'.$data['inc_efficiency_id'].',inc_efficiency_id',
        ],
          'd2d_location' => 'required',
          'line_no' => 'required',
          'efficiency_date' => 'required',
          'efficiency_rate' => 'required',


      ];
    }

    public function __construct() {
        parent::__construct();
    }



}
