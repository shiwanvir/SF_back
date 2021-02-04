<?php

namespace App\Models\IncentiveCalculationSystem;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\BaseValidator;
use App\Libraries\ICS_UniqueIdGenerator;

class LadderUploadHeader extends BaseValidator
{
    protected $table = 'inc_efficiency_ladder_header';
    protected $primaryKey = 'ladder_id';
    //public $incrementing = false;
    //protected $keyType = 'string';
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';

    protected $fillable = ['ladder_year'];

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
        'ladder_year' => [
          'required',
          'unique:inc_efficiency_ladder_header,ladder_year,'.$data['ladder_id'].',ladder_id',
        ],
      ];
    }

    public function __construct() {
        parent::__construct();
    }


    // public static function boot()
    // {
    //     static::creating(function ($model) {
    //       $location = auth()->payload()['loc_id'];
    //       $code = ICS_UniqueIdGenerator::generateUniqueId('INC_LADDER' , null);
    //       $model->serial = $code;
    //
    //     });
    //
    //
    //
    //     parent::boot();
    // }



}
