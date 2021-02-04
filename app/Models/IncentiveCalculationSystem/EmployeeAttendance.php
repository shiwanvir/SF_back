<?php

namespace App\Models\IncentiveCalculationSystem;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\BaseValidator;
use App\Libraries\ICS_UniqueIdGenerator;

class EmployeeAttendance extends BaseValidator
{
    protected $table = 'inc_employee_attendance';
    protected $primaryKey = 'inc_employee_attendance_id';
    //public $incrementing = false;
    //protected $keyType = 'string';
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';

    protected $fillable = ['emp_no','epf_no','attendance_date','raise_location','in_time','out_time','strDay','shift_start_time',
    'shift_end_time','emp_duration','shift_duration','buffer_duration','incentive_attendance'];

    protected function getValidationRules($data) {
      return [
        'emp_no' => [
          'required',
          'unique:inc_employee_attendance,emp_no,'.$data['inc_employee_attendance_id'].',inc_employee_attendance_id',
        ],
          'epf_no' => 'required',
          'attendance_date' => 'required',
          'raise_location' => 'required',
          'in_time' => 'required',
          'out_time' => 'required',
          'strDay' => 'required',
          'shift_start_time' => 'required',
          'shift_end_time' => 'required',
          'emp_duration' => 'required',
          'shift_duration' => 'required',
          'buffer_duration' => 'required',
          'incentive_attendance' => 'required',

      ];
    }

    public function __construct() {
        parent::__construct();
    }



}
