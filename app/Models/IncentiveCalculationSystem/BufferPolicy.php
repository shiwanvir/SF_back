<?php

namespace App\Models\IncentiveCalculationSystem;

use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class BufferPolicy extends BaseValidator
{
    protected $table = 'inc_buffer_policy';
    protected $primaryKey = 'inc_buffer_id';
    public $incrementing = false;
    protected $keyType = 'string';
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';

    protected $fillable = ['hours'];

    // protected $rules = array(
    //     'prod_cat_description' => 'required',
    // );

    //Validation Functions
    /**
    *unique:table,column,except,idColumn
    *The field under validation must not exist within the given database table
    **/
    protected function getValidationRules($data) {
      return [
        'hours' => [
          'required',
          'unique:inc_buffer_policy,hours,'.$data['inc_buffer_id'].',inc_buffer_id',
        ],
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
