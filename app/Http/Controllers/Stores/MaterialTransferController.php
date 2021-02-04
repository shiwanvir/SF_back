<?php
namespace App\Http\Controllers\Stores;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Http\Controllers\Controller;

use App\Models\Store\Stock;
use App\Models\Store\SubStore;
use App\Models\stores\TransferLocationUpdate;
use App\Models\stores\MaterialTransferInHeader;
use App\Models\stores\MaterialTransferInDetail;
use App\Models\stores\GatePassHeader;
use App\Models\stores\GatePassDetails;
use App\Models\Store\StockTransaction;
use App\Models\Store\Store;
use App\Models\Store\StoreBin;
use App\Models\Org\UOM;
use App\Models\stores\RollPlan;
use Illuminate\Support\Facades\DB;
use App\Models\Merchandising\ShopOrderHeader;
use App\Models\Merchandising\ShopOrderDetail;
use App\Models\Store\StockDetails;
use App\Models\Org\ConversionFactor;



/**
*
*/
class MaterialTransferController extends Controller
{

  function __construct()
  {
    //add functions names to 'except' paramert to skip authentication
    $this->middleware('jwt.verify', ['except' => ['index','getStores']]);
  }


  public function index(Request $request)
  {

    $type = $request->type;

    if($type == 'datatable')   {
      $data = $request->all();
      return response($this->datatable_search($data));
    }
    else if($type == 'auto')    {
      $search = $request->search;
      //dd($search);
      return response($this->gate_pass_autocomplete_search($search));
    }
    else if ($type == 'getStores'){
      $search = $request->query;
      $location=$request->location;
      //dd($request->token);
      return response($this->getStores($search,$location));
      //echo"im here in get Stores";
    }
    else if($type=='getSubStores'){
      $search=$request->query;
      $store=$request->store;
      return response($this->getSubStores($search,$store));
    }
    else if($type=='getBins'){
      $search=$request->query;
      $store=$request->store;
      $subStore=$request->substore;
      return response($this->getBins($search,$store,$subStore));
    }
    else if($type=='getBinsById'){
      $search=$request->query;
      $store=$request->store;
      $subStore=$request->substore;
      return response($this->getBinsById($search,$store,$subStore));
    }

    else if($type=='loadDetails'){
      $gatepassNo=$request->gatePassNo;
      return response(['data'=>$this->tabaleLoad($gatepassNo)]);
    }

    else{
      $active = $request->active;
      $fields = $request->fields;
      return response([
        'data' => $this->list($active , $fields)
      ]);
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
    $matTransfer_list=MaterialTransferInHeader::join('store_gate_pass_header','store_material_transfer_in_header.gate_pass_id','=','store_gate_pass_header.gate_pass_id')
    ->join('org_location as t_location','store_material_transfer_in_header.transfer_location','=','t_location.loc_id')
    ->join('usr_login','store_material_transfer_in_header.updated_by','=','usr_login.user_id')
    ->select('store_gate_pass_header.gate_pass_no','store_material_transfer_in_header.*','t_location.loc_name',DB::raw("DATE_FORMAT(store_gate_pass_header.updated_date, '%d-%b-%Y') 'send_date'"),DB::raw("DATE_FORMAT(store_material_transfer_in_header.updated_date, '%d-%b-%Y') as 'received_date'"),'usr_login.user_name')
    ->where('store_gate_pass_header.gate_pass_no','like',$search.'%')
    ->orWhere('t_location.loc_name', 'like', $search.'%')
    //->orWhere(DB::raw("DATE_FORMAT(store_material_transfer_in_header.updated_date, '%d-%b-%Y') as 'received_date'"), 'like', $search.'%')
    //->orWhere('send_date', 'like', $search.'%')
    ->orderBy($order_column, $order_type)
    ->offset($start)->limit($length)->get();


    $matTransfer_list_count= MaterialTransferInHeader::join('store_gate_pass_header','store_material_transfer_in_header.gate_pass_id','=','store_gate_pass_header.gate_pass_id')
    ->join('org_location as t_location','store_material_transfer_in_header.transfer_location','=','t_location.loc_id')
    ->join('usr_login','store_material_transfer_in_header.updated_by','=','usr_login.user_id')
    ->select('store_gate_pass_header.gate_pass_no','store_material_transfer_in_header.*','t_location.loc_name',DB::raw("DATE_FORMAT(store_gate_pass_header.updated_date, '%d-%b-%Y') 'send_date'"),DB::raw("DATE_FORMAT(store_material_transfer_in_header.updated_date, '%d-%b-%Y') as 'received_date'"),'usr_login.user_name')
    ->where('store_gate_pass_header.gate_pass_no','like',$search.'%')
    ->orWhere('t_location.loc_name', 'like', $search.'%')
    //->orWhere('received_date', 'like', $search.'%')
    //->orWhere('send_date', 'like', $search.'%')
    ->count();
    return [
      "draw" => $draw,
      "recordsTotal" =>  $matTransfer_list_count,
      "recordsFiltered" => $matTransfer_list_count,
      "data" =>$matTransfer_list
    ];


  }
  private function gate_pass_autocomplete_search($search){

    $active=1;
    $transfer_status="CONFIRMED";
    $gate_pass_list = GatePassHeader::select('gate_pass_id','gate_pass_no')
    ->where([['gate_pass_no', 'like', '%' . $search . '%'],])
    ->where('status','=',$active)
    ->where('transfer_status','=',$transfer_status)
    ->get();
    return $gate_pass_list;
  }

  private function tabaleLoad($gatepassNo){
    //dd($gatepassNo);
    $trasnfer_status="CONFIRMED";
    $status=1;
    $user = auth()->payload();
    $user_location=$user['loc_id'];


    $dataSetDetails=GatePassHeader::join('store_gate_pass_details','store_gate_pass_header.gate_pass_id','=','store_gate_pass_details.gate_pass_id')
    ->join('merc_shop_order_header','store_gate_pass_details.shop_order_id_from','=','merc_shop_order_header.shop_order_id')
    ->join('merc_shop_order_detail','store_gate_pass_details.shop_order_detail_id','=','merc_shop_order_detail.shop_order_detail_id')
    ->join('merc_shop_order_delivery','merc_shop_order_header.shop_order_id','=','merc_shop_order_delivery.shop_order_id')
    ->join('merc_customer_order_details','merc_shop_order_delivery.delivery_id','=','merc_customer_order_details.details_id')
    ->join('style_creation','store_gate_pass_details.style_id','=','style_creation.style_id')
    ->join('item_master','item_master.master_id','=','store_gate_pass_details.item_id')
    ->join('store_stock_details','store_gate_pass_details.stock_detail_id','=','store_stock_details.stock_detail_id')
  //  ->join('store_stock','store_stock_details.stock_id','=','store_stock.stock_id')
    ->join('store_rm_plan','store_gate_pass_details.rm_plan_id','=','store_rm_plan.rm_plan_id')
    ->join('store_grn_detail','store_rm_plan.grn_detail_id','=','store_grn_detail.grn_detail_id')
    ->leftJoin('org_color','org_color.color_id','=','store_gate_pass_details.color_id')
    ->leftJoin('org_size','org_size.size_id','=','store_gate_pass_details.size_id')
    //->leftJoin('org_store_bin','org_store_bin.store_bin_id','=','store_gate_pass_details.bin_id')
    ->leftJoin('org_uom','org_uom.uom_id','=','store_gate_pass_details.uom_id')
    ->select('item_master.master_code','item_master.inventory_uom','item_master.master_id','item_master.master_description','style_creation.style_no','style_creation.style_id','store_gate_pass_details.trns_qty','org_color.color_name','org_color.color_id','org_size.size_name','org_size.size_id','org_uom.uom_code','org_uom.uom_id','store_gate_pass_header.gate_pass_id','store_gate_pass_details.item_id','store_gate_pass_details.shop_order_id_from','store_gate_pass_details.shop_order_detail_id','store_rm_plan.*','store_gate_pass_details.rm_plan_id','merc_shop_order_detail.purchase_price','merc_shop_order_detail.purchase_uom','merc_customer_order_details.details_id as cus_po_details_id',
    'merc_shop_order_detail.purchase_price','item_master.standard_price','store_grn_detail.po_details_id','store_grn_detail.po_number','store_gate_pass_header.shop_order_to','store_gate_pass_header.shop_order_from','store_stock_details.issue_status','store_stock_details.inspection_status')
    //DB::raw("sum(avaliable_qty)ppp from store_stock_details where store_stock_details.location=$user_location"))
    ->where('store_gate_pass_header.gate_pass_id','=',$gatepassNo)
    ->where('store_gate_pass_header.receiver_location','=',$user_location)
    ->where('store_gate_pass_header.transfer_status','=',$trasnfer_status)
    ->where('store_gate_pass_header.status','=',$status)->get();

  //  return $dataSetDetails;
    return ['dataset'=>$dataSetDetails,
    'user_location'=>$user_location];
  /*  $dataSetDetails=DB::SELECT("SELECT
      item_master.master_code,
      item_master.inventory_uom,
      item_master.master_id,
      item_master.master_description,
      style_creation.style_no,
      style_creation.style_id,
      store_gate_pass_details.trns_qty,
      org_color.color_name,
      org_color.color_id,
      org_size.size_name,
      org_size.size_id,
      org_uom.uom_code,
      org_uom.uom_id,
      store_gate_pass_header.gate_pass_id,
      store_gate_pass_details.item_id,
      store_gate_pass_details.shop_order_id,
      store_gate_pass_details.shop_order_detail_id,
      store_rm_plan.*, store_gate_pass_details.rm_plan_id,
      merc_shop_order_detail.purchase_price,
      merc_customer_order_details.details_id AS cus_po_details_id,
      merc_shop_order_detail.purchase_price,
      item_master.standard_price,
      (SELECT
        IFNULL(sum(avaliable_qty),0)
        FROM
        store_stock_details AS SSD

        WHERE
        SSD.location =$user_location
        AND SSD.item_id=item_master.master_id
        AND SSD.stock_id=store_stock.stock_id

      ) AS total_stock_qty_location
      FROM
      store_gate_pass_header
      INNER JOIN store_gate_pass_details ON store_gate_pass_header.gate_pass_id = store_gate_pass_details.gate_pass_id
      INNER JOIN merc_shop_order_header ON store_gate_pass_details.shop_order_id = merc_shop_order_header.shop_order_id
      INNER JOIN merc_shop_order_detail ON store_gate_pass_details.shop_order_detail_id = merc_shop_order_detail.shop_order_detail_id
      INNER JOIN merc_shop_order_delivery ON merc_shop_order_header.shop_order_id = merc_shop_order_delivery.shop_order_id
      INNER JOIN merc_customer_order_details ON merc_shop_order_delivery.delivery_id = merc_customer_order_details.details_id
      INNER JOIN style_creation ON store_gate_pass_details.style_id = style_creation.style_id
      INNER JOIN item_master ON item_master.master_id = store_gate_pass_details.item_id
      INNER JOIN store_stock_details ON store_gate_pass_details.stock_detail_id = store_stock_details.stock_detail_id
      INNER JOIN store_stock ON store_stock_details.stock_id = store_stock.stock_id
      INNER JOIN store_rm_plan ON store_stock_details.rm_plan_id = store_rm_plan.rm_plan_id
      LEFT JOIN org_color ON org_color.color_id = store_gate_pass_details.color_id
      LEFT JOIN org_size ON org_size.size_id = store_gate_pass_details.size_id
      LEFT JOIN org_store_bin ON org_store_bin.store_bin_id = store_gate_pass_details.bin_id
      LEFT JOIN org_uom ON org_uom.uom_id = store_gate_pass_details.uom_id
      WHERE
      store_gate_pass_header.gate_pass_id =$gatepassNo
      AND store_gate_pass_header.receiver_location = $user_location
      AND store_gate_pass_header.status ='CONFIRMED'  ");

      //dd($dataSetDetails);
      return ['dataset'=>$dataSetDetails,
      'user_location'=>$user_location];
      //$this->setStatuszero($details);
*/

    }

    public function getStores($search,$location){
      //dd(ddadaa);
      //dd($user_location);
      //$user_location=auth()->payload()['loc_id'];
      //dd($user_location);
      $store_list = Store::where('status',1)
      ->where('loc_id',$location)
      //->where('status',1)
      //  ->where('store_name', 'like', '%' . $search . '%')
      ->pluck('store_name')
      ->toArray();
      return json_encode($store_list);
      //return $store_list;
    }

    public function getSubStores($search,$store){
      $store=Store::where('store_name','=',$store)->first();
      //dd($store);
      $sub_store_list=SubStore::where('status',1)
      ->where('store_id',$store->store_id)
      ->pluck('substore_name')
      ->toArray();
      return json_encode($sub_store_list);

    }

    public function getBins($search,$store,$subStore){
      $Store=Store::where('store_name','=',$store)->first();
      $subStore=SubStore::Where('substore_name','=',$subStore)->first();
      //$user_location=$user['loc_id'];
      //$user_location=3;
      $store_bin_list=StoreBin::where('status',1)
      ->where('store_id',$Store->store_id)
      ->where('substore_id',$subStore->substore_id)
      ->pluck('store_bin_name')
      ->toArray();
      return json_encode($store_bin_list);

    }

    public function getBinsById($search,$store,$subStore){

      $store_bin_list=StoreBin::where('status',1)
      ->where('store_id',$store)
      ->where('substore_id',$subStore)
      ->pluck('store_bin_name')
      ->toArray();
      return json_encode($store_bin_list);

    }


    public function storedetails (Request $request){
      $user = auth()->payload();
      $location=$user['loc_id'];
      $qtyforShoporder=0;
      $gate_pass_id=$request->gate_pass_id;
      //dd($request);
      $details= $request->data;

      $headerData=new MaterialTransferInHeader();
      $headerData->gate_pass_id=$request->gate_pass_id;
      $headerData->transfer_location=$user['loc_id'];
      $headerData->receiver_location=$location;
      $headerData->shop_order_from=$details[0]['shop_order_from'];
      $headerData->shop_order_to=$details[0]['shop_order_to'];
      $headerData->status=1;
      $headerData->transfer_status="RECEIVED";
      $headerData->save();

      foreach ($details as $value) {

        $store_id = Store::where('status',1)
        ->where('loc_id','=',$location)
        ->where('store_name','=',$value['store_name'])
        ->select('store_id')->first();
        $store_id=$store_id->store_id;
        //dd($store_id);
        $sub_store_id=SubStore::Where('substore_name','=',$value['substore_name'])
        ->where('store_id','=',$store_id)
        ->select('substore_id')->first();
        //dd($sub_store_id);
        $sub_store_id=$sub_store_id->substore_id;
        //dd($sub_store_id);
        $bin_id=StoreBin::where('store_id','=',$store_id)
        ->where('substore_id','=',$sub_store_id)
        ->where('store_bin_name','=',$value['store_bin_name'])
        ->select('store_bin_id')
        ->first();
      //dd($bin_id);
        $bin_id=$bin_id->store_bin_id;
      //  dd($bin_id);
      //save transfer in details;
      $detailData=new MaterialTransferInDetail();
      $detailData->meterial_transfer_id=$headerData->meterial_transfer_id;
      $detailData->size_id=$value['size_id'];
      $detailData->shop_order_id_to=$value['shop_order_to'];
      $detailData->style_id=$value['style_id'];
      $detailData->item_id=$value['item_id'];
      $detailData->color_id=$value['color_id'];
      $detailData->store_id=$store_id;
      $detailData->sub_store_id=$sub_store_id;
      $detailData->bin_id=$bin_id;
      $detailData->uom_id=$value['inventory_uom'];
      $detailData->rm_plan_id=$value['rm_plan_id'];
      $detailData->trns_qty=$value['trns_qty'];
      $detailData->received_qty=$value['received_qty'];


      //find shop order line this might have to change because same item code can be available for a selected shop order

      $update_shop_order_detail=ShopOrderDetail::where('shop_order_id','=',$value['shop_order_to'])
                                                ->where('inventory_part_id','=',$value['item_id'])
                                                ->first();

                                                if($value['inventory_uom']!=$value['purchase_uom']){
                                                  //$storeUpdate->uom = $dataset[$i]['inventory_uom'];
                                                  $_uom_unit_code=UOM::where('uom_id','=',$value['inventory_uom'])->pluck('uom_code');
                                                  $_uom_base_unit_code=UOM::where('uom_id','=',$value['purchase_uom'])->pluck('uom_code');
                                                  $ConversionFactor=ConversionFactor::select('*')
                                                  ->where('unit_code','=',$_uom_unit_code[0])
                                                  ->where('base_unit','=',$_uom_base_unit_code[0])
                                                  ->first();
                                                  // convert values according to the convertion rate
                                                  $qtyforShoporder=(double)($value['received_qty']*$ConversionFactor->present_factor);


                                                }
                                                if($value['inventory_uom']==$value['purchase_uom']){
                                                  $qtyforShoporder=$value['received_qty'];
                                                }
      $detailData->shop_order_detail_id=$update_shop_order_detail->shop_order_detail_id;
      $detailData->save();
      $update_shop_order_detail->asign_qty=$update_shop_order_detail->asign_qty+$qtyforShoporder;


      $stock_detail_id=$this->updateStockTable($value,$store_id,$sub_store_id,$bin_id,$update_shop_order_detail,$user['loc_id'],$headerData->meterial_transfer_id,$detailData->details_id);
      $find_transfer_in_line=MaterialTransferInDetail::find($detailData->details_id);
      $find_transfer_in_line->stock_detail_id=$stock_detail_id;
      $find_transfer_in_line->save();

      }

      $find_gate_pass_header_line=GatePassHeader::find($request->gate_pass_id) ;
      $find_gate_pass_header_line->transfer_status="TRANSFERED";
      $find_gate_pass_header_line->save();
        return response(['data'=>[
          'message'=>'Items Transferd in Successfully',   ]

      ]
    );

}

  public function updateStockTable($savedDetailLine,$store_id,$sub_store_id,$bin_id,$update_shop_order_detail,$location,$header_id,$details_id){
    $year=DB::select("SELECT YEAR(CURDATE())AS current_d");
    $month=DB::select("SELECT MONTHNAME(CURDATE()) as current_month");
    $current_year=$year["0"]->current_d;
    $current_month=$month['0']->current_month;
    $style_id=$savedDetailLine['style_id'];
    $item_id=$savedDetailLine['item_id'];
    //find stock head line for transfer
    $isStocklineAvaliable=DB::SELECT ("SELECT * FROM store_stock
      where item_id= ?
      AND shop_order_id=?
      AND style_id=?
      AND shop_order_detail_id=?
      AND store_id=?
      AND substore_id=?
      AND location=?",[$item_id,$update_shop_order_detail->shop_order_id,$style_id,$update_shop_order_detail->shop_order_detail_id,$store_id,$sub_store_id,$location]);
      //dd($isStocklineAvaliable);
      $stock=null;
     if($isStocklineAvaliable==null){
       $stock=new Stock();
       $stock->shop_order_id=$update_shop_order_detail->shop_order_id;
       $stock->shop_order_detail_id=$update_shop_order_detail->shop_order_detail_id;
       $stock->style_id = $savedDetailLine['style_id'];
       $stock->item_id=$savedDetailLine['item_id'];
       $stock->size = $savedDetailLine['size_id'];
       $stock->color =  $savedDetailLine['color_id'];
       $stock->location = $location;
       $stock->store_id =$store_id;
       $stock->substore_id =$sub_store_id;
       $stock->uom=$savedDetailLine['inventory_uom'];
       $stock->standard_price = $savedDetailLine['standard_price'];
       $stock->purchase_price = $savedDetailLine['purchase_price'];
       $stock->financial_year=$current_year;
       $stock->financial_month=$current_month;
       $stock->uom = $savedDetailLine['inventory_uom'];
       $stock->avaliable_qty =$savedDetailLine['received_qty'];
       $stock->in_qty =$savedDetailLine['received_qty'];
       $stock->status=1;
       //d($stock);
       $stock->save();
     }
     else if($isStocklineAvaliable!=null){
       $stock=Stock::find($isStocklineAvaliable[0]->stock_id);
       $stock->avaliable_qty= $stock->avaliable_qty+$savedDetailLine['received_qty'];
       $stock->in_qty= $stock->in_qty+$savedDetailLine['received_qty'];
       $stock->save();
        }

        //add stock Detial_line
        $stockDetails= new StockDetails();
        $stockDetails->stock_id=$stock->stock_id;
        $stockDetails->bin=$bin_id;
        $stockDetails->location=$location;
        $stockDetails->batch_no=$savedDetailLine['batch_no'];
        $stockDetails->store_id=$store_id;
        $stockDetails->substore_id=$sub_store_id;
        $stockDetails->item_id=$savedDetailLine['item_id'];
        $stockDetails->financial_year=$stock->financial_year;
        $stockDetails->financial_month=$stock->financial_month;
        $stockDetails->rm_plan_id=$savedDetailLine['rm_plan_id'];
        $stockDetails->barcode=$savedDetailLine['barcode'];
        $stockDetails->parent_rm_plan_id=$savedDetailLine['rm_plan_id'];
        $stockDetails->in_qty=$savedDetailLine['received_qty'];
        $stockDetails->avaliable_qty=$savedDetailLine['received_qty'];
        $stockDetails->status=1;
        $stockDetails->inspection_status=$savedDetailLine['inspection_status'];
        $stockDetails->issue_status=$savedDetailLine['issue_status'];
        $stockDetails->save();

        $this->push_transaction_line($savedDetailLine,$update_shop_order_detail,$stock,$stockDetails,$header_id,$details_id,$location);

        return $stockDetails->stock_detail_id;

  }

  public function push_transaction_line($savedDetailLine,$update_shop_order_detail,$stock,$stockDetails,$header_id,$details_id,$location){

    $year=DB::select("SELECT YEAR(CURDATE())AS current_d");
    $month=DB::select("SELECT MONTHNAME(CURDATE()) as current_month");
    $current_year=$year["0"]->current_d;
    $current_month=$month['0']->current_month;
    $st = new StockTransaction;
    $st->doc_type = 'TRANSFER_IN';
    $st->style_id = $savedDetailLine['style_id'];
    $st->stock_id = $stock->stock_id;
    $st->stock_detail_id = $stockDetails->stock_detail_id;
    $st->doc_header_id=$header_id;
    $st->doc_detail_id=$details_id;
    $st->size = $savedDetailLine['size_id'];
    $st->color = $savedDetailLine['color_id'];
    $st->main_store = $stock->store_id;
    $st->sub_store = $stock->substore_id;
    $st->location = $location;
    $st->bin = $stockDetails->bin;
    $st->sup_po_header_id = $savedDetailLine['po_number'];
    $st->sup_po_details_id= $savedDetailLine['po_details_id'];
    $st->shop_order_id = $update_shop_order_detail->shop_order_id;
    $st->shop_order_detail_id = $update_shop_order_detail->shop_order_detail_id;
    $st->direction = '+';
    $st->status=1;
    $st->item_id = $savedDetailLine['item_id'];
    $st->qty = $savedDetailLine['received_qty'];
    $st->uom = $stock->uom;
    $st->standard_price = $savedDetailLine['standard_price'];
    $st->purchase_price = $savedDetailLine['purchase_price'];
    $st->financial_year=$current_year;
    $st->financial_month=$current_month;
    $st->rm_plan_id=$savedDetailLine['rm_plan_id'];
    $st->created_by = auth()->payload()['user_id'];
    $st->save();


  }

}
