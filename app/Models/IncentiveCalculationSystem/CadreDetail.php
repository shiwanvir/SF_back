<?php

namespace App\Models\IncentiveCalculationSystem;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\BaseValidator;
use App\Libraries\ICS_UniqueIdGenerator;

class CadreDetail extends BaseValidator
{
    protected $table = 'inc_cadre_detail';
    protected $primaryKey = 'cadre_detail_id';
    //public $incrementing = false;
    //protected $keyType = 'string';
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';

    protected $fillable = ['cadre_id','emp_no','line_no'];

    protected function getValidationRules($data) {
      return [
        'cadre_id' => [
          'required',
          'unique:inc_cadre_detail,cadre_id,'.$data['cadre_detail_id'].',cadre_detail_id',
        ],
          'emp_no' => 'required',
          'line_no' => 'required',
      ];
    }

    public function __construct() {
        parent::__construct();
    }





}
