<?php
namespace APP\Models\stores;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\BaseValidator;
use App\Libraries\UniqueIdGenerator;

class RMPlanHeader extends BaseValidator{

  protected $table='store_rm_plan_header';
  protected $primaryKey='rm_plan_header_id';
  const UPDATED_AT='updated_date';
  const CREATED_AT='created_date';


  public function __construct() {
      parent::__construct();
  }



}
