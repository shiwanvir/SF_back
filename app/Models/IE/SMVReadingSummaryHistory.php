<?php

namespace App\Models\IE;

use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class SMVReadingSummaryHistory extends BaseValidator
{
 protected $table = 'ie_smv_reading_summary_history';
    protected $primaryKey = 'history_summary_id';

    const UPDATED_AT = 'updated_date';
    const CREATED_AT = 'created_date';

    protected $fillable = [ 'summary_id','garment_operation_id','cost_smv'];
        //Validation Functions
        /**
        *unique:table,column,except,idColumn
        *The field under validation must not exist within the given database table
        **/
      // protected function getValidationRules($data /*model data with attributes*/) {
	    //   return [
			// 			'smv_reading_id' => [
	    //         'required',
	    //         'unique:ie_smv_reading_header,smv_reading_id,'.$data['smv_reading_id'].',smv_reading_id',
	    //       ],
		  //       'sillhouette_id' => 'required',
		  //       'customer_id' => 'required',
		  //       'total_smv' => 'required',
		  //        ];
	    // }

    public function __construct() {
        parent::__construct();
        $this->attributes = array(
            'updated_by' => 2//Session::get("user_id")
        );
    }
}
