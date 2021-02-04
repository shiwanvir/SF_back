<?php

namespace App\Models\IncentiveCalculationSystem;

use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class Section extends BaseValidator
{
    protected $table = 'inc_section';
    protected $primaryKey = 'inc_section_id';
    //public $incrementing = false;
    //protected $keyType = 'string';
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';

    protected $fillable = ['line_no','raise_intCompanyID'];

    protected function getValidationRules($data) {
      return [
        'line_no' => [
          'required',
          'unique:inc_section,line_no,'.$data['inc_section_id'].',inc_section_id',
        ]
      ];
    }

    public function __construct()
    {
        parent::__construct();
        $this->attributes = array(
            'updated_by' => 2//Session::get("user_id")
          );
    }


}
