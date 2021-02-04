<?php

namespace App\Models\IncentiveCalculationSystem;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\BaseValidator;
use App\Libraries\ICS_UniqueIdGenerator;

class EmployeeDetails extends BaseValidator
{
    protected $table = 'inc_employee';
    protected $primaryKey = 'emp_detail_id';
    //public $incrementing = false;
    //protected $keyType = 'string';
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';

    protected $fillable = ['emp_header_id','emp_no','epf_no','raise_location','emp_name','emp_designation','line_no','shift_start_time','shift_end_time','department'];

    protected function getValidationRules($data) {
      return [
        'emp_header_id' => [
          'required',
          'unique:inc_employee,emp_header_id,'.$data['emp_detail_id'].',emp_detail_id',
        ],
          'emp_no' => 'required',
          'epf_no' => 'required',
          'raise_location' => 'required',
          'emp_name' => 'required',
          'emp_designation' => 'required',
          'shift_start_time' => 'required',
          'shift_end_time' => 'required',
          'department' => 'required',
          'line_no' => 'required',

      ];
    }

    public function __construct() {
        parent::__construct();
    }



}
