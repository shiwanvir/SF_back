<?php

namespace App\Models\Store;
use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class BinTransferHeader extends BaseValidator
{
    protected $table='store_bin_transfer_header';
    protected $primaryKey='transfer_id';
    const UPDATED_AT = 'updated_date';
    const CREATED_AT = 'created_date';

    protected $fillable=[
        'location',
        'status',
        'bin_transfer_no'
    ];

    //Validation functions......................................................
    /**
    *unique:table,column,except,idColumn
    *The field under validation must not exist within the given database table
    */
    protected function getValidationRules($data) {
      return [
        'location' => 'required',
        'status' => 'required'
        //'bin_transfer_no'=>'required'
      ];
    }

    public function __construct()
    {
    	parent::__construct();
    }


}
