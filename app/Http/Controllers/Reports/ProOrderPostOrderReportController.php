<?php

namespace App\Http\Controllers\Reports;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Org\Division;

class ProOrderPostOrderReportController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->type;
        if ($type == 'division_by_customer') {
            $customer_id = $request->customer_id;
            return response([
                'data' => $this->load_divisions($customer_id)
            ]);
        }
    }

    public function load_divisions($customer_id)
    {
        $divisions = Division::select('division_id', 'division_description', 'division_code')
            ->whereIn('division_id', function ($selected) use ($customer_id) {
                $selected->select('division_id')
                    ->from('org_customer_divisions')
                    ->where('customer_id', $customer_id);
            })->get();
        return $divisions;
    }

    public function load_preorder_postorder(Request $request)
    {
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;
        $data = $request->data;
        $customer = $data['customer']['customer_id'];
        $division = $data['division']['division_id'];

        $query = DB::table('merc_customer_order_details')
            ->join('merc_customer_order_header', 'merc_customer_order_header.order_id', '=', 'merc_customer_order_details.order_id')
            ->leftJoin('merc_shop_order_header', 'merc_shop_order_header.shop_order_id', '=', 'merc_customer_order_details.shop_order_id')
            ->join('merc_shop_order_detail', 'merc_shop_order_detail.shop_order_id', '=', 'merc_shop_order_header.shop_order_id')
            ->join('merc_shop_order_delivery', 'merc_shop_order_delivery.shop_order_id', '=', 'merc_shop_order_header.shop_order_id')
            ->join('item_master AS ITEM', 'ITEM.master_id', '=', 'merc_shop_order_detail.inventory_part_id')
            ->join('item_category', 'item_category.category_id', '=', 'ITEM.category_id')
            ->join('cust_customer', 'cust_customer.customer_id', '=', 'merc_customer_order_header.order_customer')
            ->join('style_creation', 'style_creation.style_id', '=', 'merc_customer_order_header.order_style')
            ->join('prod_category', 'prod_category.prod_cat_id', '=', 'style_creation.product_category_id')
            ->leftJoin('item_master AS FNG', 'FNG.master_id', '=', 'merc_customer_order_details.fng_id')
            ->join('org_country', 'org_country.country_id', '=', 'merc_customer_order_details.country')
            ->join('org_location', 'org_location.loc_id', '=', 'merc_customer_order_details.user_loc_id')
            ->join('costing', 'costing.id', '=', 'merc_shop_order_detail.costing_id')
            ->select(
                'cust_customer.customer_name',
                'merc_customer_order_header.order_code',
                'merc_shop_order_header.shop_order_id',
                'prod_category.prod_cat_description',
                'style_creation.style_no',
                'FNG.master_description',
                'merc_customer_order_details.order_qty',
                'merc_customer_order_details.fob',
                'merc_customer_order_details.planned_qty',
                'org_country.country_description',
                'org_location.loc_name',
                'item_category.category_code',
                'merc_shop_order_detail.purchase_price',
                DB::raw("(SUM(merc_shop_order_detail.po_qty)) AS po_qty"),
                DB::raw("(SUM(merc_shop_order_detail.issue_qty)) AS issue_qty"),
                'costing.labour_cost',
                'costing.finance_cost',
                'costing.coperate_cost',
                'costing.epm',
                'costing.np_margine'
            );

        if (($dateFrom != null || $dateFrom != "") && ($dateTo != null || $dateTo != "")) {
            $query->whereBetween('merc_customer_order_details.planned_delivery_date', [date("Y-m-d", strtotime($dateFrom)), date("Y-m-d", strtotime($dateTo))]);
        }

        if ($customer != null || $customer != "") {
            $query->where('merc_customer_order_header.order_customer', $customer);
        }

        if ($division != null || $division != "") {
            $query->where('merc_customer_order_header.order_division', $division);
        }

        $load_list = $query->where('po_qty', '!=', null)
            ->groupBy('merc_shop_order_header.shop_order_id', 'item_category.category_code')
            ->distinct()
            ->get();

        echo json_encode([
            "data" => $load_list
        ]);
    }
}
