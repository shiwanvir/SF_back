<?php

namespace App\Models\Merchandising;

use Illuminate\Database\Eloquent\Model;
use App\Models\Merchandising\PurchaseOrderManual;
use App\BaseValidator;

class POManualDetails extends BaseValidator
{
    protected $table = 'merc_po_order_manual_details';
    protected $primaryKey = 'id';
    const UPDATED_AT = 'updated_date';
    const CREATED_AT = 'created_date';

    protected $fillable = ['id', 'po_header_id', 'po_no', 'line_no', 'category', 'sub_category', 'inventory_part_id', 'part_code', 'description', 'uom', 'uom_id', 'item_currency', 'purchase_uom', 'purchase_uom_code', 'standard_price', 'purchase_price', 'qty', 'req_date', 'total_value', 'po_status', 'user_loc_id', 'po_inv_type', 'status'];

    protected $rules = array(
        'po_no' => 'required',
        'inventory_part_id' => 'required'
    );

    public function __construct()
    {
        parent::__construct();
    }

    public function item()
    {
        return $this->belongsTo('App\Models\Merchandising\Item\Item', 'inventory_part_id')->select(['master_id', 'master_code', 'master_description', 'category_id', 'standard_price', 'gsm', 'width', 'cuttable_uom', 'for_calculation']);
    }

    public function purchase_uom()
    {
        return $this->belongsTo('App\Models\Org\UOM', 'purchase_uom')->select(['uom_id', 'uom_code']);
    }

    public function category()
    {
        return $this->belongsTo('App\Models\Merchandising\Item\Category', 'category')->select(['category_id', 'category_name']);
    }

    public function sub_category()
    {
        return $this->belongsTo('App\Models\Merchandising\Item\SubCategory', 'sub_category')->select(['subcategory_id', 'subcategory_name']);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrderManual::class);
    }

}
