<?php

namespace App\Models\stores;
use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class StoreScarpHeader extends BaseValidator
{
    protected $table='store_inv_scarp_header';
    protected $primaryKey='scarp_id';
    const UPDATED_AT='updated_date';
    const CREATED_AT='created_date';

    protected $fillable=['scarp_no','location','store','from_sub_store','to_sub_store','status'];
    protected $rules=array();

    public function __construct() {
        parent::__construct();
    }

    protected function getValidationRules($data) {
      return [
          'scarp_no' => 'required',
          'location' => 'required',
          'store' => 'required',
          'from_sub_store' => 'required',
          'to_sub_store' => 'required',
          'status' => 'required'
      ];
    }

}
