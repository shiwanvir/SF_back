<?php

namespace App\Models\IE;

use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class SMVReadingHeader extends BaseValidator
{
 protected $table = 'ie_smv_reading_header';
    protected $primaryKey = 'smv_reading_id';

    const UPDATED_AT = 'updated_date';
    const CREATED_AT = 'created_date';

    protected $fillable = [ 'product_silhouette_id','customer_id','total_smv'];
        //Validation Functions
        /**
        *unique:table,column,except,idColumn
        *The field under validation must not exist within the given database table
        **/
      protected function getValidationRules($data /*model data with attributes*/) {
	      return [
						'customer_id' => [
	            'required',
	            'unique:ie_smv_reading_header,smv_reading_id,'.$data['smv_reading_id'].',smv_reading_id',
	          ],
		        'product_silhouette_id' => 'required',
		         'total_smv' => 'required',
		         ];
	    }

    public function __construct() {
        parent::__construct();
        $this->attributes = array(
            'updated_by' => 2//Session::get("user_id")
        );
    }
}
