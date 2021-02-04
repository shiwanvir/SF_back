<?php

namespace App\Models\Merchandising;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\BaseValidator;
use App\Libraries\UniqueIdGenerator;

class PurchaseOrderManual extends BaseValidator
{
    protected $table = 'merc_po_order_manual_header';
    protected $primaryKey = 'po_id';
    const UPDATED_AT = 'updated_date';
    const CREATED_AT = 'created_date';

    protected $fillable = ['cost_center_id', 'dept_id', 'po_type', 'po_number', 'po_sup_id', 'po_def_cur', 'delivery_date', 'deliver_to', 'invoice_to', 'pay_mode', 'pay_term', 'ship_term', 'po_status', 'po_inv_type', 'status', 'remark_header'];

    protected $dates = ['delivery_date'];
    protected $rules = array(
        'po_type' => 'required',
        'po_sup_id' => 'required',
        'deliver_to' => 'required',
        'po_def_cur' => 'required',
        'pay_mode' => 'required',
        'pay_term' => 'required',
        'ship_term' => 'required'
    );

    public function __construct()
    {
        parent::__construct();
    }

    public function setDiliveryDateAttribute($value)
    {
        $this->attributes['delivery_date'] = date('Y-m-d', strtotime($value));
    }

    public function currency()
    {
        return $this->belongsTo('App\Models\Finance\Currency', 'po_def_cur')->select(['currency_id','currency_code']);
    }

    public function location()
    {
        return $this->belongsTo('App\Models\Org\Location\Location', 'deliver_to')->select(['loc_id','loc_name']);
    }

    public function company()
    {
        return $this->belongsTo('App\Models\Org\Location\Company', 'invoice_to')->select(['company_id','company_name']);
    }

    public function supplier()
    {
        return $this->belongsTo('App\Models\Org\Supplier', 'po_sup_id')->select(['supplier_id','supplier_name']);
    }

    public static function boot()
    {
        static::creating(function ($model) {

            //      if ($model->po_type == 'BULK'){$rep = 'BUL';}
            //  elseif ($model->po_type == 'GENERAL'){$rep = 'GEN';}
            //  elseif ($model->po_type == 'GREAIGE'){$rep = 'GRE';}
            //  elseif ($model->po_type == 'RE-ORDER'){$rep = 'REO';}
            //  elseif ($model->po_type == 'SAMPLE'){$rep = 'SAM';}
            //  elseif ($model->po_type == 'SERVICE'){$rep = 'SER';}
            $user = auth()->payload();
            $user_loc = $user['loc_id'];
            $code = UniqueIdGenerator::generateUniqueId('PO_GENERAL', $user_loc);
            //  $model->po_number = $rep.$code;
            $model->po_number = 'M-' . $code;
            $model->user_loc_id = $user_loc;
        });

        /*static::updating(function ($model) {
            $user = auth()->pay_loa();
            $model->updated_by = $user->user_id;
        });*/

        parent::boot();
    }
}
