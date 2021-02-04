<?php

namespace App\Http\Controllers\Reports;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class IssueReportController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->type;
        if ($type == 'datatable') {
            $data = $request->all();
            $this->datatable_search($data);
        }
    }

    private function datatable_search($data)
    {
        $from_date = $data['from_date'];
        $to_date = $data['to_date'];
        $customer = $data['data']['customer_name']['customer_id'];
        $location = $data['data']['loc_name']['loc_id'];
        $style = $data['data']['style_no']['style_id'];

        $query = DB::table('store_issue_detail')
            ->join('store_issue_header', 'store_issue_header.issue_id', '=', 'store_issue_detail.issue_id')
            ->join('store_mrn_detail', 'store_mrn_detail.mrn_detail_id', '=', 'store_issue_detail.mrn_detail_id')
            ->join('store_mrn_header', 'store_mrn_header.mrn_id', '=', 'store_mrn_detail.mrn_id')
            ->join('style_creation', 'style_creation.style_id', '=', 'store_mrn_header.style_id')
            ->join('item_master', 'item_master.master_id', '=', 'store_issue_detail.item_id')
            ->join('item_category', 'item_category.category_id', '=', 'item_master.category_id')
            ->join('merc_shop_order_detail', 'merc_shop_order_detail.shop_order_detail_id', '=', 'store_mrn_detail.shop_order_detail_id')
            ->join('merc_customer_order_details', 'merc_customer_order_details.details_id', '=', 'store_mrn_detail.cust_order_detail_id')
            ->join('merc_customer_order_header', 'merc_customer_order_header.order_id', '=', 'merc_customer_order_details.order_id')
            ->join('cust_customer', 'cust_customer.customer_id', '=', 'merc_customer_order_header.order_customer')
            ->join('merc_po_order_details', 'merc_po_order_details.shop_order_detail_id', '=', 'merc_shop_order_detail.shop_order_detail_id')
            ->join('merc_po_order_header', 'merc_po_order_header.po_id', '=', 'merc_po_order_details.po_header_id')
            ->join('store_grn_detail', 'store_grn_detail.shop_order_detail_id', '=', 'store_mrn_detail.shop_order_detail_id')
            ->join('usr_profile', 'usr_profile.user_id', '=', 'store_issue_header.created_by')
            ->select(
                'item_master.master_code',
                'item_master.master_description',
                'item_category.category_name',
                'style_creation.style_no',
                'merc_shop_order_detail.shop_order_id',
                'store_issue_detail.issue_detail_id',
                'merc_po_order_header.po_number',
                'merc_po_order_header.po_date',
                'cust_customer.customer_name',
                'merc_customer_order_details.po_no',
                'store_grn_detail.grn_qty',
                'store_grn_detail.bal_qty',
                DB::raw("(DATE_FORMAT(store_grn_detail.created_date,'%d-%b-%Y')) AS grn_date"),
                'store_mrn_header.mrn_no',
                'store_issue_header.issue_no',
                'store_issue_detail.qty AS issue_qty',
                DB::raw("(DATE_FORMAT(store_issue_detail.created_date,'%d-%b-%Y')) AS issue_date"),
                'store_grn_detail.original_bal_qty',
                'usr_profile.first_name'
            );

        if (($from_date != null || $from_date != "") && ($to_date != null || $to_date != "")) {
            $query->whereBetween('store_issue_detail.created_date', [$from_date, $to_date]);
        }

        if ($customer != null || $customer != "") {
            $query->where('cust_customer.customer_id', $customer);
        }

        if ($location != null || $location != "") {
            $query->where('store_issue_detail.location_id', $location);
        }

        if ($style != null || $style != "") {
            $query->where('store_mrn_header.style_id', $style);
        }

        $load_list = $query->distinct()->get();

        echo json_encode([
            "data" => $load_list
        ]);
    }

    public function load_issuing_status(Request $request)
    {
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;
        $data = $request->data;
        $customer = $data['customer']['customer_id'];
        $location = $data['location_name']['loc_id'];

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
            ->leftJoin('store_mrn_detail', 'store_mrn_detail.shop_order_detail_id', '=', 'merc_shop_order_detail.shop_order_detail_id')
            ->leftJoin('store_issue_detail', 'store_issue_detail.mrn_detail_id', '=', 'store_mrn_detail.mrn_detail_id')
            ->select(
                'merc_customer_order_details.po_no',
                'merc_shop_order_delivery.ship_qty',
                'merc_po_order_header.po_number',
                'org_supplier.supplier_name',
                DB::raw("DATE_FORMAT(merc_customer_order_details.rm_in_date, '%d-%b-%Y') 'rm_in_date'"),
                DB::raw("DATE_FORMAT(store_grn_detail.created_date, '%d-%b-%Y') 'grn_date'"),
                DB::raw("DATE_FORMAT(merc_customer_order_details.planned_delivery_date, '%d-%b-%Y') 'planned_delivery_date'"),
                'cust_customer.customer_name',
                'item_master.master_code',
                'merc_shop_order_detail.po_qty',
                'store_grn_detail.grn_qty',
                'store_issue_detail.qty',
                'store_issue_detail.issue_status',
                'merc_shop_order_detail.shop_order_detail_id',
                'merc_shop_order_detail.shop_order_id'
            );

        if (($dateFrom != null || $dateFrom != "") && ($dateTo != null || $dateTo != "")) {
            $query->whereBetween('merc_customer_order_details.planned_delivery_date', [date("Y-m-d", strtotime($dateFrom)), date("Y-m-d", strtotime($dateTo))]);
        }

        if ($customer != null || $customer != "") {
            $query->where('merc_customer_order_header.order_customer', $customer);
        }

        if ($location != null || $location != "") {
            $query->where('store_issue_detail.location_id', $location);
        }

        $load_list = $query->distinct()
            ->get();

        echo json_encode([
            "data" => $load_list
        ]);
    }
}
