<?php
namespace APP\Models\stores;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\BaseValidator;
use App\Libraries\UniqueIdGenerator;
class RMPlan extends BaseValidator{

  protected $table='store_rm_plan';
  protected $primaryKey='rm_plan_id';
  const UPDATED_AT='updated_date';
  const CREATED_AT='created_date';

  protected $fillable=['invoice_no','grn_detail_id','roll_or_box_no','lot_no','batch_no','actual_qty','received_qty','bin','width','shade','rm_comment','barcode'
,'status','category_id','inspection_status'];

  public function __construct() {
      parent::__construct();
  }

  protected $rules=array(
    'invoiceNo'=>'required',
  );

}
