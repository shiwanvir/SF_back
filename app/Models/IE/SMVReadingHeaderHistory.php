<?php

namespace App\Models\IE;

use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class SMVReadingHeaderHistory extends BaseValidator
{
 protected $table = 'ie_smv_reading_header_history';
    protected $primaryKey = 'id';

    const UPDATED_AT = 'updated_date';
    const CREATED_AT = 'created_date';

    protected $fillable = [ 'smv_reading_id','product_silhouette_id','customer_id','total_smv','operation_component_id'];
        //Validation Functions
        /**
        *unique:table,column,except,idColumn
        *The field under validation must not exist within the given database table
        **/


    public function __construct() {
        parent::__construct();
        $this->attributes = array(
            'updated_by' => 2//Session::get("user_id")
        );
    }
}
