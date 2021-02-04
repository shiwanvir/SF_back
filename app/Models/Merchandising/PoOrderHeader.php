<?php

namespace App\Models\Merchandising;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\BaseValidator;
use App\Libraries\UniqueIdGenerator;

class PoOrderHeader extends BaseValidator
{
    protected $table='merc_po_order_header';
    protected $primaryKey='po_id';
    const UPDATED_AT='updated_date';
    const CREATED_AT='created_date';

    protected $fillable=['po_type','po_sup_code','po_deli_loc','po_def_cur','po_status','order_type','delivery_date','invoice_to','pay_mode','pay_term','ship_mode','po_date','prl_id','ship_term','special_ins'];

    protected $dates = ['delivery_date','po_date'];
    protected $rules=array(
        //'po_type'=>'required',
        'po_sup_code' => 'required',
        //'po_deli_loc' => 'required',
        'po_def_cur' => 'required',
        'pay_mode' => 'required',
        'pay_term' => 'required',
        //'ship_mode' => 'required',
        'po_date' => 'required',
        'prl_id' => 'required',
        'ship_term' => 'required'
    );


    public function __construct() {
        parent::__construct();
    }

    public function setDiliveryDateAttribute($value)
		{
    	$this->attributes['delivery_date'] = date('Y-m-d', strtotime($value));
    }

    /*public function getDiliveryDateAttribute($value){
    $this->attributes['delivery_date'] = date('d F,Y', strtotime($value));
    return $this->attributes['delivery_date'];
    }*/

    public function setpoDateAttribute($value)
		{
    	$this->attributes['po_date'] = date('Y-m-d', strtotime($value));
    }

    /*public function getpoDateAttribute($value){
    $this->attributes['po_date'] = date('d F,Y', strtotime($value));
    return $this->attributes['delivery_date'];
    }*/

    public function currency()
    {
        return $this->belongsTo('App\Models\Finance\Currency' , 'po_def_cur');
    }

    public function location()
        {
            return $this->belongsTo('App\Models\Org\Location\Location' , 'po_deli_loc');
        }


    public function supplier()
        {
            return $this->belongsTo('App\Models\Org\Supplier' , 'po_sup_code');
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
          $code = UniqueIdGenerator::generateUniqueId('PO_MANUAL' , $user_loc);
        //  $model->po_number = $rep.$code;
          $model->po_number = $code;
          $model->loc_id = $user_loc;


        });

        /*static::updating(function ($model) {
            $user = auth()->pay_loa();
            $model->updated_by = $user->user_id;
        });*/

        parent::boot();
    }

    public function poDetails(){
        return $this->belongsTo('App\Models\Merchandising\PoOrderDetails' , 'po_id');
    }

    public function getPOSupplierAndInvoice($id,$grn_type_code){
      if($grn_type_code=="AUTO"){
        return self::select('s.supplier_name', 's.supplier_id','l.loc_id','l.loc_name')
            ->join('org_supplier as s', 's.supplier_id', '=', 'merc_po_order_header.po_sup_code')
            ->join('org_location as l','l.loc_id','=','merc_po_order_header.po_deli_loc')
            ->where('merc_po_order_header.po_id','=',$id)
            ->get();
      }
      if($grn_type_code=="MANUAL"){
        //dd("dad");
        $po_details=DB::SELECT("SELECT org_supplier.supplier_name,org_supplier.supplier_id,org_location.loc_id,org_location.loc_name
          from merc_po_order_manual_header
          INNER JOIN merc_po_order_manual_details on merc_po_order_manual_header.po_id=merc_po_order_manual_details.po_header_id
          INNER JOIN org_supplier on merc_po_order_manual_header.po_sup_id=org_supplier.supplier_id
          INNER JOIN org_location on merc_po_order_manual_header.po_deli_loc=org_location.loc_id
          where merc_po_order_manual_header.po_id=$id
          ");
          return $po_details;
      }
    }

public static function getPoLineData($request){
  $poData=null;
  if($request->grn_type_code=="AUTO"){
$po_status="CONFIRMED";
$poData=DB::Select("SELECT DISTINCT
style_creation.style_no,
cust_customer.customer_name,
merc_po_order_header.po_id,
merc_po_order_details.id,
item_master.master_description,
org_color.color_name,
org_size.size_name,
org_size.size_id,
org_uom.uom_code,
merc_po_order_details.req_qty,
DATE_FORMAT(merc_customer_order_details.rm_in_date, '%d-%b-%Y')as rm_in_date,
#  merc_customer_order_details.rm_in_date,
DATE_FORMAT(merc_customer_order_details.pcd, '%d-%b-%Y')as pcd,
#  merc_customer_order_details.pcd,
merc_customer_order_details.po_no,
merc_customer_order_header.order_id,
merc_customer_order_details.details_id as cus_order_details_id,
item_master.master_id,
item_master.master_code,
merc_shop_order_header.shop_order_id,
merc_shop_order_detail.shop_order_detail_id,
item_master.category_id,
item_master.master_code,
merc_po_order_details.purchase_price,
item_master.standard_price,
item_master.inventory_uom,
item_category.category_code,
item_master.width,
org_supplier.supplier_id,

(SELECT
 IFNULL(SUM(SGD.i_rec_qty),0)
 FROM
 store_grn_header AS SGH
 JOIN store_grn_detail as SGD on SGD.grn_id=SGH.grn_id
 WHERE
SGD.po_details_id = merc_po_order_details.id
AND SGH.grn_type='AUTO'

 ) AS tot_i_rec_qty,
(merc_po_order_details.req_qty-(SELECT
 IFNULL(SUM(SGD.i_rec_qty),0)
 FROM
 store_grn_header AS SGH
 JOIN store_grn_detail as SGD on SGD.grn_id=SGH.grn_id
 WHERE
 SGD.po_details_id = merc_po_order_details.id
 AND SGH.grn_type='AUTO')
 ) AS bal_qty,
(
SELECT
((IFNULL(sum(merc_po_order_details.req_qty*for_uom.max/100),0))+merc_po_order_details.req_qty )as maximum_tolarance
FROM
org_supplier_tolarance AS for_uom
WHERE
for_uom.supplier_id=org_supplier.supplier_id and
merc_po_order_details.req_qty BETWEEN for_uom.min_qty AND for_uom.max_qty
) AS maximum_tolarance
FROM
merc_po_order_header
INNER JOIN merc_po_order_details ON merc_po_order_header.po_number = merc_po_order_details.po_no
INNER JOIN style_creation ON merc_po_order_details.style = style_creation.style_id
INNER JOIN cust_customer ON style_creation.customer_id = cust_customer.customer_id
INNER JOIN org_supplier on merc_po_order_header.po_sup_code=org_supplier.supplier_id
#INNER JOIN merc_customer_order_header ON style_creation.style_id = merc_customer_order_header.order_style
#INNER JOIN merc_customer_order_details ON merc_customer_order_header.order_id = merc_customer_order_details.order_id
INNER JOIN merc_shop_order_detail on merc_po_order_details.shop_order_detail_id=merc_shop_order_detail.shop_order_detail_id
INNER JOIN merc_shop_order_header on  merc_shop_order_detail.shop_order_id=merc_shop_order_header.shop_order_id
INNER JOIN merc_shop_order_delivery on merc_shop_order_header.shop_order_id=merc_shop_order_delivery.shop_order_id
#INNER JOIN merc_shop_order_detail on merc_shop_order_header.shop_order_id=merc_shop_order_detail.shop_order_id
INNER JOIN merc_customer_order_details ON merc_shop_order_delivery.delivery_id = merc_customer_order_details.details_id
INNER JOIN merc_customer_order_header ON merc_customer_order_details.order_id = merc_customer_order_header.order_id
INNER JOIN item_master ON merc_po_order_details.item_code = item_master.master_id
INNER JOIN item_category ON item_master.category_id=item_category.category_id
LEFT JOIN org_supplier_tolarance AS for_category ON merc_po_order_header.po_sup_code = for_category.supplier_id
LEFT JOIN org_color ON merc_po_order_details.colour = org_color.color_id
LEFT JOIN org_size ON merc_po_order_details.size = org_size.size_id
LEFT JOIN org_uom ON merc_po_order_details.purchase_uom = org_uom.uom_id
WHERE
merc_po_order_header.po_id =?
AND merc_po_order_header.po_sup_code=?
AND merc_po_order_header.po_status=?
AND merc_po_order_details.po_status=?
GROUP BY(merc_po_order_details.id)
order By(merc_customer_order_details.rm_in_date)DESC",[$request->id,$request->sup_id,$po_status,$po_status]);
return $poData;
}
else if($request->grn_type_code="MANUAL"){
$po_status="CONFIRMED";
$poData=DB::SELECT("SELECT item_master.master_code,item_master.width,item_master.master_id,item_master.master_description,item_master.inventory_uom,merc_po_order_manual_details.qty as req_qty,org_uom.uom_code,merc_po_order_manual_header.*,
merc_po_order_manual_details.id,
merc_po_order_manual_details.po_header_id,
merc_po_order_manual_details.line_no,
merc_po_order_manual_details.inventory_part_id,
merc_po_order_manual_details.part_code,
merc_po_order_manual_details.description,
merc_po_order_manual_details.uom,
merc_po_order_manual_details.uom_id,
merc_po_order_manual_details.purchase_uom,
merc_po_order_manual_details.purchase_uom_code,
merc_po_order_manual_details.standard_price,
merc_po_order_manual_details.purchase_price,
merc_po_order_manual_details.qty,
merc_po_order_manual_details.req_date,
merc_po_order_manual_details.total_value,
merc_po_order_manual_details.po_status,
merc_po_order_manual_details.created_date,
merc_po_order_manual_details.created_by,
merc_po_order_manual_details.updated_date,
merc_po_order_manual_details.updated_by,
merc_po_order_manual_details.user_loc_id,
merc_po_order_manual_details.po_inv_type,
merc_po_order_manual_details.status,
item_category.category_code,
item_category.category_id,
org_color.color_name,
org_size.size_name,
org_color.color_id,
org_size.size_id,
  (SELECT
    IFNULL(SUM(SGD.i_rec_qty),0)
    FROM
    store_grn_header AS SGH
    JOIN store_grn_detail as SGD on SGD.grn_id=SGH.grn_id
    WHERE
    SGD.po_details_id = merc_po_order_manual_details.id
    AND SGH.grn_type='MANUAL'
   ) AS tot_i_rec_qty,

   (merc_po_order_manual_details.qty-(SELECT
                    IFNULL(SUM(SGD.i_rec_qty),0)
                      FROM
                     store_grn_header AS SGH
                     JOIN store_grn_detail as SGD on SGD.grn_id=SGH.grn_id
                     WHERE
                    SGD.po_details_id = merc_po_order_manual_details.id
                  AND SGH.grn_type='MANUAL')
    ) AS bal_qty,
    (
  SELECT
  ((IFNULL(sum(merc_po_order_manual_details.qty*for_uom.max/100),0))+merc_po_order_manual_details.qty )as maximum_tolarance
  FROM
  org_supplier_tolarance AS for_uom
  WHERE
  for_uom.supplier_id=org_supplier.supplier_id and
  merc_po_order_manual_details.qty BETWEEN for_uom.min_qty AND for_uom.max_qty
  ) AS maximum_tolarance

 From
  merc_po_order_manual_header
  INNER JOIN merc_po_order_manual_details on merc_po_order_manual_header.po_id=merc_po_order_manual_details.po_header_id
  INNER JOIN org_uom on merc_po_order_manual_details.purchase_uom=org_uom.uom_id
  INNER JOIN item_master on merc_po_order_manual_details.inventory_part_id=item_master.master_id
  INNER JOIN item_category ON item_master.category_id=item_category.category_id
  INNER JOIN org_supplier ON merc_po_order_manual_header.po_sup_id=org_supplier.supplier_id
  LEFT JOIN org_supplier_tolarance AS for_category ON merc_po_order_manual_header.po_sup_id = for_category.supplier_id
  LEFT JOIN org_color on item_master.color_id=org_color.color_id
  LEFT JOIN org_size on item_master.size_id=org_size.size_id
  where merc_po_order_manual_header.po_id=?
  AND merc_po_order_manual_header.po_status=?
  AND merc_po_order_manual_details.po_status=?
  GROUP BY(merc_po_order_manual_details.id)",[$request->id,$po_status,$po_status]);
}
  return $poData;
}
}
