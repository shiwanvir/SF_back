<?php

namespace App\Models\IncentiveCalculationSystem;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\BaseValidator;
use App\Libraries\ICS_UniqueIdGenerator;

class TempLadderUpload extends BaseValidator
{
    protected $table = 'inc_efficiency_ladder_temp';
    protected $primaryKey = 'inc_efficiency_ladder_id';
    //public $incrementing = false;
    //protected $keyType = 'string';
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';

    protected $fillable = ['order_type','qco_date','efficeincy_rate','incentive_payment','ladder_year'];

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
        'order_type' => [
          'required',
          'unique:inc_efficiency_ladder_temp,order_type,'.$data['inc_efficiency_ladder_id'].',inc_efficiency_ladder_id',
        ],
          'qco_date' => 'required',
          'efficeincy_rate' => 'required',
          'incentive_payment' => 'required',
          'ladder_year' => 'required',
      ];
    }

    public function __construct() {
        parent::__construct();
    }


    // public static function boot()
    // {
    //     static::creating(function ($model) {
    //       $location = auth()->payload()['loc_id'];
    //       $code = ICS_UniqueIdGenerator::generateUniqueId('INC_TEMP_LADDER' , null);
    //       $model->serial = $code;
    //
    //     });
    //
    //
    //
    //     parent::boot();
    // }



}
