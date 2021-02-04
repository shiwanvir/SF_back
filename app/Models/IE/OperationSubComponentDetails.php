<?php

namespace App\Models\IE;

use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class OperationSubComponentDetails extends BaseValidator
{
 protected $table = 'ie_operation_sub_component_details';
    protected $primaryKey = 'detail_id';
    const UPDATED_AT = 'updated_date';
    const CREATED_AT = 'created_date';

    protected $fillable = [ 'operation_code','operation_name','operation_sub_component_id','cost_smv','gsd_smv','options'];
    // protected $rules = array(
    //     'garment_operation_name' => 'required',

    // );


    //Validation Functions
    /**
    *unique:table,column,except,idColumn
    *The field under validation must not exist within the given database table
    **/
    protected function getValidationRules($data) {
      //dd($data);
      return [
          'operation_code' => [
            'required',
            'unique:ie_operation_sub_component_details,operation_code,'.$data['detail_id'].',detail_id',
          ],
      ];
    }

    public function __construct() {
        parent::__construct();
        $this->attributes = array(
            'updated_by' => 2//Session::get("user_id")
        );
    }
}
