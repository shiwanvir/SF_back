<?php

namespace App\Models\IE;
use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class SilhouetteOperationMappingDetails extends BaseValidator
{
 protected $table = 'ie_silhouette_operation_mapping_details';
    protected $primaryKey = 'mapping_id';
  //public $incrementing = false;
    //protected $keyType = 'int';

    const UPDATED_AT = 'updated_date';
    const CREATED_AT = 'created_date';

    protected $fillable = [ 'product_silhouette_id','operation_component_id'];
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
          'product_silhouette_id' => [
            'required',
            'unique:product_silhouette_id,operation_component_id,'.$data['mapping_id'].',mapping_id',
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
