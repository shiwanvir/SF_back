<?php

namespace App\Http\Controllers\Reports;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use App\Models\Report\DbLog;

class SalesReportController extends Controller
{
  public function index(Request $request)
  {
    $type = $request->type;
    if ($type == 'datatable') {
      $data = $request->all();
      $this->datatable_search($data);
    } else if ($type == 'headers') {
      $this->load_sizes();
    }
  }

  public function db2()
  {
    $employee_attendance =
      DB::Connection('mysql2')
      ->table('d2d_ord_detail')
      // ->leftJoin('USERINFO', 'CHECKINOUT.USERID', '=', 'USERINFO.USERID')
      // ->leftjoin(DB::Connection('mysql'))
      ->table('EMPLOYEE', 'USERINFO.CardNo', '=', 'EMPLOYEE.CardID')
      ->where('d2d_ord_detail.scNumber', '=', 2)
      ->take(1)
      ->get();

    // $response = DB::table($database1 . '.table1 as t1')
    //   ->leftJoin($database2 . '.table2 as t2', 't2.t1_id', '=', 't2.id')
    //   ->get();

    // DB::Connection('sqlsrv')
    //               ->table('CHECKINOUT')
    //               ->leftJoin('USERINFO', 'CHECKINOUT.USERID', '=', 'USERINFO.USERID')
    //               ->leftjoin(DB::Connection('sqlsrv2'))
    //               ->table('EMPLOYEE', 'USERINFO.CardNo', '=', 'EMPLOYEE.CardID')
    //               ->get();

    echo "<pre>";
    print_r($employee_attendance);
    echo "</pre>";
  }

  private function datatable_search($data)
  {
    $pcd_from = $data['from'];
    $pcd_to = $data['to'];
    $customer = $data['customer'];
    $status = $data['status'];
    $query = DB::table('merc_customer_order_details')
      ->join('merc_customer_order_header', 'merc_customer_order_header.order_id', '=', 'merc_customer_order_details.order_id')
      ->join('cust_customer', 'cust_customer.customer_id', '=', 'merc_customer_order_header.order_customer')
      ->join('cust_division', 'cust_division.division_id', '=', 'merc_customer_order_header.order_division')
      ->join('org_country', 'org_country.country_id', '=', 'merc_customer_order_details.country')
      ->join('style_creation', 'style_creation.style_id', '=', 'merc_customer_order_header.order_style')
      ->join('org_color', 'org_color.color_id', '=', 'merc_customer_order_details.style_color')
      ->join('merc_shop_order_header', 'merc_shop_order_header.shop_order_id', '=', 'merc_customer_order_details.shop_order_id')
      ->join('merc_shop_order_detail', 'merc_shop_order_detail.shop_order_id', '=', 'merc_shop_order_header.shop_order_id')
      ->join('item_master', 'item_master.master_id', '=', 'merc_customer_order_details.fng_id')
      // ->join('costing', 'costing.id', '=', 'merc_customer_order_details.costing_id')
      ->join('org_season', 'org_season.season_id', '=', 'merc_customer_order_header.order_season')
      ->join('org_location', 'org_location.loc_id', '=', 'merc_customer_order_details.projection_location')
      ->join('merc_customer_order_bill_to','merc_customer_order_header.bill_to','=','merc_customer_order_bill_to.bill_to')
      ->join('merc_customer_order_ship_to','merc_customer_order_header.ship_to','=','merc_customer_order_ship_to.ship_to')
      // ->join('merc_customer_order_size', 'merc_customer_order_size.details_id', '=', 'merc_customer_order_details.details_id')
      ->select(
        'merc_customer_order_details.order_id',
        'merc_customer_order_details.details_id',
        'cust_customer.customer_name',
        'cust_division.division_description',
        DB::raw("DATE_FORMAT(merc_customer_order_details.planned_delivery_date, '%d-%b-%Y') 'planned_delivery_date'"),
        'merc_customer_order_header.order_buy_name',
        'merc_customer_order_header.order_code',
        'merc_customer_order_header.order_type',
        'org_country.country_description',
        'merc_customer_order_bill_to.bill_to_address_name_2 as ship_to',
        'merc_customer_order_ship_to.customer_code as bill_to',
        'style_creation.remark_style',
        'style_creation.style_no',
        'style_creation.style_description',
        'org_color.color_code',
        'org_color.color_name',
        DB::raw("DATE_FORMAT(merc_customer_order_header.created_date, '%d-%b-%Y') 'created_date'"),
        'merc_customer_order_details.ac_date',
        'merc_customer_order_details.revised_delivery_date',
        'merc_customer_order_details.ship_mode',
        'merc_customer_order_details.excess_presentage',
        'merc_customer_order_details.po_no',
        'merc_customer_order_details.order_qty',
        'merc_customer_order_details.fob',
        'merc_shop_order_header.shop_order_id',
        'item_master.master_code',
        'merc_customer_order_details.active_status',
        'org_season.season_name',
        'org_location.loc_name',
        'merc_customer_order_details.details_id',
        'merc_shop_order_detail.po_qty'
        // DB::raw('(SELECT sum(merc_customer_order_size.order_qty) as total1 FROM merc_customer_order_size
        //         WHERE merc_customer_order_size.details_id = merc_customer_order_details.details_id GROUP BY merc_customer_order_details.details_id) AS total1'),
        // DB::raw('(SELECT sum(merc_customer_order_size.planned_qty) as total2 FROM merc_customer_order_size
        //         WHERE merc_customer_order_size.details_id = merc_customer_order_details.details_id GROUP BY merc_customer_order_details.details_id) AS total2')
        // DB::raw('sum(merc_customer_order_size.planned_qty) as total2')
      )
      ->where('merc_customer_order_details.active_status', 'ACTIVE');

    if ($customer != null || $customer != "") {
      $query->where('cust_customer.customer_id', $customer);
    }

    if ($status != null || $status != "") {
      $query->where('merc_customer_order_header.order_status', $status);
    }

    if (($pcd_from != null || $pcd_from != "") && ($pcd_to != null || $pcd_to != "")) {
      $query->whereBetween('merc_customer_order_details.pcd', [$pcd_from, $pcd_to]);
    }

    $load_list = $query->distinct()->get();

    $sizeA = [];
    $sizeLast = [];
    $sumQua = [];
    $loadCount = $load_list->count();

    for ($i = 0; $i < $loadCount; $i++) {
      array_push($sizeA, $load_list[$i]->details_id);
    }

    $sizeUniq = array_unique($sizeA);

    foreach ($sizeUniq as $p) {

      $query2 = DB::table('merc_customer_order_size')
        ->join('merc_customer_order_details', 'merc_customer_order_details.details_id', '=', 'merc_customer_order_size.details_id')
        ->join('org_size', 'org_size.size_id', '=', 'merc_customer_order_size.size_id')
        ->select(
          'merc_customer_order_details.details_id',
          'org_size.size_name AS size',
          'merc_customer_order_size.order_qty AS quantity'
        )
        ->where('merc_customer_order_details.details_id', $p)
        ->distinct()
        ->get();

      // $query3 = DB::table('merc_customer_order_size')
      //   ->join('merc_customer_order_details', 'merc_customer_order_details.details_id', '=', 'merc_customer_order_size.details_id')
      //   ->join('org_size', 'org_size.size_id', '=', 'merc_customer_order_size.size_id')
      //   ->select(
      //     'merc_customer_order_details.details_id',
      //     DB::raw('sum(merc_customer_order_size.order_qty) as total1'),
      //     DB::raw('sum(merc_customer_order_size.planned_qty) as total2')
      //   )
      //   ->where('merc_customer_order_details.details_id', $p)
      //   ->groupBy('merc_customer_order_details.details_id')
      //   ->distinct()
      //   ->get();

      foreach ($query2 as $p) {
        array_push($sizeLast, $query2);
      }

      // foreach ($query3 as $p) {
      //   array_push($sumQua, $query3);
      // }
    }

    $merged_collection = new Collection();
    $merged_collection_total = new Collection();

    foreach ($sizeLast as $collection) {
      foreach ($collection as $item) {
        $merged_collection->push($item);
      }
    }

    // foreach ($sumQua as $collection) {
    //   foreach ($collection as $item) {
    //     $merged_collection_total->push($item);
    //   }
    // }

    $arr = [];
    $arrFinal = [];
    $arraysx = [];
    $sizeLastC = count($merged_collection);

    for ($x = 0; $x < $sizeLastC; $x++) {
      $arr[$x]['details_id'] = $merged_collection[$x]->details_id;
      $arr[$x]['size'] = $merged_collection[$x]->size;
      $arr[$x]['qua'] = $merged_collection[$x]->quantity;
      // $arr[$x]['' . $merged_collection[$x]->size . ''] = $merged_collection[$x]->quantity;
    }

    $arrFinal = array_map('unserialize', array_unique(array_map('serialize', $arr)));

    foreach ($arrFinal as $object) {
      $arraysx[] = (array) $object;
    }

    echo json_encode([
      "recordsTotal" => "",
      "recordsFiltered" => "",
      "sizes" => $arraysx,
      // "total" => $merged_collection_total,
      "data" => $load_list
    ]);
  }

  public function load_sales_freeze(Request $request)
  {
    $dateFrom = $request->date_from;
    $dateTo = $request->date_to;
    $data = $request->data;
    $customer = $data['customer']['customer_id'];

    $query = DB::table('merc_customer_order_details')
      ->join('merc_customer_order_header', 'merc_customer_order_header.order_id', '=', 'merc_customer_order_details.order_id')
      ->leftJoin('merc_shop_order_header', 'merc_shop_order_header.shop_order_id', '=', 'merc_customer_order_details.shop_order_id')
      ->join('cust_customer', 'cust_customer.customer_id', '=', 'merc_customer_order_header.order_customer')
      ->join('style_creation', 'style_creation.style_id', '=', 'merc_customer_order_header.order_style')
      ->join('prod_category', 'prod_category.prod_cat_id', '=', 'style_creation.product_category_id')
      ->leftJoin('item_master AS FNG', 'FNG.master_id', '=', 'merc_customer_order_details.fng_id')
      ->join('org_country', 'org_country.country_id', '=', 'merc_customer_order_details.country')
      ->join('org_location', 'org_location.loc_id', '=', 'merc_customer_order_details.user_loc_id')
      ->select(
        'cust_customer.customer_name',
        'merc_customer_order_details.po_no',
        'merc_shop_order_header.shop_order_id',
        'prod_category.prod_cat_description',
        'style_creation.style_no',
        'FNG.master_description',
        'merc_customer_order_details.order_qty',
        'merc_customer_order_details.fob',
        'merc_customer_order_details.planned_qty',
        'org_country.country_description',
        'org_location.loc_name'
      );

    if (($dateFrom != null || $dateFrom != "") && ($dateTo != null || $dateTo != "")) {
      $query->whereBetween('merc_customer_order_details.planned_delivery_date', [date("Y-m-d", strtotime($dateFrom)), date("Y-m-d", strtotime($dateTo))]);
    }

    if ($customer != null || $customer != "") {
      $query->where('merc_customer_order_header.order_customer', $customer);
    }

    $load_list = $query->distinct()->get();

    echo json_encode([
      "data" => $load_list
    ]);
  }

  public function load_actual_sales(Request $request)
  {
    $dateFrom = $request->date_from;
    $dateTo = $request->date_to;
    $data = $request->data;
    $customer = $data['customer']['customer_id'];

    $query = DB::table('merc_customer_order_details')
      ->join('merc_customer_order_header', 'merc_customer_order_header.order_id', '=', 'merc_customer_order_details.order_id')
      ->leftJoin('merc_shop_order_header', 'merc_shop_order_header.shop_order_id', '=', 'merc_customer_order_details.shop_order_id')
      ->join('cust_customer', 'cust_customer.customer_id', '=', 'merc_customer_order_header.order_customer')
      ->join('style_creation', 'style_creation.style_id', '=', 'merc_customer_order_header.order_style')
      ->join('prod_category', 'prod_category.prod_cat_id', '=', 'style_creation.product_category_id')
      ->leftJoin('item_master AS FNG', 'FNG.master_id', '=', 'merc_customer_order_details.fng_id')
      ->join('org_country', 'org_country.country_id', '=', 'merc_customer_order_details.country')
      ->join('org_location', 'org_location.loc_id', '=', 'merc_customer_order_details.user_loc_id')
      ->select(
        'cust_customer.customer_name',
        'merc_customer_order_details.po_no',
        'merc_shop_order_header.shop_order_id',
        'prod_category.prod_cat_description',
        'style_creation.style_no',
        'FNG.master_description',
        'merc_shop_order_header.ship_qty',
        'merc_customer_order_details.fob',
        'merc_customer_order_details.planned_qty',
        'org_country.country_description',
        'org_location.loc_name'
      );

    if (($dateFrom != null || $dateFrom != "") && ($dateTo != null || $dateTo != "")) {
      $query->whereBetween('merc_customer_order_details.planned_delivery_date', [date("Y-m-d", strtotime($dateFrom)), date("Y-m-d", strtotime($dateTo))]);
    }

    if ($customer != null || $customer != "") {
      $query->where('merc_customer_order_header.order_customer', $customer);
    }

    $load_list = $query->distinct()->get();

    echo json_encode([
      "data" => $load_list
    ]);
  }

  public function load_rmc_sales(Request $request)
  {
    $dateFrom = $request->date_from;
    $dateTo = $request->date_to;
    $data = $request->data;
    $customer = $data['customer']['customer_id'];

    $query = DB::table('merc_customer_order_details')
      ->join('merc_customer_order_header', 'merc_customer_order_header.order_id', '=', 'merc_customer_order_details.order_id')
      ->leftJoin('merc_shop_order_header', 'merc_shop_order_header.shop_order_id', '=', 'merc_customer_order_details.shop_order_id')
      ->join('merc_shop_order_detail', 'merc_shop_order_detail.shop_order_id', '=', 'merc_shop_order_header.shop_order_id')
      ->join('item_master AS ITEM', 'ITEM.master_id', '=', 'merc_shop_order_detail.inventory_part_id')
      ->join('item_category', 'item_category.category_id', '=', 'ITEM.category_id')
      ->join('cust_customer', 'cust_customer.customer_id', '=', 'merc_customer_order_header.order_customer')
      ->join('style_creation', 'style_creation.style_id', '=', 'merc_customer_order_header.order_style')
      ->join('prod_category', 'prod_category.prod_cat_id', '=', 'style_creation.product_category_id')
      ->leftJoin('item_master AS FNG', 'FNG.master_id', '=', 'merc_customer_order_details.fng_id')
      ->join('org_country', 'org_country.country_id', '=', 'merc_customer_order_details.country')
      ->join('org_location', 'org_location.loc_id', '=', 'merc_customer_order_details.user_loc_id')
      ->select(
        'cust_customer.customer_name',
        'merc_customer_order_details.po_no',
        'merc_shop_order_header.shop_order_id',
        'prod_category.prod_cat_description',
        'style_creation.style_no',
        'FNG.master_description',
        'merc_shop_order_header.ship_qty',
        'merc_customer_order_details.fob',
        'merc_customer_order_details.planned_qty',
        'org_country.country_description',
        'org_location.loc_name',
        'item_category.category_code',
        'merc_shop_order_detail.purchase_price',
        DB::raw("(SUM(merc_shop_order_detail.po_qty)) AS po_qty"),
        DB::raw("(SUM(merc_shop_order_detail.issue_qty)) AS issue_qty")
      );

    if (($dateFrom != null || $dateFrom != "") && ($dateTo != null || $dateTo != "")) {
      $query->whereBetween('merc_customer_order_details.planned_delivery_date', [date("Y-m-d", strtotime($dateFrom)), date("Y-m-d", strtotime($dateTo))]);
    }

    if ($customer != null || $customer != "") {
      $query->where('merc_customer_order_header.order_customer', $customer);
    }

    $load_list = $query->where('merc_shop_order_detail.po_qty', '!=', null)->groupBy('merc_shop_order_header.shop_order_id', 'item_category.category_code')
      ->distinct()
      ->get();

    // $remove = null;

    // $filtered = $load_list->filter(function ($value, $key) use ($remove) {
    //   return $value->po_qty != null;
    // });


    // $load_list = $load_list->where('po_qty', null)->delete();

    echo json_encode([
      "data" => $load_list
    ]);
  }

  public function load_customer_po_status(Request $request)
  {
    $dateFrom = $request->date_from;
    $dateTo = $request->date_to;
    $data = $request->data;
    $customer = $data['customer'] == null ? null : $data['customer']['customer_id'];
    $division = $data['division'] == null ? null : $data['division']['division_id'];

    $query = DB::table('merc_customer_order_details')
      ->join('merc_customer_order_header', 'merc_customer_order_header.order_id', '=', 'merc_customer_order_details.order_id')
      ->leftJoin('merc_shop_order_delivery', 'merc_shop_order_delivery.delivery_id', '=', 'merc_customer_order_details.details_id')
      ->join('merc_shop_order_detail', 'merc_shop_order_detail.shop_order_id', '=', 'merc_shop_order_delivery.shop_order_id')
      ->join('item_master AS ITEM', 'ITEM.master_id', '=', 'merc_shop_order_detail.inventory_part_id')
      ->join('item_category', 'item_category.category_id', '=', 'ITEM.category_id')
      ->leftJoin('store_grn_detail', 'store_grn_detail.shop_order_detail_id', '=', 'merc_shop_order_detail.shop_order_detail_id')
      ->join('merc_po_order_header', 'merc_po_order_header.po_id', '=', 'store_grn_detail.po_number')
      ->select(
        'merc_po_order_header.po_number',
        'merc_customer_order_details.order_qty',
        'merc_shop_order_delivery.ship_qty',
        DB::raw("DATE_FORMAT(merc_customer_order_details.pcd, '%d-%b-%Y') 'pcd_date'"),
        DB::raw("DATE_FORMAT(store_grn_detail.created_date, '%d-%b-%Y') 'grn_date'"),
        DB::raw("DATE_FORMAT(merc_customer_order_details.planned_delivery_date, '%d-%b-%Y') 'delivery_date'"),
        'item_category.category_code',
        'merc_shop_order_detail.shop_order_id',
        'merc_shop_order_detail.shop_order_detail_id',
        DB::raw("(SUM(store_grn_detail.grn_qty)) AS grn_qty"),
        // DB::raw("(SUM(store_grn_detail.po_qty)) AS grn_po_qty"),
        //DB::raw("(SUM(merc_shop_order_detail.po_qty)) AS po_qty"),
        'merc_shop_order_detail.po_qty',
        DB::raw("(SUM(merc_shop_order_detail.issue_qty)) AS issue_qty")
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

    $load_list = $query->where('merc_shop_order_detail.po_qty', '!=', null)->groupBy('merc_shop_order_delivery.shop_order_id', 'item_category.category_code', 'merc_po_order_header.po_number')
      ->distinct()
      ->get();

    echo json_encode([
      "data" => $load_list
    ]);
  }
}
