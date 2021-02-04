<?php

namespace App\Http\Controllers\Reports;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class YarnCountReportController extends Controller
{
    public function index(Request $request)
    {
    }

    public function load_yarn_count_detail(Request $request)
    {
        $year = $request['data']['year'];
        $supplier = $request['data']['supplier']['supplier_id'];

        $query = DB::table('item_master')
            ->join('item_property_data', 'item_property_data.master_id', '=', 'item_master.master_id')
            ->join('item_property', 'item_property.property_id', '=', 'item_property_data.property_id')
            ->join('item_property_assign_value', 'item_property_assign_value.property_id', '=', 'item_property.property_id')
            ->join('store_grn_detail', 'store_grn_detail.item_code', '=', 'item_master.master_id')
            ->join('store_grn_header', 'store_grn_header.grn_id', '=', 'store_grn_detail.grn_id')
            ->select(
                'item_property_assign_value.assign_value',
                'item_master.gsm',
                'item_master.width',
                DB::raw("MONTH(store_grn_detail.created_date) as month"),
                DB::raw("SUM(store_grn_detail.grn_qty) AS del_qty")
            );

        if ($supplier != null || $supplier != "") {
            $query->where('store_grn_header.sup_id', $supplier);
        }

        $load_list = $query->where('item_property.property_name', '=', 'FABRIC YARN COUNT')
            ->where('item_master.category_id', 1)
            ->whereYear('store_grn_detail.created_date', $year)
            ->groupBy(
                'item_property_assign_value.assign_value',
                'month'
            )
            ->get();

        $arr = [];
        $nextArr = [];
        $arrFinal = [];
        $arraysx = [];

        if (count($load_list) > 0) {

            foreach ($load_list as $key => $item) {
                $ydKg = (($item->gsm) * ($item->width) * 2.5454) / (1000 * 100 * 1.0936);
                $value = ($item->del_qty) / $ydKg;

                $arr[$key]['yarn_count'] = $item->assign_value;
                $arr[$key]['value'] = $value;
                $arr[$key]['month'] = $item->month;
            }

            for ($x = 0; $x < count($arr); $x++) {
                if ($this->searchForId($arr[$x]['yarn_count'], $nextArr) == null) {
                    $nextArr[$x]['yarn_count'] = $arr[$x]['yarn_count'];
                    $nextArr[$x]['month_' . $arr[$x]['month'] . ''] = $arr[$x]['value'];
                } else {                    
                    $key = $this->searchForId($arr[$x]['yarn_count'], $nextArr);
                    $nextArr[$key]['month_' . $arr[$x]['month'] . ''] = $arr[$x]['value'];
                }
            }

        }

        $arrFinal = array_map('unserialize', array_unique(array_map('serialize', $nextArr)));

        foreach ($arrFinal as $object) {
            $arraysx[] = (array) $object;
        }

        echo json_encode([
            "data" => $arraysx
        ]);
    }

    private function searchForId($id, $array)
    {
        foreach ($array as $key => $val) {
            if ($val['yarn_count'] === $id) {                
                return $key;
            }
        }
        return null;
    }
}
