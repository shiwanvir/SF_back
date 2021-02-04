<?php

namespace App\Models\IncentiveCalculationSystem;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\BaseValidator;
use App\Libraries\ICS_UniqueIdGenerator;

class IndirectLadderUpload extends BaseValidator
{
    protected $table = 'inc_efficiency_indirect_ladder';
    protected $primaryKey = 'inc_efficiency_indirect_ladder_id';
    //public $incrementing = false;
    //protected $keyType = 'string';
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';

    protected $fillable = ['indirect_location','efficeincy_rate','incentive_payment','ladder_year'];

    protected function getValidationRules($data) {
      return [
        'indirect_location' => [
          'required',
          'unique:inc_efficiency_indirect_ladder,indirect_location,'.$data['inc_efficiency_indirect_ladder_id'].',inc_efficiency_indirect_ladder_id',
        ],
          'efficeincy_rate' => 'required',
          'incentive_payment' => 'required',
          'ladder_year' => 'required',
      ];
    }

    public function __construct() {
        parent::__construct();
    }




}
