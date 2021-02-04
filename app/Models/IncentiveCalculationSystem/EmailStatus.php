<?php

namespace App\Models\IncentiveCalculationSystem;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\BaseValidator;
use App\Libraries\ICS_UniqueIdGenerator;

class EmailStatus extends BaseValidator
{
    protected $table = 'inc_email';
    protected $primaryKey = 'email_id';
    //public $incrementing = false;
    //protected $keyType = 'string';
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';

    protected $fillable = ['inc_email_month'];

    protected function getValidationRules($data) {
      return [
        'inc_email_month' => [
          'required',
          'unique:inc_email,inc_email_month,'.$data['email_id'].',email_id',
        ]

      ];
    }

    public function __construct() {
        parent::__construct();
    }



}
