<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;
use App\Libraries\UniqueIdGenerator;

class NonInvGrnHeader extends BaseValidator
{
    protected $table = 'non_inventory_grn_header';
    protected $primaryKey = 'grn_id';
    const UPDATED_AT = 'updated_date';
    const CREATED_AT = 'created_date';

    protected $fillable = ['po_id', 'grn_number', 'invoice_no', 'sup_id', 'invoice_id', 'deliver_id', 'status', 'grn_status', 'transferred', 'payment_made'];

    protected $rules = array(
        // 'po_id' => 'required',
        // 'sup_id' => 'required',
        // 'invoice_no' => 'required',
        // 'invoice_id' => 'required',
        // 'deliver_id' => 'required'
    );

    public function __construct()
    {
        parent::__construct();
    }

    public static function boot()
    {
        static::creating(function ($model) {
            $user = auth()->payload();
            $user_loc = $user['loc_id'];
            $code = UniqueIdGenerator::generateUniqueId('NON_INV_GRN', $user_loc);
            $model->grn_number = $code;
        });

        parent::boot();
    }
}
