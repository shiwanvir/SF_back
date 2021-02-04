<?php

namespace App\Models\IE;

use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class SMVReadingDetailsHistory extends BaseValidator
{
 protected $table = 'ie_smv_reading_details_history';
    protected $primaryKey = 'history_detail_id';

    const UPDATED_AT = 'updated_date';
    const CREATED_AT = 'created_date';

   protected $fillable = [ 'detail_id','sub_component_detail_id','operation_component_id','machine_type_id','operation_name','cost_smv','operation_code','gsd_smv'];


    public function __construct() {
        parent::__construct();
        $this->attributes = array(
            'updated_by' => 2//Session::get("user_id")
        );
    }
}
