<?php

namespace App\Http\Controllers\Reports;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class StyleStatusReportController extends Controller
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
        $rm_in_date = $data['rm_date'];
        $style = $data['data']['style']['style_id'];
        $location = $data['data']['location']['loc_id'];

        $query = DB::table('merc_shop_order_detail')
            ->join('merc_shop_order_header', 'merc_shop_order_header.shop_order_id', '=', 'merc_shop_order_detail.shop_order_id')
            ->leftJoin('merc_customer_order_details', 'merc_customer_order_details.shop_order_id', '=', 'merc_shop_order_header.shop_order_id')
            ->join('item_master', 'item_master.master_id', '=', 'merc_shop_order_detail.inventory_part_id')
            ->join('store_grn_detail', 'store_grn_detail.shop_order_detail_id', '=', 'merc_shop_order_detail.shop_order_detail_id')
            ->join('store_grn_header', 'store_grn_header.grn_id', '=', 'store_grn_detail.grn_id')
            ->join('store_mrn_detail', 'store_mrn_detail.shop_order_detail_id', '=', 'merc_shop_order_detail.shop_order_detail_id')
            ->join('store_issue_detail', 'store_issue_detail.mrn_detail_id', '=', 'store_mrn_detail.mrn_detail_id')
            ->join('store_issue_header', 'store_issue_header.issue_id', '=', 'store_issue_detail.issue_id')
            ->join('style_creation', 'style_creation.style_id', '=', 'store_grn_detail.style_id')
            ->select(
                'merc_shop_order_detail.shop_order_detail_id',
                'style_creation.style_no',
                'merc_customer_order_details.rm_in_date',
                'item_master.master_code',
                'item_master.master_description',
                'merc_shop_order_header.order_qty',
                'merc_customer_order_details.po_no',
                'store_grn_header.grn_number',
                'store_issue_header.issue_no'
            );

        if ($rm_in_date != null || $rm_in_date != "") {
            $query->where('merc_customer_order_details.rm_in_date', $rm_in_date);
        }

        if ($style != null || $style != "") {
            $query->where('style_creation.style_id', $style);
        }

        if ($location != null || $location != "") {
            $query->where('store_grn_header.location', $location);
        }

        $load_list = $query->distinct()->get();

        echo json_encode([
            "data" => $load_list
        ]);
    }
}
