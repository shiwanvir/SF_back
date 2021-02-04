<?php

namespace App\Models\IncentiveCalculationSystem;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\BaseValidator;
use App\Libraries\ICS_UniqueIdGenerator;

class CadreHeader extends BaseValidator
{
    protected $table = 'inc_cadre_header';
    protected $primaryKey = 'cadre_id';
    //public $incrementing = false;
    //protected $keyType = 'string';
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';

    protected $fillable = ['cadre_name','cadre_type'];

    protected function getValidationRules($data) {
      return [
        'cadre_name' => [
          'required',
          'unique:inc_cadre_header,cadre_name,'.$data['cadre_id'].',cadre_id',
        ],
          'cadre_type' => 'required',
      ];
    }

    public function __construct() {
        parent::__construct();
    }





}
