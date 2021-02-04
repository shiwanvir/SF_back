<?php

namespace App\Http\Controllers\Merchandising;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Controller;
use App\Models\Merchandising\PoOrderHeader;
use App\Models\Merchandising\PoOrderDetails;
use App\Models\Merchandising\PoOrderDetailsRevision;
use App\Models\Merchandising\PoOrderHeaderRevision;
use App\Models\Merchandising\PoOrderDeliverySplit;
//use App\Libraries\UniqueIdGenerator;
use App\Models\Merchandising\StyleCreation;
use App\Models\Store\GrnHeader;
use App\Models\Store\GrnDetail;

use App\Models\Merchandising\ShopOrderHeader;
use App\Models\Merchandising\ShopOrderDetail;
use App\Models\Merchandising\ShopOrderDelivery;
use App\Models\Merchandising\PoOrderApproval;
use App\Libraries\SAPService;
use App\Models\Org\Supplier;

use App\Libraries\Approval;
use App\Jobs\MailSendJob;

class PurchaseOrderManualDetailsController extends Controller
{
    public function __construct()
    {
      //add functions names to 'except' paramert to skip authentication
      $this->middleware('jwt.verify', ['except' => ['index']]);
    }

    //get customer list
    public function index(Request $request)
    {

      $type = $request->type;
      if($type == 'datatable') {
        $data = $request->all();
        return response($this->datatable_search($data));
      }
      else if($type == 'auto')    {
        $search = $request->search;
        return response($this->autocomplete_search($search));
      }

      else{
        $order_id = $request->order_id;
        return response(['data' => $this->list($order_id)]);
      }
    }


    //create a customer
    public function store(Request $request)
    {
      $order_details = new PoOrderDetails();
      if($order_details->validate($request->all()))
      {
		    $po = $request->po_no;

        $order_details->fill($request->all());
        $order_details->status = '1';
        $order_details->po_header_id = $request->po_id;
		    $order_details->line_no = $this->get_next_line_no($request->po_id);
        $order_details->tot_qty = $request->req_qty * $request->unit_price;
        $order_details->save();
		    $order_details['total'] = $request->req_qty * $request->unit_price;
		    $order_details['status_view'] = $this->get_next_line_no($request->po_id);

        return response([ 'data' => [
          'message' => 'Purchase Order Saved Successfully',
          'PurchaseOrderDetails' => $order_details
          ]
        ], Response::HTTP_CREATED );
      }
      else
      {
          $errors = $order_details->errors();// failure, get errors
          return response(['errors' => ['validationErrors' => $errors]], Response::HTTP_UNPROCESSABLE_ENTITY);
      }
    }

	private function get_next_line_no($po_id)
    {
      $max_no = PoOrderDetails::where('po_header_id','=',$po_id)->max('line_no');
	    if($max_no == NULL){ $max_no= 0;}
      return ($max_no + 1);
    }


    //get a customer
    public function show($id)
    {
      $customer = PoOrderDetails::find($id);
      if($customer == null)
        throw new ModelNotFoundException("Requested customer not found", 1);
      else
        return response([ 'data' => $customer ]);
    }


    //update a customer
    public function update(Request $request, $id)
    {
      $customer = PoOrderDetails::find($id);
      if($customer->validate($request->all()))
      {
        $customer->fill($request->except('po_no'));
        $customer->tot_qty = $request->req_qty * $request->unit_price;
        $customer->save();

        return response([ 'data' => [
          'message' => 'Purchase Order Updated Successfully',
          'PurchaseOrderDetails' => $customer
        ]]);
      }
      else
      {
        $errors = $customer->errors();// failure, get errors
        return response(['errors' => ['validationErrors' => $errors]], Response::HTTP_UNPROCESSABLE_ENTITY);
      }
    }


    //deactivate a customer
    public function destroy($id)
    {

     $check_details = PoOrderDetails::select('*')
                    ->where('id' , '=', $id )
                    ->where('status' , '<>', 0 )
                    ->get();

     $check_grn = GrnDetail::select('*')
                   ->where('po_details_id' , '=', $id )
                   ->where('status' , '<>', 0 )
                   ->get();
     if(sizeof($check_grn) > 0)
       {
        return response([ 'data' => ['status' => 'error','message' => 'Purchase Order Line already in use']]);
       }

     // $get_sap_doc_entry = PoOrderDetails::select('doc_entry')->where('id' , '=', $id )->where('status' , '<>', 0 )->get();
     // $get_load = PoOrderDetails::select('merc_po_order_header.transferred')->join('merc_po_order_header', 'merc_po_order_header.po_id', '=', 'merc_po_order_details.po_header_id')
     // ->where('merc_po_order_details.id' , '=', $id )
     // ->where('merc_po_order_details.status' , '<>', 0 )
     // ->get();
     //
     // if($get_sap_doc_entry[0]['doc_entry'] != null){
     //
     //   $sap = new SAPService;
     //   $sap_result = $sap->cancelDoc("PurchaseOrders(".$get_sap_doc_entry[0]['doc_entry'].")/Cancel");
     //
     //   if($sap_result!=1 || $get_load[0]['transferred'] != 0 )
     //   {
     //           return response([ 'data' => ['status' => 'error','message' => 'Purchase Order already Used on SAP']]);
     //     }


     $get_sap_doc_entry = PoOrderHeader::join('merc_po_order_details','merc_po_order_header.po_id','=','merc_po_order_details.po_header_id')
     ->select('merc_po_order_header.doc_entry')
     ->where('merc_po_order_details.id' , '=', $id )
     ->get();

     $headers = PoOrderHeader::join('merc_po_order_details','merc_po_order_header.po_id','=','merc_po_order_details.po_header_id')
     ->select('merc_po_order_header.doc_entry',
     'merc_po_order_details.sap_line_no',
     'merc_po_order_details.po_status',
     'merc_po_order_header.transferred')
     ->where('merc_po_order_details.id', $id)
     ->get();

    if($get_sap_doc_entry[0]['doc_entry'] != null)
    {

       foreach($headers as $header)
       {
           $doc_entry=$header->doc_entry;
           $body = array(
               "DocEntry" => $header->doc_entry,
               "DocumentLines" => array(
                   array(
                       "DocEntry" => $header->doc_entry,
                       "LineNum" => $header->sap_line_no,
                       "U_ARGNS_PO_STATUS" => "CANCELLED",
                   )
               )
           );

           $body2 = array(
               "DocEntry" => $header->doc_entry,
               "DocumentLines" => array(
                   array(
                       "DocEntry" => $header->doc_entry,
                       "LineNum" => $header->sap_line_no,
                       "LineStatus" => "C",
                   )
               )
           );
       }

       $sap = new SAPService;

       $update_status = $sap->patchRequest("PurchaseOrders",$doc_entry,$body);
       if($update_status==""){

         $cancel_line = $sap->patchRequest("PurchaseOrders",$doc_entry,$body2);
         if($cancel_line==""){

           $intv_value = DB::select("SELECT * FROM merc_po_order_details AS MPOD WHERE MPOD.id = '$id'");

           $po_details_r = new PoOrderDetailsRevision();
           $po_details_r->po_no = $intv_value[0]->po_no;
           $po_details_r->sc_no = $intv_value[0]->sc_no;
           $po_details_r->line_no = $intv_value[0]->line_no;
           $po_details_r->item_code = $intv_value[0]->item_code;
           $po_details_r->style = $intv_value[0]->style;
           $po_details_r->colour = $intv_value[0]->colour;
           $po_details_r->size = $intv_value[0]->size;
           $po_details_r->ori_unit_price = $intv_value[0]->ori_unit_price;
           $po_details_r->base_unit_price = $intv_value[0]->base_unit_price;
           $po_details_r->unit_price = $intv_value[0]->unit_price;
           $po_details_r->uom = $intv_value[0]->uom;
           $po_details_r->req_qty = $intv_value[0]->req_qty;
           $po_details_r->deli_date = $intv_value[0]->deli_date;
           $po_details_r->tot_qty = $intv_value[0]->tot_qty;
           $po_details_r->remarks = $intv_value[0]->remarks;
           $po_details_r->status = '0';
           $po_details_r->po_status = 'CANCELLED';
           $po_details_r->version =$this->get_max_version($intv_value[0]->po_header_id);
           $po_details_r->reason = 'LINE_CANCELLATION';
           $po_details_r->bom_id = $intv_value[0]->bom_id;

           $po_details_r->shop_order_id = $intv_value[0]->shop_order_id;
           $po_details_r->shop_order_detail_id = $intv_value[0]->shop_order_detail_id;
           $po_details_r->mat_colour = $intv_value[0]->mat_colour;
           $po_details_r->po_header_id = $intv_value[0]->po_header_id;
           $po_details_r->purchase_uom = $intv_value[0]->purchase_uom;
           $po_details_r->rm_in_date = $intv_value[0]->rm_in_date;
           $po_details_r->rm_revised_in_date = $intv_value[0]->rm_revised_in_date;

           $po_details_r->save();

           $tot_po_qty = 0;
           $find_po_qty = DB::select('SELECT * FROM merc_shop_order_detail AS SOD
                          WHERE SOD.shop_order_detail_id =  "'.$intv_value[0]->shop_order_detail_id.'"
                          AND SOD.status = 1 ' );

           $tot_po_qty = $find_po_qty[0]->po_qty - $intv_value[0]->req_qty;
           $balance_qty = $find_po_qty[0]->po_balance_qty + $intv_value[0]->req_qty ;

           DB::table('merc_shop_order_detail')
               ->where('shop_order_detail_id', $intv_value[0]->shop_order_detail_id)
               ->update([ 'po_qty' => $tot_po_qty ,
                          'po_balance_qty' => $balance_qty ]);


           //$Delete_poline = PoOrderDetails::where('id', $id)->delete();
           DB::table('merc_po_order_details') ->where('id', $id) ->update([ 'po_status' => "CANCELLED" ]);

           $check_po =  PoOrderDetails::select('*')
                        ->where('po_no' , '=', $intv_value[0]->po_no )
                        ->where('status' , '<>', 0 )
                        ->get();

           if(sizeof($check_po) == 0)
            {
              DB::table('merc_po_order_header')
                  ->where('po_number', $intv_value[0]->po_no)
                  ->update([ 'po_status' => "CANCELLED" ]);

             }

           return response([ 'data' => [
             'status' => 'succes',
             'message' => 'Selected Line(s) Cancelled Successfully.',
             'Check_PO' => $check_po
             ]]);



         }
         else{
             return response([ 'data' => ['status' => 'error','message' => 'Purchase Order already Used on SAP','sap_return' => $cancel_line ]]);
         }

       }
       else{
           return response([ 'data' => ['status' => 'error','message' => 'Purchase Order already Used on SAP 2','sap_return' => $update_status ]]);
       }
     }else{


         $intv_value = DB::select("SELECT * FROM merc_po_order_details AS MPOD WHERE MPOD.id = '$id'");

         $po_details_r = new PoOrderDetailsRevision();
         $po_details_r->po_no = $intv_value[0]->po_no;
         $po_details_r->sc_no = $intv_value[0]->sc_no;
         $po_details_r->line_no = $intv_value[0]->line_no;
         $po_details_r->item_code = $intv_value[0]->item_code;
         $po_details_r->style = $intv_value[0]->style;
         $po_details_r->colour = $intv_value[0]->colour;
         $po_details_r->size = $intv_value[0]->size;
         $po_details_r->ori_unit_price = $intv_value[0]->ori_unit_price;
         $po_details_r->base_unit_price = $intv_value[0]->base_unit_price;
         $po_details_r->unit_price = $intv_value[0]->unit_price;
         $po_details_r->uom = $intv_value[0]->uom;
         $po_details_r->req_qty = $intv_value[0]->req_qty;
         $po_details_r->deli_date = $intv_value[0]->deli_date;
         $po_details_r->tot_qty = $intv_value[0]->tot_qty;
         $po_details_r->remarks = $intv_value[0]->remarks;
         $po_details_r->status = '0';
         $po_details_r->po_status = 'CANCELLED';
         $po_details_r->version =$this->get_max_version($intv_value[0]->po_header_id);
         $po_details_r->reason = 'LINE_CANCELLATION';
         $po_details_r->bom_id = $intv_value[0]->bom_id;

         $po_details_r->shop_order_id = $intv_value[0]->shop_order_id;
         $po_details_r->shop_order_detail_id = $intv_value[0]->shop_order_detail_id;
         $po_details_r->mat_colour = $intv_value[0]->mat_colour;
         $po_details_r->po_header_id = $intv_value[0]->po_header_id;
         $po_details_r->purchase_uom = $intv_value[0]->purchase_uom;
         $po_details_r->rm_in_date = $intv_value[0]->rm_in_date;
         $po_details_r->rm_revised_in_date = $intv_value[0]->rm_revised_in_date;

         $po_details_r->save();

         $tot_po_qty = 0;
         $find_po_qty = DB::select('SELECT * FROM merc_shop_order_detail AS SOD
                        WHERE SOD.shop_order_detail_id =  "'.$intv_value[0]->shop_order_detail_id.'"
                        AND SOD.status = 1 ' );

         $tot_po_qty = $find_po_qty[0]->po_qty - $intv_value[0]->req_qty;
         $balance_qty = $find_po_qty[0]->po_balance_qty + $intv_value[0]->req_qty ;

         DB::table('merc_shop_order_detail')
             ->where('shop_order_detail_id', $intv_value[0]->shop_order_detail_id)
             ->update([ 'po_qty' => $tot_po_qty ,
                        'po_balance_qty' => $balance_qty ]);


         //$Delete_poline = PoOrderDetails::where('id', $id)->delete();
         DB::table('merc_po_order_details') ->where('id', $id) ->update([ 'po_status' => "CANCELLED" ]);

         $check_po =  PoOrderDetails::select('*')
                      ->where('po_no' , '=', $intv_value[0]->po_no )
                      ->where('status' , '<>', 0 )
                      ->get();

         if(sizeof($check_po) == 0)
          {
            DB::table('merc_po_order_header')
                ->where('po_number', $intv_value[0]->po_no)
                ->update([ 'po_status' => "CANCELLED" ]);

           }

         return response([ 'data' => [
           'status' => 'succes',
           'message' => 'Selected Line(s) Cancelled Successfully.',
           'Check_PO' => $check_po
           ]]);
       }









    }

    private function get_max_version($po_header_id)
      {
        $max_no = PoOrderDetailsRevision::where('po_header_id','=',$po_header_id)->max('version');
  	    if($max_no == NULL){ $max_no= 0;}
        return ($max_no + 1);
      }


    //validate anything based on requirements
    public function validate_data(Request $request){
      /*$for = $request->for;
      if($for == 'duplicate')
      {
        return response($this->validate_duplicate_code($request->customer_id , $request->customer_code));
      }*/
    }


    public function customer_divisions(Request $request) {
        /*$type = $request->type;
        $customer_id = $request->customer_id;

        if($type == 'selected')
        {
          $selected = Division::select('division_id','division_description')
          ->whereIn('division_id' , function($selected) use ($customer_id){
              $selected->select('division_id')
              ->from('org_customer_divisions')
              ->where('customer_id', $customer_id);
          })->get();
          return response([ 'data' => $selected]);
        }
        else
        {
          $notSelected = Division::select('division_id','division_description')
          ->whereNotIn('division_id' , function($notSelected) use ($customer_id){
              $notSelected->select('division_id')
              ->from('org_customer_divisions')
              ->where('customer_id', $customer_id);
          })->get();
          return response([ 'data' => $notSelected]);
        }*/

    }

    public function save_customer_divisions(Request $request)
    {
      /*$customer_id = $request->get('customer_id');
      $divisions = $request->get('divisions');
      if($customer_id != '')
      {
        DB::table('org_customer_divisions')->where('customer_id', '=', $customer_id)->delete();
        $customer = Customer::find($customer_id);
        $save_divisions = array();

        foreach($divisions as $devision)		{
          array_push($save_divisions,Division::find($devision['division_id']));
        }

        $customer->divisions()->saveMany($save_divisions);
        return response([
          'data' => [
            'customer_id' => $customer_id
          ]
        ]);
      }
      else {
        throw new ModelNotFoundException("Requested customer not found", 1);
      }*/
    }


    //check customer code already exists
    private function validate_duplicate_code($id , $code)
    {
      /*$customer = Customer::where('customer_code','=',$code)->first();
      if($customer == null){
        return ['status' => 'success'];
      }
      else if($customer->customer_id == $id){
        return ['status' => 'success'];
      }
      else {
        return ['status' => 'error','message' => 'Customer code already exists'];
      }*/
    }


    //search customer for autocomplete
    private function list($order_id)
  	{
  		$order_details = DB::select('SELECT MH.po_id, MD.*
                      FROM merc_po_order_header AS MH
                      INNER JOIN merc_po_order_details AS MD ON MH.po_id = MD.po_header_id
                      WHERE MH.po_id = "'.$order_id.'" ');
      return $order_details;
  	}


    //search customer for autocomplete
    private function style_search($search)
  	{
  		$style_lists = StyleCreation::select('style_id','style_no','customer_id')
  		->where([['style_no', 'like', '%' . $search . '%'],]) ->get();
  		return $style_lists;
  	}


    //get searched customers for datatable plugin format
    private function datatable_search($data)
    {
      /*$start = $data['start'];
      $length = $data['length'];
      $draw = $data['draw'];
      $search = $data['search']['value'];
      $order_details = $data['order'][0];
      $order_details_column = $data['columns'][$order_details['column']]['data'];
      $order_details_type = $order_details['dir'];

      $customer_list = Customer::select('*')
      ->where('customer_code'  , 'like', $search.'%' )
      ->orWhere('customer_name'  , 'like', $search.'%' )
      ->orWhere('customer_short_name'  , 'like', $search.'%' )
      ->orderBy($order_details_column, $order_details_type)
      ->offset($start)->limit($length)->get();

      $customer_count = Customer::where('customer_code'  , 'like', $search.'%' )
      ->orWhere('customer_name'  , 'like', $search.'%' )
      ->orWhere('customer_short_name'  , 'like', $search.'%' )
      ->count();

      return [
          "draw" => $draw,
          "recordsTotal" => $customer_count,
          "recordsFiltered" => $customer_count,
          "data" => $customer_list
      ];*/
    }


    public function save_line_details(Request $request){
      $lines = $request->lines;
      //dd($lines);
      $formData = $request->formData;
      $po = $formData['po_id'];
      //dd($lines[0]['uom_code']);
      $prl_id = $formData['prl_id'];
      //print_r($lines[0]['bom_id']);
      if($lines != null && sizeof($lines) >= 1){

        for($x = 0 ; $x < sizeof($lines) ; $x++){
        $po_details = new PoOrderDetails();

        $po_details->po_no = $formData['po_number'];
        $po_details->bom_id = $lines[$x]['bom_id'];
        $po_details->shop_order_id = $lines[$x]['shop_order_id'];
        $po_details->shop_order_detail_id = $lines[$x]['shop_order_detail_id'];
        //$po_details->bom_detail_id = $lines[$x]['bom_detail_id'];
        $po_details->line_no = $this->get_next_line_no($po);
        $po_details->item_code = $lines[$x]['master_id'];
        $po_details->style = $lines[$x]['style_id'];
        $po_details->colour = $lines[$x]['color_id'];
        $po_details->size = $lines[$x]['size_id'];
        $po_details->unit_price = $lines[$x]['unit_price'];
        $po_details->uom = $lines[$x]['uom_id'];
        $po_details->req_qty = $lines[$x]['tra_qty'];
        $po_details->deli_date = $formData['delivery_date'];
        $po_details->tot_qty = $lines[$x]['value_sum'];
        $po_details->remarks = '';
        $po_details->status = '1';
        $po_details->ori_unit_price = $lines[$x]['unit_price'];
        $po_details->base_unit_price = $lines[$x]['unit_price'];
        //$po_details->mat_id = $lines[$x]['mat_id'];
        $po_details->mat_colour = $lines[$x]['mat_colour'];
        $po_details->po_status = 'PLANNED';
        $po_details->po_header_id = $formData['po_id'];
        $po_details->purchase_price = $lines[$x]['sumunit_price'];
        //$po_details->purchase_uom =  if($lines[$x]['purchase_uom'] != null){$lines[$x]['purchase_uom'];}else{$lines[$x]['uom_code'];};
        $po_details->rm_in_date = $lines[$x]['rm_in_date'];
        $po_details->rm_revised_in_date = $lines[$x]['pcd'];

        if ($lines[$x]['purchase_uom'] != null) {
            $po_details->purchase_uom = $lines[$x]['purchase_uom'];
        }else{
            $po_details->purchase_uom = $lines[$x]['uom_code'];
        }
        $po_details->original_req_qty = $lines[$x]['original_req_qty'];

        $po_details->save();

        DB::table('merc_purchase_req_lines')
            ->where('merge_no', $prl_id)
            ->update(['status_user' => 'RELEASED']);

        $tot_po_qty = 0;
        $find_po_qty = DB::select('SELECT Sum(POD.original_req_qty) AS req_qty FROM merc_po_order_details AS POD
                       WHERE POD.shop_order_detail_id =  "'.$lines[$x]['shop_order_detail_id'].'"
                       AND POD.status = 1 ' );

        $tot_order_qty = $lines[$x]['order_qty'] * $lines[$x]['gross_consumption'];
        $tot_po_qty = $find_po_qty[0]->req_qty;
        $balance_qty = $tot_order_qty - $tot_po_qty;


        DB::table('merc_shop_order_detail')
            ->where('shop_order_detail_id', $lines[$x]['shop_order_detail_id'])
            ->update([ 'po_qty' => $tot_po_qty ,
                       'po_balance_qty' => $balance_qty ]);

        }

        DB::table('merc_purchase_req_lines')
            ->join('merc_shop_order_detail', 'merc_shop_order_detail.shop_order_detail_id', '=', 'merc_purchase_req_lines.shop_order_detail_id')
            ->where('merge_no', $prl_id)
            ->update(['po_status' => null]);

        return response([
          'data' => [
            'status' => 'success',
            'message' => 'Purchase Order Saved Successfully.'
          ]
        ] , 200);

      }

    }

    public function update_line_details(Request $request){
      $lines = $request->lines;
      $formData = $request->formData;
      //dd($lines);
      $po = $formData['po_id'];

      $is_exsits = PoOrderDetails::where([['po_status', '=', 'PLANNED'],['po_header_id','=',$po]])->count();

      //dd($is_exsits);
      if($is_exsits == 0){
        return response([
          'data' => [
            'status' => 'error',
            'message' => "Purchase Order Already Created."
          ]
        ] , 200);
      }else{

      if($lines != null && sizeof($lines) >= 1){

        for($x = 0 ; $x < sizeof($lines) ; $x++){

          $uom            = $lines[$x]['uom_code'];
          $purchase_uom   = $lines[$x]['pur_uom_code'];
          $fractions      = $lines[$x]['fractions'];
          $present_factor = $lines[$x]['present_factor'];
          $gsm            = $lines[$x]['gsm'];
          $width          = $lines[$x]['width'];
          $if_inch        = $lines[$x]['for_calculation'];
          $category_name  = $lines[$x]['category_name'];

          // if($category_name == "FABRIC" && $uom != $purchase_uom){
          //   if($fractions == "D"){
          //     $convert =  floatval($present_factor)/(floatval($gsm)*floatval($width)*floatval($if_inch));
          //   }else if($fractions == "N"){
          //     $convert =  (floatval($gsm)*floatval($width)*floatval($if_inch))/floatval($present_factor);
          //   }else if($fractions == null || $fractions == ''){
          //     $convert =  floatval($present_factor);
          //   }
          // }else{
          //     $convert = 1;
          // }


          if($category_name == "FABRIC" && $uom != $purchase_uom){
            if($fractions == "D"){
              $find_fac = DB::select('SELECT conversion_factor.present_factor FROM conversion_factor
                                       WHERE conversion_factor.unit_code =  "'.$purchase_uom.'"
                                       AND conversion_factor.base_unit =  "'.$uom .'" ' );
              $present_factor = floatval($find_fac[0]->present_factor);
              //$convert =  floatval($present_factor)/(floatval($gsm)*floatval($width)*floatval($if_inch));
              $convert =  (floatval($gsm)*floatval($width)*floatval($if_inch))/floatval($present_factor);
            }else if($fractions == "N"){
              $find_fac = DB::select('SELECT conversion_factor.present_factor FROM conversion_factor
                                       WHERE conversion_factor.unit_code =  "'.$purchase_uom.'"
                                       AND conversion_factor.base_unit =  "'.$uom .'" ' );
              $present_factor = floatval($find_fac[0]->present_factor);
              //$convert =  (floatval($gsm)*floatval($width)*floatval($if_inch))/floatval($present_factor);
              $convert =  floatval($present_factor)/(floatval($gsm)*floatval($width)*floatval($if_inch));
            }else if($fractions == null || $fractions == ''){
            //  $convert =  floatval($present_factor);
              $find_fac = DB::select('SELECT conversion_factor.present_factor FROM conversion_factor
                                       WHERE conversion_factor.unit_code =  "'.$uom.'"
                                       AND conversion_factor.base_unit =  "'.$purchase_uom .'" ' );
              $present_factor = floatval($find_fac[0]->present_factor);
              $convert =  floatval($present_factor);
            }
          }else{
              $convert = 1;
          }

        //  dd($convert);

          DB::table('merc_po_order_details')
            ->where('po_header_id', $formData['po_id'])
            ->where('bom_id', $lines[$x]['bom_id'])
            ->where('shop_order_detail_id', $lines[$x]['shop_order_detail_id'])
            ->where('line_no', $lines[$x]['line_no'])
            ->update(['req_qty' => $lines[$x]['tra_qty'],
                      'tot_qty' => $lines[$x]['value_sum'],
                      'base_unit_price' => $lines[$x]['base_unit_price_revise'],
                      'original_req_qty' => $lines[$x]['tra_qty'] * $convert,
                      'po_status' => 'PLANNED']);

            $tot_po_qty = 0;
            $find_po_qty = DB::select('SELECT Sum(POD.original_req_qty) AS req_qty FROM merc_po_order_details AS POD
                                     WHERE POD.shop_order_detail_id =  "'.$lines[$x]['shop_order_detail_id'].'"
                                     AND POD.status = 1 ' );

            $tot_order_qty = $lines[$x]['order_qty'] * $lines[$x]['gross_consumption'];
            $tot_po_qty = $find_po_qty[0]->req_qty;
            $balance_qty = $tot_order_qty - $tot_po_qty;

            DB::table('merc_shop_order_detail')
              ->where('shop_order_detail_id', $lines[$x]['shop_order_detail_id'])
              ->update([ 'po_qty' => $tot_po_qty ,
                         'po_balance_qty' => $balance_qty ]);

        }

        return response([
          'data' => [
            'status' => 'success',
            'message' => 'Purchase Order Updated Successfully.'
          ]
        ] , 200);

      }
    }

    }

    public function send_to_approval(Request $request){


      $lines = $request->lines;
      $formData = $request->formData;

      $POA = new PoOrderApproval();
      $POA->po_id      = $formData['po_id'];
      $POA->po_header  = json_encode($formData);
      $POA->po_details = json_encode($lines);
      $POA->save();

      //dd();
      $approval = new Approval();
      $approval->start('PO', $POA['email_id'], $POA['created_by']);//start po approval process

      DB::table('merc_po_order_header')
          ->where('po_id', $formData['po_id'])
          ->update([ 'approval_status' => 'PENDING' ,
                     'approval_no' => $POA['email_id'] ]);

    }


    public function close_line_details(Request $request){

      $line_id = $request->line_id;
      $check_grn = GrnHeader::select('*')->where('po_number' , '=', $line_id )->where('status' , '<>', 0 )->get();
      if(sizeof($check_grn) > 0)
      {
        return response([ 'data' => ['status' => 'error','message' => 'Purchase Order Line already in use']]);
      }

      DB::table('merc_po_order_details')->where('id', $line_id)->update([ 'po_status' => 'CLOSED' ]);

        return response([ 'data' => [ 'status' => 'success', 'message' => 'Purchase Order Closed Successfully.' ] ] , 200);

    }

    public function save_line_details_revision(Request $request){
      $lines = $request->lines;
      $formData = $request->formData;
      //print_r($formData);
      $po = $formData['po_number'];
      $po_id = $formData['po_id'];

      //dd($lines);

      $check = PoOrderHeader::select('*')->where('po_id' , '=', $po_id )->where('status' , '<>', 0 )->get();
      if($check[0]['po_status'] == "CONFIRMED")
      {
        return response([ 'data' => ['status' => 'error','message' => 'Purchase Order Line already CONFIRMED']]);
      }

      $check_grn = GrnHeader::select('*')->where('po_number' , '=', $po_id )->where('status' , '<>', 0 )->get();
      if(sizeof($check_grn) > 0)
      {
        return response([ 'data' => ['status' => 'error','message' => 'Purchase Order Line already in use']]);
      }

      $POH = DB::select("SELECT * FROM merc_po_order_header  AS MPOH WHERE MPOH.po_id = '$po_id'");
      $POD = DB::select("SELECT * FROM merc_po_order_details AS MPOD WHERE MPOD.po_header_id = '$po_id'");

      $max_number_D = $this->get_max_version($po_id);

      if($lines != null && sizeof($lines) >= 1){

      $POH_R = new PoOrderHeaderRevision();
      $POH_R->po_type = $POH[0]->po_type;
      $POH_R->po_number = $POH[0]->po_number;
      $POH_R->po_sup_code = $POH[0]->po_sup_code;
      $POH_R->po_deli_loc = $POH[0]->po_deli_loc;
      $POH_R->po_def_cur = $POH[0]->po_def_cur;
      $POH_R->order_type = $POH[0]->order_type;
      $POH_R->po_status = $POH[0]->po_status;
      $POH_R->po_com_loc = $POH[0]->po_com_loc;
      $POH_R->delivery_date = $POH[0]->delivery_date;
      $POH_R->invoice_to = $POH[0]->invoice_to;
      $POH_R->pay_mode = $POH[0]->pay_mode;
      $POH_R->pay_term = $POH[0]->pay_term;
      $POH_R->ship_mode = $POH[0]->ship_mode;
      $POH_R->po_date = $POH[0]->po_date;
      $POH_R->prl_id = $POH[0]->prl_id;
      $POH_R->loc_id = $POH[0]->loc_id;
      $POH_R->cur_value = $POH[0]->cur_value;
      $POH_R->ship_term = $POH[0]->ship_term;
      $POH_R->special_ins = $POH[0]->special_ins;
      $POH_R->user_loc_id = $POH[0]->user_loc_id;
      $POH_R->status = '0';
      $POH_R->version = $max_number_D;
      $POH_R->reason = 'PO_UPDATE';
      $POH_R->save();


      for($x = 0 ; $x < sizeof($POD) ; $x++){

      $POD_R = new PoOrderDetailsRevision();
      $POD_R->po_no = $POD[$x]->po_no;
      $POD_R->bom_id = $POD[$x]->bom_id;
      $POD_R->shop_order_id = $POD[$x]->shop_order_id;
      $POD_R->shop_order_detail_id = $POD[$x]->shop_order_detail_id;
      $POD_R->line_no = $POD[$x]->line_no;
      $POD_R->item_code = $POD[$x]->item_code;
      $POD_R->style = $POD[$x]->style;
      $POD_R->colour = $POD[$x]->colour;
      $POD_R->mat_colour = $POD[$x]->mat_colour;
      $POD_R->size = $POD[$x]->size;
      $POD_R->ori_unit_price = $POD[$x]->ori_unit_price;
      $POD_R->base_unit_price = $POD[$x]->base_unit_price;
      $POD_R->unit_price = $POD[$x]->unit_price;
      $POD_R->uom = $POD[$x]->uom;
      $POD_R->req_qty = $POD[$x]->req_qty;
      $POD_R->deli_date = $POD[$x]->deli_date;
      $POD_R->tot_qty = $POD[$x]->tot_qty;
      $POD_R->remarks = $POD[$x]->remarks;
      $POD_R->user_loc_id = $POD[$x]->user_loc_id;
      $POD_R->po_header_id = $POD[$x]->po_header_id;
      $POD_R->status = '0';
      $POD_R->po_status = $POD[$x]->po_status;
      $POD_R->version = $max_number_D;
      $POD_R->reason = 'PO_UPDATE';
      $POD_R->purchase_uom = $POD[$x]->purchase_uom;
      $POD_R->rm_in_date = $POD[$x]->rm_in_date;
      $POD_R->rm_revised_in_date = $POD[$x]->rm_revised_in_date;

      $POD_R->save();

      }

      for($y = 0 ; $y < sizeof($lines) ; $y++){




        $uom            = $lines[$y]['uom_code'];
        $purchase_uom   = $lines[$y]['pur_uom_code'];
        $fractions      = $lines[$y]['fractions'];
        $present_factor = $lines[$y]['present_factor'];
        $gsm            = $lines[$y]['gsm'];
        $width          = $lines[$y]['width'];
        $if_inch        = $lines[$y]['for_calculation'];
        $category_name  = $lines[$y]['category_name'];


        if($category_name == "FABRIC" && $uom != $purchase_uom){
          if($fractions == "D"){
            $find_fac = DB::select('SELECT conversion_factor.present_factor FROM conversion_factor
                                     WHERE conversion_factor.unit_code =  "'.$purchase_uom.'"
                                     AND conversion_factor.base_unit =  "'.$uom .'" ' );
            $present_factor = floatval($find_fac[0]->present_factor);
            //$convert =  floatval($present_factor)/(floatval($gsm)*floatval($width)*floatval($if_inch));
            $convert =  (floatval($gsm)*floatval($width)*floatval($if_inch))/floatval($present_factor);
          }else if($fractions == "N"){
            $find_fac = DB::select('SELECT conversion_factor.present_factor FROM conversion_factor
                                     WHERE conversion_factor.unit_code =  "'.$purchase_uom.'"
                                     AND conversion_factor.base_unit =  "'.$uom .'" ' );
            $present_factor = floatval($find_fac[0]->present_factor);
            //$convert =  (floatval($gsm)*floatval($width)*floatval($if_inch))/floatval($present_factor);
            $convert =  floatval($present_factor)/(floatval($gsm)*floatval($width)*floatval($if_inch));
          }else if($fractions == null || $fractions == ''){
          //  $convert =  floatval($present_factor);
            $find_fac = DB::select('SELECT conversion_factor.present_factor FROM conversion_factor
                                     WHERE conversion_factor.unit_code =  "'.$uom.'"
                                     AND conversion_factor.base_unit =  "'.$purchase_uom .'" ' );
            $present_factor = floatval($find_fac[0]->present_factor);
            $convert =  floatval($present_factor);
          }
        }else{
            $convert = 1;
        }

        //dd($convert);

        // $tot_po_qty = 0;
        // $find_po_qty = DB::select('SELECT Sum(POD.req_qty) AS req_qty FROM merc_po_order_details AS POD
        //                WHERE POD.shop_order_detail_id =  "'.$lines[$y]['shop_order_detail_id'].'"
        //                AND POD.status = 1 ' );
        //
        // $tot_order_qty = $lines[$y]['order_qty'] * $lines[$y]['gross_consumption'];
        // $tot_po_qty = $find_po_qty[0]->req_qty;
        // $balance_qty = $tot_order_qty - $tot_po_qty;


        if($lines[$y]['po_status'] != 'CLOSED'){

          $po_details = PoOrderDetails::find($lines[$y]['id']);
          $po_details->base_unit_price = $lines[$y]['base_unit_price_revise'];
          $po_details->unit_price = $lines[$y]['unit_price'];
          $po_details->req_qty = $lines[$y]['tra_qty'];
          $po_details->tot_qty = $lines[$y]['value_sum'];
          $po_details->original_req_qty = $lines[$y]['tra_qty'] * $convert;
          $po_details->save();

          $tot_po_qty = 0;
          $find_po_qty = DB::select('SELECT Sum(POD.original_req_qty) AS req_qty FROM merc_po_order_details AS POD
                                   WHERE POD.shop_order_detail_id =  "'.$lines[$y]['shop_order_detail_id'].'"
                                   AND POD.status = 1 ' );

          $tot_order_qty = $lines[$y]['order_qty'] * $lines[$y]['gross_consumption'];
          $tot_po_qty = $find_po_qty[0]->req_qty;
          $balance_qty = $tot_order_qty - $tot_po_qty;


          DB::table('merc_shop_order_detail')
              ->where('shop_order_detail_id', $lines[$y]['shop_order_detail_id'])
              ->update([ 'po_qty' => $tot_po_qty ,
                         'po_balance_qty' => $balance_qty ]);


        }



      }

      $po_header = PoOrderHeader::find($formData['po_id']);
      $po_header->delivery_date = $formData['delivery_date'];
      $po_header->po_deli_loc = $formData['deliverto']['loc_id'];
      $po_header->invoice_to = $formData['invoiceto']['company_id'];
      $po_header->special_ins = $formData['special_ins'];
      $po_header->pay_mode = $formData['pay_mode'];
      $po_header->pay_term = $formData['pay_term'];
      $po_header->ship_mode = $formData['ship_mode']['ship_mode'];
      $po_header->ship_term = $formData['ship_term'];
      $po_header->save();


      if($formData['po_status'] == "CONFIRMED")
      {



      }


      return response([
              'data' => [
              'status' => 'success',
              'message' => 'Purchase Order Saved Successfully.'
          ]
         ] , 200);
    }


      //   print_r($lines[0]['bom_id']);
      //   if($lines != null && sizeof($lines) >= 1){
      //
      //   for($x = 0 ; $x < sizeof($lines) ; $x++){
      //   $po_details = new PoOrderDetails();
      //
      //   $po_details->po_no = $formData['po_number'];
      //   $po_details->sc_no = $lines[$x]['bom_id'];
      //   $po_details->line_no = $this->get_next_line_no($po);
      //   $po_details->item_code = $lines[$x]['master_id'];
      //   $po_details->style = $lines[$x]['master_id'];
      //   $po_details->colour = $lines[$x]['color_id'];
      //   $po_details->size = $lines[$x]['size_id'];
      //   $po_details->unit_price = $lines[$x]['sumunit_price'];
      //   $po_details->uom = $lines[$x]['uom_id'];
      //   $po_details->req_qty = $lines[$x]['tra_qty'];
      //   $po_details->deli_date = $formData['delivery_date'];
      //   $po_details->tot_qty = $lines[$x]['value_sum'];
      //   $po_details->remarks = '';
      //   $po_details->status = '1';
      //   $po_details->base_unit_price = $lines[$x]['unit_price'];
      //
      //
      //   $po_details->save();
      //
      //   }
      //
      //   return response([
      //     'data' => [
      //       'status' => 'success',
      //       'message' => 'Saved successfully.'
      //     ]
      //   ] , 200);
      //
      // }

    }


    public function load_po_history(Request $request){
      //dd($request->formData['po_number']);

      $load_history = PoOrderDetailsRevision::select('*',DB::raw("DATE_FORMAT(merc_po_order_details_revision.rm_in_date, '%d-%b-%Y') AS rm_in_date"),
      DB::raw("DATE_FORMAT(merc_po_order_details_revision.rm_revised_in_date, '%d-%b-%Y') AS rm_revised_in_date"))
                  ->join('item_master', 'item_master.master_id', '=', 'merc_po_order_details_revision.item_code')
                  ->join('item_category', 'item_master.category_id', '=', 'item_category.category_id')
                   ->where('merc_po_order_details_revision.po_no', '=', $request->formData['po_number'])
                   ->where('merc_po_order_details_revision.po_status', '=','CANCELLED')
                   ->get();

      $arr['load_history'] = $load_history;

      if($arr == null)
          throw new ModelNotFoundException("Requested section not found", 1);
      else
          return response([ 'data' => $arr ]);

      //dd($arr['load_history'] );

    }


    public function change_load_methods(Request $request){

    //  dd($request->formData['supplier']['supplier_id']);

      $LOAD_CUR= DB::select('SELECT PM.payment_method_id,PM.payment_method_description,
            SUP.payemnt_terms,FPT.payment_description,PS.ship_term_id,PS.ship_term_description
            FROM org_supplier AS SUP
            INNER JOIN fin_payment_method AS PM ON SUP.payment_mode = PM.payment_method_id
            INNER JOIN fin_payment_term AS FPT ON SUP.payemnt_terms = FPT.payment_term_id
            INNER JOIN fin_shipment_term AS PS ON SUP.ship_terms_agreed = PS.ship_term_id
            WHERE SUP.supplier_id = "'.$request->formData['supplier']['supplier_id'].'" ');

      $LOAD_CUR2= DB::select('SELECT fin_currency.currency_code,fin_currency.currency_id FROM fin_currency
            INNER JOIN org_exchange_rate ON fin_currency.currency_id = org_exchange_rate.currency
            WHERE fin_currency.`status` <> 0');

      $arr['load_cur']=$LOAD_CUR;
      $arr['load_cur_2']=$LOAD_CUR2;


      if($arr == null)
       throw new ModelNotFoundException("Requested section not found", 1);
       else
       return response([ 'data' => $arr ]);

    }

    public function prl_header_load(Request $request){
      $order_id = $request->PORID;

      $LOAD_SUP= DB::select('SELECT PRL.supplier_id,OS.supplier_name,OS.currency,OS.payment_mode,OS.payemnt_terms,
            OS.ship_terms_agreed FROM merc_purchase_req_lines AS PRL
            INNER JOIN org_supplier AS OS ON PRL.supplier_id = OS.supplier_id WHERE PRL.merge_no = "'.$order_id.'"
            GROUP BY PRL.merge_no');
      if( $LOAD_SUP != null ){
        $po_sup_code = $LOAD_SUP[0]->supplier_id;
      }else{
        $po_sup_code = 0;
      }

      $de = Supplier::where('supplier_id', '=', $po_sup_code)->first();
      //dd($order_id);

      if($de->currency == '999'){

        $LOAD_CUR= DB::select('SELECT fin_currency.currency_id,fin_currency.currency_code FROM fin_currency Where status <> 0');
        $LOAD_CUR_D = 'ALL';

      }else{

        $LOAD_CUR= DB::select('SELECT SUP.currency as currency_id,CUR.currency_code,PM.payment_method_id,PM.payment_method_description,
              SUP.payemnt_terms,FPT.payment_description,PS.ship_term_id,PS.ship_term_description
              FROM org_supplier AS SUP
              INNER JOIN fin_currency AS CUR ON SUP.currency = CUR.currency_id
              INNER JOIN fin_payment_method AS PM ON SUP.payment_mode = PM.payment_method_id
              INNER JOIN fin_payment_term AS FPT ON SUP.payemnt_terms = FPT.payment_term_id
              INNER JOIN fin_shipment_term AS PS ON SUP.ship_terms_agreed = PS.ship_term_id
              WHERE SUP.supplier_id = "'.$po_sup_code.'" ');

        $LOAD_CUR_D = 'ONE';
      }



      $PO_NUM= DB::select('SELECT MPOH.po_number,MPOH.po_id FROM merc_po_order_header AS MPOH
            WHERE MPOH.prl_id = "'.$order_id.'"');

      $LOAD_STAGE= DB::select('SELECT MBS.bom_stage_id,MBS.bom_stage_description,item_master.category_id  FROM merc_purchase_req_lines AS MPRL
        INNER JOIN merc_bom_stage AS MBS ON MPRL.bom_stage_id = MBS.bom_stage_id
        INNER JOIN item_master ON MPRL.item_code = item_master.master_id
            WHERE MPRL.merge_no = "'.$order_id.'" GROUP BY MPRL.merge_no ');

      $LOAD_SHIP= DB::select('SELECT MPRL.ship_mode FROM merc_purchase_req_lines AS MPRL
            WHERE MPRL.merge_no = "'.$order_id.'" GROUP BY MPRL.merge_no');

      $LOAD_DEL_LOC= DB::select('SELECT MPRL.delivery_loc as loc_id,LOC.loc_name FROM merc_purchase_req_lines AS MPRL
            INNER JOIN org_location AS LOC ON MPRL.delivery_loc= LOC.loc_id
            WHERE MPRL.merge_no = "'.$order_id.'" GROUP BY MPRL.merge_no');

      $LOAD_DEL_COM= DB::select('SELECT LOC.company_id,COM.company_name FROM merc_purchase_req_lines AS MPRL
            INNER JOIN org_location AS LOC ON MPRL.delivery_loc = LOC.loc_id
            INNER JOIN org_company AS COM ON LOC.company_id = COM.company_id
            WHERE MPRL.merge_no = "'.$order_id.'" GROUP BY MPRL.merge_no');

      if( $LOAD_SUP != null ){ $LOAD_SUP=$LOAD_SUP; }else{$LOAD_SUP=NULL;}
      if( $LOAD_CUR != null ){ $LOAD_CUR=$LOAD_CUR; }else{$LOAD_CUR=NULL;}
      if( $PO_NUM != null ){ $PO_NUM=$PO_NUM; }else{$PO_NUM=NULL;}

      $porl_arr['load_sup']=$LOAD_SUP;
      $porl_arr['load_cur']=$LOAD_CUR;
      $porl_arr['load_cur_check']=$LOAD_CUR_D;
      $porl_arr['po_num']=$PO_NUM;
      $porl_arr['stage']=$LOAD_STAGE;
      $porl_arr['ship']=$LOAD_SHIP;
      $porl_arr['deli_loc']=$LOAD_DEL_LOC;
      $porl_arr['deli_com']=$LOAD_DEL_COM;



      if($porl_arr == null)
          throw new ModelNotFoundException("Requested section not found", 1);
      else
          return response([ 'data' => $porl_arr ]);

    }

    public function load_po_revision_header(Request $request){

      $order_id = $request->POID;
      $order_details = DB::select("SELECT MH.*,org_supplier.supplier_name,fin_currency.currency_code,
        merc_bom_stage.bom_stage_description,
        DATE_FORMAT(MH.delivery_date, '%d-%b-%Y') as deli_date2
        FROM merc_po_order_header AS MH
        INNER JOIN org_supplier ON MH.po_sup_code = org_supplier.supplier_id
        INNER JOIN fin_currency ON MH.po_def_cur = fin_currency.currency_id
        INNER JOIN merc_bom_stage ON MH.po_type = merc_bom_stage.bom_stage_id
        WHERE MH.po_id = '.$order_id.' ");

      $po_sup_code = $order_details[0]->po_sup_code;

      $deli_loc = DB::select('SELECT OL.loc_id,OL.loc_name FROM merc_po_order_header AS MH
        INNER JOIN org_location AS OL ON MH.po_deli_loc = OL.loc_id
        WHERE MH.po_id = "'.$order_id.'"');

      $inv_loc = DB::select('SELECT OC.company_id,OC.company_name FROM merc_po_order_header AS MH
        INNER JOIN org_company AS OC ON MH.invoice_to = OC.company_id
        WHERE MH.po_id = "'.$order_id.'"');

      $ship_mode = DB::select('SELECT org_ship_mode.ship_mode FROM merc_po_order_header AS MH
        INNER JOIN org_ship_mode ON MH.ship_mode = org_ship_mode.ship_mode
        WHERE MH.po_id = "'.$order_id.'"');

      $pay_mode = DB::select('SELECT OS.payment_mode,PM.payment_method_description FROM merc_po_order_header AS POH
        INNER JOIN org_supplier AS OS ON POH.po_sup_code = OS.supplier_id
        INNER JOIN fin_payment_method AS PM ON OS.payment_mode = PM.payment_method_id
        WHERE POH.po_id = "'.$order_id.'" AND OS.supplier_id = "'.$po_sup_code.'" ');

      $pay_Term = DB::select('SELECT OS.payemnt_terms,PT.payment_description FROM merc_po_order_header AS POH
        INNER JOIN org_supplier AS OS ON POH.po_sup_code = OS.supplier_id
        INNER JOIN fin_payment_term AS PT ON OS.payemnt_terms = PT.payment_term_id
        WHERE POH.po_id = "'.$order_id.'" AND OS.supplier_id = "'.$po_sup_code.'" ');

      $ship_Term = DB::select('SELECT OS.ship_terms_agreed,ST.ship_term_description FROM merc_po_order_header AS POH
        INNER JOIN org_supplier AS OS ON POH.po_sup_code = OS.supplier_id
        INNER JOIN fin_shipment_term AS ST ON OS.ship_terms_agreed = ST.ship_term_id
        WHERE POH.po_id = "'.$order_id.'" AND OS.supplier_id = "'.$po_sup_code.'" ');

      $por_arr['order_details']=$order_details;
      $por_arr['deli_loc']=$deli_loc;
      $por_arr['inv_loc']=$inv_loc;
      $por_arr['ship_mode']=$ship_mode;
      $por_arr['pay_mode']=$pay_mode;
      $por_arr['pay_term']=$pay_Term;
      $por_arr['ship_term']=$ship_Term;

      if($por_arr == null)
          throw new ModelNotFoundException("Requested section not found", 1);
      else
          return response([ 'data' => $por_arr ]);

      //return $order_details;
    }


    public function load_por_line(Request $request)
    {
      $prl_id = $request->prl_id;
      $po_number = $request->po_number;
      $po_id = $request->po_id;
      $user = auth()->user();

      $load_list = PoOrderDetails::join('item_master', 'item_master.master_id', '=', 'merc_po_order_details.item_code')
       ->join('merc_shop_order_detail', 'merc_po_order_details.shop_order_detail_id', '=', 'merc_shop_order_detail.shop_order_detail_id')
       ->join('merc_shop_order_header', 'merc_shop_order_detail.shop_order_id', '=', 'merc_shop_order_header.shop_order_id')
       ->join('item_subcategory', 'item_subcategory.subcategory_id', '=', 'item_master.subcategory_id')
       ->join('item_category', 'item_category.category_id', '=', 'item_subcategory.category_id')
       ->join('org_uom', 'org_uom.uom_id', '=', 'merc_po_order_details.uom')
       ->leftjoin('org_size', 'org_size.size_id', '=', 'merc_po_order_details.size')
       ->leftjoin('org_color', 'org_color.color_id', '=', 'merc_po_order_details.colour')
       ->join('merc_po_order_header', 'merc_po_order_header.po_id', '=', 'merc_po_order_details.po_header_id')
       ->join('fin_currency', 'fin_currency.currency_id', '=', 'merc_po_order_header.po_def_cur')
       ->leftjoin('org_uom AS purchase_u', 'purchase_u.uom_id', '=', 'merc_po_order_header.purchase_uom')
       ->leftjoin("conversion_factor",function($join){
         $join->on("conversion_factor.unit_code","=","org_uom.uom_code")
              ->on("conversion_factor.base_unit","=","purchase_u.uom_code");
             })
       ->select('purchase_u.uom_code as pur_uom_code','merc_po_order_header.purchase_uom','fin_currency.currency_code',
       'merc_po_order_header.cur_value','item_category.*','item_master.*','org_uom.*','org_color.*','org_size.*','merc_po_order_details.*',
       'merc_po_order_details.req_qty as tra_qty','merc_po_order_details.tot_qty as value_sum',
       'merc_po_order_details.base_unit_price as base_unit_price_revise',
       'merc_shop_order_header.order_qty','merc_shop_order_detail.gross_consumption','merc_po_order_details.purchase_price',
       'conversion_factor.present_factor','conversion_factor.fractions',

         DB::raw('(CASE WHEN merc_po_order_details.status = 1 THEN "ACTIVE" ELSE "INACTIVE" END) AS polineststus'),
         DB::raw("DATE_FORMAT(merc_po_order_details.rm_in_date, '%d-%b-%Y') AS rm_in_date"),
         DB::raw("DATE_FORMAT(merc_po_order_details.rm_revised_in_date, '%d-%b-%Y') AS rm_revised_in_date"))
       //->where('merc_po_order_details.status'  , '=', 1 )
       ->where('po_id'  , '=', $po_id )
       ->Where('merc_po_order_details.created_by','=', $user->user_id)
       ->get();



       //$count = $load_list->count();
      // for()
      // if($load_list[0]->polineststus == 1)
       //{$load_list[0]['polineststus']='Active';}else{$load_list[0]['polineststus']='Inactive';};

       //return;
       //print_r($load_list[0]->polineststus;);
       //return $customer_list;
       return response([ 'data' => [
         'load_list' => $load_list,
         'prl_id' => $prl_id,
         'count' => sizeof($load_list)
         ]
       ], Response::HTTP_CREATED );

    }


    public function load_reqline_2(Request $request)
    {
      $prl_id = $request->prl_id;
      $user = auth()->user();

      /*$load_list = PoOrderDetails::join("bom_details",function($join){
        $join->on("bom_details.bom_id","=","merc_po_order_details.bom_id")
             ->on("bom_details.id","=","merc_po_order_details.bom_detail_id");
            })
       ->join('bom_header', 'bom_header.bom_id', '=', 'bom_details.bom_id')
       ->join('costing', 'costing.id', '=', 'bom_header.costing_id')
       ->join('item_master', 'item_master.master_id', '=', 'bom_details.master_id')
       ->join('item_subcategory', 'item_subcategory.subcategory_id', '=', 'item_master.subcategory_id')
       ->join('item_category', 'item_category.category_id', '=', 'item_subcategory.category_id')
       ->leftjoin('mat_ratio', 'mat_ratio.id', '=', 'merc_po_order_details.mat_id')
       ->join('org_uom', 'org_uom.uom_id', '=', 'merc_po_order_details.uom')
       ->leftjoin('org_size', 'org_size.size_id', '=', 'merc_po_order_details.size')
       ->leftjoin('org_color', 'org_color.color_id', '=', 'merc_po_order_details.colour')
       ->join('merc_po_order_header', 'merc_po_order_header.po_id', '=', 'merc_po_order_details.po_header_id')
       //->select((DB::raw('round((merc_purchase_req_lines.unit_price * merc_po_order_header.cur_value) * merc_purchase_req_lines.bal_order,2) AS value_sum')),(DB::raw('round(merc_purchase_req_lines.unit_price,2) * round(merc_po_order_header.cur_value,2) as sumunit_price')),'merc_po_order_header.cur_value','item_category.*','item_master.*','org_uom.*','bom_details.*','org_color.*','org_size.*','merc_purchase_req_lines.*','merc_purchase_req_lines.bal_order as tra_qty')
       ->select('merc_po_order_header.cur_value','item_category.*','item_master.*','org_uom.*',
       'bom_details.*','org_color.*','org_size.*','merc_po_order_details.*',
       'merc_po_order_details.req_qty as tra_qty','merc_po_order_details.req_qty as bal_order',
       'merc_po_order_details.req_qty as sumunit_price','merc_po_order_details.base_unit_price as base_unit_price_revise')
       ->where('prl_id'  , '=', $prl_id )
       ->Where('merc_po_order_details.created_by','=', $user->user_id)
       ->get();*/



       $load_list = PoOrderDetails::join('merc_shop_order_detail', 'merc_po_order_details.shop_order_detail_id', '=', 'merc_shop_order_detail.shop_order_detail_id')
        ->join('merc_shop_order_header', 'merc_shop_order_detail.shop_order_id', '=', 'merc_shop_order_header.shop_order_id')
        ->join('item_master', 'item_master.master_id', '=', 'merc_shop_order_detail.inventory_part_id')
        ->join('item_category', 'item_category.category_id', '=', 'item_master.category_id')
        ->join('org_uom', 'org_uom.uom_id', '=', 'merc_po_order_details.uom')
        ->leftjoin('org_size', 'org_size.size_id', '=', 'merc_po_order_details.size')
        ->leftjoin('org_color', 'org_color.color_id', '=', 'merc_po_order_details.mat_colour')
        ->join('merc_po_order_header', 'merc_po_order_header.po_id', '=', 'merc_po_order_details.po_header_id')
        ->join('merc_customer_order_details AS a', 'a.shop_order_id', '=', 'merc_shop_order_detail.shop_order_id')
        ->join('merc_customer_order_header', 'merc_customer_order_header.order_id', '=', 'a.order_id')
        ->leftjoin('org_uom AS purchase_u', 'purchase_u.uom_id', '=', 'merc_po_order_header.purchase_uom')
        ->leftjoin("conversion_factor",function($join){
          $join->on("conversion_factor.unit_code","=","org_uom.uom_code")
               ->on("conversion_factor.base_unit","=","purchase_u.uom_code");
              })
        ->select('purchase_u.uom_code as pur_uom_code','merc_po_order_header.purchase_uom','merc_po_order_header.cur_value','item_category.*','item_master.*','org_uom.*',
        'org_color.*','org_size.*','merc_po_order_details.*',
        'merc_po_order_details.req_qty as tra_qty','merc_po_order_details.req_qty as bal_order',
        'merc_po_order_details.req_qty as sumunit_price','merc_po_order_details.base_unit_price as base_unit_price_revise',
        'merc_shop_order_header.order_qty','merc_shop_order_detail.gross_consumption','merc_po_order_details.purchase_price',
        'conversion_factor.present_factor','conversion_factor.fractions','a.rm_in_date','a.pcd')
        ->where('prl_id'  , '=', $prl_id )
        ->Where('merc_po_order_details.created_by','=', $user->user_id)
        ->whereRaw('a.version_no = (select MAX(b.version_no) from merc_customer_order_details b where b.order_id = a.order_id and a.line_no=b.line_no)')
        ->get();

       //print_r($load_list);
       return response([ 'data' => [
         'load_list' => $load_list,
         'prl_id' => $prl_id,
         'count' => sizeof($load_list)
         ]
       ], Response::HTTP_CREATED );

    }


    public function delete_po_delivery(Request $request){

      //dd($request);
      $updateDetails = ['status' => 0];
      PoOrderDeliverySplit::where('split_id', $request->row)->update($updateDetails);

      return response([
              'data' => [
              'status' => 'success',
              'message' => 'Delivery Split Removed Successfully.'
          ]
         ] , 200);

    }

    public function po_delivery_split(Request $request){

      //dd($request);
      if($request->status == 'SAVE'){

        $formData = $request->formData;
        $po_details_split = new PoOrderDeliverySplit();
        $po_details_split->po_details_id = $formData['po_details_id'];
        $po_details_split->line_no =$formData['line_no'];
        $po_details_split->split_qty = $formData['split_qty'];
        $po_details_split->delivery_date = $formData['delivery_date'];
        $po_details_split->status = 1;
        $po_details_split->save();

        return response([
                'data' => [
                'status' => 'success',
                'message' => 'Delivery Split successfully.'
            ]
           ] , 200);


      }else{
        $formData = $request->formData;
        $updateDetails = ['delivery_date' => $formData['delivery_date'],'split_qty' => $formData['split_qty']];
        PoOrderDeliverySplit::where('po_details_id', $formData['po_details_id'])->where('line_no', $formData['line_no'])->update($updateDetails);

        return response([
                'data' => [
                'status' => 'success',
                'message' => 'Delivery Split Updated Successfully.'
            ]
           ] , 200);



      }


    }


    public function load_po_delivery(Request $request){

    //  dd($request->line_id);

      $delivery = PoOrderDeliverySplit::select(DB::raw("DATE_FORMAT(merc_po_order_split.delivery_date, '%d-%b-%Y') 'delivery_date2'"),'merc_po_order_split.*')
                   ->where('merc_po_order_split.split_id', '=', $request->line_id)
                   ->where('merc_po_order_split.status', '<>', 0)
                   ->get();

      if($delivery == null)
          throw new ModelNotFoundException("Requested section not found", 1);
      else
          return response([ 'data' => $delivery ]);

    }


    public function po_delivery_split_load(Request $request){

        // $delivery = DB::select('SELECT *,DATE_FORMAT(merc_po_order_split.delivery_date, '%d-%b-%Y') as delivery_date,
        //             FROM merc_po_order_split WHERE
        //              merc_po_order_split.po_details_id = "'.$request->line_id.'" ');

        $delivery = PoOrderDeliverySplit::select(DB::raw("DATE_FORMAT(merc_po_order_split.delivery_date, '%d-%b-%Y') 'delivery_date2'"),'merc_po_order_split.*')
                     ->where('merc_po_order_split.po_details_id', '=', $request->line_id)
                     ->where('merc_po_order_split.status', '<>', 0)
                     ->get();

        $delivery_sum = DB::select('SELECT IFNULL(Sum(POS.split_qty),0) AS split_qty_sum FROM merc_po_order_split AS POS
                        WHERE POS.po_details_id = "'.$request->line_id.'" AND POS.status <> 0 ');


        $pors_arr['delivery']=$delivery;
        $pors_arr['delivery_sum']=$delivery_sum;

        if($pors_arr == null)
            throw new ModelNotFoundException("Requested section not found", 1);
        else
            return response([ 'data' => $pors_arr ]);
    }


    public function remove_header(Request $request){
        $data = $request->data;

        $check_grn = DB::table('merc_purchase_req_lines')->select('shop_order_detail_id')->where('merge_no', $data)->get();
        //$count = DB::table('merc_purchase_req_lines')->where('merge_no', $data)->count();
        for($r = 0 ; $r < sizeof($check_grn) ; $r++)
        {
          DB::table('merc_shop_order_detail')
              ->where('shop_order_detail_id', $check_grn[$r]->shop_order_detail_id)
              ->update(['po_status' => null ]);
        }
        //dd($check_grn[0]->shop_order_detail_id);


        DB::table('merc_purchase_req_lines')
            ->where('merge_no', $data)
            ->delete();



            return response([
              'data' => [
                'status' => 'success',
                'message' => 'Purchase Order Confirmed Successfully.'
              ]
            ] , 200);




    }

    public function confirm_po(Request $request){
        $formData = $request->formData;
        //dd($formData);
        $po_id = $formData['po_id'];

        DB::table('merc_po_order_header')
            ->where('po_id', $po_id)
            ->update(['po_status' => 'CONFIRMED']);

        DB::table('merc_po_order_details')
            ->where('po_header_id', $po_id)
            ->where('po_status', '<>', 'CLOSED')
            ->update(['po_status' => 'CONFIRMED']);

            return response([
              'data' => [
                'status' => 'success',
                'message' => 'Purchase Order Confirmed Successfully.'
              ]
            ] , 200);




    }


    public function save_print_status(Request $request){
        $formData = $request->formData;
        $po_id = $formData['po_id'];

        //dd($formData);

        DB::table('merc_po_order_header')
            ->where('po_id', $po_id)
            ->update(['print_status' => '1']);

            return response([
              'data' => [
                'status' => 'success',
                'message' => 'Successfully.'
              ]
            ] , 200);




    }




    private function send_po_confirm_notification($po_id){
      $po_data = DB::table('merc_po_order_header')
      ->join('usr_login', 'usr_login.user_id', '=', 'merc_po_order_header.created_by')
      ->select('merc_po_order_header.*', 'usr_login.user_name')->where('po_id', $po_id)->first();

      $po_lines = DB::table('merc_po_order_details')
      ->join('item_master', 'item_master.master_id', '=', 'merc_po_order_details.item_code')
      ->leftjoin('org_color', 'org_color.color_id', 'merc_po_order_details.colour')
      ->leftjoin('org_uom', 'org_uom.uom_id', 'merc_po_order_details.uom')
      ->where('merc_po_order_details.po_header_id', '=', $po_id)
      ->select('merc_po_order_details.*', 'item_master.master_code', 'item_master.master_description',
      'org_color.color_code', 'org_color.color_name', 'org_uom.uom_code')->get();
//echo json_encode($po_lines);die();
        $to_users = DB::select("SELECT usr_profile.email, '' AS name FROM app_notification_assign
        INNER JOIN usr_profile ON usr_profile.user_id = app_notification_assign.user_id
        WHERE app_notification_assign.type = 'PO CONFIRM'");

        $data = [
          'type' => 'PO_CONFIRM',
          'data' => [
            'po' => $po_data,
            'po_lines' => $po_lines
          ],
          'mail_data' => [
            'subject' => 'Purchase Order Notification',
            'to' => $to_users
          ]
        ];
        $job = new MailSendJob($data);//dispatch mail to the queue
        dispatch($job);
    }


}
