<?php

namespace App\Http\Controllers\Merchandising;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Models\Merchandising\PurchaseOrderManual;
use App\Models\Merchandising\POManualDetails;
use App\Models\Merchandising\Item\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class POManualDetailsController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->type;
        if ($type == 'datatable') {
        } elseif ($type == 'loadPUOM') {
            return response(['data' => $this->loadPurUOM()]);
        } else {
            $order_id = $request->order_id;
            return response(['data' => $this->list($order_id)]);
        }
    }

    //create a detail
    public function store(Request $request)
    {
        $order_details = new POManualDetails();
        if ($order_details->validate($request->all())) {
            $po = $request->po_no;

            $order_details->fill($request->all());
            $order_details->po_header_id = $request->po_header_id;
            $order_details->line_no = $this->get_next_line_no($request->po_header_id);
            $order_details->part_code = $this->get_part_code($order_details->inventory_part_id);
            $order_details->po_inv_type = 'INVENTORY';
            $order_details->status = 1;
            $order_details->save();
            $order_details->req_date_2 = date('d-M-Y', strtotime($order_details->req_date));

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
        $max_no = POManualDetails::where('po_header_id', '=', $po_id)->max('line_no');
        if ($max_no == NULL) {
            $max_no = 0;
        }
        return ($max_no + 1);
    }

    private function get_part_code($part_id)
    {
        $part_code = DB::table('item_master')
            ->select('master_code')
            ->where('master_id', '=', $part_id)
            ->first();
        return $part_code->master_code;
    }

    //get a detail line
    public function show($id)
    {
        $detail = POManualDetails::with(['item', 'purchase_uom', 'category', 'sub_category'])->find($id);
        $header = PurchaseOrderManual::find($detail['po_header_id']);
        $part = $detail['inventory_part_id'];

        $item_des =  DB::table('item_master')
            ->select('item_master.master_description')
            ->where('item_master.master_id', '=', $part)
            ->first();

        $item_code =  DB::table('item_master')
            ->select('item_master.master_code')
            ->where('item_master.master_id', '=', $part)
            ->first();

        $detail['description']  = $item_des;
        $detail['part_code']  = $item_code;

        if ($detail == null)
            throw new ModelNotFoundException("Requested Order Details Not Found", 1);
        else
            return response(['data' => $detail]);
    }

    //update a detail line
    public function update(Request $request, $id)
    {
        $order_details = POManualDetails::find($id);

        if ($order_details->validate($request->all())) {

            $order_details->fill($request->all());
            $order_details->line_no = $request->line_no;
            $order_details->part_code = $request->part_code;
            $order_details->description = $request->description;
            $order_details->po_inv_type = 'INVENTORY';
            $order_details->status = 1;
            $order_details->save();

            $order_details->req_date_2 = date('d-M-Y', strtotime($order_details->req_date));

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

    private function list($order_id)
    {
        $detail = DB::table('merc_po_order_manual_details')
            ->select('merc_po_order_manual_details.*',  DB::raw("DATE_FORMAT(merc_po_order_manual_details.req_date, '%d-%b-%Y') 'req_date_2'"))
            ->where('merc_po_order_manual_details.po_header_id', $order_id)
            ->get();

        foreach ($detail as $de) {
            $de->req_date = date('d-M-Y', strtotime($de->req_date));
        }

        return  $detail;
    }

    private function loadPurUOM()
    {
        $detail = DB::table('org_uom')
            ->select(
                'org_uom.uom_id',
                'org_uom.uom_code'
            )
            ->where('org_uom.conversion_factor', 1)
            ->get();

        return  $detail;
    }

    public function remove_po_line(Request $request)
    {

        $details_id   = $request->id;
        $status       = $request->status;
        $order_id     = $request->po_header_id;

        if ($status == 'PLANNED') {
            $line = POManualDetails::find($details_id);
            if ($line['po_status'] == 'PLANNED') {
                $updateDetails = ['status' => 0, 'po_status' => 'CANCELLED'];
                POManualDetails::where('id', $details_id)->update($updateDetails);
            } else {
                return response([
                    'data' => [
                        'status' => 'error',
                        'message' => "This Line Already Released ."
                    ]
                ], 200);
            }
        } else if ($status == 'CANCELLED') {

            POManualDetails::where('id', $details_id)
                ->update(['status' => 0]);
        }

        $check_po =  DB::table('merc_po_order_manual_details')
                     ->where('po_header_id', $order_id)
                     ->where('status' , '<>', 0 )
                     ->get();

        if(sizeof($check_po) == 0)
         {
           DB::table('merc_po_order_manual_header')
                 ->where('po_id', $order_id)
               ->update(['status' => 0, 'po_status' => 'CANCELLED']);

          }

        $checkStatus = POManualDetails::where('po_header_id', $order_id)->select('po_status')->get();
        $count = POManualDetails::where('po_header_id', $order_id)->count();
        $x = 0;

        return response([
            'data' => [
                'status' => 'success',
                'message' => 'Line cancelled Successfully.'
            ]
        ], 200);
    }

    //cancel po header
    public function cancel_po(Request $request)
    {
        $po_id = $request->grn_id;

        $check_grn = DB::table('merc_po_order_manual_header')
                      ->where('po_id' , '=', $po_id )
                      ->where('po_status', '!=', 'CONFIRMED')
                      ->where('status' , '<>', 0 )
                      ->get();

        //dd($request);
        if(sizeof($check_grn) > 0)
          {
            $header = DB::table('merc_po_order_manual_header')
                ->where('po_id', $po_id)
                ->where('po_status', '!=', 'CONFIRMED')
                ->update(['status' => 0, 'po_status' => 'CANCELLED']);



            $detail = DB::table('merc_po_order_manual_details')
                ->where('po_header_id', $po_id)
                ->where('po_status', '!=', 'CONFIRMED')
                ->update(['status' => 0, 'po_status' => 'CANCELLED']);

            $check_po =  DB::table('merc_po_order_manual_details')
                             ->where('po_header_id', $po_id)
                             ->where('status' , '<>', 0 )
                             ->get();

            if(sizeof($check_po) == 0)
                 {
                   DB::table('merc_po_order_manual_header')
                         ->where('po_id', $po_id)
                       ->update([ 'po_status' => "CANCELLED" ]);

                  }


                  return response([
                      'data' => [
                          'status' => 'success',
                          'message' => 'Manual Purchase Order Cancelled Successfully.'
                      ]
                  ], 200);


          }
          else {
              return response([
                  'data' => [
                      'status' => 'error',
                      'message' => 'Manual Purchase Order Already Confirmed.'
                  ]
              ], 200);
          }



    }


    public function cancel_po_2(Request $request)
    {
        $po_id = $request->po_id;

        $check_grn = DB::table('merc_po_order_manual_header')
                      ->where('po_id' , '=', $po_id )
                      ->where('po_status', '!=', 'CONFIRMED')
                      ->where('status' , '<>', 0 )
                      ->get();

        //dd($request);
        if(sizeof($check_grn) > 0)
          {
            $header = DB::table('merc_po_order_manual_header')
                ->where('po_id', $po_id)
                ->where('po_status', '!=', 'CONFIRMED')
                ->update(['status' => 0, 'po_status' => 'CANCELLED']);



            $detail = DB::table('merc_po_order_manual_details')
                ->where('po_header_id', $po_id)
                ->where('po_status', '!=', 'CONFIRMED')
                ->update(['status' => 0, 'po_status' => 'CANCELLED']);

            $check_po =  DB::table('merc_po_order_manual_details')
                             ->where('po_header_id', $po_id)
                             ->where('status' , '<>', 0 )
                             ->get();

            if(sizeof($check_po) == 0)
                 {
                   DB::table('merc_po_order_manual_header')
                         ->where('po_id', $po_id)
                       ->update([ 'po_status' => "CANCELLED" ]);

                  }


                  return response([
                      'data' => [
                          'status' => 'success',
                          'message' => 'Manual Purchase Order Cancelled Successfully.'
                      ]
                  ], 200);


          }
          else {
              return response([
                  'data' => [
                      'status' => 'error',
                      'message' => 'Manual Purchase Order Already Confirmed.'
                  ]
              ], 200);
          }



    }


    //copy po line
    public function copy_po_line(Request $request)
    {
        $line_id = $request->line_id;

        $order_details = POManualDetails::find($line_id);

        $order_details_new = new POManualDetails();
        $order_details_new->po_header_id = $order_details->po_header_id;
        $order_details_new->po_no = $order_details->po_no;
        $order_details_new->category = $order_details->category;
        $order_details_new->sub_category = $order_details->sub_category;
        $order_details_new->inventory_part_id = $order_details->inventory_part_id;
        $order_details_new->part_code = $order_details->part_code;
        $order_details_new->description = $order_details->description;
        $order_details_new->uom = $order_details->uom;
        $order_details_new->uom_id = $order_details->uom_id;
        $order_details_new->item_currency = $order_details->item_currency;
        $order_details_new->purchase_uom = $order_details->purchase_uom;
        $order_details_new->purchase_uom_code = $order_details->purchase_uom_code;
        $order_details_new->standard_price = $order_details->standard_price;
        $order_details_new->purchase_price = $order_details->purchase_price;
        $order_details_new->qty = $order_details->qty;
        $order_details_new->req_date = $order_details->req_date;
        $order_details_new->total_value = $order_details->total_value;
        $order_details_new->po_status = 'PLANNED';
        $order_details_new->po_inv_type = 'INVENTORY';
        $order_details_new->status = 1;
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
