<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class NonInvGrnDetails extends BaseValidator
{
    protected $table = 'non_inventory_grn_details';
    protected $primaryKey = 'grn_detail_id';
    const UPDATED_AT = 'updated_date';
    const CREATED_AT = 'created_date';

    protected $fillable = ['grn_id', 'grn_line_no', 'description_id', 'description', 'uom', 'uom_id', 'po_qty', 'balance_qty', 'received_qty', 'po_details_id', 'status', 'grn_status', 'transferred', 'payment_made'];

    protected $rules = array(
    );

    public function __construct()
    {
        parent::__construct();
    }
}
