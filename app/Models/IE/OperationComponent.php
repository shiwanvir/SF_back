<?php

namespace App\Models\IE;

use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class OperationComponent extends BaseValidator
{
   protected $table = 'ie_operation_component';
    protected $primaryKey = 'operation_component_id';
    public $incrementing = false;
    protected $keyType = 'string';

    const UPDATED_AT = 'updated_date';
    const CREATED_AT = 'created_date';

    protected $fillable = [ 'operation_component_code','operation_component_name'];
    // protected $rules = array(
    //     'garment_operation_name' => 'required',

    // );
    public function __construct() {
        parent::__construct();

    }

    //Validation Functions
    /**
    *unique:table,column,except,idColumn
    *The field under validation must not exist within the given database table
    **/
    protected function getValidationRules($data) {

      return [
          'operation_component_code' => [
            'required',
            'unique:ie_operation_component,operation_component_code,'.$data['operation_component_id'].',operation_component_id',
          ],
      ];
    }


}
