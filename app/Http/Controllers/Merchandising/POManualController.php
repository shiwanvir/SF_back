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
use App\Models\Merchandising\POManualDetails;
use PDF;

class POManualController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->type;
        if ($type == 'datatable') {
            $data = $request->all();
            return response($this->datatable_search($data));
        }
    }

    public function load_cost_division()
    {
        $user = auth()->payload();
        $userId = $user['user_id'];

        $cost_div = DB::table('usr_profile')
            ->join('org_cost_center', 'org_cost_center.cost_center_id', '=', 'usr_profile.cost_center_id')
            ->join('org_departments', 'org_departments.dep_id', '=', 'usr_profile.dept_id')
            ->join('user_locations', 'user_locations.user_id', '=', 'usr_profile.user_id')
            ->join('org_location', 'org_location.loc_id', '=', 'user_locations.loc_id')
            ->join('org_company', 'org_company.company_id', '=', 'org_location.company_id')
            ->select('org_cost_center.cost_center_id', 'org_cost_center.cost_center_name', 'org_departments.dep_id', 'org_departments.dep_name','org_company.company_id', 'org_company.company_name')
            ->where('usr_profile.user_id', $userId)
            ->distinct()
            ->get();

        return response([
            'data' => [
                'cost' => $cost_div
            ]
        ], Response::HTTP_CREATED);
        // return ['costDiv' => $cost_div];
    }

    public function load_invoiceto()
    {
        $user = auth()->payload();
        $userId = $user['user_id'];

        $invoice = DB::table('usr_profile')
            ->join('user_locations', 'user_locations.user_id', '=', 'usr_profile.user_id')
            ->join('org_location', 'org_location.loc_id', '=', 'user_locations.loc_id')
            ->join('org_company', 'org_company.company_id', '=', 'org_location.company_id')
            ->select('org_company.company_id', 'org_company.company_name')
            ->where('usr_profile.user_id', $userId)
            ->get();

        return response([
            'data' => [
                'invoice' => $invoice
            ]
        ], Response::HTTP_CREATED);
    }

    //search Company for autocomplete
    public function load_company(Request $request)
    {
        $user = auth()->payload();
        $userId = $user['user_id'];
        $search = $request->search;

        $company_lists = DB::table('usr_profile')
            ->join('user_locations', 'user_locations.user_id', '=', 'usr_profile.user_id')
            ->join('org_location', 'org_location.loc_id', '=', 'user_locations.loc_id')
            ->join('org_company', 'org_company.company_id', '=', 'org_location.company_id')
            ->select('org_company.company_id', 'org_company.company_name')
            ->where('usr_profile.user_id', $userId)
            ->where([['company_name', 'like', '%' . $search . '%'],])
            ->distinct()
            ->get();
        return $company_lists;
    }

    //search part
    public function load_part(Request $request)
    {
        $supplier = $request->sup;
        $_category = $request->_category;
        $sub_category = $request->sub_category;

        $part_nos = DB::table('item_master')
            ->select('item_master.master_id', 'item_master.master_code', 'item_master.master_description')
            ->where('item_master.standard_price', '!=', 0)
            ->where('item_master.standard_price', '!=', null)
            ->where('item_master.supplier_id', $supplier)
            ->where('item_master.supplier_id', '!=', null)
            ->where('item_master.category_id', $_category)
            ->where('item_master.subcategory_id', $sub_category)
            ->get();

        return response([
            'data' => $part_nos
        ]);
        // return $part_nos;
    }

    //search part description
    public function load_part_description(Request $request)
    {
        $part_id = $request->part_id;


        $part_des = DB::table('item_master')
            ->leftJoin('org_uom', 'org_uom.uom_id', '=', 'item_master.inventory_uom')
            ->leftJoin('item_category', 'item_category.category_id', '=', 'item_master.category_id')
            ->leftJoin('org_supplier', 'org_supplier.supplier_id', '=', 'item_master.supplier_id')
            ->leftJoin('fin_currency', 'fin_currency.currency_id', '=', 'org_supplier.currency')
            ->select(
                'item_master.master_id',
                'item_master.master_code',
                'item_master.master_description',
                'item_master.category_id',
                'org_uom.uom_id',
                'org_uom.uom_code',
                'item_master.standard_price',
                'item_category.category_name',
                'item_master.gsm',
                'item_master.width',
                'item_master.for_calculation',
                'fin_currency.currency_code',
                'item_master.cuttable_uom'
            )
            ->where('item_master.master_id', $part_id)
            ->get();

        return response([
            'data' => [
                'partDes' => $part_des
            ]
        ], Response::HTTP_CREATED);
    }

    public function load_part_des(Request $request)
    {
        $supplier = $request->sup;
        $part = $request->search;

        $part_nos = DB::table('item_master')
            ->select('item_master.master_id', 'item_master.master_code', 'item_master.master_description')
            ->where('item_master.standard_price', '!=', 0)
            ->where('item_master.standard_price', '!=', null)
            ->where('item_master.supplier_id', $supplier)
            ->where('item_master.supplier_id', '!=', null)
            ->where('item_master.master_description', 'like', '%' . $part . '%')
            ->get();

        // return response([
        //     'data' => [
        //         'part' => $part_nos
        //     ]
        // ], Response::HTTP_CREATED);
        return $part_nos;
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
            $order->po_inv_type = 'INVENTORY';
            $order->status = 1;
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

    //get a detail line
    public function show($id)
    {
        $Order = PurchaseOrderManual::with(['supplier', 'currency', 'location'])->find($id);
        $sup_id = $Order->po_sup_id;

        $currency = Supplier::join('fin_currency', 'fin_currency.currency_id', '=', 'org_supplier.currency')
            ->join('fin_payment_method', 'fin_payment_method.payment_method_id', '=', 'org_supplier.payment_mode')
            ->join('fin_payment_term', 'fin_payment_term.payment_term_id', '=', 'org_supplier.payemnt_terms')
            ->join('fin_shipment_term', 'fin_shipment_term.ship_term_id', '=', 'org_supplier.ship_terms_agreed')
            ->select('org_supplier.*', 'fin_currency.*', 'fin_payment_method.*', 'fin_payment_term.*', 'fin_shipment_term.*')
            ->where('org_supplier.supplier_id', '=', $sup_id)
            ->get();

        $Order['currency_det'] = $currency;

        if ($Order == null)
            throw new ModelNotFoundException("Requested Purchase Order Not Found", 1);
        else
            return response([
                'data' => $Order
            ]);
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
            ->where('merc_po_order_manual_header.po_inv_type', '=', 'INVENTORY')
            //->where('merc_po_order_manual_header.status', '=', 1)
            ->Where(function ($query) use ($search) {
                $query->orWhere('po_number', 'like', $search . '%')
                      ->orWhere('po_type', 'like', $search . '%')
                      ->orWhere('supplier_name', 'like', $search . '%')
                      ->orWhere(DB::raw("(DATE_FORMAT(merc_po_order_manual_header.delivery_date,'%d-%b-%Y'))"),'like', $search . '%')
                      //->orWhere('delivery_date', 'like', $search . '%')
                      ->orWhere('loc_name', 'like', $search . '%');
            })
            ->orderBy($order_column, $order_type)
            ->offset($start)
            ->limit($length)
            ->get();

        $po_count = PurchaseOrderManual::join('org_supplier', 'org_supplier.supplier_id', '=', 'merc_po_order_manual_header.po_sup_id')
            ->join('org_location', 'org_location.loc_id', '=', 'merc_po_order_manual_header.deliver_to')
            ->Where('merc_po_order_manual_header.created_by', '=', $user->user_id)
            ->where('merc_po_order_manual_header.po_inv_type', '=', 'INVENTORY')
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

    //confirm order
    public function confirm_po(Request $request)
    {
        $formData = $request->formData;
        $po_id = $formData['po_id'];

        DB::table('merc_po_order_manual_header')
            ->where('po_id', $po_id)
            ->update(['po_status' => 'CONFIRMED']);

        DB::table('merc_po_order_manual_details')
            ->where('po_header_id', $po_id)
            ->where('status', '=', 1)
            ->update(['po_status' => 'CONFIRMED']);

        return response([
            'data' => [
                'status' => 'success',
                'message' => 'Manual Purchase Order confirmed Successfully'
            ]
        ], 200);
    }

    //load conversion
    public function load_conversion(Request $request)
    {
        $uom = $request->uom;
        $purchase_uom = $request->purchase_uom['uom_code'];

        $con_fac =  DB::table('conversion_factor')
            ->select('conversion_factor.*')
            ->where('unit_code', $uom)
            ->where('base_unit', $purchase_uom)
            ->get();

        return response([
            'data' => [
                'factor' => $con_fac
            ]
        ], Response::HTTP_CREATED);

        // return $con_fac;
    }

    //generate pdf
    public function generate_pdf(Request $request)
    {
        $is_exists = false;
        $text = '';

        $result = DB::table('merc_po_order_manual_header')
            ->join('org_supplier', 'merc_po_order_manual_header.po_sup_id', '=', 'org_supplier.supplier_id')
            ->join('org_location', 'merc_po_order_manual_header.deliver_to', '=', 'org_location.loc_id')
            ->join('org_company', 'org_company.company_id', '=', 'merc_po_order_manual_header.invoice_to')
            ->join('usr_profile', 'merc_po_order_manual_header.created_by', '=', 'usr_profile.user_id')
            ->join('org_country', 'org_supplier.supplier_country', '=', 'org_country.country_id')
            ->join('fin_currency', 'merc_po_order_manual_header.po_def_cur', '=', 'fin_currency.currency_id')
            ->join('fin_payment_method', 'merc_po_order_manual_header.pay_mode', '=', 'fin_payment_method.payment_method_id')
            ->join('fin_payment_term', 'merc_po_order_manual_header.pay_term', '=', 'fin_payment_term.payment_term_id')
            ->join('fin_shipment_term', 'merc_po_order_manual_header.ship_term', '=', 'fin_shipment_term.ship_term_id')
            ->join('org_departments', 'org_departments.dep_id', '=', 'merc_po_order_manual_header.dept_id')
            ->select(
                'merc_po_order_manual_header.*',
                DB::raw("DATE_FORMAT(merc_po_order_manual_header.delivery_date, '%d-%b-%Y') 'deli_date'"),
                'org_supplier.supplier_code',
                'org_supplier.supplier_name',
                'org_supplier.supplier_address1',
                'org_supplier.supplier_address2',
                'org_supplier.supplier_city',
                'org_supplier.supplier_country',
                'org_country.country_description',
                'org_location.loc_name',
                'org_location.loc_address_1',
                'org_location.loc_address_2',
                'org_company.company_name',
                'org_company.company_address_1',
                'usr_profile.first_name',
                'fin_payment_method.payment_method_description',
                'fin_payment_term.payment_description',
                'fin_shipment_term.ship_term_description',
                'fin_currency.currency_code',
                'org_departments.dep_name'
            )
            ->where('merc_po_order_manual_header.po_number', $request->po_no)
            ->distinct()
            ->get();

        if ($result[0]->print == null) {
            $is_exists = true;
            $text = 'ORIGINAL';

            DB::table('merc_po_order_manual_header')
                ->where('merc_po_order_manual_header.po_number', $request->po_no)
                ->update(['print' => 'PRINTED']);
        } elseif ($result[0]->print == 'PRINTED') {
            $is_exists = true;
            $text = 'DUPLICATE';
        }

        $total_qty = POManualDetails::select('total_value')
            ->where('po_no', $request->po_no)
            ->where('merc_po_order_manual_details.po_status', '!=', 'CANCELLED')
            ->sum('total_value');
        $words = $this->displaywords(round($total_qty, 4));

        // $list=PoOrderDetails::select('*')->where('po_no', $request->po_no)->get();
        $list = DB::table('merc_po_order_manual_details')
            ->join('item_master', 'merc_po_order_manual_details.inventory_part_id', '=', 'item_master.master_id')
            ->select(
                'merc_po_order_manual_details.*',
                DB::raw("DATE_FORMAT(merc_po_order_manual_details.req_date, '%d-%b-%Y') 'required_date'"),
                'item_master.master_code',
                'item_master.master_description'
            )
            ->where('merc_po_order_manual_details.po_no', $request->po_no)
            ->where('merc_po_order_manual_details.po_status', '!=', 'CANCELLED')
            ->get();

        $groupList = [];

        $groupList = DB::table('merc_po_order_manual_details')
            ->join('item_master', 'merc_po_order_manual_details.inventory_part_id', '=', 'item_master.master_id')
            ->select(
                'merc_po_order_manual_details.id',
                'merc_po_order_manual_details.line_no',
                DB::raw("DATE_FORMAT(merc_po_order_manual_details.req_date, '%d-%b-%Y') 'req_date'"),
                'merc_po_order_manual_details.standard_price',
                'merc_po_order_manual_details.purchase_price',
                'merc_po_order_manual_details.purchase_uom_code',
                'merc_po_order_manual_details.uom',
                DB::raw("SUM(merc_po_order_manual_details.qty) AS qty"),
                'item_master.master_code',
                'item_master.master_description'
            )
            ->where('merc_po_order_manual_details.po_no', $request->po_no)
            ->where('merc_po_order_manual_details.po_status', '!=', 'CANCELLED')
            ->groupBy('item_master.master_id', 'merc_po_order_manual_details.req_date')
            ->orderBy('merc_po_order_manual_details.line_no')
            ->get();

        if ($result) {
            $data = [
                'po' => $result[0]->po_number,
                'po_status' => $result[0]->po_status,
                'delivery_date' => $result[0]->deli_date,
                'supplier_code' => $result[0]->supplier_code,
                'supplier_name' => $result[0]->supplier_name,
                'supplier_address1' => $result[0]->supplier_address1,
                'supplier_address2' => $result[0]->supplier_address2,
                'supplier_city' => $result[0]->supplier_city,
                'supplier_country' => $result[0]->supplier_country,
                'sup_country_name' => $result[0]->country_description,
                'loc_name' => $result[0]->loc_name,
                'loc_address_1' => $result[0]->loc_address_1,
                'loc_address_2' => $result[0]->loc_address_2,
                'company_name' => $result[0]->company_name,
                'company_address_1' => $result[0]->company_address_1,
                'created_by' => $result[0]->first_name,
                'department' => $result[0]->dep_name,
                'payment_method_description' => $result[0]->payment_method_description,
                'payment_description' => $result[0]->payment_description,
                'ship_term_description' => $result[0]->ship_term_description,
                'currency' => $result[0]->currency_code,
                'po_inv_type' => $result[0]->po_inv_type,
                'total_qty' => $total_qty,
                'data' => $list,
                'words' => $words,
                'summary' => $groupList
            ];
        }

        $config = [
            'format' => 'A4',
            'orientation' => 'P',
            'watermark' => $text,
            'show_watermark' => $is_exists,
        ];

        $pdf = PDF::loadView('print-manual-po', $data, [], $config);
        return $pdf->stream('document.pdf');
    }

    private function displaywords($number)
    {
        $no = (int) floor($number);
        $point = (int) round(($number - $no) * 100);
        $hundred = null;
        $digits_1 = strlen($no);
        $i = 0;
        $str = array();
        $words = array(
            '0' => '', '1' => 'ONE', '2' => 'TWO',
            '3' => 'THREE', '4' => 'FOUR', '5' => 'FIVE', '6' => 'SIX',
            '7' => 'SEVEN', '8' => 'EIGHT', '9' => 'NINE',
            '10' => 'TEN', '11' => 'ELEVEN', '12' => 'TWELEVE',
            '13' => 'THIRTEEN', '14' => 'FOURTEEN',
            '15' => 'FIFTEEN', '16' => 'SIXTEEN', '17' => 'SEVENTEEN',
            '18' => 'EIGHTEEN', '19' => 'NINETEEN', '20' => 'TWENTY',
            '30' => 'THIRTY', '40' => 'FORTY', '50' => 'FIFTY',
            '60' => 'SIXTY', '70' => 'SEVENTY',
            '80' => 'EIGHTY', '90' => 'NINETY'
        );
        $digits = array('', 'HUNDRED', 'THOUSAND', 'LAKH', 'CRORE');
        while ($i < $digits_1) {
            $divider = ($i == 2) ? 10 : 100;
            $number = floor($no % $divider);
            $no = floor($no / $divider);
            $i += ($divider == 10) ? 1 : 2;


            if ($number) {
                $plural = (($counter = count($str)) && $number > 9) ? 'S' : null;
                $hundred = ($counter == 1 && $str[0]) ? ' AND ' : null;
                $str[] = ($number < 21) ? $words[$number] .
                    " " . $digits[$counter] . $plural . " " . $hundred
                    :
                    $words[floor($number / 10) * 10]
                    . " " . $words[$number % 10] . " "
                    . $digits[$counter] . $plural . " " . $hundred;
            } else $str[] = null;
        }
        $str = array_reverse($str);
        $result = implode('', $str);


        if ($point > 20) {
            $points = ($point) ?
                "" . $words[floor($point / 10) * 10] . " " .
                $words[$point = $point % 10] : '';
        } else {
            $points = $words[$point];
        }
        if ($points != '') {
            return $result . " AND " . $points . " POINTS ONLY";
        } else {

            return $result . " ONLY";
        }
    }
}
