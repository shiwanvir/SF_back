<?php

namespace App\Http\Controllers\Merchandising;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Merchandising\ProductAverageEfficiency;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProductAverageEfficiencyController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->type;
        if ($type == 'datatable') {
            $data = $request->all();
            return response($this->datatable_search($data));
        }
    }

    //create a ProductAverageEfficiency
    public function store(Request $request)
    {
        $efficiency = new ProductAverageEfficiency();
        if ($efficiency->validate($request->all())) {

            $pro_cate_id = $request->product_category['prod_cat_id'];
            $pro_sil_id = $request->product_silhouette['product_silhouette_id'];

            $lastQty = DB::table('product_average_efficiency')
                ->select('qty_to')
                ->where('product_average_efficiency.prod_cat_id', $pro_cate_id)
                ->where('product_average_efficiency.product_silhouette_id', $pro_sil_id)
                ->latest('updated_date')
                ->first();

            if ($lastQty != null && $lastQty->qty_to >= $request->qty_from) {
                return response([
                    'data' => [
                        'message' => 'Qty From must be greater than ' . $lastQty->qty_to . '',
                        'status' => 0
                    ]
                ], Response::HTTP_CREATED);
            } else {

                $efficiency->fill($request->all());
                $efficiency->status = 1;
                $efficiency->version = 1;
                $efficiency->save();

                return response([
                    'data' => [
                        'message' => 'Product Efficiency Saved Successfully',
                        'efficiency' => $efficiency,
                        'status' => 1
                    ]
                ], Response::HTTP_CREATED);
            }
        } else {
            $errors = $efficiency->errors(); // failure, get errors
            return response(['errors' => ['validationErrors' => $errors]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    private function datatable_search($data)
    {
        $start = $data['start'];
        $length = $data['length'];
        $draw = $data['draw'];
        $search = $data['search']['value'];
        $order = $data['order'][0];
        $order_column = $data['columns'][$order['column']]['data'];
        $order_type = $order['dir'];

        $efficiencylist = ProductAverageEfficiency::join('prod_category', 'product_average_efficiency.prod_cat_id', '=', 'prod_category.prod_cat_id')
            ->join('product_silhouette', 'product_average_efficiency.product_silhouette_id', '=', 'product_silhouette.product_silhouette_id')
            ->select('product_average_efficiency.*', 'prod_category.prod_cat_description', 'product_silhouette.product_silhouette_description')
            ->where('product_average_efficiency.status', 1)
            ->Where(function ($query) use ($search) {
                $query->orWhere('prod_cat_description', 'like', $search . '%')
                    ->orWhere('product_silhouette_description', 'like', $search . '%');
            })
            ->orderBy($order_column, $order_type)
            ->offset($start)->limit($length)->get();

        $efficiencycount = ProductAverageEfficiency::join('prod_category', 'product_average_efficiency.prod_cat_id', '=', 'prod_category.prod_cat_id')
            ->join('product_silhouette', 'product_average_efficiency.product_silhouette_id', '=', 'product_silhouette.product_silhouette_id')
            ->select('product_average_efficiency.*', 'prod_category.prod_cat_description', 'product_silhouette.product_silhouette_description')
            ->where('product_average_efficiency.status', 1)
            ->Where(function ($query) use ($search) {
                $query->orWhere('prod_cat_description', 'like', $search . '%')
                    ->orWhere('product_silhouette_description', 'like', $search . '%');
            })
            ->count();

        return [
            "draw" => $draw,
            "recordsTotal" => $efficiencycount,
            "recordsFiltered" => $efficiencycount,
            "data" => $efficiencylist
        ];
    }

    //get a ProductAverageEfficiency
    public function show($id)
    {
        $smvupdate = ProductAverageEfficiency::with(['pro_category', 'silhouette'])->find($id);

        if ($smvupdate == null)
            throw new ModelNotFoundException("Requested Product Average Efficiency not found", 1);
        else
            return response(['data' => $smvupdate]);
    }

    //update a ProductAverageEfficiency
    public function update(Request $request, $id)
    {
        $efficiency = ProductAverageEfficiency::find($id);

        if ($efficiency->validate($request->all())) {
            $efficiency->fill($request->all());
            $efficiency->where('id', $id)->update(['version' => $request->version + 1]);
            $efficiency->save();

            return response(['data' => [
                'message' => 'Product Efficiency Updated Successfully',
                'efficiency' => $efficiency,
                'status' => 1
            ]]);
        } else {
            $errors = $efficiency->errors(); // failure, get errors
            return response(['errors' => ['validationErrors' => $errors]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
