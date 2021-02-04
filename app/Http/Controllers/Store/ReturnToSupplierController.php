<?php

namespace App\Http\Controllers\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Libraries\AppAuthorize;
use App\Libraries\CapitalizeAllFields;
use App\Libraries\UniqueIdGenerator;
use Exception;

use App\Models\stores\RollPlan;
use App\Models\Store\TrimPacking;
use App\Models\stores\RMPlan;
use App\Models\Merchandising\ShopOrderHeader;
use App\Models\Merchandising\ShopOrderDetail;
use App\Models\Store\StockTransaction;
use App\Models\Org\ConversionFactor;
use App\Models\Store\Stock;
use App\Models\Store\StockDetails;

use App\Models\Store\ReturnToSupplierHeader;
use App\Models\Store\ReturnToSupplierDetails;
use App\Models\Store\GrnHeader;
use App\Models\Store\GrnDetail;
//use App\Models\Merchandising\ShopOrderDetail;
use App\Models\Org\UOM;

class ReturnToSupplierController extends Controller
{

    public function index(Request $request)
    {
      $type = $request->type;
      if($type == 'load_details') {
        $data = $request->all();
        $this->load_details($data);
      }else if($type == 'datatable'){
        $data = $request->all();
        $this->datatable_search($data);
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

        $list = ReturnToSupplierHeader::join('store_return_to_supplier_detail','store_return_to_supplier_header.return_id','=','store_return_to_supplier_detail.return_id')
        ->join('store_grn_header','store_return_to_supplier_header.grn_id','=','store_grn_header.grn_id')
        ->join('item_master','store_return_to_supplier_detail.item_id','=','item_master.master_id')
        ->join('usr_login','store_return_to_supplier_header.created_by','=','usr_login.user_id')
        ->select('store_return_to_supplier_header.return_no',DB::raw("DATE_FORMAT(store_return_to_supplier_header.updated_date, '%d-%b-%Y') 'updated_date_'"),
        'store_grn_header.grn_number',
        'store_return_to_supplier_detail.*',
        'item_master.master_code',
        'item_master.master_description',
        'usr_login.user_name')
        ->where('usr_login.user_name' , 'like', $search.'%' )
        ->orWhere('store_grn_header.grn_number' , 'like', $search.'%' )
        ->orWhere('store_return_to_supplier_header.return_no','like',$search.'%')
        ->orWhere('item_master.master_code','like',$search.'%')
        ->orWhere('item_master.master_description','like',$search.'%')
        ->orderBy($order_column, $order_type)
        ->offset($start)->limit($length)->get();

        $count = ReturnToSupplierHeader::join('store_return_to_supplier_detail','store_return_to_supplier_header.return_id','=','store_return_to_supplier_detail.return_id')
        ->join('store_grn_header','store_return_to_supplier_header.grn_id','=','store_grn_header.grn_id')
        ->join('item_master','store_return_to_supplier_detail.item_id','=','item_master.master_id')
        ->join('usr_login','store_return_to_supplier_header.created_by','=','usr_login.user_id')
        ->select('store_return_to_supplier_header.return_no',
        'store_grn_header.grn_number',
        'store_return_to_supplier_detail.*',
        'item_master.master_code',
        'item_master.master_description',
        'usr_login.user_name')
        ->where('usr_login.user_name' , 'like', $search.'%' )
        ->orWhere('store_grn_header.grn_number' , 'like', $search.'%' )
        ->orWhere('store_return_to_supplier_header.return_no','like',$search.'%')
        ->orWhere('item_master.master_code','like',$search.'%')
        ->orWhere('item_master.master_description','like',$search.'%')
        ->count();

        echo json_encode([
          "draw" => $draw,
          "recordsTotal" => $count,
          "recordsFiltered" => $count,
          "data" => $list
        ]);

    }

    public function load_grn_details(Request $request)
    {
      //dd($request);
        $grn_id = $request['search']['grn_no']['grn_id'];
        $roll_from = $request['details']['roll_from'];
        $roll_to = $request['details']['roll_to'];
        $lab_comments = $request['details']['lab_comments'];
      /*  if($lab_comments==null){
          $lab_comments=' ';
        }*/
        $shade = $request['details']['shade'];
        $item_code = $request['details']['item_code']['master_code'];
        $batch = $request['details']['batch']['batch_no'];
        $ins_status_code = $request['details']['ins_status']['status_name'];
        //dd($lab_comments);
        //  dd([$grn_id,$lab_comments,$shade,$item_code,$batch,$ins_status_code]);
$data=DB::table('store_grn_header')
->join('store_grn_detail','store_grn_header.grn_id','=','store_grn_detail.grn_id')
->join('merc_po_order_details','store_grn_detail.po_details_id','=','merc_po_order_details.id')
->join('store_rm_plan','store_grn_detail.grn_detail_id','=','store_rm_plan.grn_detail_id')
->join('store_stock_details','store_rm_plan.rm_plan_id','=','store_stock_details.rm_plan_id')
->join('store_stock','store_stock_details.stock_id','=','store_stock.stock_id')
->join('item_master','store_grn_detail.item_code','=','item_master.master_id')
->join('org_store','store_grn_header.main_store','=','org_store.store_id')
->join('org_location','store_grn_header.location','=','org_location.loc_id')
->join('org_substore','store_grn_header.sub_store','=','org_substore.substore_id')
->join('org_store_bin','store_rm_plan.bin','=','org_store_bin.store_bin_id')
->join('org_uom','store_grn_detail.uom','=','org_uom.uom_id')
->where('store_grn_header.grn_id','=',$grn_id)
->where('store_stock_details.avaliable_qty','>',0)
->select('store_stock_details.*', 'store_rm_plan.*', 'store_grn_header.grn_number',
'org_location.loc_name',
'org_store_bin.store_bin_name',
'org_substore.substore_name',
'org_store_bin.store_bin_name',
'item_master.master_code',
'item_master.master_description',
'org_uom.uom_code',
'store_rm_plan.inspection_status',
'org_store.store_name',
'store_grn_detail.uom AS request_uom',
'store_stock.uom AS inventory_uom',
'merc_po_order_details.purchase_uom',
'store_grn_detail.*');

if($item_code!=null&&$item_code!=""){
  $data=$data->where('item_master.master_code','like','%'.$item_code.'%');
  //$data=
}
if($shade!=null&&$shade!=""){
  $data=$data->where('store_rm_plan.shade','like','%'.$shade.'%');
}
if($batch!=null && $batch!=""){
  $data=$data->where('store_rm_plan.batch_no','like','%'.$batch.'%');
}
if($ins_status_code!=null && $ins_status_code!=""){
    $data=$data->where('store_rm_plan.inspection_status','like','%'.$ins_status_code.'%');
}
//dd($lab_comments);
if($lab_comments!=null){
  //dd($lab_comments);
  $data=$data->where('store_rm_plan.lab_comment', 'like','%'.$lab_comments.'%');
}
if($roll_from!=null&&$roll_to!=null){
    $data=$data->whereBetween('store_rm_plan.roll_or_box_no', [$roll_from,$roll_to]);
}

$data=$data->get();

        echo json_encode([
            "recordsTotal" => "",
            "recordsFiltered" => "",
            "data" => $data
        ]);

    }

    public function load_grn_header(Request $request)
    {
        $grn = GrnHeader::join('org_supplier','store_grn_header.sup_id','=','org_supplier.supplier_id')
        ->join('merc_po_order_header','store_grn_header.po_number','=','merc_po_order_header.po_id')
        ->select('sup_id','supplier_name','inv_number','batch_no','grn_number','merc_po_order_header.po_number')
        ->where('store_grn_header.grn_id',$request->grn_id)
        ->get();

        return [ 'data'=> $grn ];
    }

    public function store(Request $request)
{
    $return_no = UniqueIdGenerator::generateUniqueId('RE_SUP', auth()->payload()['loc_id']);
    $header_data = array(
        "grn_id"=> $request->header['grn_no']['grn_id'],
        "status"=> 1,
        "return_no" => $return_no
    );

    $save_header = new ReturnToSupplierHeader();
    if($save_header->validate($header_data))
    {
      $save_header->fill($header_data);
      $save_header->save();

      if($save_header){

        $save_details = $this->save_return_details($save_header['return_id'],$request['details']);
        //$save_stock_transaction = $this->save_stock_transaction($save_header['return_id'],$request['details']);
        //$update_roll_plan = $this->update_roll_plan($save_header['return_id'],$request['details']);
        //$update_store_stock = $this->update_store_stock($save_header['return_id'],$request['details']);

        return response([ 'data' => [
            'message' => 'Return Qty Saved Successfully',
            'id' => $save_header['return_id'],
            'status'=> 'success'
            ]
        ], Response::HTTP_CREATED );

      }else{

        return response([ 'data' => [
            'message' => 'Data saving failed',
            'id' => '',
            'status'=> 'fail'
            ]
        ], Response::HTTP_CREATED );
      }

    }
    else
    {
      $errors = $save_header->errors();// failure, get errors
      $errors_str = $save_header->errors_tostring();
      return response(['errors' => ['validationErrors' => $errors, 'validationErrorsText' => $errors_str]], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

}



    public function save_return_details($return_id,$data){

        foreach($data as $row){

            if(!isset($row['comments'])){
              $comments = "";
            }else{
              $comments = $row['comments'];
            }

            if($row['inventory_uom']==$row['request_uom']){
            	$inv_qty = $row['return_qty'];
            }else{
            	$inv_qty = $this->convert_into_inventory_uom($row['request_uom'],$row['inventory_uom'],$row['return_qty']);
            }

            $detail_data = array(
            'return_id' => $return_id,
            'item_id' => $row['item_id'],
            'inv_uom' => $row['inventory_uom'],
            'request_uom' => $row['request_uom'],
            'grn_qty' => $row['actual_qty'],
            'return_qty' => $row['return_qty'],
            'return_inv_qty' => $inv_qty,
            'status' => 1,
            'location_id' => $row['location'],
            'store_id' => $row['store_id'],
            'sub_store_id' => $row['substore_id'],
            'bin' => $row['bin'],
            'roll_box' => $row['roll_or_box_no'],
            'batch_no' => $row['batch_no'],
            'shade' => $row['shade'],
            'rm_plan_id' => $row['rm_plan_id'],
            'stock_detail_id'=>$row['stock_detail_id'],
            'comments' => $comments,
            'purchase_price'=>$row['purchase_price'],
            'grn_id'=>$row['grn_id'],
            'grn_detail_id'=>$row['grn_detail_id'],
            'style_id'=>$row['style_id'],
            );
			$save_detail = new ReturnToSupplierDetails();
            //dd($save_detail->validate($detail_data));

            if($save_detail->validate($detail_data))
            {

              $save_detail->fill($detail_data);
              $save_detail->total_value=$row['purchase_price']*$row['return_qty'];
              $save_detail->save();
              $update_store_stock = $this->update_store_stock($return_id,$row,$save_detail);

            }
            else
            {
              $errors = $save_detail->errors();// failure, get errors
              $errors_str = $save_detail->errors_tostring();
              return response(['errors' => ['validationErrors' => $errors, 'validationErrorsText' => $errors_str]], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

        }

    }

    public function save_stock_transaction($return_id,$data,$stock_detail_line,$savedReturnDetailLine){

            if($data['inventory_uom']==$data['request_uom']){
               $qty = $data['return_qty'];
            }else{
               $qty = $this->convert_into_inventory_uom($data['request_uom'],$data['inventory_uom'],$data['return_qty']);
            }

            $year=DB::select("SELECT YEAR(CURDATE())AS current_d");
            $month=DB::select("SELECT MONTHNAME(CURDATE()) as current_month");
            $current_year=$year["0"]->current_d;
            $current_month=$month['0']->current_month;
            $st = new StockTransaction;
            $st->doc_type = 'RETURN_TO_SUPPLIER';
            $st->style_id = $data['style_id'];
            $st->stock_id = $data['stock_id'];
            $st->stock_detail_id = $data['stock_detail_id'];
            $st->doc_header_id=$savedReturnDetailLine->return_id;
            $st->doc_detail_id=$savedReturnDetailLine->return_detail_id;
            $st->size = $data['size'];
            $st->color = $data['color'];
            $st->main_store = $data['store_id'];
            $st->sub_store = $data['substore_id'];
            $st->location = auth()->payload()['loc_id'];
            $st->bin = $data['bin'];
            $st->sup_po_header_id = $data['po_number'];
            $st->sup_po_details_id= $data['po_details_id'];
            $st->shop_order_id = $data['shop_order_id'];
            $st->shop_order_detail_id = $data['shop_order_detail_id'];
            $st->direction = '-';
            $st->status=1;
            $st->item_id = $data['item_code'];
            $st->qty = $qty;
            $st->uom = $data['inventory_uom'];
            $st->standard_price = $data['standard_price'];
            $st->purchase_price = $data['purchase_price'];
            $st->financial_year=$current_year;
            $st->financial_month=$current_month;
            $st->rm_plan_id=$data['rm_plan_id'];
            $st->created_by = auth()->payload()['user_id'];
            $st->save();



            ///////////////////////////////////////////////////////////////////////////////////////////
/*
            $st = new StockTransaction;
            $st->doc_num = $return_id;
            $st->doc_type = 'RETURN_TO_SUPPLIER';
            $st->style_id = $row['style_id'];
            //$st->customer_po_id = $row['customer_po_id'];
            $st->size = $row['size_id'];
            $st->color = $row['color_id'];
            $st->main_store = $row['main_store'];
            $st->sub_store = $row['sub_store'];
            $st->location = $row['location'];
            $st->bin = $row['bin'];
            $st->status = 'CONFIRM';
            $st->shop_order_id = $row['shop_order_id'];
            $st->shop_order_detail_id = $row['shop_order_detail_id'];
            $st->direction = '-';
            $st->item_code = $row['item_code'];
            $st->qty = -$qty;
            $st->uom = $row['inventory_uom'];
            $st->standard_price = $row['standard_price'];
            $st->purchase_price = $row['purchase_price'];
            $st->created_by = auth()->payload()['user_id'];
            $st->save();
*/




    }

    public function update_roll_plan($return_id,$data){

        foreach($data as $row){

            if($row['inventory_uom']==$row['request_uom']){
               $return_qty = $row['return_qty'];
            } else {
               $return_qty = $this->convert_into_inventory_uom($row['request_uom'],$row['inventory_uom'],$row['return_qty']);
            }

            if($row['category_code']=='FAB'){
                $available_qty=RollPlan::where('roll_plan_id','=',$row['item_detail_id'])->pluck('qty');
                $update = RollPlan::where('roll_plan_id', $row['item_detail_id'])->update(['qty' => ($available_qty[0]-$return_qty) ]);
            } else {
               $available_qty=TrimPacking::where('trim_packing_id','=',$row['item_detail_id'])->pluck('qty');
               $update = TrimPacking::where('trim_packing_id', $row['item_detail_id'])->update(['qty' => ($available_qty[0]-$return_qty) ]);
            }

        }

    }

    public function update_store_stock($return_id,$data,$returnDetails){

            if($data['inventory_uom']==$data['request_uom']){
               $return_qty = $data['return_qty'];
            } else {
               $return_qty = $this->convert_into_inventory_uom($data['request_uom'],$data['inventory_uom'],$data['return_qty']);
            }

            $stock_line=Stock::find($data['stock_id']);
            $stock_line->avaliable_qty=$stock_line->avaliable_qty-$return_qty;
            $stock_line->out_qty=$stock_line->out_qty+$return_qty;
            $stock_line->save();

            $stock_detail_line=StockDetails::find($data['stock_detail_id']);
            $stock_detail_line->avaliable_qty=$stock_detail_line->avaliable_qty-$return_qty;
            $stock_detail_line->out_qty=$stock_detail_line->out_qty+$return_qty;
            $stock_detail_line->save();
            if($data['inspection_status']=="PASS"){
            $update_shop_order_detail=ShopOrderDetail::find($data['shop_order_detail_id']);
            $update_shop_order_detail->asign_qty=$update_shop_order_detail->asign_qty-$data['return_qty'];
            $update_shop_order_detail->save();
            }
            $updateStocktransation=$this->save_stock_transaction($return_id,$data,$stock_detail_line,$returnDetails);



    }

    public function convert_into_inventory_uom($request_uom,$inventory_uom,$qty){
        $unit_code=UOM::where('uom_id','=',$inventory_uom)->pluck('uom_code');
        $base_unit_code=UOM::where('uom_id','=',$request_uom)->pluck('uom_code');

        $con=ConversionFactor::select('*')
        ->where('unit_code','=',$unit_code[0])
        ->where('base_unit','=',$base_unit_code[0])
        ->first();

        return ($qty*$con->present_factor);
    }

    public function update_grn_qty($return_id,$data){
        foreach($data as $row){
            $available_qty=GrnDetail::where('grn_detail_id','=',$row['grn_detail_id'])->pluck('grn_qty');
            $update = GrnDetail::where('grn_detail_id', $row['grn_detail_id'])->update(['grn_qty' => ($available_qty[0]-$row['return_qty']) ]);
        }
    }

    public function update_shop_order_qty($return_id,$data){
        foreach($data as $row){
            $available_qty=ShopOrderDetail::where('shop_order_detail_id','=',$row['shop_order_detail_id'])->pluck('asign_qty');
            $update = ShopOrderDetail::where('shop_order_detail_id', $row['shop_order_detail_id'])->update(['asign_qty' => ($available_qty[0]-$row['return_qty']) ]);
        }
    }

}
