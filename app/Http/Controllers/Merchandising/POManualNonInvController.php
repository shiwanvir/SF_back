<?php

namespace App\Http\Controllers\Merchandising;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use App\Models\Org\Location\Company;
use App\Models\Org\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Merchandising\PurchaseOrderManual;

class POManualNonInvController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->type;
        if ($type == 'datatable') {
            $data = $request->all();
            return response($this->datatable_search($data));
        } elseif ($type == 'load_description') {
            $category_id = $request->category_id;
            $subcategory_id = $request->subcategory_id;
            return response([
                'data' => $this->load_description($category_id, $subcategory_id)
            ]);
        }
    }

    //create po manual inv header
    public function store(Request $request)
    {
        $order = new PurchaseOrderManual();
        $user = auth()->payload();
        $user_loc = $user['loc_id'];

        if ($order->validate($request->all())) {
            $order->fill($request->all());
            $order->user_loc_id = $user_loc;
            $order->po_status = 'PLANNED';
            $order->po_inv_type = 'NONINVENTORY';
            //new line
            if($request->remark_header != null){
                $order->remark_header = strtoupper($request->remark_header);
            }
            //end
            $order->save();

            $order_id = $order->po_id;
            $po_num  = $order->po_number;

            $cur_update = PurchaseOrderManual::find($order_id);
            $cur_update->po_number = $po_num;
            $cur_update->save();

            return response([
                'data' => [
                    'message' => 'Manual Purchase Order Saved Successfully',
                    'savepo' => $order,
                    'newpo' => $po_num,
                    'status' => 'PLANNED'
                ]
            ], Response::HTTP_CREATED);
        } else {
            $errors = $order->errors(); // failure, get errors
            return response(['errors' => ['validationErrors' => $errors]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    //search description
    public function load_description(Request $request)
    {
        // $part = $request->search;
        $category = $request->_category;
        $sub_category = $request->sub_category;

        $part_nos = DB::table('item_master')
            ->select('item_master.master_id', 'item_master.master_code', 'item_master.master_description')
            // ->where('item_master.master_description', 'like', '%' . $part . '%')
            ->where('item_master.category_id', $category)
            ->where('item_master.subcategory_id', $sub_category)
            ->whereNull('item_master.master_code')
            ->get();

        return response([
            'data' => $part_nos
        ]);
        // return $part_nos;
    }

    //get searched purchase orders for datatable plugin format
    private function datatable_search($data)
    {
        $start = $data['start'];
        $length = $data['length'];
        $draw = $data['draw'];
        $search = $data['search']['value'];
        $order = $data['order'][0];
        $order_column = $data['columns'][$order['column']]['data'];
        $order_type = $order['dir'];
        $fields = json_decode($data['query_data']);
        $user = auth()->user();


        $po_list = PurchaseOrderManual::join('org_supplier', 'org_supplier.supplier_id', '=', 'merc_po_order_manual_header.po_sup_id')
            ->join('org_location', 'org_location.loc_id', '=', 'merc_po_order_manual_header.deliver_to')
            ->select(
                'merc_po_order_manual_header.*',
                DB::raw("DATE_FORMAT(merc_po_order_manual_header.delivery_date, '%d-%b-%Y') 'del_date'"),
                'org_supplier.supplier_name',
                'org_location.loc_name'
            )
            ->where('merc_po_order_manual_header.created_by', '=', $user->user_id)
            ->where('merc_po_order_manual_header.po_inv_type', '=', 'NONINVENTORY')
            //->where('merc_po_order_manual_header.status', '=', 1)
            ->Where(function ($query) use ($search) {
                $query->orWhere('po_number', 'like', $search . '%')
                    ->orWhere('po_type', 'like', $search . '%')
                    ->orWhere('supplier_name', 'like', $search . '%')
                    ->orWhere(DB::raw("(DATE_FORMAT(merc_po_order_manual_header.delivery_date,'%d-%b-%Y'))"),'like', $search . '%')
                    ->orWhere('loc_name', 'like', $search . '%');
            })
            ->orderBy($order_column, $order_type)
            ->offset($start)
            ->limit($length)
            ->get();

        $po_count = PurchaseOrderManual::join('org_supplier', 'org_supplier.supplier_id', '=', 'merc_po_order_manual_header.po_sup_id')
            ->join('org_location', 'org_location.loc_id', '=', 'merc_po_order_manual_header.deliver_to')
            ->Where('merc_po_order_manual_header.created_by', '=', $user->user_id)
            ->where('merc_po_order_manual_header.po_inv_type', '=', 'NONINVENTORY')
            //->where('merc_po_order_manual_header.status', '=', 1)
            ->Where(function ($query) use ($search) {
                $query->orWhere('po_number', 'like', $search . '%')
                    ->orWhere('po_type', 'like', $search . '%')
                    ->orWhere('supplier_name', 'like', $search . '%')
                    ->orWhere(DB::raw("(DATE_FORMAT(merc_po_order_manual_header.delivery_date,'%d-%b-%Y'))"),'like', $search . '%')
                    ->orWhere('loc_name', 'like', $search . '%');
            })
            ->count();

        return [
            "draw" => $draw,
            "recordsTotal" => $po_count,
            "recordsFiltered" => $po_count,
            "data" => $po_list
        ];
    }

    //update a header
    public function update(Request $request, $id)
    {
        $order = PurchaseOrderManual::find($id);

        if ($order->validate($request->all())) {

            $order->fill($request->all());
            $order->save();
            $po_num  = $order->po_number;

            return response([
                'data' => [
                    'message' => 'Manual Purchase Order Updated Successfully',
                    'savepo' => $order,
                    'newpo' => $po_num,
                    'status' => 'PLANNED'
                ]
            ], Response::HTTP_CREATED);
        } else {
            $errors = $order->errors(); // failure, get errors
            return response(['errors' => ['validationErrors' => $errors]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
