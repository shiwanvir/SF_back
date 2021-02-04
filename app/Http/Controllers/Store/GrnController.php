<?php

namespace App\Http\Controllers\Store;

use App\Libraries\UniqueIdGenerator;
use App\Models\Store\StoreBin;
use App\Models\Org\SupplierTolarance;
use App\Models\Store\Stock;
use App\Models\Store\StockTransaction;
use App\Models\Store\SubStore;
use App\Models\Finance\Transaction;
use App\Models\Store\Store;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Models\Store\GrnHeader;
use App\Models\Store\GrnDetail;
use App\Models\Merchandising\PoOrderHeader;
use App\Models\Merchandising\PoOrderDetails;
use App\Models\Finance\PriceVariance;
use App\Models\Org\ConversionFactor;
use App\Models\Merchandising\ShopOrderHeader;
use App\Models\Merchandising\ShopOrderDetail;
use App\Models\Org\UOM;
use App\Models\Merchandising\Item\Item;
use Illuminate\Support\Facades\DB;
use App\Libraries\AppAuthorize;
use Carbon\Carbon;
use App\Models\stores\RMPlan;
use App\Models\stores\RMPlanHeader;
use App\Models\Store\StockTransactionDetail;
use App\Models\Store\StockDetails;
use App\Libraries\CapitalizeAllFields;
use App\Models\Merchandising\PurchaseOrderManual;
use App\Models\Merchandising\POManualDetails;
use App\Libraries\SAPService;
use App\Libraries\InventoryValidation;
class GrnController extends Controller
{

  public function __construct()
  {
    //add functions names to 'except' paramert to skip authentication
    $this->middleware('jwt.verify', ['except' => ['index']]);
  }
  public function grnDetails() {
    return view('grn.grn_details');

  }

  public function index(Request $request){
    $type = $request->type;
    //dd($type);
    if($type == 'datatable') {
      $data = $request->all();
      //dd($data);
      return response($this->datatable_search($data));
    }else if($type == 'auto')    {
      $search = $request->search;
      return response($this->autocomplete_search($search));
    }
    else if($type == 'filter-stores-aginst-po'){
      //dd("dada");
      $search = $request->po_id;
      //dd($search);
      return response([
        'data' => $this->loadFilterdSubStores($search)
      ]);
    }
    else if($type == 'load_batch_details'){
      //dd("dada");
      $search = $request->grn_id;
      //dd($search);
      return response([
        'data' => $this->load_batch_details($search)
      ]);
    }
    else if($type == 'load_grn_type'){
      $search = $request->search;
      return response($this->autocomplete_search_grn_type($search));
    }

  }

  private function autocomplete_search($search)
  {
    $lists = GrnHeader::select('*')
    ->where([['grn_number', 'like', '%' . $search . '%'],]) ->get();
    return $lists;
  }


  private function autocomplete_search_grn_type($search) {
      $type_list = DB::table('store_grn_type')->select('grn_type_id', 'grn_type_code')
      ->where([['grn_type_code', 'like', '%' . $search . '%'],])->get();
      return $type_list;
  }
  /*private function loadFilterdSubStores($serach){
  $poDetails = PoOrderDetails::select('item_category.category')
  ->join('merc_po_order_header','merc_po_order_details.po_header_id','=','merc_po_order_header.po_id')
  ->join('item_master','merc_po_order_details.item_code','=','item_master.master_id')
  ->join('item_category','item_master.category_id','item_master.category_id')
  ->where('merc_po_order_header','=',$serach)
  ->first()
  //$subStore_list=SubStore::select('*')
  //->join('org_store','org_substore.store_id')
}
*/
public function store(Request $request){
  $grn_type_code="";
  $y=0;
  if(empty($request['grn_id'])) {
    $header = new GrnHeader;
    $locId=auth()->payload()['loc_id'];
    $unId = UniqueIdGenerator::generateUniqueId('ARRIVAL', auth()->payload()['company_id']);
    $header->grn_number = $unId;
    $header->po_number = $request->header['po_id'];
    $header->grn_type=$request->header['grn_type_code']['grn_type_id'];
    $grn_type_code=$request->header['grn_type_code']['grn_type_code'];

  }else{
    $header = GrnHeader::find($request['grn_id']);
    $header->updated_by = auth()->payload()['user_id'];
    $grn_type_code=$request->header['grn_type_code']['grn_type_code'];
    // Remove all added grn details
    GrnDetail::where('grn_id', $request['grn_id'])->delete();
  }

  //get current date and month
  $year=DB::select("SELECT YEAR(CURDATE())AS current_d");
  $month=DB::select("SELECT MONTHNAME(CURDATE()) as current_month");
  $current_year=$year["0"]->current_d;
  $current_month=$month['0']->current_month;

  //Get Main store
  $store = SubStore::find($request->header['substore_id']);

  //$header->batch_no = $request->header['batch_no'];
  $header->inv_number = $request->header['invoice_no'];
  $header->note = $request->header['note'];
  $header->location = auth()->payload()['loc_id'];
  $header->main_store = $store->store_id;
  $header->sub_store = $store->substore_id;
  $header->sup_id=$request->header['sup_id'];
  $header->status=1;
  $header->arrival_status="PLANNED";
  $header->created_by = auth()->payload()['user_id'];
  $header->grn_type=$request->header['grn_type_code']['grn_type_id'];
  $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($header);
  $header->save();

  $i = 1;

  //$valTol = $this->validateSupplierTolerance($request['dataset'], $request->header['sup_id']);

  //for tempary
  $valTol=true;
  //dd($request['dataset'] );
  foreach ($request['dataset'] as $rec){

    if($valTol) {
      $poDetails=null;
        $grnDetails = new GrnDetail;
      if($grn_type_code=="AUTO"){
        $poDetails = PoOrderDetails::find($rec['id']);
        $grnDetails->grn_id = $header->grn_id;
        $grnDetails->po_number=$request->header['po_id'];
        $grnDetails->grn_line_no = $i;
        $grnDetails->style_id = $poDetails->style;
        $grnDetails->po_details_id=$rec['id'];
        $grnDetails->combine_id = $poDetails->comb_id;
        $grnDetails->color = $poDetails->colour;
        $grnDetails->size = $poDetails->size;
        $grnDetails->uom = $poDetails->purchase_uom;
        $grnDetails->po_qty = (double)$poDetails->req_qty;
        $grnDetails->item_code = $poDetails->item_code;
        //$grnDetails->grn_qty = $rec['qty'];
        $grnDetails->i_rec_qty = $rec['qty'];
        $grnDetails->bal_qty =(double)$rec['bal_qty'];
        $grnDetails->original_bal_qty=(double)$rec['original_bal_qty'];
        $grnDetails->maximum_tolarance =$rec['maximum_tolarance'];
        $grnDetails->customer_po_id=$rec['cus_order_details_id'];
        $grnDetails->excess_qty=(double)$rec['excess_qty'];
        $grnDetails->standard_price =(double)$rec['standard_price'];
        $grnDetails->purchase_price =(double)$rec['purchase_price'];
        $grnDetails->shop_order_id =$rec['shop_order_id'];
        $grnDetails->shop_order_detail_id=$rec['shop_order_detail_id'];
        $grnDetails->inventory_uom =$rec['inventory_uom'];
        $grnDetails->status=1;
        $grnDetails->arrival_status="PLANNED";
       }
       else if($grn_type_code=="MANUAL"){
        $poDetails=POManualDetails::find($rec['id']);
        $grnDetails->grn_id = $header->grn_id;
        $grnDetails->po_number=$request->header['po_id'];
        $grnDetails->grn_line_no = $i;
        //$grnDetails->style_id = $poDetails->style;
        $grnDetails->po_details_id=$rec['id'];
        //$grnDetails->combine_id = $poDetails->comb_id;
        $grnDetails->color = $rec['color_id'];
        $grnDetails->size = $rec['size_id'];
        $grnDetails->uom = $poDetails->purchase_uom;
        $grnDetails->po_qty = (double)$poDetails->qty;
        $grnDetails->item_code = $rec['master_id'];
        //$grnDetails->grn_qty = $rec['qty'];
        $grnDetails->i_rec_qty = $rec['qty'];
        $grnDetails->bal_qty =(double)$rec['bal_qty'];
        $grnDetails->original_bal_qty=(double)$rec['original_bal_qty'];
        $grnDetails->maximum_tolarance =$rec['maximum_tolarance'];
        //$grnDetails->customer_po_id=$rec['cus_order_details_id'];
        $grnDetails->excess_qty=(double)$rec['excess_qty'];
        $grnDetails->standard_price =(double)$rec['standard_price'];
        $grnDetails->purchase_price =(double)$rec['purchase_price'];
        //$grnDetails->shop_order_id =$rec['shop_order_id'];
        //$grnDetails->shop_order_detail_id=$rec['shop_order_detail_id'];
        $grnDetails->inventory_uom =$rec['inventory_uom'];
        $grnDetails->status=1;
        $grnDetails->arrival_status="PLANNED";
       }




      /*if($rec['category_code']=="FAB"){
      $rec['inspection_allowed']=1;
    }*/
    if(empty($rec['inspection_allowed'])==true|| $rec['inspection_allowed']==0){
      $grnDetails->inspection_allowed=0;
    }
    else if(empty($rec['inspection_allowed'])==false|| $rec['inspection_allowed']==1){
      $grnDetails->inspection_allowed=1;
    }
    $responseData[$y]=$grnDetails;
    $y++;
    $i++;

    $grnDetails->save();

    if (!$grnDetails->save()) {
      return response(['data' => [
        'type' => 'error',
        'message' => 'Not Saved',
        'grnId' => $header->grn_id
      ]
    ], Response::HTTP_CREATED);
  }
}else{
  return response([ 'data' => [
    'type' => 'error',
    'message' => 'Not matching with supplier tolerance.',
    'grnId' => $header->grn_id,
    'detailData'=>$responseData
  ]
], Response::HTTP_CREATED );
}

}

return response(['data' => [
  'type' => 'success',
  'message' => 'Received Qty Updated Successfully.',
  'grnId' => $header->grn_id,
  'detailData'=>$responseData
]
], Response::HTTP_CREATED);


}

//deactivate a Grn Header
public function destroy($id)
{
  $updatedQty=0;
  $year=DB::select("SELECT YEAR(CURDATE())AS current_d");
  $month=DB::select("SELECT MONTHNAME(CURDATE()) as current_month");
  $current_year=$year["0"]->current_d;
  $current_month=$month['0']->current_month;
  $isMatchedforPayment=GrnHeader::where('payment_made','=',1)->exists();
  $rmPlan=GrnHeader::join('store_grn_detail','store_grn_header.grn_id','=','store_grn_detail.grn_id')
  ->join('store_rm_plan','store_grn_detail.grn_detail_id','=','store_rm_plan.grn_detail_id')
  ->where('store_grn_header.grn_id','=',$id)
  ->where('store_rm_plan.status','=',1)->exists();
   if($isMatchedforPayment==true){
      return response([
        'data' => [
          'message' => 'Inward Register Already matched for payments',
          'status'=>0
        ]
        ] );
    }

    else{
     $sap = new SAPService;
        $docEntry=GrnHeader::where('grn_id', $id)->select('sap_doc_entry')->first();
		    $code=$docEntry->sap_doc_entry;

    $canceledStatus=$sap->cancelDoc('PurchaseDeliveryNotes('.$code.')/Cancel');
        //$canceledStatus=true;
    //dd($canceledStatus);
		//dd($canceledStatus);
      if(isset($canceledStatus['error'])){

        return response([
          'data' => [
            'message' => 'Inward Register Cancelletion Faild on SAP',
            'status'=>0
          ]
          ] );

      }
      else if($canceledStatus==true){
      $grnDetailupdate=GrnHeader::select('store_grn_header.*','store_grn_detail.*','store_rm_plan.rm_plan_id','store_rm_plan.actual_qty','store_grn_type.grn_type_code','merc_shop_order_detail.purchase_uom as shop_order_purchase_uom','store_rm_plan.is_excess as is_excess_')
      ->join('store_grn_type','store_grn_header.grn_type','=','store_grn_type.grn_type_id')
      ->join('store_grn_detail','store_grn_header.grn_id','=','store_grn_detail.grn_id')
      ->join('merc_shop_order_detail','store_grn_detail.shop_order_detail_id','=','merc_shop_order_detail.shop_order_detail_id')
      ->join('store_rm_plan','store_grn_detail.grn_detail_id','=','store_rm_plan.grn_detail_id')
      ->where('store_grn_header.grn_id','=',$id)->get();
      //dd($header->sub_store);
          //dd($grnDetailupdate);
      for($i=0;$i<sizeof($grnDetailupdate);$i++){
      if($grnDetailupdate[$i]->arrival_status!="PLANNED"){
        $findStoreStockLine=null;
        if($grnDetailupdate[$i]->grn_type_code=="AUTO"){
        $findStoreStockLine=Stock::join('store_stock_details','store_stock.stock_id','=','store_stock_details.stock_id')
                                  ->where('store_stock.item_id','=',$grnDetailupdate[$i]->item_code)
                                  ->where('store_stock.shop_order_id','=',$grnDetailupdate[$i]->shop_order_id)
                                  ->where('store_stock.style_id','=',$grnDetailupdate[$i]->style_id)
                                  ->where('store_stock.shop_order_detail_id','=',$grnDetailupdate[$i]->shop_order_detail_id)
                                  ->where('store_stock.store_id','=',$grnDetailupdate[$i]->main_store)
                                  ->where('store_stock.substore_id','=',$grnDetailupdate[$i]->sub_store)
                                  ->where('store_stock.location','=',$grnDetailupdate[$i]->location)
                                  ->where('store_stock.inventory_type','=',"AUTO")
                                  //->where('store_stock_details.location','=',$grnDetailupdate[$i]->location)
                                  //->where('store_stock_details.bin','=',$grnDetailupdate[$i]->bin)
                                  ->where('store_stock_details.rm_plan_id',$grnDetailupdate[$i]->rm_plan_id)->get();
                  //dd($findStoreStockLine);
                  }
          else if($grnDetailupdate[$i]->grn_type_code=="MANUAL"){
            //dd($grnDetailupdate[$i]);
            $findStoreStockLine=Stock::join('store_stock_details','store_stock.stock_id','=','store_stock_details.stock_id')
                                      ->where('store_stock.item_id','=',$grnDetailupdate[$i]->item_code)
                                      //->where('store_stock.shop_order_id','=',$grnDetailupdate[$i]->shop_order_id)
                                      //->where('store_stock.style_id','=',$grnDetailupdate[$i]->style_id)
                                      //->where('store_stock.shop_order_detail_id','=',$grnDetailupdate[$i]->shop_order_detail_id)
                                      ->where('store_stock.store_id','=',$grnDetailupdate[$i]->main_store)
                                      ->where('store_stock.substore_id','=',$grnDetailupdate[$i]->sub_store)
                                      ->where('store_stock.location','=',$grnDetailupdate[$i]->location)
                                      ->where('store_stock.inventory_type','=',"MANUAL")
                                      //->where('store_stock_details.bin','=',$grnDetailupdate[$i]->bin)
                                      ->where('store_stock_details.rm_plan_id',$grnDetailupdate[$i]->rm_plan_id)->get();
                                      //dd($findStoreStockLine);
          }
            //dd($grnDetailupdate[$i]);
          if($grnDetailupdate[$i]->arrival_status!="RECEIVED" && $grnDetailupdate[$i]->grn_type_code=="AUTO"&& $grnDetailupdate[$i]->is_excess_==0){
            $shopOrder=ShopOrderDetail::find($grnDetailupdate[$i]->shop_order_detail_id);
            if($grnDetailupdate[$i]->uom==$grnDetailupdate[$i]->shop_order_purchase_uom){
                $shopOrder->asign_qty=$shopOrder->asign_qty-(double)$grnDetailupdate[$i]->actual_qty;
            }
            if($grnDetailupdate[$i]->uom!=$grnDetailupdate[$i]->shop_order_purchase_uom){
              $_uom_unit_code=UOM::where('uom_id','=',$grnDetailupdate[$i]->purchase_uom)->pluck('uom_code');
              $_uom_base_unit_code=UOM::where('uom_id','=',$grnDetailupdate[$i]->shop_order_purchase_uom)->pluck('uom_code');
                //get convertion equatiojn details
              $ConversionFactor=ConversionFactor::select('*')
              ->where('unit_code','=',$_uom_unit_code[0])
              ->where('base_unit','=',$_uom_base_unit_code[0])
              ->first();
                $shopOrder->asign_qty=$shopOrder->asign_qty-(double)$grnDetailupdate[$i]->actual_qty*$ConversionFactor->present_factor;
            }

            $shopOrder->save();
          }

            $stock=Stock::find($findStoreStockLine[0]->stock_id);
            $stockDetail=StockDetails::find($findStoreStockLine[0]->stock_detail_id);
            //dd($stockDetail);
            if($stock->uom!=$grnDetailupdate[$i]->uom){
              $_uom_unit_code=UOM::where('uom_id','=',$grnDetailupdate[$i]->uom)->pluck('uom_code');
              $_uom_base_unit_code=UOM::where('uom_id','=',$stock->uom)->pluck('uom_code');
              //get convertion equatiojn details
              $ConversionFactor=ConversionFactor::select('*')
              ->where('unit_code','=',$_uom_unit_code[0])
              ->where('base_unit','=',$_uom_base_unit_code[0])
              ->first();
              //dd($grnDetailupdate[$i]->uom);
              // convert values according to the convertion rate
              //update stock qty with convertion qty
              $stock->avaliable_qty =(double)$stock->avaliable_qty-(double)($grnDetailupdate[$i]->actual_qty*$ConversionFactor->present_factor);
              $stock->in_qty =(double)$stock->in_qty-(double)($grnDetailupdate[$i]->actual_qty*$ConversionFactor->present_factor);
              //$stock->out_qty =(double)$stock->out_qty-(double)($grnDetailupdate[$i]->grn_qty*$ConversionFactor->present_factor);
              //$stock->excess_qty =(double)$stock->excess_qty-(double)($grnDetailupdate[$i]->excess_qty*$ConversionFactor->present_factor);
              $updatedQty=(double)($grnDetailupdate[$i]->actual_qty*$ConversionFactor->present_factor);
              $stock->save();

              $stockDetail->avaliable_qty =(double)$stockDetail->avaliable_qty-(double)($grnDetailupdate[$i]->actual_qty*$ConversionFactor->present_factor);
              $stockDetail->in_qty =(double)$stockDetail->in_qty-(double)($grnDetailupdate[$i]->actual_qty*$ConversionFactor->present_factor);
              $stockDetail->save();

            }
            else if($stock->uom==$grnDetailupdate[$i]->uom){
            //  dd($grnDetailupdate[$i]);
              $stock->avaliable_qty =(double)$stock->avaliable_qty-(double)($grnDetailupdate[$i]->actual_qty);
              $stock->in_qty =(double)$stock->in_qty-(double)($grnDetailupdate[$i]->actual_qty);
              $updatedQty=(double)($grnDetailupdate[$i]->actual_qty);
              $stock->save();

              $stockDetail->avaliable_qty =(double)$stock->avaliable_qty-(double)($grnDetailupdate[$i]->actual_qty);
              $stockDetail->in_qty =(double)$stock->in_qty-(double)($grnDetailupdate[$i]->actual_qty);
              $stockDetail->save();

              //dd($stockDetail);
            }
          //dd($grnDetailupdate[$i]);
          $rmPlan=RMPlan::find($findStoreStockLine[0]->rm_plan_id);
          $rmPlan->status=0;
          $rmPlan->save();
          $rmPlanHeader=RMPlanHeader::where('rm_plan_header_id',$rmPlan->rm_plan_header_id)->update(['status' => 0]);
          $transaction = Transaction::where('trans_description', 'GRNCANCEL')->first();
          $stockTransaction=new StockTransaction();
          $stockTransaction->doc_header_id=$grnDetailupdate[$i]->grn_id;
          $stockTransaction->doc_detail_id=$grnDetailupdate[$i]->grn_detail_id;
          $stockTransaction->doc_type = $transaction->trans_code;
          $stockTransaction->style_id=$grnDetailupdate[$i]->style_id;
          $stockTransaction->shop_order_id =$grnDetailupdate[$i]->shop_order_id;
          $stockTransaction->shop_order_detail_id =$grnDetailupdate[$i]->shop_order_detail_id;
          $stockTransaction->sup_po_header_id=$grnDetailupdate[$i]->po_number;
          $stockTransaction->sup_po_details_id=$grnDetailupdate[$i]->po_details_id;
          //dd()
          $stockTransaction->size = $grnDetailupdate[$i]->size;
          $stockTransaction->color = $grnDetailupdate[$i]->color;
          $stockTransaction->stock_id=$findStoreStockLine[0]->stock_id;
          $stockTransaction->location=$grnDetailupdate[$i]->location;
          $stockTransaction->main_store=$grnDetailupdate[$i]->main_store;
          $stockTransaction->sub_store=$grnDetailupdate[$i]->sub_store;
          $stockTransaction->stock_detail_id=$findStoreStockLine[0]->stock_detail_id;
          $stockTransaction->bin=$findStoreStockLine[0]->bin;
          $stockTransaction->item_id=$grnDetailupdate[$i]->item_code;
          $stockTransaction->qty=$updatedQty;
          $stockTransaction->financial_year=$current_year;
          $stockTransaction->financial_month=$current_month;
          $stockTransaction->standard_price =$findStoreStockLine[0]->standard_price;
          $stockTransaction->purchase_price =$findStoreStockLine[0]->purchase_price;
          $stockTransaction->rm_plan_id=$findStoreStockLine[0]->rm_plan_id;
          $stockTransaction->uom=$findStoreStockLine[0]->uom;
          $stockTransaction->status=1;
          $stockTransaction->direction="-";
          $stockTransaction->save();
          }
          else if($grnDetailupdate[$i]->arrival_status=="PLANNED"){
            //dd($grnDetailupdate[$i]);
              $rmPlan=RMPlan::find($grnDetailupdate[$i]->rm_plan_id);
              $rmPlan->status=0;
              $rmPlan->save();
              $rmPlanHeader=RMPlanHeader::where('rm_plan_header_id',$rmPlan->rm_plan_header_id)->update(['status' => 0]);
          }



    }
        $this->updateExcessQty($id);
          $grnHeader = GrnHeader::where('grn_id', $id)->update(['status' => 0]);
          $grnDetailLines=GrnDetail::where('grn_id', $id)->get();
          foreach ($grnDetailLines as $value) {
          $value['status']=0;
          $value->save();
          }
          return response([
            'data' => [
              'message' => 'Inward Registery deactivate successfully.',
              'status'=>1
            ]
            ] );
          }
          }

        }


      public function updateExcessQty($grn_id){

        $grnDetailupdate=GrnHeader::join('store_grn_type','store_grn_header.grn_type','=','store_grn_type.grn_type_id')
        ->join('store_grn_detail','store_grn_header.grn_id','=','store_grn_detail.grn_id')
        ->where('store_grn_header.grn_id','=',$grn_id)->get();
        //dd(sizeof($grnDetailupdate));
                for($i=0;$i<sizeof($grnDetailupdate);$i++){
                  if($grnDetailupdate[$i]->arrival_status!="PLANNED"){
                    $findStoreStockLine=null;
                    if($grnDetailupdate[$i]->grn_type_code=="AUTO"){
                      $findStoreStockLine=Stock::where('item_id','=',$grnDetailupdate[$i]->item_code)
                                                    ->where('shop_order_id','=',$grnDetailupdate[$i]->shop_order_id)
                                                    ->where('style_id','=',$grnDetailupdate[$i]->style_id)
                                                    ->where('shop_order_detail_id','=',$grnDetailupdate[$i]->shop_order_detail_id)
                                                    ->where('store_id','=',$grnDetailupdate[$i]->main_store)
                                                    ->where('substore_id','=',$grnDetailupdate[$i]->sub_store)
                                                    ->where('location','=',$grnDetailupdate[$i]->location)
                                                    ->where('inventory_type','=',"AUTO")
                                                    ->get();
                        }
                      else if($grnDetailupdate[$i]->grn_type_code=="MANUAL"){
                          $findStoreStockLine=Stock::where('item_id','=',$grnDetailupdate[$i]->item_code)
                                                        //->where('shop_order_id','=',$grnDetailupdate[$i]->shop_order_id)
                                                        //->where('style_id','=',$grnDetailupdate[$i]->style_id)
                                                        //->where('shop_order_detail_id','=',$grnDetailupdate[$i]->shop_order_detail_id)
                                                        ->where('store_id','=',$grnDetailupdate[$i]->main_store)
                                                        ->where('substore_id','=',$grnDetailupdate[$i]->sub_store)
                                                        ->where('location','=',$grnDetailupdate[$i]->location)
                                                        ->where('inventory_type','=',"MANUAL")
                                                        ->get();
                            }
                    //dd(dd($grnDetailupdate[$i]->excess_qty));
              $stock=Stock::find($findStoreStockLine[0]->stock_id);
              //dd($stock);
              if($stock->uom!=$grnDetailupdate[$i]->uom){
                $_uom_unit_code=UOM::where('uom_id','=',$grnDetailupdate[$i]->uom)->pluck('uom_code');
                $_uom_base_unit_code=UOM::where('uom_id','=',$stock->uom)->pluck('uom_code');
                //get convertion equatiojn details
                $ConversionFactor=ConversionFactor::select('*')
                ->where('unit_code','=',$_uom_unit_code[0])
                ->where('base_unit','=',$_uom_base_unit_code[0])
                ->first();

                $stock->excess_qty =(double)$stock->excess_qty-(double)($grnDetailupdate[$i]->excess_qty*$ConversionFactor->present_factor);

              }
              if($stock->uom==$grnDetailupdate[$i]->uom){

                $stock->excess_qty =(double)$stock->excess_qty-(double)($grnDetailupdate[$i]->excess_qty);
              }

                $stock->save();

            }
         }


      }

      public function datatable_search($data){
       $locId=auth()->payload()['loc_id'];
        $start = $data['start'];
        $length = $data['length'];
        $draw = $data['draw'];
        $search = $data['search']['value'];
        $order = $data['order'][0];
        $order_column = $data['columns'][$order['column']]['data'];
        $order_type = $order['dir'];
        $grn_type=$data['grn_type'];
        $selection_list=null;
        $section_count=null;
      if($grn_type=="MANUAL"){
        //dd("man");
        $section_list=GrnHeader::select(DB::raw("DATE_FORMAT(store_grn_header.updated_date, '%d-%b-%Y') 'updated_date_'"),'store_grn_header.grn_number','store_grn_header.status', 'store_grn_detail.grn_id','merc_po_order_manual_header.po_number', 'org_supplier.supplier_name', 'org_store.store_name', 'org_substore.substore_name','store_grn_header.inv_number','usr_login.user_name','store_grn_type.grn_type_code')
        ->join('store_grn_detail', 'store_grn_detail.grn_id', '=', 'store_grn_header.grn_id')
        ->leftjoin('merc_po_order_manual_header','store_grn_header.po_number','=','merc_po_order_manual_header.po_id')
        ->join('store_grn_type','store_grn_header.grn_type','store_grn_type.grn_type_id')
        ->leftjoin('org_substore', 'store_grn_header.sub_store', '=', 'org_substore.substore_id')
        ->leftjoin('org_store', 'org_substore.store_id', '=', 'org_store.store_id')
        ->leftjoin('org_supplier', 'store_grn_header.sup_id', '=', 'org_supplier.supplier_id')
        ->leftjoin('usr_login','store_grn_detail.created_by','=','usr_login.user_id')
        ->where('store_grn_header.location','=',$locId)
        ->where('store_grn_type.grn_type_code','=',$grn_type)
        ->where(function($q) use($search) {
        $q->Where('supplier_name', 'like', $search.'%')
        ->orWhere('substore_name', 'like', $search.'%')
        ->orWhere('grn_number', 'like', $search.'%')
        ->orWhere('inv_number', 'like', $search.'%')
        ->orWhere('user_name', 'like', $search.'%')
        ->orWhere('merc_po_order_manual_header.po_number', 'like', $search.'%');
      })
        ->orderBy($order_column, $order_type)
        ->orderBy('store_grn_header.updated_date',$order_column.' DESC', $order_type)
        ->groupBy('store_grn_header.grn_id')
        ->offset($start)->limit($length)->get();
        $section_count = GrnHeader::join('store_grn_type','store_grn_header.grn_type','store_grn_type.grn_type_id')
        ->where('grn_number'  , 'like', $search.'%' )
        ->where('store_grn_type.grn_type_code','=',$grn_type)->count();

}
   else if($grn_type=="AUTO"){

                $section_list = GrnHeader::select(DB::raw("DATE_FORMAT(store_grn_header.updated_date, '%d-%b-%Y') 'updated_date_'"),'store_grn_header.grn_number','store_grn_header.status', 'store_grn_detail.grn_id','merc_po_order_header.po_number', 'org_supplier.supplier_name', 'org_store.store_name', 'org_substore.substore_name','store_grn_header.inv_number','usr_login.user_name','store_grn_type.grn_type_code')
                ->join('store_grn_detail', 'store_grn_detail.grn_id', '=', 'store_grn_header.grn_id')
                ->join('store_grn_type','store_grn_header.grn_type','store_grn_type.grn_type_id')
                ->leftjoin('merc_po_order_header','store_grn_header.po_number','=','merc_po_order_header.po_id')
                //.->leftjoin('merc_po_order_manual_header','store_grn_header.po_number','=','merc_po_order_manual_header.po_id')
                ->leftjoin('org_substore', 'store_grn_header.sub_store', '=', 'org_substore.substore_id')
                ->leftjoin('org_store', 'org_substore.store_id', '=', 'org_store.store_id')
                ->leftjoin('org_supplier', 'store_grn_header.sup_id', '=', 'org_supplier.supplier_id')
                ->leftjoin('usr_login','store_grn_detail.created_by','=','usr_login.user_id')
                ->where('store_grn_type.grn_type_code','=',$grn_type)
                ->where('store_grn_header.location','=',$locId)
                ->where(function($q) use($search) {
                $q->Where('supplier_name', 'like', $search.'%')
                ->orWhere('substore_name', 'like', $search.'%')
                ->orWhere('grn_number', 'like', $search.'%')
                ->orWhere('inv_number', 'like', $search.'%')
                ->orWhere('user_name', 'like', $search.'%')
                ->orWhere('merc_po_order_header.po_number', 'like', $search.'%');
              })
                ->orderBy($order_column, $order_type)
                ->orderBy('store_grn_header.updated_date',$order_column.' DESC', $order_type)
                ->groupBy('store_grn_header.grn_id')
                ->offset($start)->limit($length)
                ->get();
                $section_count = GrnHeader::join('store_grn_type','store_grn_header.grn_type','store_grn_type.grn_type_id')
                 ->where('grn_number'  , 'like', $search.'%' )
                ->where('store_grn_type.grn_type_code','=',$grn_type)->count();

}

        //->orWhere('style_description'  , 'like', $search.'%' )


        return [
          "draw" => $draw,
          "recordsTotal" => $section_count,
          "recordsFiltered" => $section_count,
          "data" => $section_list
        ];
      }

      public function validateSupplierTolerance($dataArr, $suppId){
        //  dd($dataArr);

        $poQty = 0;
        $qty = 0;
        foreach ($dataArr as $data){
          $qty += $data['qty'];
          $poQty += $data['req_qty'];

        }

        //Get Supplier Tolarance
        $supTol = SupplierTolarance::where('supplier_id', $suppId)->first();

        $tolQty = $poQty*($supTol->tolerance_percentage/100);
        $plusQty = $tolQty + $poQty;
        $minusQty = $poQty - $tolQty;
        if($qty >= $minusQty || $qty <= $plusQty){
          return true;
        }else{
          return false;
        }


      }

      public function addGrnLines(Request $request){
        // dd($request); exit;
        $lineCount = 0;

        //Check po lines selected
        foreach ($request['item_list'] as $rec){
          if($rec['item_select']){
            $lineCount++;
          }
        }

        if($lineCount > 0){
          if(!$request['id']){
            $grnHeader = new GrnHeader;
            $grnHeader->grn_number = 0;
            $grnHeader->po_number = $request->po_no;
            $grnHeader->save();
            $grnNo = $grnHeader->grn_id;
          }else{
            $grnNo = $request['id'];
          }

          $i = 1;
          foreach ($request['item_list'] as $rec){
            if($rec['item_select']){

              //$poData = new PoOrderDetails;
              $poData = PoOrderDetails::where('id', $rec['po_line_id'])->first();

              // dd($poData);

              $grnDetails = new GrnDetail;
              $grnDetails->grn_id = $grnNo;
              $grnDetails->grn_line_no = $i;
              $grnDetails->style_id = $poData->style;
              $grnDetails->sc_no = $poData->sc_no;
              $grnDetails->color = $poData->colour;
              $grnDetails->size = $poData->size;
              $grnDetails->uom = $poData->uom;
              $grnDetails->po_qty = $poData->req_qty;
              $grnDetails->i_rec_qty = (float)$rec['qty'];
              //$grnDetails->pre_qty = (float)$rec['qty'];
              $grnDetails->bal_qty = $poData->bal_qty - (float)$rec['qty'];
              $grnDetails->status = 0;
              $grnDetails->item_code = $poData->item_code;
              $grnDetails->save();

            }
            $i++;
          }

        }

        return response([
          'id' => $grnNo
        ]);
      }

      public function saveGrnBins(Request $request){
        dd($request);
        $grnData = GrnDetail::find($request->line_id);

      }

      public function receivedGrn(Request $request){
        $y=0;
        $responsedata;
        $error_type;
        $grnGroup=$request->grnGroup;
        $isAllRmdetailsAdded=GrnHeader::join('store_grn_detail','store_grn_detail.grn_id','=','store_grn_header.grn_id')
        ->join('item_master','store_grn_detail.item_code','=','item_master.master_id')
        ->join('item_category','item_master.category_id','=','item_category.category_id')
        ->leftjoin('store_rm_plan','store_rm_plan.grn_detail_id','=','store_grn_detail.grn_detail_id')
        ->where('store_grn_detail.grn_id','=',$grnGroup['grn_id'])->get();
        if($isAllRmdetailsAdded->isEmpty()){
          $mat_type=GrnHeader::join('store_grn_detail','store_grn_detail.grn_id','=','store_grn_header.grn_id')
          ->join('item_master','store_grn_detail.item_code','=','item_master.master_id')
          ->join('item_category','item_master.category_id','=','item_category.category_id')->first();
          if($mat_type->category_code=="FAB"){
            $error_type="Roll Plan Details";
          }
          else if($mat_type->category_code!="FAB"){
            $error_type="Trim Packing Details";
          }
          return response(['data' => [
            'type' => 'Error',
            'message' => 'Please Update '.$error_type,
            'grnId' => $grnGroup['grn_id'],
          ]
        ], Response::HTTP_CREATED);
      }
      else if(!$isAllRmdetailsAdded->isEmpty()){

        foreach ($isAllRmdetailsAdded as $value) {
          $isExisit=RMPlan::where('grn_detail_id','=',$value['grn_detail_id'])->get();
          if($isExisit->isEmpty()){
            if($value['category_code']=="FAB"){
              $error_type="Roll Plan";
            }
            else if($value['category_code']!="FAB"){
                $error_type="Trim Packing";
            }
            return response(['data' => [
              'type' => 'Error',
              'message' => 'Please Update '.$error_type,
              'grnId' => $grnGroup['grn_id'],
            ]
          ], Response::HTTP_CREATED);
        }

      }

    }
    //for conversion_factor_validation
    $grnDetails=GrnDetail::where('grn_id','=',$grnGroup['grn_id'])->get();
    $grn_type=$request->grnGroup['grn_type_code']["grn_type_code"];
    foreach ($grnDetails as $value) {
      $invUom=Item::find($value['item_code']);
      //dd($value['uom'],$invUom->inventory_uom);
      $validation_status_po_to_inventory=InventoryValidation::conversion_factor_validation($value['uom'],$invUom->inventory_uom);
      if($validation_status_po_to_inventory==false){
        return response(['data' => [
          'type' => 'Error',
          'message' => 'Please set Convertion factores',
          'grnId' => $grnGroup['grn_id'],
        ]
      ], Response::HTTP_CREATED);
      }
      if($grn_type=="AUTO"){
      $shopOrder_uom=ShopOrderDetail::find($value['shop_order_detail_id']);
      $validation_status_po_to_shop_order=InventoryValidation::conversion_factor_validation($value['uom'],$shopOrder_uom->purchase_uom);
      if($validation_status_po_to_shop_order==false){
        return response(['data' => [
          'type' => 'Error',
          'message' => 'Please set Convertion factores',
          'grnId' => $grnGroup['grn_id'],
        ]
      ], Response::HTTP_CREATED);
      }
    }

    }


    $header=GrnHeader::find($grnGroup['grn_id']);
    $header->arrival_status="RECEIVED";
    $header->update();
    $grnDetails=GrnDetail::where('grn_id','=',$grnGroup['grn_id'])->get();

    foreach ($grnDetails as $rec){
      $rec['arrival_status']="RECEIVED";
      $rec->update();
      $responsedata[$y]=$rec;
      $y++;
    }
    //dd("oo");
    //Update Stock Transaction
    $transaction = Transaction::where('trans_description', 'ARRIVAL')->first();

    //get current date and month
    $year=DB::select("SELECT YEAR(CURDATE())AS current_d");
    $month=DB::select("SELECT MONTHNAME(CURDATE()) as current_month");
    $current_year=$year["0"]->current_d;
    $current_month=$month['0']->current_month;
    $current_update_stock_qty=0;
    foreach ($grnDetails as $rec){
      //dd($grnDetails);
      //get po line details{}
      if($grn_type=="AUTO"){
      $poDetails = PoOrderDetails::find($rec['po_details_id']);
      $itemCode=Item::find($poDetails->item_code);

      $findStoreStockLine=DB::SELECT ("SELECT * FROM store_stock
        where item_id= ?
        AND shop_order_id=?
        AND style_id=?
        AND shop_order_detail_id=?
        AND store_id=?
        AND substore_id=?
        AND location=?
        AND inventory_type='AUTO'" ,[$poDetails->item_code,$poDetails->shop_order_id,$poDetails->style,$poDetails->shop_order_detail_id,$header->main_store,$header->sub_store,$header->location]);

        if($findStoreStockLine==null){
          $stock=new Stock();
          $stock->shop_order_id=$rec['shop_order_id'];
          $stock->shop_order_detail_id=$rec['shop_order_detail_id'];
          $stock->style_id = $poDetails->style;
          $stock->item_id=$poDetails->item_code;
          $stock->size = $poDetails->size;
          $stock->color =  $poDetails->colour;
          $stock->location = auth()->payload()['loc_id'];
          $stock->store_id = $header->main_store;
          $stock->substore_id =$header->sub_store;
          $stock->uom=$itemCode->inventory_uom;
          $stock->standard_price = $rec['standard_price'];
          $stock->purchase_price = $rec['purchase_price'];
          $stock->financial_year=$current_year;
          $stock->financial_month=$current_month;
          $stock->inventory_type="AUTO";
          if($rec['standard_price']!=(double)$rec['purchase_price']){
            //save data on price variation table
            $priceVariance= new PriceVariance;
            $priceVariance->item_id=$poDetails->item_code;
            $priceVariance->standard_price=$rec['standard_price'];
            $priceVariance->purchase_price =$rec['purchase_price'];
            $priceVariance->shop_order_id =$rec['shop_order_id'];
            $priceVariance->shop_order_detail_id =$rec['shop_order_detail_id'];
            $priceVariance->status =1;
            $priceVariance->save();
          }
          //check inventory uom and purchase uom varied each other
          if($poDetails->purchase_uom!=$itemCode->inventory_uom){
            $stock->uom = $itemCode->inventory_uom;
            $_uom_unit_code=UOM::where('uom_id','=',$itemCode->inventory_uom)->pluck('uom_code');
            $_uom_base_unit_code=UOM::where('uom_id','=',$poDetails->purchase_uom)->pluck('uom_code');

            //get convertion equatiojn details
            $ConversionFactor=ConversionFactor::select('*')
            ->where('unit_code','=',$_uom_unit_code[0])
            ->where('base_unit','=',$_uom_base_unit_code[0])
            ->first();
            // convert values according to the convertion rate
            $stock->avaliable_qty = (double)( $rec['i_rec_qty']*$ConversionFactor->present_factor);
            $stock->in_qty = (double)( $rec['i_rec_qty']*$ConversionFactor->present_factor);
            //$stock->out_qty = (double)( $grnDetails->i_rec_qty*$ConversionFactor->present_factor);
            $stock->excess_qty = (double)( $rec['excess_qty']*$ConversionFactor->present_factor);
            $current_update_stock_qty=(double)( $rec['i_rec_qty']*$ConversionFactor->present_factor);
          }
          //if inventory uom and purchase uom are the same
          if($poDetails->purchase_uom==$itemCode->inventory_uom){
            $stock->avaliable_qty = (double)($rec['i_rec_qty']);
            //$stock->out_qty = (double)($grnDetails->i_rec_qty);
            $stock->in_qty = (double)($rec['i_rec_qty']);
            $stock->excess_qty = (double)($rec['excess_qty']);
            $current_update_stock_qty=(double)($rec['i_rec_qty']);
          }

          $stock->status=1;
          //$shopOrder=ShopOrderDetail::find($rec['shop_order_detail_id']);
          //  $shopOrder->asign_qty=$stock->qty+$shopOrder->asign_qty;
          //$shopOrder->save();
          $stock->save();

        }
        else if($findStoreStockLine!=null){
          //find exaxt line in stock
          $stock=Stock::find($findStoreStockLine[0]->stock_id);
          //if previous standerd price and new price is same

          if($rec['standard_price']!=$rec['purchase_price']){
            $priceVariance= new PriceVariance;
            $priceVariance->item_id=$poDetails->item_code;
            $priceVariance->standard_price=$rec['standard_price'];
            $priceVariance->purchase_price =$rec['purchase_price'];
            $priceVariance->shop_order_id =$rec['shop_order_id'];
            $priceVariance->shop_order_detail_id =$rec['shop_order_detail_id'];
            $priceVariance->status =1;
            $priceVariance->save();
          }
          //check inventory uom and purchase uom varied each other
          if($poDetails->purchase_uom!=$itemCode->inventory_uom){
            $stock->uom = $rec['inventory_uom'];
            $_uom_unit_code=UOM::where('uom_id','=',$itemCode->inventory_uom)->pluck('uom_code');
            $_uom_base_unit_code=UOM::where('uom_id','=',$poDetails->purchase_uom)->pluck('uom_code');

            //get convertion equatiojn details
            $ConversionFactor=ConversionFactor::select('*')
            ->where('unit_code','=',$_uom_unit_code[0])
            ->where('base_unit','=',$_uom_base_unit_code[0])
            ->first();
            // convert values according to the convertion rate
            //update stock qty with convertion qty
            $stock->avaliable_qty =(double)$stock->avaliable_qty+(double)($rec['i_rec_qty']*$ConversionFactor->present_factor);
            $stock->in_qty =(double)$stock->in_qty+(double)($rec['i_rec_qty']*$ConversionFactor->present_factor);
            //$stock->out_qty =(double)$stock->out_qty+(double)($grnDetails->i_rec_qty*$ConversionFactor->present_factor);
            $stock->excess_qty =(double)$stock->excess_qty+(double)($rec['excess_qty']*$ConversionFactor->present_factor);
            $current_update_stock_qty=(double)($rec['i_rec_qty']*$ConversionFactor->present_factor);
          }
          //if inventory uom and purchase uom is same
          if($poDetails->purchase_uom==$itemCode->inventory_uom){
            $stock->avaliable_qty = (double)$stock->avaliable_qty+(double)($rec['i_rec_qty']);
            $stock->in_qty = (double)$stock->in_qty+(double)($rec['i_rec_qty']);
            //$stock->out_qty = (double)$stock->out_qty+(double)($grnDetails->i_rec_qty);
            $stock->excess_qty = (double)$stock->excess_qty+(double)($rec['excess_qty']);
            $current_update_stock_qty=(double)($rec['i_rec_qty']);
          }

          $stock->financial_year=$current_year;
          $stock->financial_month=$current_month;
          $stock->save();

        }

        $rmPlan=RMPlan::join('store_grn_detail','store_rm_plan.grn_detail_id','=','store_grn_detail.grn_detail_id')
        ->where('store_grn_detail.grn_detail_id','=',$rec['grn_detail_id'])->get();
        //dd($rmPlan);
          $this->pushTransactioDetails($rec,$rmPlan,$stock,$poDetails->purchase_uom,$itemCode->inventory_uom,$poDetails,$grn_type);

          }

          else if($grn_type=="MANUAL"){
          $poDetails = POManualDetails::find($rec['po_details_id']);
          $itemCode=Item::find($poDetails->inventory_part_id);

          $findStoreStockLine=DB::SELECT ("SELECT * FROM store_stock
            where item_id= ?
            AND store_id=?
            AND substore_id=?
            AND location=?
            AND inventory_type='MANUAL'",[$poDetails->inventory_part_id,$header->main_store,$header->sub_store,$header->location]);
            //dd($findStoreStockLine);


            if($findStoreStockLine==null){
              $stock=new Stock();
              //$stock->shop_order_id=$rec['shop_order_id'];
              //$stock->shop_order_detail_id=$rec['shop_order_detail_id'];
              //$stock->style_id = $poDetails->style;
              $stock->item_id=$poDetails->inventory_part_id;
              $stock->size = $itemCode->size_id;
              $stock->color =  $poDetails->color_id;
              $stock->location = auth()->payload()['loc_id'];
              $stock->store_id = $header->main_store;
              $stock->substore_id =$header->sub_store;
              $stock->uom=$itemCode->inventory_uom;
              $stock->standard_price = $rec['standard_price'];
              $stock->purchase_price = $rec['purchase_price'];
              $stock->financial_year=$current_year;
              $stock->financial_month=$current_month;
              if($rec['standard_price']!=(double)$rec['purchase_price']){
                //save data on price variation table
                $priceVariance= new PriceVariance;
                $priceVariance->item_id=$poDetails->inventory_part_id;
                $priceVariance->standard_price=$rec['standard_price'];
                $priceVariance->purchase_price =$rec['purchase_price'];
                //$priceVariance->shop_order_id =$rec['shop_order_id'];
                //$priceVariance->shop_order_detail_id =$rec['shop_order_detail_id'];
                $priceVariance->status =1;
                $priceVariance->save();
              }
              //check inventory uom and purchase uom varied each other
              if($poDetails->purchase_uom!=$itemCode->inventory_uom){
                $stock->uom = $itemCode->inventory_uom;
                $_uom_unit_code=UOM::where('uom_id','=',$itemCode->inventory_uom)->pluck('uom_code');
                $_uom_base_unit_code=UOM::where('uom_id','=',$poDetails->purchase_uom)->pluck('uom_code');

                //get convertion equatiojn details
                $ConversionFactor=ConversionFactor::select('*')
                ->where('unit_code','=',$_uom_unit_code[0])
                ->where('base_unit','=',$_uom_base_unit_code[0])
                ->first();
                // convert values according to the convertion rate
                $stock->avaliable_qty = (double)( $rec['i_rec_qty']*$ConversionFactor->present_factor);
                $stock->in_qty = (double)( $rec['i_rec_qty']*$ConversionFactor->present_factor);
                //$stock->out_qty = (double)( $grnDetails->i_rec_qty*$ConversionFactor->present_factor);
                $stock->excess_qty = (double)( $rec['excess_qty']*$ConversionFactor->present_factor);
                $current_update_stock_qty=(double)( $rec['i_rec_qty']*$ConversionFactor->present_factor);
              }
              //if inventory uom and purchase uom are the same
              if($poDetails->purchase_uom==$itemCode->inventory_uom){
                $stock->avaliable_qty = (double)($rec['i_rec_qty']);
                //$stock->out_qty = (double)($grnDetails->i_rec_qty);
                $stock->in_qty = (double)($rec['i_rec_qty']);
                $stock->excess_qty = (double)($rec['excess_qty']);
                $current_update_stock_qty=(double)($rec['i_rec_qty']);
              }

              $stock->status=1;
              $stock->inventory_type="MANUAL";
              //$shopOrder=ShopOrderDetail::find($rec['shop_order_detail_id']);
              //  $shopOrder->asign_qty=$stock->qty+$shopOrder->asign_qty;
              //$shopOrder->save();
              $stock->save();

            }
            else if($findStoreStockLine!=null){
              //find exaxt line in stock
              $stock=Stock::find($findStoreStockLine[0]->stock_id);
              //if previous standerd price and new price is same

              if($rec['standard_price']!=$rec['purchase_price']){
                $priceVariance= new PriceVariance;
                $priceVariance->item_id=$poDetails->inventory_part_id;
                $priceVariance->standard_price=$rec['standard_price'];
                $priceVariance->purchase_price =$rec['purchase_price'];
                //$priceVariance->shop_order_id =$rec['shop_order_id'];
                //$priceVariance->shop_order_detail_id =$rec['shop_order_detail_id'];
                $priceVariance->status =1;
                $priceVariance->save();
              }
              //check inventory uom and purchase uom varied each other
            if($poDetails->purchase_uom!=$itemCode->inventory_uom){
                $stock->uom = $itemCode->inventory_uom;
                $_uom_unit_code=UOM::where('uom_id','=',$itemCode->inventory_uom)->pluck('uom_code');
                $_uom_base_unit_code=UOM::where('uom_id','=',$poDetails->purchase_uom)->pluck('uom_code');
                //get convertion equatiojn details
                $ConversionFactor=ConversionFactor::select('*')
                ->where('unit_code','=',$_uom_unit_code[0])
                ->where('base_unit','=',$_uom_base_unit_code[0])
                ->first();
                // convert values according to the convertion rate
                //update stock qty with convertion qty
                $stock->avaliable_qty =(double)$stock->avaliable_qty+(double)($rec['i_rec_qty']*$ConversionFactor->present_factor);
                $stock->in_qty =(double)$stock->in_qty+(double)($rec['i_rec_qty']*$ConversionFactor->present_factor);
                //$stock->out_qty =(double)$stock->out_qty+(double)($grnDetails->i_rec_qty*$ConversionFactor->present_factor);
                $stock->excess_qty =(double)$stock->excess_qty+(double)($rec['excess_qty']*$ConversionFactor->present_factor);
                $current_update_stock_qty=(double)($rec['i_rec_qty']*$ConversionFactor->present_factor);
              }
              //if inventory uom and purchase uom is same
              if($poDetails->purchase_uom==$itemCode->inventory_uom){
                $stock->avaliable_qty = (double)$stock->avaliable_qty+(double)($rec['i_rec_qty']);
                $stock->in_qty = (double)$stock->in_qty+(double)($rec['i_rec_qty']);
                //$stock->out_qty = (double)$stock->out_qty+(double)($grnDetails->i_rec_qty);
                $stock->excess_qty = (double)$stock->excess_qty+(double)($rec['excess_qty']);
                $current_update_stock_qty=(double)($rec['i_rec_qty']);
              }

              $stock->financial_year=$current_year;
              $stock->financial_month=$current_month;
              $stock->save();

            }
            $rmPlan=RMPlan::join('store_grn_detail','store_rm_plan.grn_detail_id','=','store_grn_detail.grn_detail_id')
            ->where('store_grn_detail.grn_detail_id','=',$rec['grn_detail_id'])->get();

              $this->pushTransactioDetails($rec,$rmPlan,$stock,$poDetails->purchase_uom,$itemCode->inventory_uom,$poDetails,$grn_type);
              }




      }

      return response(['data' => [
        'type' => 'success',
        'message' => 'Items Received to Stores.',
        'grnId' => $header['grn_id'],
        'responceData'=>$responsedata,
      ]
    ], Response::HTTP_CREATED);


  }


  public function pushTransactioDetails($grnDetails,$rmPlan,$stock,$purchaseUom,$invUom,$poDetails,$grn_type){
    $stockArray = (array) $stock;
    //dd($stockArray);
    foreach ($rmPlan as $rec){
      $stockDetails= new StockDetails();
      $stockDetails->stock_id=$stock->stock_id;
      $stockDetails->bin=$rec['bin'];
      $stockDetails->location=$stock->location;
      $stockDetails->batch_no=$rec['batch_no'];
      $stockDetails->store_id=$stock->store_id;
      $stockDetails->substore_id=$stock->substore_id;
      $stockDetails->item_id=$stock->item_id;
      $stockDetails->financial_year=$stock->financial_year;
      $stockDetails->financial_month=$stock->financial_month;
      $stockDetails->rm_plan_id=$rec['rm_plan_id'];
      $stockDetails->barcode=$rec['barcode'];
      $stockDetails->parent_rm_plan_id=$rec['rm_plan_id'];
      $findRmLine=RMPlan::find($rec['rm_plan_id']);
      if($grnDetails['inspection_allowed']==0){
        $findRmLine->confirm_status="CONFIRMED";
        $findRmLine->save();
        $findRMheaderLine=RMPlanHeader::find($findRmLine->rm_plan_header_id);
        $findRMheaderLine->confirm_status="CONFIRMED";
        $findRMheaderLine->save();
      }
      if($grnDetails['inspection_allowed']==1){
        $findRMheaderLine=RMPlanHeader::find($findRmLine->rm_plan_header_id);
        $findRMheaderLine->confirm_status="RECEIVED";
        $findRMheaderLine->save();
      }
      if($purchaseUom!=$invUom){
        $_uom_unit_code=UOM::where('uom_id','=',$invUom)->pluck('uom_code');
        $_uom_base_unit_code=UOM::where('uom_id','=',$purchaseUom)->pluck('uom_code');

        //get convertion equatiojn details
        $ConversionFactor=ConversionFactor::select('*')
        ->where('unit_code','=',$_uom_unit_code[0])
        ->where('base_unit','=',$_uom_base_unit_code[0])
        ->first();
        $stockDetails->in_qty =(double)($rec['received_qty']*$ConversionFactor->present_factor);
        $stockDetails->avaliable_qty =(double)($rec['received_qty']*$ConversionFactor->present_factor);
      }
      else if ($purchaseUom==$invUom){
        $stockDetails->in_qty=$rec['received_qty'];
        $stockDetails->avaliable_qty=$rec['received_qty'];
      }
      $stockDetails->status=1;
      $stockDetails->inspection_status="PENDING";
      if($rec['is_excess']==0){
      $stockDetails->issue_status="PENDING";
      }
      if($rec['is_excess']==1){
        $stockDetails->issue_status="UNASINGNED";
      }
      $stockDetails->save();
      $transaction = Transaction::where('trans_description', 'ARRIVAL')->first();
      $stockTransaction=new StockTransaction();
      //$stockTransaction->transaction_id=$transactionHeader->transaction_id;
      $stockTransaction->doc_header_id=$grnDetails['grn_id'];
      $stockTransaction->doc_detail_id=$grnDetails['grn_detail_id'];
      $stockTransaction->doc_type = $transaction->trans_code;
      if($rec['is_excess']==1){
      $stockTransaction->is_un_assigned="UNASINGNED";
      }
      if($grn_type="AUTO"){
      //$stockTransaction->style_id=$poDetails->style;
      //$stockTransaction->shop_order_id =$grnDetails['shop_order_id'];
      //$stockTransaction->shop_order_detail_id =$grnDetails['shop_order_detail_id'];
      $stockTransaction->sup_po_header_id=$poDetails->po_header_id;
      $stockTransaction->sup_po_details_id=$poDetails->id;
      $stockTransaction->size = $poDetails->size;
      $stockTransaction->color = $poDetails->colour;
      $stockTransaction->stock_id=$stock->stock_id;
      $stockTransaction->location=$stock->location;
      $stockTransaction->main_store=$stock->store_id;
      $stockTransaction->sub_store=$stock->substore_id;
    }

    else if($grn_type="MANUAL"){
      $stockTransaction->style_id=$poDetails->style;
      $stockTransaction->shop_order_id =$grnDetails['shop_order_id'];
      $stockTransaction->shop_order_detail_id =$grnDetails['shop_order_detail_id'];
      $stockTransaction->sup_po_header_id=$poDetails->po_header_id;
      $stockTransaction->sup_po_details_id=$poDetails->id;
      $stockTransaction->size = $stock->size;
      $stockTransaction->color = $stock->color;
      $stockTransaction->stock_id=$stock->stock_id;
      $stockTransaction->location=$stock->location;
      $stockTransaction->main_store=$stock->store_id;
      $stockTransaction->sub_store=$stock->substore_id;

    }
      $stockTransaction->stock_detail_id=$stockDetails->stock_detail_id;
      $stockTransaction->bin=$rec['bin'];
      $stockTransaction->item_id=$stock->item_id;
      $stockTransaction->qty=$stockDetails->in_qty;
      $stockTransaction->financial_year=$stock->financial_year;
      $stockTransaction->financial_month=$stock->financial_month;
      $stockTransaction->standard_price =(double)$grnDetails['standard_price'];
      $stockTransaction->purchase_price =(double)$grnDetails['purchase_price'];
      $stockTransaction->rm_plan_id=$rec['rm_plan_id'];
      $stockTransaction->uom=$stock->uom;
      $stockTransaction->status=1;
      $stockTransaction->direction="+";
      $stockTransaction->save();



    }

  }



  public function update(Request $request, $id)
  {
    //save grn header
    $y=0;
    $current_update_stock_qty=0;
    $responseData=[];
    $header=$request->header;
    $dataset=$request->dataset;
    //get current date and month
    $year=DB::select("SELECT YEAR(CURDATE())AS current_d");
    $month=DB::select("SELECT MONTHNAME(CURDATE()) as current_month");
    $current_year=$year["0"]->current_d;
    $current_month=$month['0']->current_month;
    $grnHeader=GrnHeader::find($id);
    //$grnHeader['batch_no']=$header['batch_no'];
    $grnHeader['sub_store']=$header['sub_store']['substore_id'];
    $grnHeader['note']=$header['note'];
    $grnHeader->save();
    $grn_type=$grnHeader->grn_type;
    //dd($dataset);
    //loop through data set
    for($i=0;$i<sizeof($dataset);$i++){

      //if data set have grn id (updaated line with several new lines)
      if(isset($dataset[$i]['grn_detail_id'])==true){
        //find related grn line in detail table
        $grnDetails=GrnDetail::find($dataset[$i]['grn_detail_id']);
        //update grn qtys
        //$grnDetails['i_rec_qty']=(float)$dataset[$i]['qty'];
        $grnDetails['i_rec_qty'] = (float)$dataset[$i]['qty'];
        $grnDetails['bal_qty']=(float)$dataset[$i]['bal_qty'];
        $grnDetails['excess_qty']=(double)$dataset[$i]['excess_qty'];
        $grnDetails->save();


        //cretate responce data array
        $responseData[$y]=$grnDetails;
      }
      //if dataset line dont have grn id
      else if(isset($dataset[$i]['grn_detail_id'])==false){
        //find po details line

        //get next grn line no reated to the header
        $max_line_no=DB::table('store_grn_detail')->where('grn_id','=',$id)
        ->max('grn_line_no');
        //save grn details
        $grnDetails = new GrnDetail;
        if($grn_type=="AUTO"){
        $poDetails = PoOrderDetails::find($dataset[$i]['id']);
        $grnDetails->grn_id =$id;
        $grnDetails->po_number=$header['po_id'];
        $grnDetails->grn_line_no = $max_line_no++;
        $grnDetails->style_id = $poDetails->style;
        $grnDetails->po_details_id=$dataset[$i]['id'];
        $grnDetails->combine_id = $poDetails->comb_id;
        $grnDetails->color = $poDetails->colour;
        $grnDetails->size = $poDetails->size;
        $grnDetails->uom = $poDetails->purchase_uom;
        $grnDetails->po_qty = (double)$poDetails->req_qty;
        //$grnDetails->grn_qty = $dataset[$i]['qty'];
        $grnDetails->i_rec_qty = $dataset[$i]['qty'];
        $grnDetails->bal_qty =(double)$dataset[$i]['bal_qty'];
        $grnDetails->maximum_tolarance =$dataset[$i]['maximum_tolarance'];
        $grnDetails->original_bal_qty=(double)$dataset[$i]['original_bal_qty'];
        $grnDetails->item_code = $poDetails->item_code;
        $grnDetails->excess_qty=(double)$dataset[$i]['excess_qty'];
        $grnDetails->customer_po_id=$dataset[$i]['cus_order_details_id'];
        $grnDetails->standard_price =(double)$dataset[$i]['standard_price'];
        $grnDetails->purchase_price =(double)$dataset[$i]['purchase_price'];
        $grnDetails->shop_order_id =$dataset[$i]['shop_order_id'];
        $grnDetails->shop_order_detail_id =$dataset[$i]['shop_order_detail_id'];
        $grnDetails->inventory_uom =$dataset[$i]['inventory_uom'];
        $grnDetails->status=1;
        $grnDetails->arrival_status="PLANNED";
      }
      else if($grn_type=="MANUAL"){
       $poDetails=POManualDetails::find($dataset[$i]['id']);
       $grnDetails->grn_id = $request->header['grn_id'];
       $grnDetails->po_number=$request->header['po_id'];
       $grnDetails->grn_line_no = $max_line_no++;
       //$grnDetails->style_id = $poDetails->style;
       $grnDetails->po_details_id=$dataset[$i]['id'];
       //$grnDetails->combine_id = $poDetails->comb_id;
       $grnDetails->color = $dataset[$i]['color_id'];
       $grnDetails->size = $dataset[$i]['size_id'];
       $grnDetails->uom = $poDetails->purchase_uom;
       $grnDetails->po_qty = (double)$poDetails->qty;
       $grnDetails->item_code = $dataset[$i]['master_id'];
       //$grnDetails->grn_qty = $rec['qty'];
       $grnDetails->i_rec_qty = $dataset[$i]['qty'];
       $grnDetails->bal_qty =(double)$dataset[$i]['bal_qty'];
       $grnDetails->original_bal_qty=(double)$dataset[$i]['original_bal_qty'];
       $grnDetails->maximum_tolarance =$dataset[$i]['maximum_tolarance'];
       //$grnDetails->customer_po_id=$rec['cus_order_details_id'];
       $grnDetails->excess_qty=(double)$dataset[$i]['excess_qty'];
       $grnDetails->standard_price =(double)$dataset[$i]['standard_price'];
       $grnDetails->purchase_price =(double)$dataset[$i]['purchase_price'];
       //$grnDetails->shop_order_id =$rec['shop_order_id'];
       //$grnDetails->shop_order_detail_id=$rec['shop_order_detail_id'];
       $grnDetails->inventory_uom =$dataset[$i]['uom_id'];
       $grnDetails->status=1;
       $grnDetails->arrival_status="PLANNED";
      }
        //add newly
        //if new line is not allowed for isnpection directly update the stock table
        if(empty($dataset[$i]['inspection_allowed'])==true|| $dataset[$i]['inspection_allowed']==0){
          $grnDetails->inspection_allowed=0;
        }
        else if(empty($dataset[$i]['inspection_allowed'])==false|| $dataset[$i]['inspection_allowed']==1){
          $grnDetails->inspection_allowed=1;
        }
        //find related stock line


        $grnDetails->save();
        //Update Stock Transaction

        $responseData[$y]=$grnDetails;
      }
      $y++;
    }



    //dd($header['grn_id']);


    return response(['data' => [
      'type' => 'success',
      'message' => 'Updated Successfully.',
      'grnId' => $header['grn_id'],
      'detailData'=>$responseData
    ]
  ], Response::HTTP_CREATED);




}

public function show($id)
{

    $get_grn_type=GrnHeader::join('store_grn_type','store_grn_header.grn_type','=','store_grn_type.grn_type_id')
    ->where('store_grn_header.grn_id','=',$id)
    ->select('store_grn_type.*')
    ->first();
    //dd($get_grn_type);
    $status=null;
    $headerData=null;
    $sub_store=null;
    $detailsData=null;
    $detailsData=null;

    if($get_grn_type->grn_type_code=="AUTO"){
    $status=1;
  $headerData=DB::SELECT("SELECT store_grn_header.*, merc_po_order_header.po_number,merc_po_order_header.po_id,org_supplier.supplier_name,org_substore.substore_name,org_location.loc_name
    FROM
    store_grn_header
    INNER JOIN merc_po_order_header ON store_grn_header.po_number=merc_po_order_header.po_id
    INNER JOIN org_supplier ON store_grn_header.sup_id=org_supplier.supplier_id
    INNER JOIN org_location on merc_po_order_header.po_deli_loc=org_location.loc_id
    INNER JOIN org_substore ON store_grn_header.sub_store=org_substore.substore_id
    WHERE store_grn_header.grn_id=$id"
  );
  //dd();
  $sub_store=SubStore::find($headerData[0]->sub_store);

  $detailsData=DB::SELECT("SELECT DISTINCT  store_grn_detail.grn_detail_id, store_grn_detail.grn_id, store_grn_detail.grn_line_no,
     store_grn_detail.style_id, store_grn_detail.combine_id, store_grn_detail.color, store_grn_detail.size, store_grn_detail.uom,
     store_grn_detail.item_code, store_grn_detail.po_qty, store_grn_detail.grn_qty,store_grn_detail.i_rec_qty,
     store_grn_detail.po_details_id, store_grn_detail.shop_order_id, store_grn_detail.shop_order_detail_id,
     store_grn_detail.po_number,store_grn_detail.customer_po_id, store_grn_detail.excess_qty,
     store_grn_detail.status,store_grn_detail.created_date, store_grn_detail.created_by,
     store_grn_detail.updated_date, store_grn_detail.updated_by, store_grn_detail.user_loc_id, store_grn_detail.standard_price,
     store_grn_detail.purchase_price, store_grn_detail.inventory_uom, store_grn_detail.inspection_allowed, store_grn_detail.arrival_status,
     store_grn_detail.inspection_pass_qty ,style_creation.style_no,merc_customer_order_header.order_id,cust_customer.customer_name,org_color.color_name,merc_po_order_details.req_qty,store_grn_detail.i_rec_qty as qty,store_grn_detail.i_rec_qty as pre_qty,store_grn_detail.po_number as po_id,merc_po_order_details.id,merc_customer_order_details.details_id as cus_order_details_id,  org_size.size_name,org_uom.uom_code,item_master.master_description,item_master.master_code,item_master.category_id,item_master.width,store_grn_detail.uom,item_category.category_code,store_grn_detail.excess_qty as pre_excess_qty,

    (SELECT
            IFNULL(SUM(SGD.i_rec_qty),0)
                    FROM
             store_grn_header AS SGH
            JOIN store_grn_detail as SGD on SGD.grn_id=SGH.grn_id
            WHERE
            SGD.po_details_id = merc_po_order_details.id
            AND SGH.grn_type='AUTO'
          ) AS tot_i_rec_qty,

          (merc_po_order_details.req_qty-(SELECT
                  IFNULL(SUM(SGD.i_rec_qty),0)
                          FROM
                  store_grn_header AS SGH
                  JOIN store_grn_detail as SGD on SGD.grn_id=SGH.grn_id
                    WHERE
                  SGD.po_details_id = merc_po_order_details.id
                  AND SGH.grn_type='AUTO'
                )
                ) AS bal_qty,

                (merc_po_order_details.req_qty-(
                          SELECT
                        IFNULL(SUM(SGD.i_rec_qty),0)
                                FROM
                          store_grn_header AS SGH
                          JOIN store_grn_detail as SGD on SGD.grn_id=SGH.grn_id
                                 WHERE
                        SGD.po_details_id = merc_po_order_details.id
                      AND SGH.grn_type='AUTO'
                    )
                      ) AS original_bal_qty,

                      (

                    SELECT
                    ((IFNULL(sum(merc_po_order_details.req_qty*for_uom.max/100),0))+merc_po_order_details.req_qty )as maximum_tolarance
                    FROM
                    org_supplier_tolarance AS for_uom
                    WHERE
                    for_uom.supplier_id=org_supplier.supplier_id and
                    merc_po_order_details.req_qty BETWEEN for_uom.min_qty AND for_uom.max_qty
                    ) AS maximum_tolarance


    from
    store_grn_header
    JOIN store_grn_detail ON store_grn_header.grn_id=store_grn_detail.grn_id
    JOIN style_creation ON store_grn_detail.style_id=style_creation.style_id
    JOIN cust_customer ON style_creation.customer_id=cust_customer.customer_id
    INNER JOIN merc_customer_order_header ON style_creation.style_id = merc_customer_order_header.order_style
    INNER JOIN merc_customer_order_details ON merc_customer_order_header.order_id = merc_customer_order_details.order_id
    LEFT JOIN org_color ON store_grn_detail.color=org_color.color_id
    LEFT JOIN org_size ON  store_grn_detail.size= org_size.size_id
    LEFT JOIN org_uom ON store_grn_detail.uom=org_uom.uom_id
    JOIN  item_master ON store_grn_detail.item_code= item_master.master_id
    INNER JOIN item_category ON item_master.category_id=item_category.category_id
    JOIN merc_po_order_header ON store_grn_detail.po_number=merc_po_order_header.po_id
    join org_supplier on merc_po_order_header.po_sup_code=org_supplier.supplier_id
    LEFT JOIN org_supplier_tolarance as for_category ON merc_po_order_header.po_sup_code=for_category.supplier_id
    JOIN  merc_po_order_details ON store_grn_detail.po_details_id=merc_po_order_details.id
    WHERE store_grn_header.grn_id=$id
    AND store_grn_detail.status= $status
    GROUP BY(merc_po_order_details.id)
    order By(merc_customer_order_details.rm_in_date)DESC
    ");
  }
  else if($get_grn_type->grn_type_code=="MANUAL"){
  $status=1;
  $headerData=DB::SELECT("SELECT store_grn_header.*, merc_po_order_manual_header.po_number,merc_po_order_manual_header.po_id,org_supplier.supplier_name,org_substore.substore_name,org_location.loc_name
    FROM
    store_grn_header
    INNER JOIN merc_po_order_manual_header ON store_grn_header.po_number=merc_po_order_manual_header.po_id
    INNER JOIN org_supplier ON store_grn_header.sup_id=org_supplier.supplier_id
    INNER JOIN org_location on merc_po_order_manual_header.deliver_to=org_location.loc_id
    INNER JOIN org_substore ON store_grn_header.sub_store=org_substore.substore_id
    WHERE store_grn_header.grn_id=$id"
  );
  $sub_store=SubStore::find($headerData[0]->sub_store);

  $detailsData=DB::SELECT("SELECT DISTINCT  store_grn_detail.grn_detail_id, store_grn_detail.grn_id, store_grn_detail.grn_line_no,
    store_grn_detail.style_id, store_grn_detail.combine_id, store_grn_detail.color, store_grn_detail.size, store_grn_detail.uom,
     store_grn_detail.item_code, store_grn_detail.po_qty, store_grn_detail.grn_qty, store_grn_detail.i_rec_qty,store_grn_detail.po_details_id,
      store_grn_detail.shop_order_id, store_grn_detail.shop_order_detail_id, store_grn_detail.po_number, store_grn_detail.customer_po_id,
      store_grn_detail.excess_qty, store_grn_detail.status, store_grn_detail.created_date, store_grn_detail.created_by, store_grn_detail.updated_date,
       store_grn_detail.updated_by, store_grn_detail.user_loc_id, store_grn_detail.standard_price, store_grn_detail.purchase_price,
        store_grn_detail.inventory_uom, store_grn_detail.inspection_allowed, store_grn_detail.arrival_status,
        store_grn_detail.inspection_pass_qty ,org_color.color_name,store_grn_detail.po_qty as req_qty,
        store_grn_detail.i_rec_qty as qty,store_grn_detail.i_rec_qty as pre_qty,store_grn_detail.po_number as po_id,org_size.size_name,org_uom.uom_code,
        item_master.master_description,item_master.width,item_master.master_code,item_master.category_id,store_grn_detail.uom,item_category.category_code,
        store_grn_detail.excess_qty as pre_excess_qty,merc_po_order_manual_details.id,org_color.color_id,org_size.size_id,
    (SELECT
      IFNULL(SUM(SGD.i_rec_qty),0)
      FROM
      store_grn_header AS SGH
      JOIN store_grn_detail as SGD on SGD.grn_id=SGH.grn_id
      WHERE
      SGD.po_details_id = merc_po_order_manual_details.id
      AND SGH.grn_type='MANUAL'

     ) AS tot_i_rec_qty,

    (merc_po_order_manual_details.qty-(SELECT
                         IFNULL(SUM(SGD.i_rec_qty),0)
                           FROM
                          store_grn_header AS SGH
                          JOIN store_grn_detail as SGD on SGD.grn_id=SGH.grn_id
                          WHERE
                         SGD.po_details_id = merc_po_order_manual_details.id
                         AND SGH.grn_type='MANUAL')
       ) AS bal_qty,
    (merc_po_order_manual_details.qty-(SELECT
                             IFNULL(SUM(SGD.i_rec_qty),0)
                               FROM
                              store_grn_header AS SGH
                              JOIN store_grn_detail as SGD on SGD.grn_id=SGH.grn_id
                              WHERE
                             SGD.po_details_id = merc_po_order_manual_details.id
                           AND SGH.grn_type='MANUAL')
     ) AS original_bal_qty,
     (
   SELECT
   ((IFNULL(sum(merc_po_order_manual_details.qty*for_uom.max/100),0))+merc_po_order_manual_details.qty )as maximum_tolarance
   FROM
   org_supplier_tolarance AS for_uom
   WHERE
   for_uom.supplier_id=org_supplier.supplier_id and
   merc_po_order_manual_details.qty BETWEEN for_uom.min_qty AND for_uom.max_qty
   ) AS maximum_tolarance
    from
    store_grn_header
    JOIN store_grn_detail ON store_grn_header.grn_id=store_grn_detail.grn_id
    LEFT JOIN org_color ON store_grn_detail.color=org_color.color_id
    LEFT JOIN org_size ON  store_grn_detail.size= org_size.size_id
    LEFT JOIN org_uom ON store_grn_detail.uom=org_uom.uom_id
    JOIN  item_master ON store_grn_detail.item_code= item_master.master_id
    INNER JOIN item_category ON item_master.category_id=item_category.category_id
    JOIN merc_po_order_manual_header ON store_grn_detail.po_number=merc_po_order_manual_header.po_id
    JOIN  merc_po_order_manual_details ON store_grn_detail.po_details_id=merc_po_order_manual_details.id
    INNER JOIN org_supplier ON merc_po_order_manual_header.po_sup_id=org_supplier.supplier_id
    LEFT JOIN org_supplier_tolarance AS for_category ON merc_po_order_manual_header.po_sup_id = for_category.supplier_id
    WHERE store_grn_header.grn_id=$id
    AND store_grn_detail.status= $status
    GROUP BY(merc_po_order_manual_details.id)",[$id,$status]);

  }
    return response([
      'data' =>[
        'headerData'=>  $headerData[0],
        'detailsData'=>$detailsData,
        'sub_store'=>$sub_store,
        'grn_type_code'=>$get_grn_type
      ]
    ]);

  }



  //validate anything based on requirements
  public function validate_data(Request $request){

    $for = $request->for;
    if($for == 'duplicate')
    {
      return response($this->validate_duplicate_code($request->grn_id, $request->invoice_no));
    }
  }


  //check customer code already exists
  private function validate_duplicate_code($id,$code)
  {
    $grnHeader = GrnHeader::where('inv_number','=',$code)->first();
    if($grnHeader == null){
      return ['status' => 'success'];
    }
    else if($grnHeader->grn_id == $id){
      return ['status' => 'success'];
    }
    else {
      return ['status' => 'error','message' => 'Invoice Number already exists'];
    }
  }

  public function filterData(Request $request){

    $customer_id=$request['customer_name']['customer_id'];
    $customer_po=$request['customer_po']['order_id'];
    $color=$request['color']['color_name'];
    $itemDesacription=$request['item_description']['master_id'];
    $pcd=$request['pcd_date'];
    $rm_in_date=$request['rm_in_date'];
    $po_id=$request['po_id'];
    $supplier_id=$request['supplier_id'];
    $grn_type_id=$request['grn_type_code'];
    $po_status="CONFIRMED";

    if($grn_type_id=="AUTO"){
    $poData=DB::Select("SELECT DISTINCT style_creation.style_no,
      cust_customer.customer_name,merc_po_order_header.po_id,merc_po_order_details.id,
      item_master.master_description,
      org_color.color_name,
      org_size.size_name,
      org_uom.uom_code,
      merc_po_order_details.req_qty,
      DATE_FORMAT(merc_customer_order_details.rm_in_date, '%d-%b-%Y')as rm_in_date,
      DATE_FORMAT(merc_customer_order_details.pcd, '%d-%b-%Y')as pcd,
      merc_customer_order_details.po_no,
      merc_customer_order_header.order_id,
      item_master.master_id,
      item_master.category_id,
      merc_customer_order_details.details_id as cus_order_details_id,
      merc_shop_order_header.shop_order_id,
      merc_shop_order_detail.shop_order_detail_id,
      item_master.category_id,
      item_master.master_code,
      item_master.width,
      merc_po_order_details.purchase_price,
      item_master.standard_price,
      item_master.inventory_uom,
      (SELECT
        SUM(SGD.i_rec_qty)
        FROM
        store_grn_detail AS SGD

        WHERE
        SGD.po_details_id = merc_po_order_details.id
        group By(SGD.po_details_id)
      ) AS tot_i_rec_qty,

      (SELECT
        bal_qty
        FROM
        store_grn_detail AS SGD2

        WHERE
        SGD2.po_details_id = merc_po_order_details.id
        group By(SGD2.po_details_id)
      ) AS bal_qty,
      (

        SELECT
        IFNULL(sum(for_uom.max ),0)as maximum_tolarance
        FROM
        org_supplier_tolarance AS for_uom
        WHERE
        for_uom.uom_id =  org_uom.uom_id AND
        for_uom.category_id = item_master.category_id AND
        for_uom.subcategory_id = item_master.subcategory_id
      ) AS maximum_tolarance


      FROM
      merc_po_order_header
      INNER JOIN merc_po_order_details ON merc_po_order_header.po_number = merc_po_order_details.po_no
      INNER JOIN style_creation ON merc_po_order_details.style = style_creation.style_id
      INNER JOIN cust_customer ON style_creation.customer_id = cust_customer.customer_id
      INNER JOIN merc_shop_order_detail on merc_po_order_details.shop_order_detail_id=merc_shop_order_detail.shop_order_detail_id
      INNER JOIN merc_shop_order_header on  merc_shop_order_detail.shop_order_id=merc_shop_order_header.shop_order_id
      INNER JOIN merc_shop_order_delivery on merc_shop_order_header.shop_order_id=merc_shop_order_delivery.shop_order_id
      INNER JOIN merc_customer_order_details ON merc_shop_order_delivery.delivery_id = merc_customer_order_details.details_id
      INNER JOIN merc_customer_order_header ON merc_customer_order_details.order_id = merc_customer_order_header.order_id
      INNER JOIN item_master ON merc_po_order_details.item_code = item_master.master_id
      LEFT JOIN org_supplier_tolarance AS for_category ON item_master.category_id = for_category.category_id
      LEFT JOIN org_color ON merc_po_order_details.colour = org_color.color_id
      LEFT JOIN org_size ON merc_po_order_details.size = org_size.size_id
      LEFT JOIN org_uom ON merc_po_order_details.purchase_uom = org_uom.uom_id
      WHERE merc_po_order_header.po_id = $po_id
      AND merc_po_order_header.po_sup_code=$supplier_id
      AND merc_po_order_details.po_status='CONFIRMED'
      AND merc_customer_order_header.order_id like  '%".$customer_po."%'
      AND cust_customer.customer_id like  '%".$customer_id."%'
      AND item_master.master_id like '%".$itemDesacription."%'
      AND merc_customer_order_details.pcd like '%".$pcd."%'
      AND merc_customer_order_details.rm_in_date like '%".$rm_in_date."%'
      AND merc_po_order_details.req_qty>(SELECT
        IFNULL(SUM(SGD.i_rec_qty),0)
        FROM
        store_grn_detail AS SGD

        WHERE
        SGD.po_details_id = merc_po_order_details.id
      )
      AND (org_color.color_name IS NULL or  org_color.color_name like  '%".$color."%')
      GROUP BY merc_po_order_details.id");
    }
    else if($grn_type_id=="MANUAL"){
      $po_status="CONFIRMED";
      $poData=DB::SELECT("SELECT item_master.master_code,item_master.width,item_master.master_id,
      item_master.master_description,item_master.inventory_uom,merc_po_order_manual_details.qty as req_qty,
      org_uom.uom_code,merc_po_order_manual_header.*,
      merc_po_order_manual_details.id,
      merc_po_order_manual_details.po_header_id,
      merc_po_order_manual_details.line_no,
      merc_po_order_manual_details.inventory_part_id,
      merc_po_order_manual_details.part_code,
      merc_po_order_manual_details.description,
      merc_po_order_manual_details.uom,
      merc_po_order_manual_details.uom_id,
      merc_po_order_manual_details.purchase_uom,
      merc_po_order_manual_details.purchase_uom_code,
      merc_po_order_manual_details.standard_price,
      merc_po_order_manual_details.purchase_price,
      merc_po_order_manual_details.qty,
      merc_po_order_manual_details.req_date,
      merc_po_order_manual_details.total_value,
      merc_po_order_manual_details.po_status,
      merc_po_order_manual_details.created_date,
      merc_po_order_manual_details.created_by,
      merc_po_order_manual_details.updated_date,
      merc_po_order_manual_details.updated_by,
      merc_po_order_manual_details.user_loc_id,
      merc_po_order_manual_details.po_inv_type,
      merc_po_order_manual_details.status,
      item_category.category_code,
      item_category.category_id,
      org_color.color_name,
      org_size.size_name,
        (SELECT
          IFNULL(SUM(SGD.i_rec_qty),0)
          FROM
          store_grn_detail AS SGD
          WHERE
          SGD.po_details_id = merc_po_order_manual_details.id
         ) AS tot_i_rec_qty,

         (merc_po_order_manual_details.qty-(SELECT
                          IFNULL(SUM(SGD.i_rec_qty),0)
                            FROM
                          store_grn_detail AS SGD
                           WHERE
                          SGD.po_details_id = merc_po_order_manual_details.id)
          ) AS bal_qty,
          (
        SELECT
        ((IFNULL(sum(merc_po_order_manual_details.qty*for_uom.max/100),0))+merc_po_order_manual_details.qty )as maximum_tolarance
        FROM
        org_supplier_tolarance AS for_uom
        WHERE
        for_uom.supplier_id=org_supplier.supplier_id and
        merc_po_order_manual_details.qty BETWEEN for_uom.min_qty AND for_uom.max_qty
        ) AS maximum_tolarance

       From
        merc_po_order_manual_header
        INNER JOIN merc_po_order_manual_details on merc_po_order_manual_header.po_id=merc_po_order_manual_details.po_header_id
        INNER JOIN org_uom on merc_po_order_manual_details.purchase_uom=org_uom.uom_id
        INNER JOIN item_master on merc_po_order_manual_details.inventory_part_id=item_master.master_id
        INNER JOIN item_category ON item_master.category_id=item_category.category_id
        INNER JOIN org_supplier ON merc_po_order_manual_header.po_sup_id=org_supplier.supplier_id
        LEFT JOIN org_supplier_tolarance AS for_category ON merc_po_order_manual_header.po_sup_id = for_category.supplier_id
        LEFT JOIN org_color on item_master.color_id=org_color.color_id
        LEFT JOIN org_size on item_master.size_id=org_size.size_id
        where merc_po_order_manual_header.po_id=?
        AND merc_po_order_manual_details.inventory_part_id like '%".$itemDesacription."%'
        AND (org_color.color_name IS NULL or  org_color.color_name like  '%".$color."%')
         ",[$po_id,$po_status]);

    }

      return response([
        'data' => $poData
      ]);
      ///return $poData;







    }


    public function deleteLine(Request $request){
      //dd($request->line);
      $grnDetails = GrnDetail::find($request->line);
      $grnDetails->status=0;
      $grnDetails->bal_qty=$grnDetails->$grnDetails->po_qty;
      $grnDetails->save();
      return response([
        'data' => [
          'status'=>1,
          'message'=>"Selected GRN line Deleted"
        ]
      ]);
    }

    public function getPoSCList(Request $request){
      dd($request);
      //echo 'xx';
      exit;
    }

    public function getAddedBins(Request $request){
      //dd($request);
      $grnData = GrnDetail::getGrnLineDataWithBins($request->id);

      return response([
        'data' => $grnData
      ]);
      //$grnData = GrnDetail::where('id', $request->lineId)->first();
      //dd($grnData);
    }

    public function loadAddedGrnLInes(Request $request){
      $grnLines = GrnHeader::getGrnLineData($request);

      return response([
        'data' => $grnLines
      ]);
    }
    public function isreadyForTrimPackingDetails(Request $request){
      $is_type_fabric=DB::table('item_category')->select('category_code')->where('category_id','=',$request->category_id)->first();
      $substorewiseBins=DB::table('org_substore')->select('*')->where('substore_id','=',$request->substore_id)->get();
      $status=0;
      $message="";
      $is_grn_same_qty=DB::table('store_grn_header')
      ->select('*')
      ->join('store_grn_detail','store_grn_header.grn_id','=','store_grn_detail.grn_id')
      ->where('store_grn_header.inv_number','=',$request->invoice_no)
      ->where('store_grn_header.po_number','=',$request->po_id)
      ->where('store_grn_header.grn_id','=',$request->grn_id)
      ->where('store_grn_detail.po_details_id','=',$request->po_line_id)
      ->first();
      //dd($is_grn_same_qty);
      if($is_type_fabric->category_code=='FAB'){
        $status=0;
        $is_grn_same_qty=null;
        $message="Selected Item is Fabric type";
      }
      else if($is_type_fabric->category_code!='FAB'){
        //dd($is_type_fabric->category_code);
        if($is_grn_same_qty==null){
          $status=0;
          $message="can not Add Trim packing Details";
        }
        else if($is_grn_same_qty!=null){
          if($is_grn_same_qty->i_rec_qty==$request->qty)
          {
            $is_aLLreaddy_trim_packing_details_added=DB::table('store_rm_plan')->select('*')->where('grn_detail_id','=',$is_grn_same_qty->grn_detail_id)
            ->where('status','=',1)->first();
            //dd($is_aLLreaddy_roll_plned);
            if($is_aLLreaddy_trim_packing_details_added!=null){
              $status=0;
              $message="Trim Packing Details Already Added";
            }
            else{
              $status=1;
            }
          }
          else if($is_grn_same_qty->i_rec_qty!=$request->qty)
          {
            $status=0;
            $message="Can not Add Trim Packing Details";
          }
        }
      }
      return response([
        'data'=> [
          'dataModel'=>$is_grn_same_qty,
          'status'=>$status,
          'message'=>$message,
          'substoreWiseBin'=>$substorewiseBins
        ]
      ]);


    }


    public function isreadyForRollPlan(Request $request){
      $is_type_fabric=DB::table('item_category')->select('category_code')->where('category_id','=',$request->category_id)->first();
      $substorewiseBins=DB::table('org_substore')->select('*')->where('substore_id','=',$request->substore_id)->get();
      $status=0;
      $message="";
      $is_grn_same_qty=DB::table('store_grn_header')
      ->select('*')
      ->join('store_grn_detail','store_grn_header.grn_id','=','store_grn_detail.grn_id')
      ->where('store_grn_header.inv_number','=',$request->invoice_no)
      ->where('store_grn_header.po_number','=',$request->po_id)
      ->where('store_grn_header.grn_id','=',$request->grn_id)
      ->where('store_grn_detail.po_details_id','=',$request->po_line_id)
      ->first();
     if($is_type_fabric->category_code!='FAB'){
        $status=0;
        $is_grn_same_qty=null;
        $message="Selected Item not a Fabric type";
      }
      else if($is_type_fabric->category_code=='FAB'){
        if($is_grn_same_qty==null){
          $status=0;
          $message="Can not Update Roll Plan";
        }
        else if($is_grn_same_qty!=null){
          if($is_grn_same_qty->i_rec_qty==$request->qty)
          {
            $is_aLLreaddy_roll_plned=DB::table('store_rm_plan')->select('*')->where('grn_detail_id','=',$is_grn_same_qty->grn_detail_id)->where('status','=',1)->first();
             if($is_aLLreaddy_roll_plned!=null){
              $status=0;
              $message="Roll Plan Already Added";
            }
            else{
              $status=1;
            }
          }
          else if($is_grn_same_qty->i_rec_qty!=$request->qty)
          {
            $status=0;
            $message="Can not Update Roll Plan";
          }
        }
      }
      return response([
        'data'=> [
          'dataModel'=>$is_grn_same_qty,
          'status'=>$status,
          'message'=>$message,
          'substoreWiseBin'=>$substorewiseBins
        ]
      ]);


    }


    public function load_batch_details($search){
      $grn=GrnHeader::find($search);
      if($grn->grn_type=="AUTO"){
      $batchesInRMplan= DB::SELECT("SELECT
        store_grn_detail.item_code,
        store_rm_plan.batch_no,
        store_rm_plan.rm_plan_header_id,
        store_rm_plan.is_excess,
        item_master.master_code,
        store_grn_detail.style_id,
        store_grn_detail.standard_price,
        store_grn_detail.purchase_price,
        store_grn_header.grn_id,
        store_grn_header.main_store,
        store_grn_header.sub_store,
        store_grn_header.location,
        store_grn_detail.shop_order_id,
        store_grn_detail.shop_order_detail_id,
        store_grn_detail.po_details_id,
        item_master.master_description,
        store_grn_detail.grn_detail_id,
        store_grn_detail.uom as purchase_uom,
        item_master.inventory_uom,
        store_grn_type.grn_type_code,
        store_rm_plan.inspection_status,
        merc_shop_order_detail.purchase_uom as shop_order_purchase_uom,
        SUM(store_rm_plan.actual_qty) AS batch_wise_actual_qty,
        SUM(store_rm_plan.received_qty) AS batch_wise_received_qty,
        IFNULL(( SELECT  sum(RM2.received_qty)
FROM
store_rm_plan AS RM2
INNER JOIN store_grn_detail as sgd2 on RM2.grn_detail_id=sgd2.grn_detail_id
INNER JOIN item_master as im2 on im2.master_id=sgd2.item_code

WHERE
RM2.is_excess = 1
AND RM2.batch_no=store_rm_plan.batch_no
AND RM2.inspection_status=store_rm_plan.inspection_status
AND RM2.grn_detail_id=store_grn_detail.grn_detail_id
GROUP BY
sgd2.grn_id,
RM2.batch_no,
im2.master_id,
RM2.inspection_status
#RM2.is_excess
),0)as total_received_excess_qty,

IFNULL(( SELECT  sum(RM2.actual_qty)
FROM
store_rm_plan AS RM2
INNER JOIN store_grn_detail as sgd2 on RM2.grn_detail_id=sgd2.grn_detail_id
INNER JOIN item_master as im2 on im2.master_id=sgd2.item_code

WHERE
RM2.is_excess = 1
AND RM2.batch_no=store_rm_plan.batch_no
AND RM2.inspection_status=store_rm_plan.inspection_status
AND RM2.grn_detail_id=store_grn_detail.grn_detail_id
GROUP BY
sgd2.grn_id,
RM2.batch_no,
im2.master_id,
RM2.inspection_status
#RM2.is_excess
),0)as total_actual_excess_qty

      FROM
      store_rm_plan
      INNER JOIN store_grn_detail ON store_rm_plan.grn_detail_id =store_grn_detail.grn_detail_id
      INNER JOIN merc_shop_order_detail ON store_grn_detail.shop_order_detail_id=merc_shop_order_detail.shop_order_detail_id
      INNER JOIN store_grn_header ON store_grn_detail.grn_id = store_grn_header.grn_id
      INNER JOIN store_grn_type ON store_grn_header.grn_type = store_grn_type.grn_type_id
      INNER JOIN item_master ON store_grn_detail.item_code = item_master.master_id
      WHERE
      store_grn_header.grn_id = $search
      AND store_rm_plan.confirm_status = 'CONFIRMED'
      GROUP BY
      store_grn_detail.grn_id,
      store_rm_plan.batch_no,
      item_master.master_id,
      store_rm_plan.inspection_status
      ");
    }
    else if($grn->grn_type=="MANUAL"){
      $batchesInRMplan= DB::SELECT("SELECT
        store_grn_detail.item_code,
        store_rm_plan.batch_no,
        store_rm_plan.rm_plan_header_id,
        store_rm_plan.is_excess,
        item_master.master_code,
        store_grn_detail.style_id,
        store_grn_detail.standard_price,
        store_grn_detail.purchase_price,
        store_grn_header.grn_id,
        store_grn_header.main_store,
        store_grn_header.sub_store,
        store_grn_header.location,
        store_grn_detail.shop_order_id,
        store_grn_detail.shop_order_detail_id,
        store_grn_detail.po_details_id,
        item_master.master_description,
        store_grn_detail.grn_detail_id,
        store_grn_detail.uom as purchase_uom,
        item_master.inventory_uom,
        store_grn_type.grn_type_code,
        store_rm_plan.inspection_status,
        SUM(store_rm_plan.actual_qty) AS batch_wise_actual_qty,
        SUM(store_rm_plan.received_qty) AS batch_wise_received_qty,
        IFNULL(( SELECT  sum(RM2.received_qty)
FROM
store_rm_plan AS RM2
INNER JOIN store_grn_detail as sgd2 on RM2.grn_detail_id=sgd2.grn_detail_id
INNER JOIN item_master as im2 on im2.master_id=sgd2.item_code

WHERE
RM2.is_excess = 1
AND RM2.batch_no=store_rm_plan.batch_no
AND RM2.inspection_status=store_rm_plan.inspection_status
AND RM2.grn_detail_id=store_grn_detail.grn_detail_id
GROUP BY
sgd2.grn_id,
RM2.batch_no,
im2.master_id,
RM2.inspection_status
#RM2.is_excess
),0)as total_received_excess_qty,

IFNULL(( SELECT  sum(RM2.actual_qty)
FROM
store_rm_plan AS RM2
INNER JOIN store_grn_detail as sgd2 on RM2.grn_detail_id=sgd2.grn_detail_id
INNER JOIN item_master as im2 on im2.master_id=sgd2.item_code

WHERE
RM2.is_excess = 1
AND RM2.batch_no=store_rm_plan.batch_no
AND RM2.inspection_status=store_rm_plan.inspection_status
AND RM2.grn_detail_id=store_grn_detail.grn_detail_id
GROUP BY
sgd2.grn_id,
RM2.batch_no,
im2.master_id,
RM2.inspection_status
#RM2.is_excess
),0)as total_actual_excess_qty

      FROM
      store_rm_plan
      INNER JOIN store_grn_detail ON store_rm_plan.grn_detail_id =store_grn_detail.grn_detail_id
      INNER JOIN store_grn_header ON store_grn_detail.grn_id = store_grn_header.grn_id
      INNER JOIN store_grn_type ON store_grn_header.grn_type = store_grn_type.grn_type_id
      INNER JOIN item_master ON store_grn_detail.item_code = item_master.master_id
      WHERE
      store_grn_header.grn_id = $search
      AND store_rm_plan.confirm_status = 'CONFIRMED'
      GROUP BY
      store_grn_detail.grn_id,
      store_rm_plan.batch_no,
      item_master.master_id,
      store_rm_plan.inspection_status
      ");
    }
      return $batchesInRMplan;
    }

    public function confirmGrn(Request $request){
      //dd($request);
      $data=$request->data;
      foreach ($data as $value) {
        if(!empty($value['confirm_status'])&&$value['confirm_status']==1){
          if($value['grn_type_code']=="AUTO"){
          $item_code=$value["item_code"];
          $shop_order_id=$value["shop_order_id"];
          $shop_order_detail_id=$value['shop_order_detail_id'];
          $style_id=$value['style_id'];
          $main_store=$value['main_store'];
          $sub_store=$value['sub_store'];
          $location=$value['location'];
          $updateQty=$value['batch_wise_actual_qty']-$value['batch_wise_received_qty'];
          $updatedExcessQty=$value['total_actual_excess_qty']-$value['total_received_excess_qty'];
          $bacth_wise_actual_qty=0;
          $batch_wise_received_qty=0;
          $poDetails = PoOrderDetails::find($value['po_details_id']);
          $rmPlanHeaderUpdate=RMPlanHeader::find($value['rm_plan_header_id']);
          $rmPlanHeaderUpdate->confirm_status="GRN_COMPLETED";
          $rmPlanHeaderUpdate->save();
          $findStoreStockLine=DB::SELECT ("SELECT * FROM store_stock
            where item_id=?
            AND shop_order_id=?
            AND style_id=?
            AND shop_order_detail_id=?
            AND store_id=?
            AND substore_id=?
            AND location=?
            AND inventory_type='AUTO'",[$item_code,$shop_order_id,$style_id,$shop_order_detail_id,$main_store,$sub_store,$location]);
            //dd($findStoreStockLine);
            $stock=Stock::find($findStoreStockLine[0]->stock_id);

            if($value['purchase_uom']!=$value['inventory_uom']){
              $_uom_unit_code=UOM::where('uom_id','=',$value['inventory_uom'])->pluck('uom_code');
              $_uom_base_unit_code=UOM::where('uom_id','=',$value['purchase_uom'])->pluck('uom_code');
              $ConversionFactor=ConversionFactor::select('*')
              ->where('unit_code','=',$_uom_unit_code[0])
              ->where('base_unit','=',$_uom_base_unit_code[0])
              ->first();

              $stock->avaliable_qty =$stock->avaliable_qty+(double)($updateQty*$ConversionFactor->present_factor);
              $stock->in_qty =$stock->in_qty+(double)($updateQty*$ConversionFactor->present_factor);
              $stock->excess_qty =$stock->excess_qty+(double)($updatedExcessQty*$ConversionFactor->present_factor);
              $updateQty=(double)($updateQty*$ConversionFactor->present_factor);
              $bacth_wise_actual_qty=(double)($value['batch_wise_actual_qty']*$ConversionFactor->present_factor);
              $batch_wise_received_qty=(double)($value['batch_wise_received_qty']*$ConversionFactor->present_factor);
            }
            //if inventory uom and purchase varid
            if($value['purchase_uom']==$value['inventory_uom']){
              $stock->avaliable_qty= $stock->avaliable_qty+(double)($updateQty);
              $stock->in_qty= $stock->in_qty+(double)($updateQty);
              $stock->excess_qty= $stock->excess_qty+(double)($updatedExcessQty);
              $bacth_wise_actual_qty=(double)$value['batch_wise_actual_qty'];
              $batch_wise_received_qty=(double)$value['batch_wise_received_qty'];
            }
            $stock->save();

            $this->updateStockDetails($stock,$value,$poDetails,$value['grn_type_code']);

            $upDateGrnTable=GrnDetail::find($value['grn_detail_id']);
            $upDateGrnTable->grn_qty=$upDateGrnTable->grn_qty+$value['batch_wise_actual_qty'];
            $upDateGrnTable->excess_qty=$upDateGrnTable->excess_qty+$updatedExcessQty;
            $upDateGrnTable->arrival_status="PENDING_GRN";
            $upDateGrnTable->save();
            $grnHeader=GrnHeader::find($upDateGrnTable->grn_id);
            $grnHeader->arrival_status="PENDING_GRN";
            $grnHeader->save();
            if($value['inspection_status']=="PASS"){
            $updateAsignQty=ShopOrderDetail::find($value['shop_order_detail_id']);
            //if purchase uom of po and purchase uom of shop order changed
            if($value['purchase_uom']==$value['shop_order_purchase_uom']){
              $updateAsignQty->asign_qty=$updateAsignQty->asign_qty+($value['batch_wise_actual_qty']-$value['total_actual_excess_qty']);
            }
            if($value['purchase_uom']!=$value['shop_order_purchase_uom']){
              $_uom_unit_code=UOM::where('uom_id','=',$value['shop_order_purchase_uom'])->pluck('uom_code');
              $_uom_base_unit_code=UOM::where('uom_id','=',$value['purchase_uom'])->pluck('uom_code');
              $ConversionFactor=ConversionFactor::select('*')
              ->where('unit_code','=',$_uom_unit_code[0])
              ->where('base_unit','=',$_uom_base_unit_code[0])
              ->first();
              $updateAsignQty->asign_qty=$updateAsignQty->asign_qty+(($value['batch_wise_actual_qty']-$value['total_actual_excess_qty'])*$ConversionFactor->present_factor);
            }

            $updateAsignQty->save();
          }
          }
          else if($value['grn_type_code']=="MANUAL"){
            $item_code=$value["item_code"];
            //$shop_order_id=$value["shop_order_id"];
            //$shop_order_detail_id=$value['shop_order_detail_id'];
            //$style_id=$value['style_id'];
            $main_store=$value['main_store'];
            $sub_store=$value['sub_store'];
            $location=$value['location'];
            $updateQty=$value['batch_wise_actual_qty']-$value['batch_wise_received_qty'];
            $updatedExcessQty=$value['total_actual_excess_qty']-$value['total_received_excess_qty'];
            $bacth_wise_actual_qty=0;
            $batch_wise_received_qty=0;
            $poDetails = POManualDetails::find($value['po_details_id']);
            $rmPlanHeaderUpdate=RMPlanHeader::find($value['rm_plan_header_id']);
            $rmPlanHeaderUpdate->confirm_status="GRN_COMPLETED";
            $rmPlanHeaderUpdate->save();
            $findStoreStockLine=DB::SELECT ("SELECT * FROM store_stock
              where item_id=?
              AND store_id=?
              AND substore_id=?
              AND location=?
              AND inventory_type='MANUAL'",[$item_code,$main_store,$sub_store,$location]);
              //dd($findStoreStockLine);
              $stock=Stock::find($findStoreStockLine[0]->stock_id);
             //dd($value);
              if($value['purchase_uom']!=$value['inventory_uom']){
                $_uom_unit_code=UOM::where('uom_id','=',$value['inventory_uom'])->pluck('uom_code');
                $_uom_base_unit_code=UOM::where('uom_id','=',$value['purchase_uom'])->pluck('uom_code');
                $ConversionFactor=ConversionFactor::select('*')
                ->where('unit_code','=',$_uom_unit_code[0])
                ->where('base_unit','=',$_uom_base_unit_code[0])
                ->first();

                $stock->avaliable_qty =$stock->avaliable_qty+(double)($updateQty*$ConversionFactor->present_factor);
                $stock->in_qty =$stock->in_qty+(double)($updateQty*$ConversionFactor->present_factor);
                $stock->excess_qty =$stock->excess_qty+(double)($updatedExcessQty*$ConversionFactor->present_factor);
                $updateQty=(double)($updateQty*$ConversionFactor->present_factor);
                $bacth_wise_actual_qty=(double)($value['batch_wise_actual_qty']*$ConversionFactor->present_factor);
                $batch_wise_received_qty=(double)($value['batch_wise_received_qty']*$ConversionFactor->present_factor);
              }
              //if inventory uom and purchase varid
              if($value['purchase_uom']==$value['inventory_uom']){
                //dd($value['uom']);
                $stock->avaliable_qty= $stock->avaliable_qty+(double)($updateQty);
                $stock->in_qty= $stock->in_qty+(double)($updateQty);
                $stock->excess_qty= $stock->excess_qty+(double)($updatedExcessQty);
                $bacth_wise_actual_qty=(double)$value['batch_wise_actual_qty'];
                $batch_wise_received_qty=(double)$value['batch_wise_received_qty'];
              }
              $stock->save();
              //dd("SSAS");
              $this->updateStockDetails($stock,$value,$poDetails,$value['grn_type_code']);

              $upDateGrnTable=GrnDetail::find($value['grn_detail_id']);
              $upDateGrnTable->grn_qty=$upDateGrnTable->grn_qty+$value['batch_wise_actual_qty'];
              $upDateGrnTable->excess_qty=$upDateGrnTable->excess_qty+$updatedExcessQty;
              $upDateGrnTable->arrival_status="PENDING_GRN";
              $upDateGrnTable->save();
              $grnHeader=GrnHeader::find($upDateGrnTable->grn_id);
              $grnHeader->arrival_status="PENDING_GRN";
              $grnHeader->save();
              //$updateAsignQty=ShopOrderDetail::find($value['shop_order_detail_id']);
              //$updateAsignQty->asign_qty=$updateAsignQty->asign_qty+$value['batch_wise_actual_qty'];
              //$updateAsignQty->save();

          }
          $this->UpdateGrnStatus($value['grn_detail_id']);
          }



        }
        return response([ 'data' => [
          'status'=>1,
          'message' => 'GRN Confirmed Successfully',
        ]
      ], Response::HTTP_CREATED );

    }

    public function UpdateGrnStatus($grnDetailId){
      $totalInspectionPassQty=0;
      $rmPlan=RMPlan::join('store_grn_detail','store_rm_plan.grn_detail_id','=','store_grn_detail.grn_detail_id')
      ->where('store_grn_detail.grn_detail_id','=',$grnDetailId)->get();

      foreach ($rmPlan as $value) {
        if($value['inspection_status']=="PASS"){
          $totalInspectionPassQty=$totalInspectionPassQty+$value['actual_qty'];
        }
      }

      foreach ($rmPlan as $value) {
        if($value['confirm_status']=="PENDING"){
          return 0;
        }
      }
      $upDateGrnTable=GrnDetail::find($grnDetailId);
      $upDateGrnTable->arrival_status="CONFIRMED";
      $upDateGrnTable->inspection_pass_qty=$upDateGrnTable->inspection_pass_qty+$totalInspectionPassQty;
      $upDateGrnTable->save();
      $status="CONFIRMED";
      $allLines=GrnDetail::where('grn_id','=',$upDateGrnTable->grn_id)->count();
      $confirmedLines=GrnDetail::where('grn_id','=',$upDateGrnTable->grn_id)->where('arrival_status','=',$status)->count();

      $header=GrnHeader::find($upDateGrnTable->grn_id);
      if($allLines==$confirmedLines){
        $header->arrival_status="CONFIRMED";
      }
      else if($allLines!=$confirmedLines){
            $header->arrival_status="PENDING_GRN";
          }
        $header->save();
    }

    public function updateStockDetails($stock,$genDetails,$poDetails,$grn_type_code){

      $year=DB::select("SELECT YEAR(CURDATE())AS current_d");
      $month=DB::select("SELECT MONTHNAME(CURDATE()) as current_month");
      $current_year=$year["0"]->current_d;
      $current_month=$month['0']->current_month;
      $grn_detail_id=$genDetails['grn_detail_id'];
      $batch_no=$genDetails['batch_no'];
      $getRmDetails=RMPlan::where('grn_detail_id','=',$grn_detail_id)->where('batch_no','=',$batch_no)->get();
      foreach ($getRmDetails as $value) {
       $findStockDetailLine=StockDetails::where('stock_id','=',$stock->stock_id)->where('rm_plan_id','=',$value['rm_plan_id'])->get();
       $updateQty=$value['actual_qty']-$value['received_qty'];
       $actual_qty=0;
       $received_qty=0;
        $stockDetails=StockDetails::find($findStockDetailLine[0]->stock_detail_id);

        if($genDetails['purchase_uom']!=$stock->uom){
          //dd("dadadada");
          $_uom_unit_code=UOM::where('uom_id','=',$stock->uom)->pluck('uom_code');
          $_uom_base_unit_code=UOM::where('uom_id','=',$genDetails['purchase_uom'])->pluck('uom_code');
          $ConversionFactor=ConversionFactor::select('*')
          ->where('unit_code','=',$_uom_unit_code[0])
          ->where('base_unit','=',$_uom_base_unit_code[0])
          ->first();

          $stockDetails->avaliable_qty =$stockDetails->avaliable_qty+(double)($updateQty*$ConversionFactor->present_factor);
          $updateQty=(double)($updateQty*$ConversionFactor->present_factor);
          $actual_qty=(double)($value['actual_qty']*$ConversionFactor->present_factor);
          $received_qty=(double)($value['received_qty']*$ConversionFactor->present_factor);

        }

        if($genDetails['purchase_uom']==$stock->uom){
          $stockDetails->avaliable_qty =$stockDetails->avaliable_qty+$updateQty;
          $actual_qty=$value['actual_qty'];
          $received_qty=$value['received_qty'];

        }
        $stockDetails->inspection_status=$value['inspection_status'];
        if($value['is_excess']==0){
        $stockDetails->issue_status="ISSUABLE";
        }
        if($value['is_excess']==1){
        $stockDetails->issue_status="UNASINGNED";
       }
        $stockDetails->save();
        $value['confirm_status']="GRN_COMPLETED";
        $value->save();
        //dd("dadadada");
        $transaction = Transaction::where('trans_description', 'ARRIVAL')->first();
        $pullStockTranaction=new StockTransaction();
        $pullStockTranaction->stock_id=$stock->stock_id;
        $pullStockTranaction->stock_detail_id=$stockDetails->stock_detail_id;
        $pullStockTranaction->doc_header_id=$genDetails['grn_id'];
        $pullStockTranaction->doc_detail_id=$genDetails['grn_detail_id'];
        $pullStockTranaction->doc_type=$transaction->trans_code;
        $pullStockTranaction->location=$stock->location;
        $pullStockTranaction->sup_po_header_id=$poDetails->po_header_id;
        $pullStockTranaction->sup_po_details_id=$poDetails->id;
        if($value['is_excess']==1){
        $pullStockTranaction->is_un_assigned="UNASINGNED";
        }
        if($grn_type_code=="AUTO"){
        $pullStockTranaction->style_id=$genDetails['style_id'];
        $pullStockTranaction->shop_order_id=$genDetails['shop_order_id'];
        $pullStockTranaction->shop_order_detail_id=$genDetails['shop_order_detail_id'];

       }
        $pullStockTranaction->size=$stock->size;
        $pullStockTranaction->color=$stock->color;
        $pullStockTranaction->item_id=$stock->item_id;
        $pullStockTranaction->uom=$stock->uom;
        $pullStockTranaction->main_store=$stock->store_id;
        $pullStockTranaction->sub_store=$stock->substore_id;
        $pullStockTranaction->stock_detail_id=$stockDetails->stock_detail_id;
        $pullStockTranaction->bin=$stockDetails->bin;
        $pullStockTranaction->item_id=$genDetails['item_code'];
        $pullStockTranaction->qty=$received_qty;
        $pullStockTranaction->standard_price=$genDetails['standard_price'];
        $pullStockTranaction->purchase_price=$genDetails['purchase_price'];
        $pullStockTranaction->financial_year=$current_year;
        $pullStockTranaction->financial_month=$current_month;
        $pullStockTranaction->rm_plan_id=$stockDetails->rm_plan_id;
        $pullStockTranaction->status=1;
        $pullStockTranaction->direction="-";
        $pullStockTranaction->save();

        $pushStockTranaction=$pullStockTranaction->replicate();
        $pushStockTranaction->qty=$actual_qty;
        $pushStockTranaction->doc_type="GRN";
        $pushStockTranaction->direction="+";
        $pushStockTranaction->save();
      }

    }

  }
