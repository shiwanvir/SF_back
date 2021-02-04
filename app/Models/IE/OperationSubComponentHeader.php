<?php

namespace App\Models\IE;

use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class OperationSubComponentHeader extends BaseValidator
{
 protected $table = 'ie_operation_sub_component_header';
    protected $primaryKey = 'operation_sub_component_id';
    public $incrementing = false;
    protected $keyType = 'string';

    const UPDATED_AT = 'updated_date';
    const CREATED_AT = 'created_date';

    protected $fillable = [ 'operation_component_id','operation_sub_component_code','operation_sub_component_name'];
    // protected $rules = array(
    //     'garment_operation_name' => 'required',

    // );


    //Validation Functions
    /**
    *unique:table,column,except,idColumn
    *The field under validation must not exist within the given database table
    **/
    protected function getValidationRules($data) {

      return [
          'operation_sub_component_code' => [
            'required',
            'unique:ie_operation_sub_component_header,operation_sub_component_code,'.$data['operation_sub_component_id'].',operation_sub_component_id',
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
