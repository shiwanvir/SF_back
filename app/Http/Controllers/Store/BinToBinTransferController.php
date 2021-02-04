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

use App\Models\Store\BinTransferheader;
use App\Models\Store\BinTransferDetails;
use App\Models\Store\StoreBin;
use App\Models\Store\GrnDetail;

use App\Models\Store\Stock;
use App\Models\Store\StockDetails;
use App\Models\Store\StockTransaction;
use App\Models\Store\Store;
use App\Models\Store\SubStore;


class BinToBinTransferController extends Controller
{

    public function index(Request $request)
    {
      $type = $request->type;
      if($type == 'load_details') {
        $data = $request->all();
        return $this->load_details($data);
      }else if($type == 'datatable'){
        $data = $request->all();
        return $this->datatable_search($data);
      }
      else if($type=="auto_master_code"){
        $search = $request->search;
        return response($this->autocomplete_search_ins_pass_item($search));
      }
    }



public function autocomplete_search_ins_pass_item($search){
  $inspection_status="PASS";
  $status=1;
  $item_list=GrnDetail::join('store_rm_plan','store_grn_detail.grn_detail_id','=','store_rm_plan.grn_detail_id')
                      ->join('item_master','store_grn_detail.item_code','=','item_master.master_id')
                      ->where('store_rm_plan.inspection_status','=',$inspection_status)
                      ->where('store_rm_plan.status','=',$status)
                      ->where([['item_master.master_code', 'like', '%' . $search . '%'],])
                      ->select('item_master.master_id','item_master.master_code')
                      ->distinct()->get();
                      return $item_list;
}
    public function load_sub_store_bin(Request $request)
    {
        //dd($request);
        $bin_lists = StoreBin::where('store_id',$request['search']['store']['store_id'])
        ->where('store_bin_id','<>',$request['search']['store_bin']['store_bin_id'])
        ->pluck('store_bin_name')
        ->toArray();
        return json_encode([ "data" => $bin_lists ]);
    }

    public function load_bin_items(Request $request)
    {
        $store = $request['search']['store']['store_id'];
        $sub_store = $request['search']['sub_store']['substore_id'];
        $bin = $request['search']['store_bin']['store_bin_id'];
        $item_id = $request['details']['item_code']['master_id'];
        $inspection_status="PASS";
        $query = DB::table('store_stock')
        ->join('store_stock_details','store_stock.stock_id','=','store_stock_details.stock_id')
        ->join('store_rm_plan','store_stock_details.rm_plan_id','=','store_rm_plan.rm_plan_id')
        ->join('org_store_bin','store_stock_details.bin','=','org_store_bin.store_bin_id')
        ->join('item_master','store_stock_details.item_id','=','item_master.master_id')
        ->join('item_category','item_master.category_id','=','item_category.category_id')
        ->join('store_grn_detail','store_rm_plan.grn_detail_id','=','store_grn_detail.grn_detail_id')
        ->join('store_grn_header','store_grn_detail.grn_id','=','store_grn_header.grn_id')
        ->join('org_uom','store_stock.uom','=','org_uom.uom_id')
        ->select('store_stock.stock_id',
        'store_stock.inventory_type',
        'store_stock.shop_order_id',
        'store_stock.shop_order_detail_id',
        'store_stock.style_id',
        'store_stock.item_id',
        'store_stock.size',
        'store_stock.color',
        'store_stock.location',
        'store_stock.store_id as from_store_id',
        'store_stock.substore_id as from_substore_id',
        'store_stock_details.stock_detail_id',
        'store_stock_details.stock_id',
        'store_stock_details.bin as bin_from',
        'store_stock_details.batch_no',
        'store_stock_details.store_id as store_id_from',
        'store_stock_details.substore_id as substore_id_from',
        'store_stock_details.item_id',
        'store_stock_details.rm_plan_id',
        'store_stock_details.barcode',
        'store_stock_details.parent_rm_plan_id',
        'store_stock_details.in_qty',
        'store_stock_details.out_qty',
        'store_stock_details.avaliable_qty',
        'store_stock_details.excess_qty',
        'store_stock_details.created_date',
        'store_stock_details.updated_date',
        'store_stock_details.status',
        'store_stock_details.updated_by',
        'store_stock_details.user_loc_id',
        'store_stock_details.created_by',
        'store_stock_details.inspection_status',
        'store_stock_details.issue_status',
        'org_store_bin.store_bin_name as store_bin_name_from',
        'store_rm_plan.grn_detail_id',
        'store_rm_plan.lot_no',
        'store_rm_plan.batch_no',
        'store_rm_plan.roll_or_box_no',
        'store_rm_plan.received_qty',
        'store_rm_plan.actual_qty',
        'store_rm_plan.width',
        'store_rm_plan.shade',
        'store_rm_plan.rm_comment',
        'store_rm_plan.barcode',
        'store_rm_plan.lab_comment',
        'item_master.category_id',
        'item_master.master_code',
        'item_master.master_description',
        'item_category.category_code',
        'store_grn_header.grn_number',
        'store_grn_detail.color',
        'store_grn_detail.size',
        'store_grn_detail.po_details_id',
        'store_grn_detail.po_number',
        'store_grn_detail.customer_po_id',
        'store_grn_detail.standard_price',
        'store_grn_detail.purchase_price',
        'store_grn_detail.inventory_uom',
        'store_grn_detail.uom',
        'org_uom.uom_code');
        $query->where('store_stock_details.bin', $bin);
        $query->where('store_stock_details.inspection_status',$inspection_status);
        $query->where('store_stock_details.avaliable_qty','>',0);




        if($item_id!=null || $item_id!=""){
            $query->where('store_stock_details.item_id', $item_id);
        }
        $data = $query->get();

        echo json_encode([
            "recordsTotal" => "",
            "recordsFiltered" => "",
            "data" => $data
        ]);

    }

    public function store(Request $request)
    {
      $unId = UniqueIdGenerator::generateUniqueId('BIN_TRANSFER', auth()->payload()['company_id']);
      $header_data = array(
          "location" => auth()->payload()['loc_id'],
          "status" => 1,
          "bin_transfer_no"=>$unId
      );
        $save_header = new BinTransferHeader();
        //dd($header_data);
        if($save_header->validate($header_data))
        {
          $save_header->fill($header_data);
          $save_header->save();

          if($save_header){

            $save_details = $this->save_transfer_details($save_header['transfer_id'],$request['header'],$request['details']);
          //  $save_stock_table = $this->save_stock_table($save_header['transfer_id'],$save_details->transfer_detail_id,$save_details,$request['header'],$request['details']);

          return response([ 'data' => [
                'message' => 'Bin Transfer successfully',
                'status'=> 'success'
                ]
            ], Response::HTTP_CREATED );

          }else{

            return response([ 'data' => [
                'message' => 'Bin Transfer failed',
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


    public function save_transfer_details($transfer_id,$header,$data){
      //dd($data);
        foreach($data as $row){

            if(!isset($row['comments'])){
              $comments = "";
            }else{
              $comments = $row['comments'];
            }

          /*  $bin=StoreBin::where('store_id','=',$header['store']['store_id'])
            ->where('store_bin_name','=',$row['transfer_bin'])
            ->pluck('store_bin_id');*/

            $detail_data = array(
            'transfer_id' => $transfer_id,
            'stock_detail_id' => $row['stock_detail_id'],
            'shop_order_id' => $row['shop_order_id'],
            'shop_order_detail_id' => $row['shop_order_detail_id'],
            'style_id' => $row['style_id'],
            'size' => $row['size'],
            'color' => $row['color'],
            'uom' => $row['uom'],
            'standard_price' => $row['standard_price'],
            'purchase_price' => $row['purchase_price'],
            'item_id' => $row['item_id'],
            'rm_plan_id' => $row['rm_plan_id'],
            'store_id' => Store::where('store_name','=',$row['store_name'])->pluck('store_id')[0],
            'substore_id' => SubStore::where('substore_name','=',$row['substore_name'])->pluck('substore_id')[0],
            'bin' => StoreBin::where('store_bin_name','=',$row['store_bin_name'])->pluck('store_bin_id')[0],
            'transfer_qty' => $row['transfer_qty'],
            'status' => 1,
            'comments' => $comments,
            'inventory_type'=>$row['inventory_type']
            );
            //dd($detail_data);
            $save_detail = new BinTransferDetails();
            if($save_detail->validate($detail_data))
            {
            //  dd($save_detail);
                $save_detail->fill($detail_data);
                $save_detail->save();
                $save_stock_table = $this->save_stock_table($transfer_id,$save_detail->transfer_detail_id,$save_detail,$row);


            }
            else
            {
              $errors = $save_detail->errors();// failure, get errors
              $errors_str = $save_detail->errors_tostring();
              return response(['errors' => ['validationErrors' => $errors, 'validationErrorsText' => $errors_str]], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

        }

    }

    public function save_stock_table($transfer_id,$transfer_detail_id,$saved_transfer_detail_line,$row){
      $location= auth()->payload()['loc_id'];
      $year=DB::select("SELECT YEAR(CURDATE())AS current_d");
      $month=DB::select("SELECT MONTHNAME(CURDATE()) as current_month");
      $current_year=$year["0"]->current_d;
      $current_month=$month['0']->current_month;
      //deduct qty from the stock haeder line
      $find_stock_header_line=Stock::find($row['stock_id']);
      $find_stock_header_line->avaliable_qty=  $find_stock_header_line->avaliable_qty-$row['transfer_qty'];
      $find_stock_header_line->out_qty=$find_stock_header_line->out_qty+$row['transfer_qty'];
      $find_stock_header_line->save();
      //deduct qty from stock Details lines
      $find_stock_detail_line=StockDetails::find($row['stock_detail_id']);
      $find_stock_detail_line->avaliable_qty=$find_stock_detail_line->avaliable_qty-$row['transfer_qty'];
      $find_stock_detail_line->out_qty=$find_stock_detail_line->out_qty+$row['transfer_qty'];
      $find_stock_detail_line->save();
      //dd($find_stock_detail_line);
      //pull line of stock transaction table
      $pull_transactionLine=$this->pull_transaction_line($transfer_id,$transfer_detail_id,$row,$find_stock_header_line,$find_stock_detail_line,$saved_transfer_detail_line);
      //sdd($pull_transactionLine);
      //find stock head line for transfer
      $isStocklineAvaliable=DB::SELECT ("SELECT * FROM store_stock
        where item_id=?
        AND shop_order_id=?
        AND style_id=?
        AND shop_order_detail_id=?
        AND store_id=?
        AND substore_id=?
        AND location=?",[$find_stock_header_line->item_id,$find_stock_header_line->shop_order_id,
        $find_stock_header_line->style_id,$find_stock_header_line->shop_order_detail_id,$saved_transfer_detail_line->store_id,$saved_transfer_detail_line->substore_id,$location]);
        //dd($isStocklineAvaliable);
        $stock=null;
       if($isStocklineAvaliable==null){
         $stock=new Stock();
         $stock->shop_order_id=$row['shop_order_id'];
         $stock->shop_order_detail_id=$row['shop_order_detail_id'];
         $stock->style_id = $row['style_id'];
         $stock->item_id=$row['item_id'];
         $stock->size = $row['size'];
         $stock->color =  $row['color'];
         $stock->location = $location;
         $stock->store_id = $saved_transfer_detail_line->store_id;
         $stock->substore_id =$saved_transfer_detail_line->substore_id;
         $stock->uom=$row['inventory_uom'];
         $stock->standard_price = $row['standard_price'];
         $stock->purchase_price = $row['purchase_price'];
         $stock->financial_year=$current_year;
         $stock->financial_month=$current_month;
         //$stock->uom = $row['uom'];
         $stock->avaliable_qty = $saved_transfer_detail_line->transfer_qty;
         $stock->in_qty =$saved_transfer_detail_line->transfer_qty;
         $stock->status=1;
         //d($stock);
         $stock->save();
       }
       else if($isStocklineAvaliable!=null){
         $stock=Stock::find($isStocklineAvaliable[0]->stock_id);
         $stock->avaliable_qty= $stock->avaliable_qty+$saved_transfer_detail_line->transfer_qty;
         $stock->in_qty= $stock->in_qty+$saved_transfer_detail_line->transfer_qty;
         $stock->save();
          }

        //add stock Detial_line
        $stockDetails= new StockDetails();
        $stockDetails->stock_id=$stock->stock_id;
        $stockDetails->bin=$saved_transfer_detail_line->bin;
        $stockDetails->location=$stock->location;
        $stockDetails->batch_no=$row['batch_no'];
        $stockDetails->store_id=$saved_transfer_detail_line->store_id;
        $stockDetails->substore_id=$saved_transfer_detail_line->substore_id;
        $stockDetails->item_id=$row['item_id'];
        $stockDetails->financial_year=$stock->financial_year;
        $stockDetails->financial_month=$stock->financial_month;
        $stockDetails->rm_plan_id=$row['rm_plan_id'];
        $stockDetails->barcode=$row['barcode'];
        $stockDetails->parent_rm_plan_id=$row['rm_plan_id'];
        $stockDetails->in_qty=$saved_transfer_detail_line->transfer_qty;
        $stockDetails->avaliable_qty=$saved_transfer_detail_line->transfer_qty;
        $stockDetails->status=1;
        $stockDetails->inspection_status=$row['inspection_status'];
        $stockDetails->issue_status=$row['issue_status'];
        $stockDetails->save();

      $this->push_transaction_line($pull_transactionLine,$saved_transfer_detail_line,$stockDetails);

    }


    public function pull_transaction_line($transfer_id,$transfer_detail_id,$row,$find_stock_header_line,$find_stock_detail_line,$save_detail){
      $year=DB::select("SELECT YEAR(CURDATE())AS current_d");
      $month=DB::select("SELECT MONTHNAME(CURDATE()) as current_month");
      $current_year=$year["0"]->current_d;
      $current_month=$month['0']->current_month;
      $st = new StockTransaction;
      $st->doc_type = 'BIN_TRANSFER';
      $st->style_id = $row['style_id'];
      $st->stock_id = $find_stock_header_line->stock_id;
      $st->stock_detail_id = $find_stock_detail_line->stock_detail_id;
      $st->doc_header_id=$save_detail->transfer_id;
      $st->doc_detail_id=$save_detail->transfer_detail_id;
      $st->size = $row['size'];
      $st->color = $row['color'];
      $st->main_store = $row['store_id_from'];
      $st->sub_store = $row['substore_id_from'];
      $st->location = $row['location'];
      $st->bin = $row['bin_from'];
      $st->sup_po_header_id = $row['po_number'];
      $st->sup_po_details_id= $row['po_details_id'];
      $st->shop_order_id = $row['shop_order_id'];
      $st->shop_order_detail_id = $row['shop_order_detail_id'];
      $st->direction = '-';
      $st->status=1;
      $st->item_id = $row['item_id'];
      $st->qty = $row['transfer_qty'];
      $st->uom = $find_stock_header_line->uom;
      $st->standard_price = $row['standard_price'];
      $st->purchase_price = $row['purchase_price'];
      $st->financial_year=$current_year;
      $st->financial_month=$current_month;
      $st->rm_plan_id=$row['rm_plan_id'];
      $st->created_by = auth()->payload()['user_id'];
      $st->save();
      return $st;
    }

    public function push_transaction_line($pull_transactionLine,$saved_transfer_detail_line,$stockDetails){

      $st=$pull_transactionLine->replicate();
      $st->stock_id=$stockDetails->stock_id;
      $st->stock_detail_id=$stockDetails->stock_detail_id;
      $st->qty=$saved_transfer_detail_line->transfer_qty;
      $st->main_store=$saved_transfer_detail_line->store_id;
      $st->sub_store=$saved_transfer_detail_line->substore_id;
      $st->bin=$saved_transfer_detail_line->bin;
      $st->direction = '+';
      $st->save();




    }

    public function save_stock_details($transfer_id,$stock_id,$row){

        $details = BinTransferHeader::join('store_bin_transfer_detail','store_bin_transfer_header.transfer_id','=','store_bin_transfer_detail.transfer_id')
        ->join('store_stock_details','store_bin_transfer_detail.stock_detail_id','=','store_stock_details.stock_detail_id')
        ->select('store_bin_transfer_header.transfer_id',
        'store_bin_transfer_header.location',
        'store_bin_transfer_detail.stock_detail_id',
        'store_bin_transfer_detail.shop_order_id',
        'store_bin_transfer_detail.shop_order_detail_id',
        'store_bin_transfer_detail.style_id',
        'store_bin_transfer_detail.size',
        'store_bin_transfer_detail.color',
        'store_bin_transfer_detail.uom',
        'store_bin_transfer_detail.item_id',
        'store_bin_transfer_detail.store_id',
        'store_bin_transfer_detail.substore_id',
        'store_bin_transfer_detail.transfer_bin',
        'store_bin_transfer_detail.standard_price',
        'store_bin_transfer_detail.purchase_price',
        'store_bin_transfer_detail.rm_plan_id',
        'store_bin_transfer_detail.transfer_qty',
        'store_stock_details.barcode')
        ->where('store_bin_transfer_header.transfer_id',$transfer_id)
        ->where('store_bin_transfer_detail.shop_order_id',$row['shop_order_id'])
        ->where('store_bin_transfer_detail.shop_order_detail_id',$row['shop_order_detail_id'])
        ->where('store_bin_transfer_detail.style_id',$row['style_id'])
        ->where('store_bin_transfer_header.location',$row['location'])
        ->where('store_bin_transfer_detail.store_id',$row['store_id'])
        ->where('store_bin_transfer_detail.substore_id',$row['substore_id'])
        ->where('store_bin_transfer_detail.item_id',$row['item_id'])
        ->get();

        foreach($details as $detail) {
            $save_stock_details = new StockDetails();
            $save_stock_details->stock_id = $stock_id;
            $save_stock_details->bin = $detail['transfer_bin'];
            $save_stock_details->location = $detail['location'];
            $save_stock_details->store_id = $detail['store_id'];
            $save_stock_details->substore_id = $detail['substore_id'];
            $save_stock_details->item_id = $detail['item_id'];
            $save_stock_details->financial_year = date('Y');
            $save_stock_details->finacial_month = date('m');
            $save_stock_details->rm_plan_id = $detail['rm_plan_id'];
            $save_stock_details->in_qty = $detail['transfer_qty'];
            $save_stock_details->out_qty = 0;
            $save_stock_details->barcode = $detail['barcode'];
            $save_stock_details->avaliable_qty = $detail['transfer_qty'];
            $save_stock_details->status = 1;
            $save_stock_details->save();
        }

    }


    //get searched Colors for datatable plugin format
    private function datatable_search($data)
    {

          $start = $data['start'];
          $length = $data['length'];
          $draw = $data['draw'];
          $search = $data['search']['value'];
          $order = $data['order'][0];
          $order_column = $data['columns'][$order['column']]['data'];
          $order_type = $order['dir'];
          $list = BinTransferHeader::join('store_bin_transfer_detail','store_bin_transfer_header.transfer_id','=','store_bin_transfer_detail.transfer_id')
          ->join('item_master','store_bin_transfer_detail.item_id','=','item_master.master_id')
          ->join('usr_login','store_bin_transfer_header.created_by','=','usr_login.user_id')
          ->join('org_location','store_bin_transfer_header.location','=','org_location.loc_id')
          ->select('store_bin_transfer_header.*','usr_login.user_name','org_location.loc_name')
          ->where('store_bin_transfer_header.bin_transfer_no' , 'like', $search.'%' )
          ->orWhere('org_location.loc_name','like',$search.'%')
          ->orWhere('usr_login.user_name','like',$search.'%')
         ->orderBy($order_column, $order_type)
          ->offset($start)->limit($length)->get();


          $count = BinTransferHeader::join('store_bin_transfer_detail','store_bin_transfer_header.transfer_id','=','store_bin_transfer_detail.transfer_id')
          ->join('item_master','store_bin_transfer_detail.item_id','=','item_master.master_id')
          ->join('usr_login','store_bin_transfer_header.created_by','=','usr_login.user_id')
          ->join('org_location','store_bin_transfer_header.location','=','org_location.loc_id')
          ->select('store_bin_transfer_header.*','store_bin_transfer_header.status','usr_login.user_name', 'org_location.loc_name')
          ->where('store_bin_transfer_header.bin_transfer_no' , 'like', $search.'%' )
          ->orWhere('org_location.loc_name','like',$search.'%')
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
