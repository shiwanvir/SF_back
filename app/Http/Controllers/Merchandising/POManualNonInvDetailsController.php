<?php

namespace App\Http\Controllers\Merchandising;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Models\Merchandising\PurchaseOrderManual;
use App\Models\Merchandising\POManualDetails;
use App\Models\Merchandising\POManualNonInvDetails;
use App\Models\Merchandising\Item\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\Merchandising\Item\Category;

class POManualNonInvDetailsController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->type;
        if ($type == 'datatable') {
        } elseif ($type == 'loadUOM') {
            $item = $request->item_id;
            return response(['data' => $this->loadUOM($item)]);
        } elseif ($type == 'loadCategory') {
            $po_type = $request->po_type;
            return response(['data' => $this->loadCategory($po_type)]);
        }
    }

    //create a detail
    public function store(Request $request)
    {
        $order_details = new POManualNonInvDetails();
        if ($order_details->validate($request->all())) {
            $po = $request->po_no;
            $order_details->fill($request->all());
            $order_details->po_header_id = $request->po_header_id;
            $order_details->purchase_uom_code=$request->uom;
            $order_details->purchase_uom=$request->uom_id;
            $order_details->line_no = $this->get_next_line_no($request->po_header_id);
            $order_details->po_inv_type = 'NONINVENTORY';
            //new line
            if ($request->remark_detail != null) {
                $order_details->remark_detail = strtoupper($request->remark_detail);
            }
            //end
            $order_details->save();
            $order_details->req_date = date('d-M-Y', strtotime($order_details->req_date));

            return response([
                'data' => [
                    'message' => 'Manual Purchase Order Line Saved Successfully',
                    'PurchaseOrderDetails' => $order_details
                ]
            ], Response::HTTP_CREATED);
        } else {
            $errors = $order_details->errors(); // failure, get errors
            return response(['errors' => ['validationErrors' => $errors]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    private function get_next_line_no($po_id)
    {
        $max_no = POManualNonInvDetails::where('po_header_id', '=', $po_id)->max('line_no');
        if ($max_no == NULL) {
            $max_no = 0;
        }
        return ($max_no + 1);
    }

    //get a detail line
    public function show($id)
    {
        $detail = POManualNonInvDetails::with(['item', 'uom', 'category', 'sub_category'])->find($id);
        $header = PurchaseOrderManual::find($detail['po_header_id']);

        if ($detail == null)
            throw new ModelNotFoundException("Requested Order Details Not Found", 1);
        else
            return response(['data' => $detail]);
    }

    //update a detail line
    public function update(Request $request, $id)
    {
        $order_details = POManualNonInvDetails::find($id);

        if ($order_details->validate($request->all())) {

            $order_details->fill($request->all());
            $order_details->line_no = $request->line_no;
            $order_details->description = $request->description;
            $order_details->po_inv_type = 'NONINVENTORY';
            $order_details->purchase_uom_code=$request->uom;
            $order_details->purchase_uom=$request->uom_id;
            $order_details->remark_detail = strtoupper($request->remark_detail);
            //end
            $order_details->save();
            $order_details->req_date = date('d-M-Y', strtotime($order_details->req_date));

            return response([
                'data' => [
                    'message' => 'Manual Purchase Order Line Updated Successfully',
                    'PurchaseOrderDetails' => $order_details
                ]
            ], Response::HTTP_CREATED);
        } else {
            $errors = $order_details->errors(); // failure, get errors
            return response(['errors' => ['validationErrors' => $errors]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    //load uom
    private function loadUOM($item)
    {
        $detail = DB::table('item_uom')
            ->join('org_uom', 'org_uom.uom_id', '=', 'item_uom.uom_id')
            ->select(
                'org_uom.uom_id',
                'org_uom.uom_code'
            )
            ->where('item_uom.master_id', $item)
            ->get();

        return  $detail;
    }

    //load category
    private function loadCategory($po_type)
    {
        $cate_code = '';
        if ($po_type == 'INV') {
            $category_list = Category::where('status', '=', '1')
                ->where('inventory_type', '=', 'INVENTORY ITEM')
                ->get();
            return $category_list;
        } else {
            if ($po_type == 'GREAIGE') {
                $cate_code = 'FAB';
            } elseif ($po_type == 'OTHER') {
                $cate_code = 'OTH';
            }
            $category_list = Category::where('status', '=', '1')
                ->where('category_code', '=', $cate_code)
                ->get();
            return $category_list;
        }
    }

    //copy po line
    public function copy_po_line(Request $request)
    {

        $line_id = $request->line_id;

        $order_details = POManualNonInvDetails::find($line_id);

        $order_details_new = new POManualNonInvDetails();
        $order_details_new->po_header_id = $order_details->po_header_id;
        $order_details_new->po_no = $order_details->po_no;
        $order_details_new->category = $order_details->category;
        $order_details_new->sub_category = $order_details->sub_category;
        $order_details_new->inventory_part_id = $order_details->inventory_part_id;
        $order_details_new->description = $order_details->description;
        $order_details_new->uom = $order_details->uom;
        $order_details_new->uom_id = $order_details->uom_id;
        $order_details_new->item_currency = $order_details->item_currency;
        $order_details_new->purchase_price = $order_details->purchase_price;
        $order_details_new->qty = $order_details->qty;
        $order_details_new->req_date = $order_details->req_date;
        $order_details_new->total_value = $order_details->total_value;
        $order_details_new->po_status = 'PLANNED';
        $order_details_new->po_inv_type = 'NONINVENTORY';
        $order_details_new->status = 1;
        //new line
        $order_details_new->remark_detail = $order_details->remark_detail;
        //end
        $order_details_new->line_no = $this->get_next_line_no($order_details->po_header_id);
        $order_details_new->save();

        return response([
            'data' => [
                'status' => 'success',
                'message' => 'Manual PO line Copied Successfully.'
            ]
        ], 200);
    }
}
