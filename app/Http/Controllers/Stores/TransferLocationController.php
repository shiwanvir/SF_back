<?php
namespace App\Http\Controllers\Stores;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Http\Controllers\Controller;
use App\Models\Merchandising\CustomerOrder;
use App\Models\Merchandising\CustomerOrderDetails;
use App\Models\Merchandising\StyleCreation;
use App\Models\Finance\Item\SubCategory;
use App\Models\Merchandising\Item\Item;
use App\Models\Org\ConversionFactor;
use App\Models\stores\RollPlan;
use App\Models\Store\Stock;
use App\Models\stores\TransferLocationUpdate;
use App\Models\stores\GatePassHeader;
use App\Models\stores\GatePassDetails;
use App\Models\Store\StockTransaction;
use App\Models\Store\GrnHeader;
use App\Models\Merchandising\ShopOrderHeader;
use App\Models\Merchandising\ShopOrderDetail;
use App\Models\Merchandising\PoOrderDetails;
use App\Models\Store\GrnDetail;
use App\Models\Org\UOM;
use Illuminate\Support\Facades\DB;
use App\Libraries\UniqueIdGenerator;
use App\Libraries\Approval;
use App\Models\Store\StockDetails;
use App\Models\stores\RMPlan;
 class TransferLocationController extends Controller{



   public function __construct()
   {
     //add functions names to 'except' paramert to skip authentication
     $this->middleware('jwt.verify', ['except' => ['index']]);
   }


   //get customer size list
   public function index(Request $request)
   {
     $type = $request->type;

     if($type == 'style')   {
       $searchFrom = $request->searchFrom;
       $searchTo=$request->searchTo;
       return response($this->styleFromSearch($searchFrom, $searchTo));
     }
/*     else if($type=='saveDetails'){
       $details=$request->details;
       print_r($details);


     }*/
    else if($type=='loadDetails'){
       $style=$request->searchFrom;
       $shopOrderId=$request->shopOrderId;
       return response(['data'=>$this->tabaleLoad($style,$shopOrderId)]);

     }


     else if ($type == 'auto')    {
       $search = $request->search;
       return response($this->autocomplete_search($search));
     }

     else if($type == 'datatable')   {
       $data = $request->all();
       //dd("dad");
       return response($this->datatable_search($data));
     }
   else{
       $active = $request->active;
       $fields = $request->fields;
       return null;
     }
   }

   public function show($id){
     dd($id);


   }

   public function destroy($id){
    $findgatePassHeader=GatePassHeader::find($id);
    if($findgatePassHeader->transfer_status=="PENDING"){
      $findgatePassHeader->status=0;
      $findgatePassHeader->save();
      $findGatepassDetails=GatePassDetails::where('gate_pass_id','=',$id)->get();
      foreach ($findGatepassDetails as $value) {
        $findgatepassLine=GatePassDetails::find($value['details_id']);
        $findgatepassLine->status=0;
        $findgatepassLine->save();
        $stockDetailLine=StockDetails::find($value['stock_detail_id']);
        $stockDetailLine->issue_status="ISSUABLE";
        $stockDetailLine->save();
      }

    }
    return response([
      'data' => [
        'message' => 'Location Transfer deactivate successfully.',
        'status'=>1
      ]
    ]);
   }

    private function styleFromSearch($searchFrom, $searchTo){
      //dd($searchTo);
   $stylefrom=ShopOrderHeader::join('merc_shop_order_detail','merc_shop_order_header.shop_order_id','=','merc_shop_order_detail.shop_order_id')
                            ->join('bom_header','merc_shop_order_detail.bom_id','=','bom_header.bom_id')
                           ->join('costing','merc_shop_order_detail.costing_id','=','costing.id')
                           ->join('style_creation','costing.style_id','=','style_creation.style_id')
                           ->select('style_creation.*')
                           ->where('merc_shop_order_detail.shop_order_id','=',$searchFrom)
                          ->where('style_creation.status','=',1)
                          ->first();
  $styleTo=ShopOrderHeader::join('merc_shop_order_detail','merc_shop_order_header.shop_order_id','=','merc_shop_order_detail.shop_order_id')
                          ->join('bom_header','merc_shop_order_detail.bom_id','=','bom_header.bom_id')
                          ->join('costing','merc_shop_order_detail.costing_id','=','costing.id')
                          ->join('style_creation','costing.style_id','=','style_creation.style_id')
                         ->select('style_creation.*')
                         ->where('merc_shop_order_detail.shop_order_id','=',$searchTo)
                         ->where('style_creation.status','=',1)
                         ->first();
                //dd($styleTo);
            if($stylefrom!=$styleTo){
              return [
                "styleFrom"=>$stylefrom->style_no,
                'style_id'=>$stylefrom->style_id,
                'message'=>"Diffrent Styles",
                'status'=>0

                ];
              }
              if($stylefrom==$styleTo){
                return [
                  "styleFrom"=>$stylefrom,
                  'style_id'=>$styleTo->style_id,
                  'message'=>"Same Style",
                  'status'=>1

                  ];
                }





              }


              private function autocomplete_search($search)
              {
                $lists = DB::table('stores_transfer_type')->select('*')
                ->where([['transfer_type', 'like', '%' . $search . '%'],]) ->get();
                return $lists;
              }

            private function datatable_search($data)
              {

                //dd("dad");
                $start = $data['start'];
                $length = $data['length'];
                $draw = $data['draw'];
                $search = $data['search']['value'];
                $order = $data['order'][0];
                $order_column = $data['columns'][$order['column']]['data'];
                $order_type = $order['dir'];

                //dd($search);
                //$search=" ";

                $gatePassDetails_list= GatePassHeader::join('org_location as t', 't.loc_id', '=', 'store_gate_pass_header.transfer_location')
                ->join('org_location as r', 'r.loc_id', '=', 'store_gate_pass_header.receiver_location')
                ->join('usr_login as created_user','store_gate_pass_header.updated_by','=','created_user.user_id')
                ->leftJoin('store_material_transfer_in_header','store_gate_pass_header.gate_pass_id','=','store_material_transfer_in_header.gate_pass_id')
                ->leftJoin('usr_login as received_user','store_material_transfer_in_header.updated_by','=','received_user.user_id')
                ->select('store_gate_pass_header.*')
                ->select('store_gate_pass_header.*',DB::raw("DATE_FORMAT(store_gate_pass_header.updated_date, '%d-%b-%Y') 'updated_date_'"),'t.loc_name as loc_transfer','r.loc_name as loc_receiver','created_user.user_name as created_user',DB::Raw('IFNULL( `received_user`.`user_name` , "Yet To Receive" ) as received_user'))
                ->where('gate_pass_no','like',$search.'%')
                ->orWhere('r.loc_name', 'like', $search.'%')
                ->orWhere('t.loc_name', 'like', $search.'%')
                ->orWhere('store_gate_pass_header.created_date', 'like', $search.'%')
                ->orderBy($order_column, $order_type)
                ->offset($start)->limit($length)->get();
                //dd($gatePassDetails_list);

                 $gatePassDetails_list_count= GatePassHeader::join('org_location as t', 't.loc_id', '=', 'store_gate_pass_header.transfer_location')
                 ->join('org_location as r', 'r.loc_id', '=', 'store_gate_pass_header.receiver_location')
                 ->join('usr_login as created_user','store_gate_pass_header.updated_by','=','created_user.user_id')
                 ->leftJoin('store_material_transfer_in_header','store_gate_pass_header.gate_pass_id','=','store_material_transfer_in_header.gate_pass_id')
                 ->leftJoin('usr_login as reveived_user','store_material_transfer_in_header.updated_by','=','reveived_user.user_id')
                 ->select('store_gate_pass_header.*','t.loc_name as loc_transfer','r.loc_name as loc_receiver','created_user.user_name as created_user',DB::Raw('IFNULL( `reveived_user`.`user_name` , "Yet To Receive" ) as received_user'))
                 ->where('gate_pass_no','like',$search.'%')
                 ->orWhere('r.loc_name', 'like', $search.'%')
                 ->orWhere('t.loc_name', 'like', $search.'%')
                 ->orWhere('store_gate_pass_header.created_date', 'like', $search.'%')
                ->count();
                return [
                    "draw" => $draw,
                    "recordsTotal" =>  $gatePassDetails_list_count,
                    "recordsFiltered" => $gatePassDetails_list_count,
                    "data" =>$gatePassDetails_list
                ];


              }


                      private function tabaleLoad($style,$shopOrderId){
                        //dd($shopOrderId);
                        $user = auth()->payload();
                        $user_location=$user['loc_id'];

                    $detailstockDetail=StockDetails::join('store_rm_plan','store_stock_details.rm_plan_id','=','store_rm_plan.rm_plan_id')
                                    ->join('store_stock','store_stock.stock_id','=','store_stock_details.stock_id')
                                    ->join('style_creation','store_stock.style_id','=','style_creation.style_id')
                                    ->join('item_master','store_stock_details.item_id','=','item_master.master_id')
                                    ->join('org_store_bin','store_stock_details.bin','=','org_store_bin.store_bin_id')
                                    ->join('org_substore','store_stock.substore_id','=','org_substore.substore_id')
                                    ->join('org_store','store_stock.store_id','=','org_store.store_id')
                                    ->select('store_stock_details.*','store_stock.size','store_stock.uom','store_stock.shop_order_id','store_stock.style_id','store_stock.shop_order_detail_id','store_stock.color','store_rm_plan.*','item_master.master_code','org_store_bin.store_bin_name','org_store.store_name','org_substore.substore_name')
                                    ->where('style_creation.style_id','=',$style)
                                    ->where('store_stock.shop_order_id','=',$shopOrderId)
                                    ->where('store_stock_details.avaliable_qty','>',0)
                                    ->where('store_stock_details.user_loc_id','=',$user_location)
                                    ->where('store_stock_details.issue_status','=','ISSUABLE')
                                    ->get();
                                    //dd($detailstockDetail);
                                      return $detailstockDetail;

                      }

                      private function setStatuszero($details){
                        for($i=0;$i<count($details);$i++){
                          $id=$details[$i]["id"];
                          //$setStatusZero=TransferLocationUpdate::find($id);
                          $setStatusZero->status=0;
                          $setStatusZero->save();


                        }



                      }

                      public function send_to_approval(Request $request) {
                        $gate_pass_id=$request->formData['gate_pass_id'];
                        $approval = new Approval();
                        $user_id=auth()->payload()['user_id'];
                        //$approval->start('GATE_PASS',$gate_pass_id,$user_id);//start costing approval process*/


                        $this->approveLocationTransfer($gate_pass_id);
                        return response([
                          'data' => [
                            'status' => 'success',
                            'message' => 'Gate Pass Send For Approval Successfully',
                            'costing' => $request
                          ]
                        ]);

                      }

                      public function approveLocationTransfer($gatePassId){
                        $year=DB::select("SELECT YEAR(CURDATE())AS current_d");
                        $month=DB::select("SELECT MONTHNAME(CURDATE()) as current_month");
                        $current_year=$year["0"]->current_d;
                        $current_month=$month['0']->current_month;
                        $findgatePassHeader=GatePassHeader::join('store_gate_pass_details','store_gate_pass_header.gate_pass_id','=','store_gate_pass_details.gate_pass_id')
                                                      ->where('store_gate_pass_header.gate_pass_id','=',$gatePassId)
                                                      ->first();
                        //dd($findgatePassHeader);
                        $findgatePassHeader->transfer_status="CONFIRMED";
                        $findgatePassHeader->save();
                        //dd($findgatePassHeader->gate_pass_id);
                        $stockheaderLine=StockDetails::join('store_gate_pass_details','store_stock_details.stock_detail_id','=','store_gate_pass_details.stock_detail_id')
                                                      ->join('store_gate_pass_header','store_gate_pass_details.gate_pass_id','=','store_gate_pass_header.gate_pass_id')
                                                      ->join('store_stock','store_stock_details.stock_id','=','store_stock.stock_id')
                                                      ->where('store_gate_pass_header.gate_pass_id','=',$findgatePassHeader->gate_pass_id)
                                                      ->get();
                                                    //dd($stockheaderLine);
                              foreach ($stockheaderLine as $value) {
                                //dd($value['trns_qty']);
                                          $poDetails=RMPlan::join('store_gate_pass_details','store_rm_plan.rm_plan_id','=','store_gate_pass_details.rm_plan_id')
                                          ->join('store_grn_detail','store_rm_plan.grn_detail_id','=','store_rm_plan.grn_detail_id')
                                          ->where('store_rm_plan.rm_plan_id','=',$value['rm_plan_id'])->first();

                             $stockUpdate=Stock::find($value['stock_id']);
                            // dd($stockUpdate);
                             $stockUpdate->avaliable_qty=$stockUpdate->avaliable_qty-$value['trns_qty'];
                             $stockUpdate->out_qty=$stockUpdate->out_qty+$value['trns_qty'];
                             $stockUpdate->save();
                             $findStockDetailLine=StockDetails::find($value['stock_detail_id']);
                             $findStockDetailLine->avaliable_qty=$findStockDetailLine->avaliable_qty-$value['trns_qty'];
                             $findStockDetailLine->out_qty=$findStockDetailLine->out_qty+$value['trns_qty'];
                             $findStockDetailLine->issue_status="ISSUABLE";
                             $findStockDetailLine->save();
                             $updateShoporderLine=ShopOrderDetail::find($value['shop_order_detail_id']);

                             if($value['uom']!=$updateShoporderLine->purchase_uom){
                               //$storeUpdate->uom = $dataset[$i]['inventory_uom'];
                               $_uom_unit_code=UOM::where('uom_id','=',$updateShoporderLine->purchase_uom)->pluck('uom_code');
                               $_uom_base_unit_code=UOM::where('uom_id','=',$value['uom'])->pluck('uom_code');
                               $ConversionFactor=ConversionFactor::select('*')
                               ->where('unit_code','=',$_uom_unit_code[0])
                               ->where('base_unit','=',$_uom_base_unit_code[0])
                               ->first();
                               // convert values according to the convertion rate
                               $qtyforShoporder=(double)($value['trns_qty']*$ConversionFactor->present_factor);
                             }
                             if($value['uom']==$updateShoporderLine->purchase_uom){
                               $qtyforShoporder=$value['trns_qty'];
                             }

                             $updateShoporderLine->asign_qty=$updateShoporderLine->asign_qty-$qtyforShoporder;
                             $updateShoporderLine->save();
                              $stockTransaction=new StockTransaction();
                              $stockTransaction->stock_id=$value['stock_id'];
                              $stockTransaction->stock_detail_id=$value['stock_detail_id'];
                              $stockTransaction->doc_header_id=$value['gate_pass_id'];
                              $stockTransaction->doc_detail_id=$value['details_id'];
                              $stockTransaction->doc_type="GATE_PASS";
                              $stockTransaction->style_id=$value['style_id'];
                              $stockTransaction->item_id=$value['item_id'];
                              $stockTransaction->uom=$value['uom'];
                              $stockTransaction->color=$value['color'];
                              $stockTransaction->qty=$value['trns_qty'];
                              $stockTransaction->main_store=$value['store_id'];
                              $stockTransaction->bin=$value['bin'];
                              $stockTransaction->sub_store=$value['substore_id'];
                              $stockTransaction->status=1;
                              $stockTransaction->location= $value['location'];
                              $stockTransaction->shop_order_id=$value['shop_order_id_from'];
                              $stockTransaction->shop_order_detail_id=$value['shop_order_detail_id'];
                              $stockTransaction->sup_po_header_id=$poDetails->po_number;
                              $stockTransaction->sup_po_details_id=$poDetails->po_details_id;
                              $stockTransaction->financial_year=$current_year;
                              $stockTransaction->financial_month=$current_month;
                              $stockTransaction->standard_price=$poDetails->standard_price;
                              $stockTransaction->purchase_price=$poDetails->purchase_price;
                              $stockTransaction->rm_plan_id=$value['rm_plan_id'];
                              $stockTransaction->direction="-";
                              $stockTransaction->save();
                                }
                      }
                      public function storedetails (Request $request){

                        $formData=$request->formData;
                        $user = auth()->payload();
                        $transer_location=$user['loc_id'];
                        $receiver_location=$formData['loc_name']['loc_id'];
                        $transfer_type=$formData['transfer_type']['transfer_type'];
                        $style=$formData['style_id'];
                        $shop_order_to=$formData['shop_order_to']['shop_order_id'];
                        $shop_order_from=$formData['shop_order_from']['shop_order_id'];
                        //print_r($receiver_location);
                          $id;
                          $qty;
                        $details= $request->data;

                            $unId = UniqueIdGenerator::generateUniqueId('GATE_PASS', auth()->payload()['company_id']);
                            $gatePassHeader=new GatePassHeader();
                            $gatePassHeader->gate_pass_no=$unId;
                            //dd($gatePassHeader->gate_pass_no);
                            $gatePassHeader->transfer_location=$transer_location;
                            $gatePassHeader->transfer_type=$transfer_type;
                            $gatePassHeader->transfer_status="PENDING";
                            $gatePassHeader->status=1;
                            $gatePassHeader->receiver_location=$receiver_location;
                            $gatePassHeader->shop_order_to=$shop_order_to;
                            $gatePassHeader->shop_order_from=$shop_order_from;
                            $gatePassHeader->style_id=$style;
                            $gatePassHeader->save();
                            $gate_pass_id=$gatePassHeader->gate_pass_id;
                            //print_r($gate_pass_id);*/
                            for($i=0;$i<count($details);$i++){
                            if(empty($details[$i]['isEdited'])==false&&$details[$i]['isEdited']==1){
                            $gatePassDetails= new GatePassDetails();
                            $stockTransaction=new StockTransaction();
                            $stockDetailLine=StockDetails::find($details[$i]['stock_detail_id']);
                            $stockDetailLine->issue_status="PENDING";
                            $stockDetailLine->save();
                            $gatePassDetails->gate_pass_id=$gate_pass_id;
                            $gatePassDetails->size_id=$details[$i]['size'];
                            $gatePassDetails->shop_order_id_from=$details[$i]['shop_order_id'];
                            $gatePassDetails->shop_order_detail_id=$details[$i]['shop_order_detail_id'];
                            $gatePassDetails->style_id=$details[$i]['style_id'];
                            $gatePassDetails->item_id=$details[$i]['item_id'];
                            $gatePassDetails->color_id=$details[$i]['color'];
                            $gatePassDetails->store_id=$details[$i]['store_id'];
                            $gatePassDetails->sub_store_id=$details[$i]['substore_id'];
                            $gatePassDetails->bin_id=$details[$i]['bin'];
                            $gatePassDetails->uom_id=$details[$i]['uom'];
                            $gatePassDetails->rm_plan_id=$details[$i]['rm_plan_id'];
                            $gatePassDetails->stock_detail_id=$details[$i]['stock_detail_id'];
                            //$gatePassDetails->material_code_id=$stockUpdateDetails->material_code;
                            $qty=$details[$i]["trans_qty"];
                            $gatePassDetails->trns_qty=$qty;
                            $gatePassDetails->save();
                          /*  $stockTransaction->doc_num=$gate_pass_id;
                            $stockTransaction->doc_type="GATE_PASS";
                            $stockTransaction->style_id=$details[$i]['style_id'];
                            $stockTransaction->size=$details[$i]['size'];
                            $stockTransaction->customer_po_id=$details[$i]['customer_po_id'];
                            $stockTransaction->item_id=$details[$i]['item_code'];
                            $stockTransaction->color=$details[$i]['color'];
                            $stockTransaction->main_store=$details[$i]['main_store'];
                            $stockTransaction->sub_store=$details[$i]['sub_store'];
                            $stockTransaction->bin=$details[$i]['bin'];
                            $stockTransaction->uom=$details[$i]['uom'];
                            $stockTransaction->shop_order_id=$details[$i]['shop_order_id'];
                            $stockTransaction->shop_order_detail_id=$details[$i]['shop_order_detail_id'];
                            $stockTransaction->direction="";
                            $stockTransaction->location=$transer_location;
                            $stockTransaction->status="PLANED";
                            $stockTransaction->qty= $qty;
                            $stockTransaction->created_by = auth()->payload()['user_id'];
                            $stockTransaction->save();*/
                          }
                          //}
                            }


                           return response(['data'=>[
                           'message'=>'Item Transfer Saved Successfully',
                            'gate_pass_id'=>$gatePassHeader->gate_pass_id,
                         ]

                          ]

                     );




                    }



}
