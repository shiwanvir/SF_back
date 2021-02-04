<?php

namespace App\Models\IncentiveCalculationSystem;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\BaseValidator;
use App\Libraries\ICS_UniqueIdGenerator;

class IndirectLadderUploadHeader extends BaseValidator
{
    protected $table = 'inc_efficiency_indirect_ladder_header';
    protected $primaryKey = 'indirect_ladder_id';
    //public $incrementing = false;
    //protected $keyType = 'string';
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';

    protected $fillable = ['indirect_ladder_year'];

    protected function getValidationRules($data) {
      return [
        'indirect_ladder_year' => [
          'required',
          'unique:inc_efficiency_indirect_ladder_header,indirect_ladder_year,'.$data['indirect_ladder_id'].',indirect_ladder_id',
        ],
      ];
    }

    public function __construct() {
        parent::__construct();
    }



}
