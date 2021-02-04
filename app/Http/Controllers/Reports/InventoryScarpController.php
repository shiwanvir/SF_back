<?php

namespace App\Http\Controllers\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Libraries\CapitalizeAllFields;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Org\BlockStatus;
use App\Models\stores\StoreScarpHeader;
use App\Models\stores\StoreScarpDetails;
use App\Models\stores\RollPlan;
use App\Models\Store\TrimPacking;
use App\Models\Store\StockTransaction;
use App\Libraries\UniqueIdGenerator;
use App\Models\Store\Stock;
use App\Models\Store\StockDetails;
use App\Models\Merchandising\ShopOrderDetail;

class InventoryScarpController extends Controller
{

    public function index(Request $request)
    {
      $type = $request->type;
      if($type == 'header') {
        $data = $request->all();
        $this->header_search($data);
      }else if($type == 'datatable'){
        $data = $request->all();
        $this->datatable_search($data);
      }
    }

    public function load_inventory(Request $request)
    {
    //dd("ssd");
        $data = $request->all();
        $from_sub_store = $data['search']['from_sub_store']['substore_id'];
        $to_sub_store = $data['search']['to_sub_store']['substore_id'];
        $category = $data['search']['item_category']['category_id'];
        $code_from = $data['search']['item_code']['master_code'];
        $code_to = $data['search']['item_code_to']['master_code'];
        $paramsArr = $data['options'];
        $store = $data['store'];
        $subStoreArr = array($from_sub_store,$to_sub_store);

        // Block stock related transations
        if(in_array("FPWP", $paramsArr)) {
          //foreach($subStoreArr as $value) {
            $is_exists = BlockStatus::where('store', $store)->exists();
            if($is_exists)
            {
              $update = BlockStatus::where('store', $store)->update(['status' => "BLOCK"]);
            }
            else
            {
              $insert = new BlockStatus();
              $insert->status_description = "BLOCK_STOCK";
              $insert->status = "BLOCK";
              $insert->store = $store;
              $insert->save();
            }
          //}
        }
        // Block stock related transations end

        $fabric = DB::table('store_stock')
        ->join('item_master','store_stock.item_id','=','item_master.master_id')
        ->join('org_store','store_stock.store_id','=','org_store.store_id')
        ->join('org_substore','store_stock.substore_id','=','org_substore.substore_id')
        ->join('org_location','store_stock.location','=','org_location.loc_id')
        ->join('org_uom','store_stock.uom','=','org_uom.uom_id')
        ->join('store_stock_details','store_stock.stock_id','=','store_stock_details.stock_id')
        ->join('store_rm_plan','store_stock_details.rm_plan_id','=','store_rm_plan.rm_plan_id')
        ->join('store_grn_detail','store_rm_plan.grn_detail_id','=','store_grn_detail.grn_detail_id')
        ->join('org_store_bin','store_stock_details.bin','=','org_store_bin.store_bin_id')
        ->join('style_creation','store_stock.style_id','=','style_creation.style_id')
        ->join('item_category','item_master.category_id','=','item_category.category_id')
        ->join('item_subcategory','item_master.subcategory_id','=','item_subcategory.subcategory_id')
        ->join('cust_customer','style_creation.customer_id','=','cust_customer.customer_id')
        ->select('store_stock.stock_id',
          'store_stock.shop_order_id',
          'store_stock.shop_order_detail_id',
          'store_stock.style_id',
          'style_creation.style_no',
          'store_stock.item_id',
          'item_master.master_code',
          'item_master.category_id',
          'item_master.subcategory_id',
          'item_master.master_description',
          'store_stock.location',
          'org_location.loc_code',
          'org_location.loc_name',
          'store_stock.store_id',
          'org_store.store_name',
          'store_stock.substore_id',
          'org_substore.substore_name',
          'store_stock_details.bin',
          'org_store_bin.store_bin_name',
          'store_stock.uom AS inventory_uom',
          'org_uom.uom_code',
          'store_stock_details.stock_detail_id',
          'store_stock_details.rm_plan_id',
          'store_stock_details.parent_rm_plan_id',
          'store_stock.standard_price',
          'store_stock.purchase_price',
          'store_stock_details.avaliable_qty',
          'store_stock_details.out_qty',
          DB::raw("(store_stock.standard_price*store_stock_details.avaliable_qty) AS total_value"),
          'store_stock.size',
          'store_stock.color',
          'store_rm_plan.grn_detail_id',
          'store_rm_plan.lot_no',
          'store_rm_plan.batch_no',
          'store_rm_plan.roll_or_box_no',
          'store_rm_plan.width',
          'store_rm_plan.shade',
          'store_rm_plan.rm_comment',
          'store_rm_plan.barcode',
          'item_category.category_code',
          'item_category.category_name',
          'item_subcategory.subcategory_code',
          'item_subcategory.subcategory_name',
          'style_creation.customer_id',
          'cust_customer.customer_code',
          'cust_customer.customer_name',
          'store_grn_detail.po_number AS po_header_id',
          'store_grn_detail.po_number AS po_detail_id');

        if($category != null){
          $fabric->where('item_master.category_id', $category);
        }
        if($code_from != null && $code_to != null) {
          $fabric->whereBetween('item_master.master_code', [$code_from, $code_to]);
        }
        $fabric->where('store_stock_details.avaliable_qty','>', 0);
        $fabric->whereIn('store_stock.substore_id', $subStoreArr);
        $fabric->orderBy('style_creation.style_no','ASC');
        //$fabric->orderBy('org_location.loc_name','ASC');
        //$fabric->orderBy('org_store.store_name','ASC');
        $fabric->orderBy('org_substore.substore_name','ASC');
        $data = $fabric->get();

        echo json_encode([
          "recordsTotal" => "",
          "recordsFiltered" => "",
          "data" => $data
        ]);

    }

    public function store(Request $request)
    {

      $storeArr = array($request['locData']['loc_store']['store_id'],$request['header']['to_sub_store']['substore_id']);

      $saveHeader = new StoreScarpHeader();
      $saveHeader->scarp_no = "IS".UniqueIdGenerator::generateUniqueId('INVENTORY_SCARP', auth()->payload()['loc_id']);
      $saveHeader->location = $request['locData']['loc_name']['loc_id'];
      $saveHeader->store = $request['locData']['loc_store']['store_id'];
      $saveHeader->from_sub_store = $request['header']['from_sub_store']['substore_id'];
      $saveHeader->to_sub_store = $request['header']['to_sub_store']['substore_id'];
      $saveHeader->status = 1;
      $saveHeader->save();
      $scarp_id = $saveHeader->scarp_id;

      if($saveHeader){

        $scarp = $this->save_scarp_details($request['details'],$scarp_id);
        //$stock_transaction = $this->save_stock_transaction($request['details'],$scarp_id);
        //$bin_balance = $this->update_bin_balance($request['details'],$scarp_id);
        $release_store = $this->release_store_status($storeArr);

        return response([ 'data' => [
          'result' => 'success',
          'message' => 'Data saved successfully',
          'aaaaaa' => $scarp
         ]
        ], Response::HTTP_CREATED );

      } else {

        $errors = $saveHeader->errors();
        return response([ 'data' => [
            'result' => $errors,
            'message' => 'Data save fail'
          ]
        ], Response::HTTP_CREATED );

      }

    }

    public function save_scarp_details($scarpData,$scarp_id){

        foreach($scarpData as $row){

            if($row['scarp_qty']!="" || $row['scarp_qty']!=null || $row['scarp_qty']!=0){

                if(!isset($row['comments'])){
                  $comments = "";
                }else{
                  $comments = $row['comments'];
                }

                $detail_data = array(
                'scarp_id' => $scarp_id,
                'style' => $row['style_id'],
                'shop_order_id' => $row['shop_order_id'],
                'shop_order_detail_id' => $row['shop_order_detail_id'],
                'grn_detail_id' => $row['grn_detail_id'],
                'item_id' => $row['item_id'],
                'inventory_uom' => $row['inventory_uom'],
                'bin_no' => $row['bin'],
                'roll_box_no' => $row['roll_or_box_no'],
                'batch' => $row['batch_no'],
                'shade' => $row['shade'],
                'inv_qty' => $row['avaliable_qty'],
                'scarp_qty' => $row['scarp_qty'],
                'standard_price' => $row['standard_price'],
                'purchase_price' => $row['purchase_price'],
                'rm_plan_id' => $row['rm_plan_id'],
                'comments' => $comments,
                'status' => 1
                );

            }
          //  dd("sdad");
            $save_detail = new StoreScarpDetails();
            if($save_detail->validate($detail_data))
            {
              $save_detail->fill($detail_data);
              $save_detail->save();
              $update_store_stock = $this->update_store_stock($scarp_id,$row,$detail_data);
            }
            else
            {
              $errors = $save_detail->errors();// failure, get errors
              $errors_str = $save_detail->errors_tostring();
              return response(['errors' => ['validationErrors' => $errors, 'validationErrorsText' => $errors_str]], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

        }

    }

    public function update_store_stock($scarp_id,$data){

        $stock_available_qty = Stock::where('stock_id','=',$data['stock_id'])->pluck('avaliable_qty');
        $stock_out_qty = Stock::where('stock_id','=',$data['stock_id'])->pluck('out_qty');
        $stock_header = Stock::find($data['stock_id']);
        $stock_header->avaliable_qty = $stock_available_qty[0]-$data['scarp_qty'];
        $stock_header->out_qty = $stock_out_qty[0]+$data['scarp_qty'];
        $stock_header->save();

        $stock_detail = StockDetails::find($data['stock_detail_id']);
        $stock_detail->avaliable_qty = $data['avaliable_qty']-$data['scarp_qty'];
        $stock_detail->out_qty = $data['out_qty']+$data['scarp_qty'];
        $stock_detail->save();

        $asign_qty = ShopOrderDetail::where('shop_order_detail_id','=',$data['shop_order_detail_id'])->pluck('asign_qty');
        $update_shop_order_detail = ShopOrderDetail::find($data['shop_order_detail_id']);
        $update_shop_order_detail->asign_qty = $asign_qty[0]-$data['scarp_qty'];
        $update_shop_order_detail->save();

        $updateStocktransation=$this->save_stock_transaction($scarp_id,$data);
    }

    public function save_stock_transaction($scarp_id,$data){

        $year=DB::select("SELECT YEAR(CURDATE())AS current_d");
        $month=DB::select("SELECT MONTHNAME(CURDATE()) as current_month");
        $current_year=$year["0"]->current_d;
        $current_month=$month['0']->current_month;

        $st = new StockTransaction;
        $st->doc_type = 'SCARP';
        $st->style_id = $data['style_id'];
        $st->stock_id = $data['stock_id'];
        $st->stock_detail_id = $data['stock_detail_id'];
        $st->doc_header_id = $scarp_id;
        $st->doc_detail_id = $data['stock_detail_id'];
        $st->size = $data['size'];
        $st->color = $data['color'];
        $st->main_store = $data['store_id'];
        $st->sub_store = $data['substore_id'];
        $st->location = $data['location'];
        $st->bin = $data['bin'];
        $st->sup_po_header_id = $data['po_header_id'];
        $st->sup_po_details_id = $data['po_detail_id'];
        $st->shop_order_id = $data['shop_order_id'];
        $st->shop_order_detail_id = $data['shop_order_detail_id'];
        $st->direction = '-';
        $st->status = 1;
        $st->item_id = $data['item_id'];
        $st->qty = -$data['scarp_qty'];
        $st->uom = $data['inventory_uom'];
        $st->standard_price = $data['standard_price'];
        $st->purchase_price = $data['purchase_price'];
        $st->financial_year = $current_year;
        $st->financial_month = $current_month;
        $st->rm_plan_id = $data['rm_plan_id'];
        $st->created_by = auth()->payload()['user_id'];
        $st->save();

    }

    public function release_store_status($storeArr){

      foreach($storeArr as $value) {
        $update = BlockStatus::where('store', $value)->update(['status' => "RELEASE"]);
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

        $list = StoreScarpHeader::join('store_inv_scarp_details','store_inv_scarp_header.scarp_id','=','store_inv_scarp_details.scarp_id')
        ->join('org_location','store_inv_scarp_header.location','=','org_location.loc_id')
        ->join('org_store','store_inv_scarp_header.store','=','org_store.store_id')
        ->join('style_creation','store_inv_scarp_details.style','=','style_creation.style_id')
        ->join('item_master','store_inv_scarp_details.item_id','=','item_master.master_id')
        ->join('usr_login','store_inv_scarp_header.created_by','=','usr_login.user_id')
        ->join('org_store_bin','store_inv_scarp_details.bin_no','=','org_store_bin.store_bin_id')
        ->select('store_inv_scarp_header.scarp_id',
        'store_inv_scarp_header.scarp_no',
        'org_location.loc_name',
        'org_store.store_name',
        'store_inv_scarp_details.scarp_detail_id',
        'style_creation.style_no',
        'store_inv_scarp_details.shop_order_id',
        'item_master.master_code',
        'item_master.master_description',
        'store_inv_scarp_details.roll_box_no',
        'store_inv_scarp_details.batch',
        'store_inv_scarp_details.shade',
        'store_inv_scarp_details.scarp_qty',
        'store_inv_scarp_details.standard_price',
        'store_inv_scarp_details.comments',
        'store_inv_scarp_header.updated_by',
        'store_inv_scarp_details.purchase_price',
        'usr_login.user_name',
        'org_store_bin.store_bin_name',
        'store_inv_scarp_header.updated_date',
        'store_inv_scarp_header.status',
        DB::raw("(FORMAT(store_inv_scarp_details.standard_price*store_inv_scarp_details.scarp_qty,4)) AS total_amount"),
        DB::raw("(DATE_FORMAT(store_inv_scarp_header.created_date,'%d-%b-%Y %H:%i:%s')) AS create_date"))
        ->where('store_inv_scarp_header.scarp_no' , 'like', $search.'%' )
        ->orWhere('org_location.loc_name' , 'like', $search.'%' )
        ->orWhere('item_master.master_code' , 'like', $search.'%' )
        ->orWhere('item_master.master_description' , 'like', $search.'%' )
        ->orWhere('usr_login.user_name','like',$search.'%')
        ->orderBy($order_column, $order_type)
        ->offset($start)->limit($length)->get();
        //dd($list);

        $count = StoreScarpHeader::join('store_inv_scarp_details','store_inv_scarp_header.scarp_id','=','store_inv_scarp_details.scarp_id')
        ->join('org_location','store_inv_scarp_header.location','=','org_location.loc_id')
        ->join('org_store','store_inv_scarp_header.store','=','org_store.store_id')
        ->join('style_creation','store_inv_scarp_details.style','=','style_creation.style_id')
        ->join('item_master','store_inv_scarp_details.item_id','=','item_master.master_id')
        ->join('usr_login','store_inv_scarp_header.created_by','=','usr_login.user_id')
        ->join('org_store_bin','store_inv_scarp_details.bin_no','=','org_store_bin.store_bin_id')
        ->select('store_inv_scarp_header.scarp_id','store_inv_scarp_header.scarp_no')
        ->where('store_inv_scarp_header.scarp_no' , 'like', $search.'%' )
        ->orWhere('org_location.loc_name' , 'like', $search.'%' )
        ->orWhere('item_master.master_code' , 'like', $search.'%' )
        ->orWhere('item_master.master_description' , 'like', $search.'%' )
        ->orWhere('usr_login.user_name','like',$search.'%')
        ->count();

        echo json_encode([
          "draw" => $draw,
          "recordsTotal" => $count,
          "recordsFiltered" => $count,
          "data" => $list
        ]);

    }


}
