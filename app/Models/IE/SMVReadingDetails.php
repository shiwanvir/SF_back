<?php

namespace App\Models\IE;

use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class SMVReadingDetails extends BaseValidator
{
 protected $table = 'ie_smv_reading_details';
    protected $primaryKey = 'detail_id';

    const UPDATED_AT = 'updated_date';
    const CREATED_AT = 'created_date';

   protected $fillable = [ 'sub_component_detail_id','operation_component_id','machine_type_id','operation_name','cost_smv','operation_code','gsd_smv'];
        //Validation Functions
        /**
        *unique:table,column,except,idColumn
        *The field under validation must not exist within the given database table
        **/
      protected function getValidationRules($data /*model data with attributes*/) {
	      return [
			 			'smv_reading_id' => [
	             'required',
	             'unique:ie_smv_reading_details,detail_id,'.$data['detail_id'].',detail_id',
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
