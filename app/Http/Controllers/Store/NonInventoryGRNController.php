<?php

namespace App\Http\Controllers\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Merchandising\PurchaseOrderManual;
use App\Models\Merchandising\POManualNonInvDetails;
use App\Models\Store\NonInvGrnHeader;
use App\Models\Store\NonInvGrnDetails;
use App\Models\Store\StockTransaction;
use PhpParser\Node\NullableType;

class NonInventoryGRNController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->type;
        if ($type == 'datatable') {
            $data = $request->all();
            return response($this->datatable_search($data));
        } elseif ($type == 'loadManualPO') {
            $search = $request->search;
            return response($this->loadManualPO($search));
        }
    }

    private function loadManualPO($search)
    {
        $user = auth()->user();
        //dd($user->user_loc_id);
        $POList = DB::table('merc_po_order_manual_header')
            ->select(
                'merc_po_order_manual_header.po_id',
                'merc_po_order_manual_header.po_number'
            )
            ->where('merc_po_order_manual_header.po_inv_type', '=', 'NONINVENTORY')
            ->where('merc_po_order_manual_header.po_type', '=', 'OTHER')
            ->where('merc_po_order_manual_header.po_status', '=', 'CONFIRMED')
            ->where('merc_po_order_manual_header.status', '=', 1)
            ->where([['merc_po_order_manual_header.po_number', 'like', '%' . $search . '%'],['merc_po_order_manual_header.user_loc_id', '=', $user->user_loc_id  ]])
            ->get();

        return  $POList;
    }

    //load saved non inv grn_no
    public function show($id){

      $headerData=NonInvGrnHeader::join('merc_po_order_manual_header','non_inventory_grn_header.po_id','=','merc_po_order_manual_header.po_id')
                            ->join('org_supplier','non_inventory_grn_header.sup_id','=','org_supplier.supplier_id')
                            ->join('org_company','non_inventory_grn_header.invoice_id','=','org_company.company_id')
                            ->join('org_location','non_inventory_grn_header.deliver_id','=','org_location.loc_id')
                            ->where('non_inventory_grn_header.grn_id','=',$id)
                           ->select('merc_po_order_manual_header.*','non_inventory_grn_header.*','org_company.*','org_location.*','org_supplier.*')->first();

      $Invoiceto=DB::table('org_company')
                      ->where('company_id','=',$headerData->invoice_id)
                      ->select('*')->first();
      $deliveryLocation=DB::table('org_location')
                            ->where('loc_id','=',$headerData->deliver_id)
                           ->select('*')->first();
    $detailsData=DB::SELECT("SELECT
	item_master.*, org_uom.*, non_inventory_grn_details.grn_detail_id,
	non_inventory_grn_details.grn_id,
	non_inventory_grn_details.grn_line_no,
	non_inventory_grn_details.description_id,
	non_inventory_grn_details.description,
	non_inventory_grn_details.uom,
	non_inventory_grn_details.uom_id,
	non_inventory_grn_details.po_qty,
	non_inventory_grn_details.balance_qty AS previous_balance_qty,
	non_inventory_grn_details.received_qty AS previous_received_qty,
  non_inventory_grn_details.received_qty,
	non_inventory_grn_details.po_details_id,
	non_inventory_grn_details.status,
	non_inventory_grn_details.created_by,
	non_inventory_grn_details.created_date,
	non_inventory_grn_details.updated_by,
	non_inventory_grn_details.updated_date,
	non_inventory_grn_details.user_loc_id,
	non_inventory_grn_details.grn_status,
	non_inventory_grn_details.transferred,
	non_inventory_grn_details.payment_made,
	non_inventory_grn_details.user_com_id,
	non_inventory_grn_details.user_division_id,
  merc_po_order_manual_details.qty,
  merc_po_order_manual_details.id,
  merc_po_order_manual_details.remark_detail,
  (merc_po_order_manual_details.qty-(SELECT
                           IFNULL(SUM(NIGD.received_qty),0)
                             FROM
                           non_inventory_grn_details AS NIGD
                            WHERE
                           NIGD.po_details_id = merc_po_order_manual_details.id)
   ) AS balance_qty,
   (SELECT
                            IFNULL(SUM(NIGD.received_qty),0)
                              FROM
                            non_inventory_grn_details AS NIGD
                             WHERE
                            NIGD.po_details_id = merc_po_order_manual_details.id) AS total_grn_qty
FROM
	non_inventory_grn_header
INNER JOIN non_inventory_grn_details ON non_inventory_grn_header.grn_id = non_inventory_grn_details.grn_id
INNER JOIN item_master ON non_inventory_grn_details.description_id = item_master.master_id
INNER JOIN org_uom ON non_inventory_grn_details.uom_id = org_uom.uom_id
INNER JOIN merc_po_order_manual_details ON non_inventory_grn_details.po_details_id = merc_po_order_manual_details.id
WHERE
non_inventory_grn_header.grn_id =$id");
  return response([
      'data'=>[
        'headerData'=>$headerData,
        'detailsData'=>$detailsData,
        'Invoiceto'=>$Invoiceto,
        'deliveryLocation'=>$deliveryLocation
      ]
    ]);


    }

    //load conversion
    public function load_po_info(Request $request)
    {
        $po_no = $request->po_no;

        $Order = PurchaseOrderManual::with(['supplier', 'location', 'company'])->find($po_no);

        $order_det = DB::table('merc_po_order_manual_details')
            ->leftJoin('non_inventory_grn_details', 'non_inventory_grn_details.po_details_id', '=', 'merc_po_order_manual_details.id')
            ->select(
                'merc_po_order_manual_details.id',
                'merc_po_order_manual_details.description',
                'merc_po_order_manual_details.uom',
                'merc_po_order_manual_details.qty',
                'merc_po_order_manual_details.remark_detail',
                'non_inventory_grn_details.balance_qty AS previous_balance_qty',
                'non_inventory_grn_details.received_qty AS previous_received_qty',
                DB::raw("( merc_po_order_manual_details.qty - ( SELECT IFNULL(SUM(NIGD.received_qty), 0) FROM non_inventory_grn_details AS NIGD WHERE NIGD.po_details_id = merc_po_order_manual_details.id )) AS balance_qty"),
                DB::raw("( SELECT IFNULL(SUM(NIGD.received_qty), 0) FROM non_inventory_grn_details AS NIGD WHERE NIGD.po_details_id = merc_po_order_manual_details.id ) AS total_grn_qty")
            )
            ->where(function ($query) {
                $query->where('non_inventory_grn_details.status', '=', 1)
                    ->orWhereNull('non_inventory_grn_details.status');
            })
            ->where('merc_po_order_manual_details.po_header_id', $po_no)
            ->groupBy('merc_po_order_manual_details.id')
            ->get();

        if (count($order_det) == 0) {
            $order_det = DB::table('merc_po_order_manual_details')
                ->select(
                    'merc_po_order_manual_details.id',
                    'merc_po_order_manual_details.description',
                    'merc_po_order_manual_details.uom',
                    'merc_po_order_manual_details.qty'
                )
                ->where('merc_po_order_manual_details.po_header_id', $po_no)
                ->groupBy('merc_po_order_manual_details.id')
                ->get();
        }

        return response([
            'data' => [
                'order' => $Order,
                'detail' => $order_det
            ]
        ], Response::HTTP_CREATED);
    }

    public function store(Request $request)
    {
        $dataset = $request->dataset;
        //dd($dataset);
        //Save GRN Header
        for($r = 0 ; $r < sizeof($dataset) ; $r++)
        {

          if(isset($dataset[$r]['received_qty']) == '')
            {
              $line_id = $r+1;
              $err = 'Received Qty Line '.$line_id.' cannot be empty.';
              return response([ 'data' => ['status' => 'error','message' => $err]]);

            }

        }

        $header = new NonInvGrnHeader;
        $locId = auth()->payload()['loc_id'];
        $header->po_id = $request->header['po_id'];
        $header->invoice_no = strtoupper($request->header['invoice_no']);
        $header->sup_id = $request->header['sup_id'];
        $header->invoice_id = $request->header['invoice_id'];
        $header->deliver_id = $request->header['deliver_id'];
        $header->grn_status = 'PLANNED';
        $header->status = 1;

        $header->save();

        $grn_id = $header->grn_id;
        $grn_num  = $header->grn_number;
        $cur_update = NonInvGrnHeader::find($grn_id);
        $cur_update->grn_number = $grn_num;
        $cur_update->save();

        //Save Grn Details
        foreach ($request['dataset'] as $rec) {

            $poDetails = POManualNonInvDetails::find($rec['id']);

            if (($rec['balance_qty'] != null || $rec['balance_qty'] == 0) && isset($rec['received_qty'])) {

                if ($rec['received_qty'] != 0) {

                    $grn_status = '';

                    if ($poDetails->qty == $rec['received_qty']) {
                        $grn_status = 'CLOSED';
                    } elseif ($poDetails->qty != $rec['received_qty'] && $rec['balance_qty'] == 0) {
                        $grn_status = 'CLOSED';
                    } elseif ($poDetails->qty != $rec['received_qty'] && $rec['balance_qty'] != 0) {
                        $grn_status = 'PARTIAL_GRN';
                    }

                    $grnDetails = new NonInvGrnDetails;
                    $grnDetails->grn_id = $header->grn_id;
                    $grnDetails->grn_line_no = $this->get_next_line_no($header->grn_id);
                    $grnDetails->description_id = $poDetails->inventory_part_id;
                    $grnDetails->description = $rec['description'];
                    $grnDetails->uom = $rec['uom'];
                    $grnDetails->uom_id = $poDetails->uom_id;
                    $grnDetails->po_qty = $poDetails->qty;
                    $grnDetails->balance_qty = $rec['balance_qty'];
                    $grnDetails->received_qty = $rec['received_qty'];
                    $grnDetails->po_details_id = $poDetails->id;
                    $grnDetails->status = 1;
                    $grnDetails->user_loc_id = $locId;
                    $grnDetails->grn_status = $grn_status;
                    $grnDetails->transferred = 0;
                    $grnDetails->payment_made = 0;

                    $grnDetails->save();
/*
                    $st = new StockTransaction;
                    $st->doc_header_id = $header->grn_id;
                    $st->doc_detail_id = $grnDetails->grn_detail_id;
                    $st->doc_type = 'NON_INV_GRN';
                    $st->sup_po_header_id = $header->po_id;
                    $st->sup_po_details_id = $poDetails->id;
                    $st->item_id = $poDetails->inventory_part_id;
                    $st->uom = $poDetails->uom_id;
                    $st->qty = $grnDetails->received_qty;
                    $st->location = $locId;
                    $st->user_loc_id = $locId;
                    $st->purchase_price = $poDetails->purchase_price;
                    $st->direction = "+";
                    $st->status = 1;
                    $st->financial_year = date('Y');
                    $st->financial_month = date("F");
                    $st->save();
        */
                }
            }
        }

        return response([
            'data' => [
                'type' => 'success',
                'message' => 'Received Qty Saved Successfully.',
                'grn_no' => $header->grn_number,
                'headerData' => $header
            ]
        ], Response::HTTP_CREATED);
    }


    public function update(Request $request,$id){
      //dd($request->header['invoice_no']);
      $header=NonInvGrnHeader::find($id);
      $locId = auth()->payload()['loc_id'];
      $header->invoice_no=strtoupper($request->header['invoice_no']);
      $header->save();
      foreach ($request['dataset'] as $rec) {

        if(isset($rec['grn_detail_id'])==true){
          $poDetails = POManualNonInvDetails::find($rec['id']);

          if (($rec['balance_qty'] != null || $rec['balance_qty'] == 0) && isset($rec['received_qty'])) {

              if ($rec['received_qty'] != 0) {

                  $grn_status = '';

                  if ($poDetails->qty == $rec['received_qty']) {
                      $grn_status = 'CLOSED';
                  } elseif ($poDetails->qty != $rec['received_qty'] && $rec['balance_qty'] == 0) {
                      $grn_status = 'CLOSED';
                  } elseif ($poDetails->qty != $rec['received_qty'] && $rec['balance_qty'] != 0) {
                      $grn_status = 'PARTIAL_GRN';
                  }

                  $grnDetails = NonInvGrnDetails::find($rec['grn_detail_id']);
                  $grnDetails->grn_line_no = $this->get_next_line_no($header->grn_id);
                  $grnDetails->description_id = $poDetails->inventory_part_id;
                  $grnDetails->description = $rec['description'];
                  $grnDetails->uom = $rec['uom'];
                  $grnDetails->uom_id = $poDetails->uom_id;
                  $grnDetails->po_qty = $poDetails->qty;
                  $grnDetails->balance_qty = $rec['balance_qty'];
                  $grnDetails->received_qty = $rec['received_qty'];
                  $grnDetails->po_details_id = $poDetails->id;
                  $grnDetails->status = 1;
                  $grnDetails->user_loc_id = $locId;
                  $grnDetails->grn_status = $grn_status;
                  $grnDetails->transferred = 0;
                  $grnDetails->payment_made = 0;

                  $grnDetails->save();
/*
                  $st = new StockTransaction;
                  $st->doc_header_id = $header->grn_id;
                  $st->doc_detail_id = $grnDetails->grn_detail_id;
                  $st->doc_type = 'NON_INV_GRN';
                  $st->sup_po_header_id = $header->po_id;
                  $st->sup_po_details_id = $poDetails->id;
                  $st->item_id = $poDetails->inventory_part_id;
                  $st->uom = $poDetails->uom_id;
                  $st->qty = $grnDetails->received_qty;
                  $st->location = $locId;
                  $st->user_loc_id = $locId;
                  $st->purchase_price = $poDetails->purchase_price;
                  $st->direction = "+";
                  $st->status = 1;
                  $st->financial_year = date('Y');
                  $st->financial_month = date("F");
                  $st->save();
*/
              }
          }
        }
      }
      return response([
          'data' => [
              'type' => 'success',
              'message' => 'Received Qty Updated Successfully.',
              'grn_no' => $header->grn_number,
              'headerData' => $header
          ]
      ], Response::HTTP_CREATED);
    }

    private function get_next_line_no($grn_id)
    {
        $max_no = NonInvGrnDetails::where('grn_id', '=', $grn_id)->max('grn_line_no');
        if ($max_no == NULL) {
            $max_no = 0;
        }
        return ($max_no + 1);
    }

    //validate anything based on requirements
    public function validate_data(Request $request)
    {
        $for = $request->for;
        if ($for == 'duplicate') {
            return response($this->validate_duplicate_code($request->invoice_no,$request->grn_id));
        }
    }


    //check customer code already exists
    private function validate_duplicate_code($code,$grn_id)
    {
        $grnHeader = NonInvGrnHeader::where('invoice_no', '=', $code)->first();
        if($grnHeader==null){
          return['status'=>'sucess'];
        }
        else if ($grnHeader->grn_id == $grn_id) {
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'message' => 'Invoice Number Already Exists'];
        }
    }

    //confirm grn
    public function confirm_grn(Request $request)
    {
        $grn_id = $request->grn_id;

        DB::table('non_inventory_grn_header')
            ->where('grn_id', $grn_id)
            ->update(['grn_status' => 'CONFIRMED']);

        DB::table('non_inventory_grn_details')
            ->where('grn_id', $grn_id)
            ->update(['grn_status' => 'CONFIRMED']);

        return response([
            'data' => [
                'status' => 'success',
                'message' => 'Non Inventory GRN confirmed Successfully.'
            ]
        ], 200);
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


        $grn_list = NonInvGrnHeader::join('merc_po_order_manual_header', 'merc_po_order_manual_header.po_id', '=', 'non_inventory_grn_header.po_id')
            ->join('org_supplier', 'org_supplier.supplier_id', '=', 'non_inventory_grn_header.sup_id')
            ->join('usr_profile', 'usr_profile.user_id', '=', 'non_inventory_grn_header.created_by')
            ->select(
                'non_inventory_grn_header.*',
                DB::raw("DATE_FORMAT(non_inventory_grn_header.created_date, '%d-%b-%Y') 'grn_date'"),
                'merc_po_order_manual_header.po_number',
                'org_supplier.supplier_name',
                'usr_profile.first_name'
            )
            //->where('non_inventory_grn_header.grn_status', '=', 'CONFIRMED')
            //->where('non_inventory_grn_header.status', '=', 1)
            ->Where(function ($query) use ($search) {
                $query->orWhere('grn_number', 'like', $search . '%')
                    ->orWhere('po_number', 'like', $search . '%')
                    ->orWhere('invoice_no', 'like', $search . '%')
                    ->orWhere('supplier_name', 'like', $search . '%');
            })
            ->orderBy($order_column, $order_type)
            ->offset($start)
            ->limit($length)
            ->get();

        $grn_count = NonInvGrnHeader::join('merc_po_order_manual_header', 'merc_po_order_manual_header.po_id', '=', 'non_inventory_grn_header.po_id')
            ->join('org_supplier', 'org_supplier.supplier_id', '=', 'non_inventory_grn_header.sup_id')
            ->join('usr_profile', 'usr_profile.user_id', '=', 'non_inventory_grn_header.created_by')
            //->where('non_inventory_grn_header.grn_status', '=', 'CONFIRMED')
            //->where('non_inventory_grn_header.status', '=', 1)
            ->Where(function ($query) use ($search) {
                $query->orWhere('grn_number', 'like', $search . '%')
                    ->orWhere('po_number', 'like', $search . '%')
                    ->orWhere('invoice_no', 'like', $search . '%')
                    ->orWhere('supplier_name', 'like', $search . '%');
            })
            ->count();

        return [
            "draw" => $draw,
            "recordsTotal" => $grn_count,
            "recordsFiltered" => $grn_count,
            "data" => $grn_list
        ];
    }

    public function cancel_grn(Request $request)
    {
        $grn_id = $request->grn_id;
        $locId = auth()->payload()['loc_id'];

        $grnHeader = NonInvGrnHeader::find($grn_id);

        $headerT = NonInvGrnHeader::where('non_inventory_grn_header.transferred', 1)
            ->where('non_inventory_grn_header.grn_id', $grn_id)
            ->exists();

        $headerP = NonInvGrnHeader::where('non_inventory_grn_header.payment_made', 1)
            ->where('non_inventory_grn_header.grn_id', $grn_id)
            ->exists();

        if ($headerT || $headerP) {
            return response([
                'data' => [
                    'message' => 'GRN Already in Use.',
                    'status' => 'error'
                ]
            ]);
        } else {

            $grnDetails = NonInvGrnDetails::where('grn_id', $grn_id)->get();

            foreach ($grnDetails as $grnDet) {
                $po_det_id = $grnDet->po_details_id;
                $rec_qty = $grnDet->received_qty;

                $poDetails = POManualNonInvDetails::find($po_det_id);

                $findDet = NonInvGrnDetails::where('po_details_id', $po_det_id)
                    ->where('grn_id', '!=', $grn_id)
                    ->orderBy('grn_detail_id', 'desc')
                    ->first();

                if ($findDet != null) {
                    $findDet->balance_qty += $rec_qty;
                    $findDet->save();
                }

                $grnDet->status = 0;
                $grnDet->grn_status = 'CANCELLED';
                $grnDet->save();


                $st = new StockTransaction;
                $st->doc_header_id = $grn_id;
                $st->doc_detail_id = $grnDet->grn_detail_id;
                $st->doc_type = 'NON_INV_GRN_CANCEL';
                $st->sup_po_header_id = $grnHeader->po_id;
                $st->sup_po_details_id = $po_det_id;
                $st->item_id = $poDetails->inventory_part_id;
                $st->uom = $poDetails->uom_id;
                $st->qty = $rec_qty;
                $st->location = $locId;
                $st->user_loc_id = $locId;
                $st->purchase_price = $poDetails->purchase_price;
                $st->direction = "-";
                $st->status = 1;
                $st->financial_year = date('Y');
                $st->financial_month = date("F");
                $st->save();
            }

            NonInvGrnHeader::where('grn_id', $grn_id)->update(['status' => 0, 'grn_status' => 'CANCELLED']);

            return response([
                'data' => [
                    'message' => 'GRN Cancelled Successfully.',
                    'status' => 'success'
                ]
            ]);
        }
    }
}
