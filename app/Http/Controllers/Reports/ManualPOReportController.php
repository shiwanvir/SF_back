<?php

namespace App\Http\Controllers\Reports;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class ManualPOReportController extends Controller
{
    public function index(Request $request)
    {
    }

    public function load_manual_po_inv_list(Request $request)
    {
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;
        $data = $request->data;
        $cost_center = $data['cost_center']['cost_center_id'];
        $department = $data['department']['dep_id'];
        $supplier  = $data['supplier']['supplier_id'];
        $po_status="CANCELLED";

        $query = DB::table('merc_po_order_manual_details')
            ->join('merc_po_order_manual_header', 'merc_po_order_manual_header.po_id', '=', 'merc_po_order_manual_details.po_header_id')
            ->leftJoin('org_company', 'org_company.company_id', '=', 'merc_po_order_manual_header.invoice_to')
            ->leftJoin('org_supplier', 'org_supplier.supplier_id', '=', 'merc_po_order_manual_header.po_sup_id')
            ->leftJoin('org_country', 'org_country.country_id', '=', 'org_supplier.supplier_country')
            ->leftJoin('store_grn_header', 'store_grn_header.po_number', '=', 'merc_po_order_manual_header.po_id')
            ->leftJoin('store_grn_detail', 'store_grn_detail.grn_id', '=', 'store_grn_header.grn_id')
            ->leftJoin('org_location', 'org_location.loc_id', '=', 'store_grn_header.location')
            ->join('usr_profile', 'usr_profile.user_id', '=', 'merc_po_order_manual_details.created_by')
            ->join('org_cost_center', 'org_cost_center.cost_center_id', '=', 'merc_po_order_manual_header.cost_center_id')
            ->join('org_departments', 'org_departments.dep_id', '=', 'merc_po_order_manual_header.dept_id')
            ->where('merc_po_order_manual_details.po_status','!=',$po_status)
            ->select(
                'org_company.company_name',
                'merc_po_order_manual_details.part_code',
                'merc_po_order_manual_details.description',
                'merc_po_order_manual_details.uom',
                'merc_po_order_manual_details.standard_price',
                'merc_po_order_manual_details.purchase_uom_code',
                'merc_po_order_manual_details.purchase_price',
                'org_supplier.supplier_name',
                'merc_po_order_manual_header.po_type',
                'org_country.country_description',
                'merc_po_order_manual_details.po_no',
                DB::raw("DATE_FORMAT(merc_po_order_manual_details.created_date, '%d-%b-%Y') 'created_date'"),
                'merc_po_order_manual_details.po_status',
                'merc_po_order_manual_details.qty',
                'store_grn_detail.grn_qty',
                'org_location.loc_name',
                'usr_profile.first_name',
                'org_departments.dep_name',
                'org_cost_center.cost_center_name'
            );

        if (($dateFrom != null || $dateFrom != "") && ($dateTo != null || $dateTo != "")) {
            $query->whereBetween(DB::raw('(DATE_FORMAT(merc_po_order_manual_details.created_date,"%Y-%m-%d"))'), [date("Y-m-d", strtotime($dateFrom)), date("Y-m-d", strtotime($dateTo))]);
        }

        if ($cost_center != null || $cost_center != "") {
            $query->where('merc_po_order_manual_header.cost_center_id', $cost_center);
        }

        if ($department != null || $department != "") {
            $query->where('merc_po_order_manual_header.dept_id', $department);
        }

        if ($supplier != null || $supplier != "") {
            $query->where('merc_po_order_manual_header.po_sup_id', $supplier);
        }

        $load_list = $query->where('merc_po_order_manual_details.po_inv_type', 'INVENTORY')
            ->distinct()
            ->get();

        echo json_encode([
            "data" => $load_list
        ]);
    }

    public function load_manual_po_non_inv_list(Request $request)
    {
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;
        $data = $request->data;
        $cost_center = $data['cost_center']['cost_center_id'];
        $department = $data['department']['dep_id'];
        $supplier  = $data['supplier']['supplier_id'];

        $query = DB::table('merc_po_order_manual_details')
            ->join('merc_po_order_manual_header', 'merc_po_order_manual_header.po_id', '=', 'merc_po_order_manual_details.po_header_id')
            ->leftJoin('org_company', 'org_company.company_id', '=', 'merc_po_order_manual_header.invoice_to')
            ->leftJoin('org_supplier', 'org_supplier.supplier_id', '=', 'merc_po_order_manual_header.po_sup_id')
            ->leftJoin('org_country', 'org_country.country_id', '=', 'org_supplier.supplier_country')
            ->leftJoin('non_inventory_grn_header', 'non_inventory_grn_header.po_id', '=', 'merc_po_order_manual_header.po_id')
            ->leftJoin('non_inventory_grn_details', 'non_inventory_grn_header.grn_id', '=', 'non_inventory_grn_details.grn_id')
            ->leftJoin('org_location', 'org_location.loc_id', '=', 'non_inventory_grn_header.user_loc_id')
            ->join('usr_profile', 'usr_profile.user_id', '=', 'merc_po_order_manual_details.created_by')
            ->join('org_cost_center', 'org_cost_center.cost_center_id', '=', 'merc_po_order_manual_header.cost_center_id')
            ->join('org_departments', 'org_departments.dep_id', '=', 'merc_po_order_manual_header.dept_id')
            ->select(
                'org_company.company_name',
                'merc_po_order_manual_details.description',
                'merc_po_order_manual_details.purchase_uom_code',
                'merc_po_order_manual_details.purchase_price',
                'org_supplier.supplier_name',
                'merc_po_order_manual_details.po_no',
                DB::raw("DATE_FORMAT(merc_po_order_manual_details.created_date, '%d-%b-%Y') 'created_date'"),
                'merc_po_order_manual_details.po_status',
                'merc_po_order_manual_details.qty',
                'non_inventory_grn_details.received_qty as grn_qty',
                'usr_profile.first_name',
                'org_departments.dep_name',
                'org_cost_center.cost_center_name'
            );

        if (($dateFrom != null || $dateFrom != "") && ($dateTo != null || $dateTo != "")) {
            $query->whereBetween(DB::raw('(DATE_FORMAT(merc_po_order_manual_details.created_date,"%Y-%m-%d"))'), [date("Y-m-d", strtotime($dateFrom)), date("Y-m-d", strtotime($dateTo))]);
        }

        if ($cost_center != null || $cost_center != "") {
            $query->where('merc_po_order_manual_header.cost_center_id', $cost_center);
        }

        if ($department != null || $department != "") {
            $query->where('merc_po_order_manual_header.dept_id', $department);
        }

        if ($supplier != null || $supplier != "") {
            $query->where('merc_po_order_manual_header.po_sup_id', $supplier);
        }

        $load_list = $query->where('merc_po_order_manual_details.po_inv_type', 'NONINVENTORY')
            ->distinct()
            ->get();

        echo json_encode([
            "data" => $load_list
        ]);
    }

    public function load_std_pur_price_report(Request $request)
    {
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;
        $data = $request->data;
        $cost_center = $data['cost_center']['cost_center_id'];
        $department = $data['department']['dep_id'];
        $supplier  = $data['supplier']['supplier_id'];

        $query = DB::table('merc_po_order_manual_details')
            ->join('merc_po_order_manual_header', 'merc_po_order_manual_header.po_id', '=', 'merc_po_order_manual_details.po_header_id')
            ->leftJoin('org_company', 'org_company.company_id', '=', 'merc_po_order_manual_header.invoice_to')
            ->leftJoin('org_supplier', 'org_supplier.supplier_id', '=', 'merc_po_order_manual_header.po_sup_id')
            ->leftJoin('org_country', 'org_country.country_id', '=', 'org_supplier.supplier_country')
            ->join('usr_profile', 'usr_profile.user_id', '=', 'merc_po_order_manual_details.created_by')
            ->join('org_cost_center', 'org_cost_center.cost_center_id', '=', 'merc_po_order_manual_header.cost_center_id')
            ->join('org_departments', 'org_departments.dep_id', '=', 'merc_po_order_manual_header.dept_id')
            ->select(
                'org_company.company_name',
                'merc_po_order_manual_details.part_code',
                'merc_po_order_manual_details.description',
                'merc_po_order_manual_details.uom',
                'merc_po_order_manual_details.standard_price',
                'merc_po_order_manual_details.purchase_uom_code',
                'merc_po_order_manual_details.purchase_price',
                'org_supplier.supplier_name',
                'merc_po_order_manual_header.po_type',
                'org_country.country_description',
                'merc_po_order_manual_details.po_no',
                DB::raw("DATE_FORMAT(merc_po_order_manual_details.created_date, '%d-%b-%Y') 'created_date'"),
                'merc_po_order_manual_details.po_status',
                'merc_po_order_manual_details.qty',
                'usr_profile.first_name',
                'org_departments.dep_name',
                'org_cost_center.cost_center_name'
            );

        if (($dateFrom != null || $dateFrom != "") && ($dateTo != null || $dateTo != "")) {
            $query->whereBetween(DB::raw('(DATE_FORMAT(merc_po_order_manual_details.created_date,"%Y-%m-%d"))'), [date("Y-m-d", strtotime($dateFrom)), date("Y-m-d", strtotime($dateTo))]);
        }

        if ($cost_center != null || $cost_center != "") {
            $query->where('merc_po_order_manual_header.cost_center_id', $cost_center);
        }

        if ($department != null || $department != "") {
            $query->where('merc_po_order_manual_header.dept_id', $department);
        }

        if ($supplier != null || $supplier != "") {
            $query->where('merc_po_order_manual_header.po_sup_id', $supplier);
        }

        $load_list = $query->where('merc_po_order_manual_details.po_status', '!=', 'CANCELLED')->distinct()
            ->get();

        echo json_encode([
            "data" => $load_list
        ]);
    }
}
