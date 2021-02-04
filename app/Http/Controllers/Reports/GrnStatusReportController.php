<?php

namespace App\Http\Controllers\Reports;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class GrnStatusReportController extends Controller
{
    public function index(Request $request)
    {
    }

    public function load_grn_status(Request $request)
    {
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;
        $data = $request->data;
        $customer = $data['customer']['customer_id'];
        $po_no = $data['po_no']['po_number'];

        $query = DB::table('merc_shop_order_detail')
            ->join('merc_customer_order_details', 'merc_customer_order_details.shop_order_id', '=', 'merc_shop_order_detail.shop_order_id')
            ->join('merc_shop_order_delivery', 'merc_shop_order_delivery.shop_order_id', '=', 'merc_shop_order_detail.shop_order_id')
            ->join('merc_po_order_details', 'merc_po_order_details.shop_order_detail_id', '=', 'merc_shop_order_detail.shop_order_detail_id')
            ->join('merc_po_order_header', 'merc_po_order_header.po_id', '=', 'merc_po_order_details.po_header_id')
            ->leftJoin('org_supplier', 'org_supplier.supplier_id', '=', 'merc_po_order_header.po_sup_code')
            ->leftJoin('store_grn_detail', 'store_grn_detail.shop_order_detail_id', '=', 'merc_shop_order_detail.shop_order_detail_id')
            ->join('merc_customer_order_header', 'merc_customer_order_header.order_id', '=', 'merc_customer_order_details.order_id')
            ->leftJoin('cust_customer', 'cust_customer.customer_id', '=', 'merc_customer_order_header.order_customer')
            ->leftJoin('item_master', 'item_master.master_id', '=', 'merc_shop_order_detail.inventory_part_id')
            ->select(
                'merc_customer_order_details.po_no',
                'merc_shop_order_delivery.ship_qty',
                'merc_po_order_header.po_number',
                'org_supplier.supplier_name',
                DB::raw("DATE_FORMAT(merc_customer_order_details.rm_in_date, '%d-%b-%Y') 'rm_in_date'"),
                DB::raw("DATE_FORMAT(store_grn_detail.created_date, '%d-%b-%Y') 'grn_date'"),
                DB::raw("DATE_FORMAT(merc_customer_order_details.pcd, '%d-%b-%Y') 'pcd_date'"),
                DB::raw("DATE_FORMAT(merc_customer_order_details.planned_delivery_date, '%d-%b-%Y') 'planned_delivery_date'"),
                'cust_customer.customer_name',
                'item_master.master_code',
                'merc_shop_order_detail.po_qty',
                'store_grn_detail.grn_qty',
                'merc_shop_order_detail.issue_qty',
                'store_grn_detail.arrival_status',
                'store_grn_detail.maximum_tolarance',
                'merc_shop_order_detail.shop_order_detail_id',
                'merc_shop_order_detail.shop_order_id'
            );

        if (($dateFrom != null || $dateFrom != "") && ($dateTo != null || $dateTo != "")) {
            $query->whereBetween('merc_customer_order_details.planned_delivery_date', [date("Y-m-d", strtotime($dateFrom)), date("Y-m-d", strtotime($dateTo))]);
        }

        if ($customer != null || $customer != "") {
            $query->where('merc_customer_order_header.order_customer', $customer);
        }

        if ($po_no != null || $po_no != "") {
            $query->where('merc_po_order_header.po_number', $po_no);
        }

        $load_list = $query->distinct()
            ->get();

        echo json_encode([
            "data" => $load_list
        ]);
    }
}
