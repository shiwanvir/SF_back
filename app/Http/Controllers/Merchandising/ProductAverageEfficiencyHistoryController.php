<?php

namespace App\Http\Controllers\Merchandising;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Merchandising\ProductAverageEfficiencyHistory;

class ProductAverageEfficiencyHistoryController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->type;
        if ($type == 'datatable') {
            $data = $request->all();
            return response($this->datatable_search($data));
        }
    }

    //create a SMVUpdate
    public function store(Request $request)
    {
        $efficiencyhis = new ProductAverageEfficiencyHistory();
        if ($efficiencyhis->validate($request->all())) {

            $pro_cate_id = $request->product_category['prod_cat_id'];
            $pro_sil_id = $request->product_silhouette['product_silhouette_id'];

            $lastQty = DB::table('product_average_efficiency')
                ->select('qty_to')
                ->where('product_average_efficiency.prod_cat_id', $pro_cate_id)
                ->where('product_average_efficiency.product_silhouette_id', $pro_sil_id)
                ->latest('updated_date')
                ->first();

            if ($lastQty != null && $lastQty->qty_to >= $request->qty_from) {
            } else {
                $efficiencyhis->fill($request->all());
                $efficiencyhis->version = 1;
                $efficiencyhis->status = 1;
                $efficiencyhis->save();

                return response([
                    'data' => [
                        'efficiencyhis' => $efficiencyhis
                    ]
                ], Response::HTTP_CREATED);
            }
        } else {
            $errors = $efficiencyhis->errors(); // failure, get errors
            return response(['errors' => ['validationErrors' => $errors]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    //get searched customers for datatable plugin format
    private function datatable_search($data)
    {
        $start = $data['start'];
        $length = $data['length'];
        $draw = $data['draw'];
        $search = $data['search']['value'];
        $order = $data['order'][0];
        $order_column = $data['columns'][$order['column']]['data'];
        $order_type = $order['dir'];

        $efficiencyhislist = ProductAverageEfficiencyHistory::join('prod_category', 'product_average_efficiency_history.prod_cat_id', '=', 'prod_category.prod_cat_id')
            ->join('product_silhouette', 'product_average_efficiency_history.product_silhouette_id', '=', 'product_silhouette.product_silhouette_id')
            ->select('product_average_efficiency_history.*', 'prod_category.prod_cat_description', 'product_silhouette.product_silhouette_description')
            ->where('product_average_efficiency_history.status', 1)
            ->Where(function ($query) use ($search) {
                $query->orWhere('prod_cat_description', 'like', $search . '%')
                    ->orWhere('product_silhouette_description', 'like', $search . '%');
            })
            // ->orderBy($order_column, $order_type)
            ->orderBy('created_date', 'desc')
            ->offset($start)->limit($length)->get();

        $efficiencyhiscount = ProductAverageEfficiencyHistory::join('prod_category', 'product_average_efficiency_history.prod_cat_id', '=', 'prod_category.prod_cat_id')
            ->join('product_silhouette', 'product_average_efficiency_history.product_silhouette_id', '=', 'product_silhouette.product_silhouette_id')
            ->select('product_average_efficiency_history.*', 'prod_category.prod_cat_description', 'product_silhouette.product_silhouette_description')
            ->Where(function ($query) use ($search) {
                $query->orWhere('prod_cat_description', 'like', $search . '%')
                    ->orWhere('product_silhouette_description', 'like', $search . '%');
            })
            ->where('product_average_efficiency_history.status', 1)
            ->count();

        return [
            "draw" => $draw,
            "recordsTotal" => $efficiencyhiscount,
            "recordsFiltered" => $efficiencyhiscount,
            "data" => $efficiencyhislist
        ];
    }

    //create SMVUpdate History
    public function update(Request $request)
    {
        $efficiencyhis = new ProductAverageEfficiencyHistory();
        if ($efficiencyhis->validate($request->all())) {
            $efficiencyhis->fill($request->all());
            $efficiencyhis->version = ($request->version) + 1;
            $efficiencyhis->status = 1;
            $efficiencyhis->save();

            return response([
                'data' => [
                    'efficiencyhis' => $efficiencyhis
                ]
            ], Response::HTTP_CREATED);
        } else {
            $errors = $efficiencyhis->errors(); // failure, get errors
            return response(['errors' => ['validationErrors' => $errors]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
