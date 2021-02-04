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
  use App\Models\Store\StockDetails;
  use App\Models\stores\RollPlan;
  use App\Models\Store\TrimPacking;
  use App\Models\Store\IssueHeader;
  use App\Models\Store\StockTransaction;
  use App\Models\Store\ReturnToStoreHeader;
  use App\Models\Store\ReturnToStoreDetails;
  use App\Models\Org\ConversionFactor;
  use App\Models\Store\Stock;
  use App\Models\Store\IssueDetails;
  use App\Models\Merchandising\ShopOrderDetail;
  class ReturnToStoresController extends Controller
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

    $list = ReturnToStoreHeader::join('store_return_to_store_detail','store_return_to_store_header.return_id','=','store_return_to_store_detail.return_id')
    ->join('item_master','store_return_to_store_detail.item_id','=','item_master.master_id')
    ->join('usr_login','store_return_to_store_header.created_by','=','usr_login.user_id')
    ->join('org_location','store_return_to_store_header.user_loc_id','=','org_location.loc_id')
    ->join('store_issue_header','store_return_to_store_header.issue_id','=','store_issue_header.issue_id')
    ->select(DB::raw("DATE_FORMAT(store_return_to_store_header.updated_date, '%d-%b-%Y') 'updated_date_'"),'store_return_to_store_header.return_no',
    'store_issue_header.issue_no',
    'store_return_to_store_detail.*',
    'item_master.master_code',
    'item_master.master_description',
    'usr_login.user_name',
    'org_location.loc_name')
    ->where('store_return_to_store_header.return_no' , 'like', $search.'%' )
    ->orWhere('item_master.master_code' , 'like', $search.'%' )
    ->orWhere('item_master.master_description' , 'like', $search.'%' )
    ->orWhere('org_location.loc_name','like',$search.'%')
    ->orWhere('usr_login.user_name','like',$search.'%')
    ->orWhere('store_issue_header.issue_no','like',$search.'%')
    ->orderBy($order_column, $order_type)
    ->offset($start)->limit($length)->get();


    $count = ReturnToStoreHeader::join('store_return_to_store_detail','store_return_to_store_header.return_id','=','store_return_to_store_detail.return_id')
    ->join('item_master','store_return_to_store_detail.item_id','=','item_master.master_id')
    ->join('usr_login','store_return_to_store_header.created_by','=','usr_login.user_id')
    ->join('org_location','store_return_to_store_header.user_loc_id','=','org_location.loc_id')
    ->join('store_issue_header','store_return_to_store_header.issue_id','=','store_issue_header.issue_id')
    ->select('store_return_to_store_header.return_no',
    'store_issue_header.issue_no',
    'store_return_to_store_detail.*',
    'item_master.master_code',
    'item_master.master_description',
    'usr_login.user_name',
    'org_location.loc_name')
    ->where('store_return_to_store_header.return_no' , 'like', $search.'%' )
    ->orWhere('item_master.master_code' , 'like', $search.'%' )
    ->orWhere('item_master.master_description' , 'like', $search.'%' )
    ->orWhere('org_location.loc_name','like',$search.'%')
    ->orWhere('usr_login.user_name','like',$search.'%')
    ->orWhere('store_issue_header.issue_no','like',$search.'%')
    ->count();

    echo json_encode([
      "draw" => $draw,
      "recordsTotal" => $count,
      "recordsFiltered" => $count,
      "data" => $list
    ]);

  }

  public function load_issue_details(Request $request)
  {
    $id=$request->search['issue_no']['issue_id'];
    if($request->type=="load"){

    //  dd($id);
      $data=DB::SELECT("SELECT
       store_rm_plan.*, store_issue_detail.qty,
       store_issue_detail.issue_detail_id,
       store_issue_detail.balance_to_return_qty,
       item_master.master_code,
       item_master.master_id,
       org_location.loc_name,
       item_master.master_description,
       store_issue_header.issue_no,
       org_store.store_name,
       org_store.store_id,
       org_substore.substore_name,
       org_substore.substore_id,
       org_store_bin.store_bin_name,
       store_mrn_detail.color_id,
       org_store_bin.store_bin_id,
       org_uom.uom_code,
       org_uom.uom_id,
       org_location.loc_id,
       store_mrn_header.style_id,
       store_mrn_detail.size_id,
       store_mrn_detail.shop_order_id,
       store_mrn_detail.shop_order_detail_id,
       store_grn_detail.purchase_price,
       store_grn_detail.standard_price,
       store_grn_detail.uom AS po_uom,
       store_grn_detail.po_number,
       store_grn_detail.po_details_id,
       store_issue_detail.stock_id,
       store_issue_detail.stock_detail_id
     FROM
       store_issue_header
     INNER JOIN store_issue_detail ON store_issue_header.issue_id = store_issue_detail.issue_id
     INNER JOIN store_mrn_detail ON store_issue_detail.mrn_detail_id = store_mrn_detail.mrn_detail_id
     INNER JOIN store_mrn_header ON store_mrn_detail.mrn_id = store_mrn_header.mrn_id
     INNER JOIN item_master ON store_issue_detail.item_id = item_master.master_id
     INNER JOIN store_rm_plan ON store_issue_detail.rm_plan_id = store_rm_plan.rm_plan_id
     INNER JOIN store_grn_detail ON store_rm_plan.grn_detail_id = store_grn_detail.grn_detail_id
     INNER JOIN org_store ON store_issue_detail.store_id = org_store.store_id
     INNER JOIN org_location ON org_store.loc_id = org_location.loc_id
     INNER JOIN org_substore ON store_issue_detail.sub_store_id = org_substore.substore_id
     INNER JOIN org_store_bin ON store_issue_detail.bin = org_store_bin.store_bin_id
     INNER JOIN org_uom ON store_issue_detail.uom = org_uom.uom_id
     WHERE
       store_issue_header.issue_id = $id
     AND store_issue_detail.balance_to_return_qty > 0
       ");
    }
    else if($request->type="filter")
    {
    $issue_no = $request['search']['issue_no']['issue_id'];
    $roll_from = $request['details']['roll_from'];
    if($roll_from==null){
      $roll_from="";
    }
    $roll_to = $request['details']['roll_to'];
    if($roll_to==null){
      $roll_to="";
    }
    $lab_comments = $request['details']['lab_comments'];
    if($lab_comments==null){
      $lab_comments="";
    }
    $shade = $request['details']['shade'];
    if($shade==null){
      $shade="";
    }
    $item_code = $request['details']['item_code']['master_code'];
    if($item_code==null){
      $item_code="";
    }
    $batch = $request['details']['batch']['batch_no'];
    if($batch==null){
      $batch="";
    }
    $ins_status_code = $request['details']['ins_status']['status_name'];
    //dd($ins_status_code);
    if($ins_status_code==null){
      $ins_status_code="";
    }

 $data=DB::SELECT("SELECT
	store_rm_plan.*, store_issue_detail.qty,
	store_issue_detail.issue_detail_id,
	store_issue_detail.balance_to_return_qty,
	item_master.master_code,
	item_master.master_id,
	org_location.loc_name,
	item_master.master_description,
	store_issue_header.issue_no,
	org_store.store_name,
	org_store.store_id,
	org_substore.substore_name,
	org_substore.substore_id,
	org_store_bin.store_bin_name,
	store_mrn_detail.color_id,
	org_store_bin.store_bin_id,
	org_uom.uom_code,
	org_uom.uom_id,
	org_location.loc_id,
	store_mrn_header.style_id,
	store_mrn_detail.size_id,
	store_mrn_detail.shop_order_id,
	store_mrn_detail.shop_order_detail_id,
	store_grn_detail.purchase_price,
	store_grn_detail.standard_price,
	store_grn_detail.uom AS po_uom,
	store_grn_detail.po_number,
	store_grn_detail.po_details_id,
	store_issue_detail.stock_id,
	store_issue_detail.stock_detail_id
FROM
	store_issue_header
INNER JOIN store_issue_detail ON store_issue_header.issue_id = store_issue_detail.issue_id
INNER JOIN store_mrn_detail ON store_issue_detail.mrn_detail_id = store_mrn_detail.mrn_detail_id
INNER JOIN store_mrn_header ON store_mrn_detail.mrn_id = store_mrn_header.mrn_id
INNER JOIN item_master ON store_issue_detail.item_id = item_master.master_id
INNER JOIN store_rm_plan ON store_issue_detail.rm_plan_id = store_rm_plan.rm_plan_id
INNER JOIN store_grn_detail ON store_rm_plan.grn_detail_id = store_grn_detail.grn_detail_id
INNER JOIN org_store ON store_issue_detail.store_id = org_store.store_id
INNER JOIN org_location ON org_store.loc_id = org_location.loc_id
INNER JOIN org_substore ON store_issue_detail.sub_store_id = org_substore.substore_id
INNER JOIN org_store_bin ON store_issue_detail.bin = org_store_bin.store_bin_id
INNER JOIN org_uom ON store_issue_detail.uom = org_uom.uom_id
WHERE
store_issue_header.issue_id =$id
AND store_issue_detail.balance_to_return_qty > 0
AND store_rm_plan.lab_comment LIKE '%".$lab_comments."%'
	AND item_master.master_code LIKE'%".$item_code."%'
	AND store_rm_plan.batch_no LIKE '%".$batch."%'
  AND store_rm_plan.shade like '%".$shade."%'
	AND store_rm_plan.inspection_status LIKE '%".$ins_status_code."%'
	");

}


  echo json_encode([
  "recordsTotal" => "",
  "recordsFiltered" => "",
  "data" => $data
  ]);

  }

  public function store(Request $request)
  {

  $return_no = UniqueIdGenerator::generateUniqueId('RE_STORE', auth()->payload()['loc_id']);
  $header_data = array(
    "issue_id" => $request->header['issue_no']['issue_id'],
    "status" => 1,
    "return_no" => $return_no
  );

  $save_header = new ReturnToStoreHeader();
  if($save_header->validate($header_data))
  {
    $save_header->fill($header_data);
    $save_header->save();

    if($save_header){

      $save_details = $this->save_return_details($save_header['return_id'],$request['details']);
      //$update_store_stock = $this->update_store_stock($save_header['return_id'],$request['details']);
      //$save_stock_transaction = $this->save_stock_transaction($save_header['return_id'],$request['details']);
      //$update_roll_plan = $this->update_roll_plan($save_header['return_id'],$request['details']);


      return response([ 'data' => [
        'message' => 'Item Return Successfully',
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

    $detail_data = array(
      'return_id' => $return_id,
      'issue_detail_id' => $row['issue_detail_id'],
      'item_id' => $row['master_id'],
      'inv_uom' => $row['uom_id'],
      'request_uom' => $row['uom_id'],
      'issue_qty' => $row['qty'],
      'return_qty' => $row['return_qty'],
      'status' => 1,
      'location_id' => $row['loc_id'],
      'store_id' => $row['store_id'],
      'sub_store_id' => $row['substore_id'],
      'bin' => $row['bin'],
      'roll_or_box_no' => $row['roll_or_box_no'],
      'batch_no' => $row['batch_no'],
      'shade' => $row['shade'],
      'rm_plan_id' => $row['rm_plan_id'],
      'comments' => $comments,
      'purchase_price'=>$row['purchase_price']
    );

    $save_detail = new ReturnToStoreDetails();
    if($save_detail->validate($detail_data))
    {
      $save_detail->fill($detail_data);
      $save_detail->total_value=$row['purchase_price']*$row['qty'];
      $save_detail->save();
      $updateIssueDetails=IssueDetails::find($row['issue_detail_id']);
      $updateIssueDetails->balance_to_return_qty=$updateIssueDetails->balance_to_return_qty-$row['return_qty'];
      $updateIssueDetails->save();
      $update_store_stock = $this->update_store_stock($return_id,$row);
      $save_stock_transaction = $this->save_stock_transaction($return_id,$row,$save_detail);

    }
    else
    {
      $errors = $save_detail->errors();// failure, get errors
      $errors_str = $save_detail->errors_tostring();
      return response(['errors' => ['validationErrors' => $errors, 'validationErrorsText' => $errors_str]], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

  }

  }

  public function save_stock_transaction($return_id,$row,$save_detail){
  $year=DB::select("SELECT YEAR(CURDATE())AS current_d");
  $month=DB::select("SELECT MONTHNAME(CURDATE()) as current_month");
  $stock=Stock::find($row['stock_id']);
  $current_year=$year["0"]->current_d;
  $current_month=$month['0']->current_month;
  $st = new StockTransaction;
  $st->doc_type = 'RETURN_TO_STORE';
  $st->style_id = $row['style_id'];
  $st->stock_id = $row['stock_id'];
  $st->stock_detail_id = $row['stock_detail_id'];
  $st->doc_header_id=$save_detail->return_id;
  $st->doc_detail_id=$save_detail->return_detail_id;
  $st->size = $row['size_id'];
  $st->color = $row['color_id'];
  $st->main_store = $row['store_id'];
  $st->sub_store = $row['substore_id'];
  $st->location = $row['loc_id'];
  $st->bin = $row['bin'];
  $st->sup_po_header_id = $row['po_number'];
  $st->sup_po_details_id= $row['po_details_id'];
  if($stock->inventory_type=="AUTO"){
  $st->shop_order_id = $row['shop_order_id'];
  $st->shop_order_detail_id = $row['shop_order_detail_id'];
  }
  $st->direction = '+';
  $st->status=1;
  $st->item_id = $row['master_id'];
  $st->qty = $row['return_qty'];
  $st->uom = $row['uom_id'];
  $st->standard_price = $row['standard_price'];
  $st->purchase_price = $row['purchase_price'];
  $st->financial_year=$current_year;
  $st->financial_month=$current_month;
  $st->rm_plan_id=$row['rm_plan_id'];
  $st->created_by = auth()->payload()['user_id'];
  $st->save();


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
      $update = RollPlan::where('roll_plan_id', $row['item_detail_id'])->update(['qty' => ($available_qty[0]+$return_qty) ]);
    } else {
      $available_qty=TrimPacking::where('trim_packing_id','=',$row['item_detail_id'])->pluck('qty');
      $update = TrimPacking::where('trim_packing_id', $row['item_detail_id'])->update(['qty' => ($available_qty[0]+$return_qty) ]);
    }

  }

  }

  public function update_store_stock($return_id,$row){
    //dd($row);
  $year=DB::select("SELECT YEAR(CURDATE())AS current_d");
  $month=DB::select("SELECT MONTHNAME(CURDATE()) as current_month");
  $current_year=$year["0"]->current_d;
  $current_month=$month['0']->current_month;
  /*if($row['inventory_uom']==$row['request_uom']){
  $return_qty = $row['return_qty'];
  } else {
  $return_qty = $this->convert_into_inventory_uom($row['request_uom'],$row['inventory_uom'],$row['return_qty']);
  }

  $stock_line=Stock::where('store_stock.item_id',$row['item_id'])
  ->where('store_stock.shop_order_id',$row['shop_order_id'])
  ->Where('store_stock.shop_order_detail_id',$row['shop_order_detail_id'])
  ->where('store_stock.style_id',$row['style_id'])
  ->Where('store_stock.bin',$row['bin'])
  ->where('store_stock.store',$row['store_id'])
  ->Where('store_stock.sub_store',$row['sub_store_id'])
  ->where('store_stock.location',$row['location_id'])
  ->first();

  if($stock_line){
  $update = Stock::where('id', $stock_line->id)->update(['qty' => ($stock_line->qty+$return_qty) ]);
  }*/
  $stock=Stock::find($row['stock_id']);
  $stock->avaliable_qty=$stock->avaliable_qty+$row['return_qty'];
  $stock->out_qty=$stock->out_qty-$row['return_qty'];
  $stock->save();
  $stockDetail=StockDetails::find($row['stock_detail_id']);
  $stockDetail->out_qty=$stockDetail->out_qty-$row['stock_detail_id'];
  $stockDetail->avaliable_qty=$stock->avaliable_qty+$row['return_qty'];
  $stockDetail->save();
  if($stock->inventory_type=="AUTO"){
  $this->up_date_shop_orde_details($stock->uom,$row['po_uom'],$row['return_qty'],$row['shop_order_detail_id']);
  }

  }

  public function up_date_shop_orde_details($inventory_uom,$po_uom,$qty,$shop_order_detail_id){

  if($inventory_uom!=$po_uom){
    //$storeUpdate->uom = $dataset[$i]['inventory_uom'];
    $_uom_unit_code=UOM::where('uom_id','=',$po_uom)->pluck('uom_code');
    $_uom_base_unit_code=UOM::where('uom_id','=',$inventory_uom)->pluck('uom_code');
    $ConversionFactor=ConversionFactor::select('*')
    ->where('unit_code','=',$_uom_unit_code[0])
    ->where('base_unit','=',$_uom_base_unit_code[0])
    ->first();
    // convert values according to the convertion rate
    $qtyforShoporder=(double)($qty*$ConversionFactor->present_factor);


  }
  if($inventory_uom==$po_uom){
    $qtyforShoporder=$qty;
  }

  $findShopOrderline=ShopOrderDetail::find($shop_order_detail_id);
  //dd($findShopOrderline);
  $findShopOrderline->asign_qty=$findShopOrderline->asign_qty+$qtyforShoporder;
  $findShopOrderline->issue_qty=$findShopOrderline->issue_qty-$qtyforShoporder;
  //$findShopOrderline->balance_to_issue_qty=$findShopOrderline->balance_to_issue_qty+$qtyforShoporder;
  $findShopOrderline->save();


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

  }
