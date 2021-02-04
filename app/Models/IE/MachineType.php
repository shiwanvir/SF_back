<?php

namespace App\Models\IE;
use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class MachineType extends BaseValidator
{
 protected $table = 'ie_machine_type';
    protected $primaryKey = 'machine_type_id';
    public $incrementing = false;
    protected $keyType = 'string';

    const UPDATED_AT = 'updated_date';
    const CREATED_AT = 'created_date';

    protected $fillable = [ 'machine_type_code','machine_type_name'];
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
          'machine_type_code' => [
            'required',
            'unique:ie_machine_type,machine_type_code,'.$data['machine_type_id'].',machine_type_id',
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
