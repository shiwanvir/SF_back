<?php

namespace App\Http\Controllers\Store;
use App\Libraries\UniqueIdGenerator;
use App\Models\Store\MRNHeader;
use App\Models\Store\MRNDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Org\Location\Cluster;
//use App\Models\mrn\MRN;
use App\Models\Finance\Transaction;
use App\Models\Store\StockTransaction;
use App\Models\Store\Stock;
use App\Models\Org\ConversionFactor;
use App\Models\Org\UOM;
use App\Models\Org\RequestType;
use App\Models\Org\Section;
use App\Models\Merchandising\ShopOrderHeader;
use App\Models\Merchandising\ShopOrderDetail;
use App\Models\Merchandising\StyleCreation;
use App\Models\Store\GrnDetail;
use App\Models\Store\IssueHeader;
class MrnController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $type = $request['type'];
        if($type == 'datatable')   {
          $data = $request->all();
          return response($this->datatable_search($data));
          }
          else if($type=="auto"){
            $search = $request->search;
            return response($this->autocomplete_search($search));
          }
          else if($type=="items_for_manual_mrn"){
            //d("sas");
            $search = $request->search;
            return response($this->autocomplete_item_search($search));
          }
        elseif($type == 'load-mrn'){
            $mrnId = $request['mrn'];
            $locId = $request['loc'];
            return $this->loadMrn($mrnId, $locId);

        }elseif ($type == 'mrn-select'){
            $soId = $request['so'];
            $active = $request->active;
            $fields = $request->fields;

            return $this->loadMrnList($soId, $fields);
        }

    }


    private function autocomplete_search($search)
     {
       $active=1;
       $mrn_list = MRNHeader::select('mrn_id','mrn_no')
       ->where([['mrn_no', 'like', '%' . $search . '%'],])
       ->where('status','=',$active)
       ->get();
       return $mrn_list;
     }

     private function autocomplete_item_search($search){
       $active=1;
       $arrival_status="CONFIRMED";
       $grned_item=GrnDetail::join('item_master','store_grn_detail.item_code','=','item_master.master_id')
                              ->join('store_rm_plan','store_grn_detail.grn_detail_id','=','store_rm_plan.grn_detail_id')
                              ->join('store_stock_details','store_rm_plan.rm_plan_id','=','store_stock_details.rm_plan_id')
                              ->select('item_master.master_id','item_master.master_code')
                              ->where([['item_master.master_code', 'like', '%' . $search . '%'],])
                              ->where('store_grn_detail.status','=',$active)
                              ->where('store_grn_detail.arrival_status','=',$arrival_status)
                              ->where('item_master.status','=',$active)
                              ->where('store_stock_details.avaliable_qty','>',0)
                              ->distinct()
                              ->get();
       return $grned_item;

     }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

      $header=$request->header;
      $details=$request->dataset;
      $locId=auth()->payload()['loc_id'];
      $unId = UniqueIdGenerator::generateUniqueId('MRN', auth()->payload()['company_id']);
      $grn_type_code=$header['grn_type_code']['grn_type_code'];
      //dd($grn_type);
    if($grn_type_code=="AUTO"){
    for($i=0;$i<sizeof($details);$i++){
      $original_req_qty=$details[$i]['requested_qty'];
      $shopOrderDetail=ShopOrderDetail::find($details[$i]['shop_order_detail_id']);
      if($shopOrderDetail->asign_qty<$details[$i]['requested_qty']){
        return response(['data' => [
                'status' => 0,
                'message' => 'is Exceed the Shop Order Asign Qty ',
                'item_code' =>   $details[$i]['master_code'],
                'detailData'=>$details
            ]
        ], Response::HTTP_CREATED);

      }

    }
  }
      $mrnHeader=new MRNHeader();
      $mrnHeader->mrn_no=$unId;
      $mrnHeader->style_id= $header['style_no']['style_id'];
      $mrnHeader->section_Id=$header['sec_name']['section_id'];
      if($header['line_no']==null){
       $mrnHeader->line_no=null;
      }
      else{
      $mrnHeader->line_no=$header['line_no'];
    }
      $mrnHeader->request_type_id=$header['request_type']['request_type_id'];
      $mrnHeader->cut_qty=$header['cut_qty'];
      $mrnHeader->shop_order_id=$header['shop_order_id']['shop_order_id'];
      $mrnHeader->mrn_type=$header['grn_type_code']['grn_type_id'];
      $mrnHeader->save();


      for($i=0;$i<sizeof($details);$i++){
      $mrndetails=new MRNDetail();
      $mrndetails->mrn_id=$mrnHeader->mrn_id;
      $mrndetails->item_id=$details[$i]['master_id'];
      $mrndetails->color_id=$details[$i]['color_id'];
      $mrndetails->size_id=$details[$i]['size_id'];
      $mrndetails->uom=$details[$i]['inventory_uom_id'];
      $mrndetails->purchase_uom=$details[$i]['purchase_uom'];
      $mrndetails->requested_qty=(double)$details[$i]['requested_qty'];
   //if requested qty uom is varid from po uom ,shop order asign qty should be changed according to the uom


      if($details[$i]['purchase_uom']!=$details[$i]['inventory_uom_id']){
        //$storeUpdate->uom = $details[$i]['inventory_uom'];$details[$i]['uom_id']
        $_uom_unit_code=UOM::where('uom_id','=',$details[$i]['inventory_uom_id'])->pluck('uom_code');
        $_uom_base_unit_code=UOM::where('uom_id','=',$details[$i]['purchase_uom'])->pluck('uom_code');

        $ConversionFactor=ConversionFactor::select('*')
                                            ->where('unit_code','=',$_uom_unit_code[0])
                                            ->where('base_unit','=',$_uom_base_unit_code[0])
                                            ->first();
                                            // convert values according to the convertion rate
                                            $mrndetails->requested_qty_ininventory_uom =(double)($details[$i]['requested_qty']*$ConversionFactor->present_factor);


      }
      if($details[$i]['purchase_uom']==$details[$i]['inventory_uom_id']){
        $mrndetails->requested_qty_ininventory_uom =$details[$i]['requested_qty'];
      }
      $qty =$details[$i]['requested_qty'];
      if($grn_type_code=="MANUAL"){
        $mrndetails->po_header_id=$details[$i]['po_header_id'];
      }
      if($grn_type_code=="AUTO"){
      $mrndetails->total_qty=$details[$i]['total_qty'];
      $mrndetails->gross_consumption=$details[$i]['gross_consumption'];
      $mrndetails->wastage=$details[$i]['wastage'];
      $mrndetails->order_qty=$details[$i]['order_qty'];
      $mrndetails->required_qty=$details[$i]['required_qty'];
      $mrndetails->cust_order_detail_id=$details[$i]['details_id'];
      $mrndetails->shop_order_id=$details[$i]['shop_order_id'];
      $mrndetails->shop_order_detail_id=$details[$i]['shop_order_detail_id'];
    //find exact line of stock
      $item_code=$details[$i]['master_id'];
      $shopOrderDetail=ShopOrderDetail::find($details[$i]['shop_order_detail_id']);
      $shopOrderDetail->mrn_qty=  $shopOrderDetail->mrn_qty+(double)$qty;
      $shopOrderDetail->balance_to_issue_qty=$shopOrderDetail->balance_to_issue_qty+(double)$qty;
      $shopOrderDetail->save();
    }
      //$shopOrderDetail->asign_qty=$shopOrderDetail->asign_qty-(double)$qty;

    /* $findStoreStockLine=DB::SELECT ("SELECT * FROM store_stock
                                    Where item_id=$item_code
                                       ");*/
      //$stock=Stock::find($findStoreStockLine[0]->id);



    $mrndetails->save();


    }


            return response(['data' => [
                    'status' => 1,
                    'message2' => ' Saved Successfully',
                    'message1'=>'MRN No ',
                    'mrnId' => $mrnHeader->mrn_id,
                    'mrnNo'=>$mrnHeader->mrn_no,
                    'detailData'=>$mrndetails
                ]
            ], Response::HTTP_CREATED);

    }

    //get searched MRN Details for datatable plugin format
    private function datatable_search($data)
    {
      $start = $data['start'];
      $length = $data['length'];
      $draw = $data['draw'];
      $search = $data['search']['value'];
      $order = $data['order'][0];
      $order_column = $data['columns'][$order['column']]['data'];
      $order_type = $order['dir'];
      $grn_type=$data['grn_type'];
      $mrn_list=null;
      $mrn_list_count=null;
      $loc=auth()->payload()['loc_id'];


      if($grn_type=="AUTO"){
    $mrn_list = MRNHeader::join('style_creation','store_mrn_header.style_id','=','style_creation.style_id')
      ->join('store_grn_type','store_mrn_header.mrn_type','=','store_grn_type.grn_type_id')
      ->join('org_request_type','store_mrn_header.request_type_id','=','org_request_type.request_type_id')
      ->join('usr_login','store_mrn_header.updated_by','=','usr_login.user_id')
      ->leftjoin('store_issue_header','store_mrn_header.mrn_id','=','store_issue_header.mrn_id')
      ->select(DB::raw("DATE_FORMAT(store_mrn_header.updated_date, '%d-%b-%Y') 'updated_date_'"),'store_mrn_header.*','style_creation.style_no','usr_login.user_name','org_request_type.request_type','store_issue_header.mrn_id as mrn_id_in_issue','store_grn_type.grn_type_code as mrn_type_code')
      ->where('store_grn_type.grn_type_code','=',$grn_type)
      ->where('store_mrn_header.user_loc_id','=',$loc)
      ->where(function($q) use($search) {
        $q->where('style_creation.style_no'  , 'like', $search.'%' )
          ->orWhere('store_mrn_header.mrn_no' , 'like', $search.'%' )
          ->orWhere('usr_login.user_name' , 'like', $search.'%' )
          ->orWhere('org_request_type.request_type' , 'like', $search.'%' );
      })
      ->groupBy('store_mrn_header.mrn_id')
      ->orderBy($order_column, $order_type)
      ->offset($start)->limit($length)->get();

      $mrn_list_count = MRNHeader::join('style_creation','store_mrn_header.style_id','=','style_creation.style_id')
        ->join('store_grn_type','store_mrn_header.mrn_type','=','store_grn_type.grn_type_id')
        ->join('org_request_type','store_mrn_header.request_type_id','=','org_request_type.request_type_id')
        ->join('usr_login','store_mrn_header.updated_by','=','usr_login.user_id')
        ->leftjoin('store_issue_header','store_mrn_header.mrn_id','=','store_issue_header.mrn_id')
        ->select(DB::raw("DATE_FORMAT(store_mrn_header.updated_date, '%d-%b-%Y') 'updated_date_'"),'store_mrn_header.*','style_creation.style_no','usr_login.user_name','org_request_type.request_type','store_issue_header.mrn_id as mrn_id_in_issue','store_grn_type.grn_type_code as mrn_type_code')
        ->where('store_grn_type.grn_type_code','=',$grn_type)
        ->where('store_mrn_header.user_loc_id','=',$loc)
        ->where(function($q) use($search) {
        $q->where('style_creation.style_no'  , 'like', $search.'%' )
          ->orWhere('store_mrn_header.mrn_no' , 'like', $search.'%' )
          ->orWhere('usr_login.user_name' , 'like', $search.'%' )
          ->orWhere('org_request_type.request_type' , 'like', $search.'%' );
        })
      ->groupBy('store_mrn_header.mrn_id')->get()
        ->count();

      }
      else if($grn_type=="MANUAL"){

        $mrn_list = MRNHeader::join('org_request_type','store_mrn_header.request_type_id','=','org_request_type.request_type_id')
          ->join('store_grn_type','store_mrn_header.mrn_type','=','store_grn_type.grn_type_id')
          ->leftjoin('style_creation','store_mrn_header.style_id','=','style_creation.style_id','left outer')
          ->join('usr_login','store_mrn_header.updated_by','=','usr_login.user_id')
          ->leftjoin('store_issue_header','store_mrn_header.mrn_id','=','store_issue_header.mrn_id')
          ->select(DB::raw("DATE_FORMAT(store_mrn_header.updated_date, '%d-%b-%Y') 'updated_date_'"),'store_mrn_header.*','usr_login.user_name','style_creation.style_no','org_request_type.request_type','store_issue_header.mrn_id as mrn_id_in_issue','store_grn_type.grn_type_code as mrn_type_code')
          ->where('store_grn_type.grn_type_code','=',$grn_type)
          ->where('store_mrn_header.user_loc_id','=',$loc)
          ->where(function($q) use($search) {
            $q->where('store_mrn_header.mrn_no' , 'like', $search.'%' )
              ->orWhere('usr_login.user_name' , 'like', $search.'%' )
              ->orWhere('org_request_type.request_type' , 'like', $search.'%' );
          })
         ->groupBy('store_mrn_header.mrn_id')
          ->orderBy($order_column, $order_type)
          ->offset($start)->limit($length)->get();

          $mrn_list_count =MRNHeader::join('org_request_type','store_mrn_header.request_type_id','=','org_request_type.request_type_id')
            ->join('store_grn_type','store_mrn_header.mrn_type','=','store_grn_type.grn_type_id')
            ->leftjoin('style_creation','store_mrn_header.style_id','=','style_creation.style_id','left outer')
            ->join('usr_login','store_mrn_header.updated_by','=','usr_login.user_id')
            ->leftjoin('store_issue_header','store_mrn_header.mrn_id','=','store_issue_header.mrn_id')
            ->select(DB::raw("DATE_FORMAT(store_mrn_header.updated_date, '%d-%b-%Y') 'updated_date_'"),'store_mrn_header.*','usr_login.user_name','style_creation.style_no','org_request_type.request_type','store_issue_header.mrn_id as mrn_id_in_issue','store_grn_type.grn_type_code as mrn_type_code')
            ->where('store_grn_type.grn_type_code','=',$grn_type)
            ->where('store_mrn_header.user_loc_id','=',$loc)
            ->where(function($q) use($search) {
              $q->where('store_mrn_header.mrn_no' , 'like', $search.'%' )
                ->orWhere('usr_login.user_name' , 'like', $search.'%' )
                ->orWhere('org_request_type.request_type' , 'like', $search.'%' );
            })
           ->groupBy('store_mrn_header.mrn_id')->get()
            ->count();

      }
        //dd($mrn_list_count);
      return [
          "draw" => $draw,
          "recordsTotal" => $mrn_list_count,
          "recordsFiltered" => $mrn_list_count,
          "data" => $mrn_list
      ];
    }



    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
      $isAlreadyIssued=IssueHeader::where('mrn_id','=',$id)->exists();
      $locId=auth()->payload()['loc_id'];
      $status=1;
      $inspect_status="PASS";
      $mrnType=MRNHeader::join('store_grn_type','store_mrn_header.mrn_type','=','store_grn_type.grn_type_id')
                          ->where('store_mrn_header.mrn_id','=',$id)
                          ->select('store_grn_type.grn_type_code','store_mrn_header.*')->first();
      $mrn_type_code=$mrnType->grn_type_code;
      $mrnHeader=null;
      $mrndetails=null;
      $style=null;
      $reqestType=null;
      $sction=null;
      $shopOrderId=null;
      //dd($mrnType);
      if($mrn_type_code=="AUTO"){
      $mrnHeader= MRNHeader::join('store_mrn_detail','store_mrn_header.mrn_id','=','store_mrn_detail.mrn_id')
        ->join('store_grn_type','store_mrn_header.mrn_type','=','store_grn_type.grn_type_id')
        ->Join('style_creation','store_mrn_header.style_id','=','style_creation.style_id')
        ->join('org_request_type','store_mrn_header.request_type_id','=','org_request_type.request_type_id')
        ->join('usr_login','store_mrn_header.updated_by','=','usr_login.user_id')
        ->join('org_section','store_mrn_header.section_id','=','org_section.section_id')
        ->where('store_mrn_header.mrn_id','=',$id)
        ->where('store_mrn_header.status','=',$status)
        ->select('store_mrn_header.*','style_creation.style_no','usr_login.user_name','org_request_type.request_type','org_section.section_name as sec_name','store_grn_type.*')
        ->first();
        //dd($mrnHeader);
      $mrndetails=DB::SELECT ("SELECT store_mrn_detail.mrn_detail_id,
        store_mrn_detail.mrn_id,
        store_mrn_detail.item_id,
        store_mrn_detail.color_id,
        store_mrn_detail.size_id,
        store_mrn_detail.uom,
        store_mrn_detail.gross_consumption,merc_shop_order_detail.wastage,store_mrn_detail.order_qty,store_mrn_detail.requested_qty,style_creation.style_no,usr_login.user_name,item_master.master_code,item_master.master_id,item_master.master_description,org_color.color_code,org_color.color_name,org_size.size_name,org_uom.uom_code,org_uom.uom_id,store_mrn_detail.*,merc_shop_order_detail.asign_qty,merc_shop_order_detail.gross_consumption,merc_shop_order_detail.balance_to_issue_qty,merc_shop_order_detail.balance_to_issue_qty,inv_uom.uom_code as inventory_uom,inv_uom.uom_id as inventory_uom_id,store_mrn_detail.requested_qty as pre_qty,merc_customer_order_details.details_id,
        (select
          IFNULL(SUM(STK_DETAILS.avaliable_qty),0)
          from store_stock as STK_BALANCE
          join store_stock_details as STK_DETAILS on STK_BALANCE.stock_id=STK_DETAILS.stock_id
          where STK_BALANCE.item_id=item_master.master_id
          AND STK_BALANCE.location=?
          AND STK_DETAILS.inspection_status='$inspect_status'
          AND STK_DETAILS.issue_status='ISSUABLE'
          GROUP By(item_master.master_id)
        ) as total_qty,

        (select (merc_shop_order_header.order_qty*merc_shop_order_detail.gross_consumption) as required_qty
        from merc_shop_order_detail as SOD2
        INNER JOIN merc_shop_order_header ON SOD2.shop_order_id=merc_shop_order_header.shop_order_id
        where SOD2.shop_order_detail_id=merc_shop_order_detail.shop_order_detail_id
        )as required_qty

      FROM
      store_mrn_header
      INNER JOIN  store_mrn_detail ON store_mrn_header.mrn_id=store_mrn_detail.mrn_id
      INNER JOIN style_creation ON store_mrn_header.style_id=style_creation.style_id
      INNER JOIN org_request_type ON store_mrn_header.request_type_id=org_request_type.request_type_id
      INNER JOIN usr_login ON store_mrn_header.updated_by=usr_login.user_id
      INNER JOIN item_master ON store_mrn_detail.item_id=item_master.master_id
      INNER JOIN store_stock oN item_master.master_id=store_stock.item_id
      INNER JOIN merc_shop_order_detail ON store_mrn_detail.shop_order_detail_id=merc_shop_order_detail.shop_order_detail_id
      INNER JOIN merc_shop_order_header ON merc_shop_order_detail.shop_order_id=merc_shop_order_header.shop_order_id
      INNER JOIN merc_shop_order_delivery ON merc_shop_order_header.shop_order_id=merc_shop_order_delivery.shop_order_id
      INNER JOIN merc_customer_order_details ON merc_shop_order_delivery.delivery_id=merc_customer_order_details.details_id
      LEFT JOIN org_color on store_mrn_detail.color_id=org_color.color_id
      LEFT JOIN org_size ON store_mrn_detail.size_id=org_size.size_id
      INNER JOIN org_uom as inv_uom ON item_master.inventory_uom=inv_uom.uom_id
      inner Join org_uom ON  merc_shop_order_detail.purchase_uom=org_uom.uom_id
      WHERE store_mrn_detail.mrn_id=$id
      AND store_mrn_detail.status=1
      /*AND merc_shop_order_detail.asign_qty>0/*(
        SELECT
          IFNULL(SUM(merc_shop_order_detail.asign_qty),0)
        from merc_shop_order_detail as SOD
        where SOD.shop_order_detail_id=merc_shop_order_detail.shop_order_detail_id
      )*/
     GROUP By(merc_shop_order_detail.shop_order_detail_id)
      ",[$locId]);

  //  dd($id);


      //  dd($mrnHeader['style_id']);
        $style=StyleCreation::find($mrnHeader['style_id']);
        $reqestType=RequestType::find($mrnHeader['request_type_id']);
        $sction=Section::find($mrnHeader['section_id']);
        $shopOrderId=ShopOrderHeader::find($mrnHeader['shop_order_id']);
}
     else if($mrn_type_code=="MANUAL"){
       //dd($mrn_type_code);
       $mrnHeader= MRNHeader::join('store_mrn_detail','store_mrn_header.mrn_id','=','store_mrn_detail.mrn_id')
         ->join('store_grn_type','store_mrn_header.mrn_type','=','store_grn_type.grn_type_id')
         ->join('org_request_type','store_mrn_header.request_type_id','=','org_request_type.request_type_id')
         ->join('usr_login','store_mrn_header.updated_by','=','usr_login.user_id')
         ->join('org_section','store_mrn_header.section_id','=','org_section.section_id')
         ->where('store_mrn_header.mrn_id','=',$id)
         ->where('store_mrn_header.status','=',$status)
         ->select('store_mrn_header.*','usr_login.user_name','org_request_type.request_type','org_section.section_name as sec_name','store_grn_type.*')
         ->first();
       $mrndetails=DB::SELECT ("SELECT store_mrn_detail.mrn_detail_id,item_master.*,
         store_mrn_detail.mrn_id,
         store_mrn_detail.item_id,
         store_mrn_detail.color_id,
         store_mrn_detail.size_id,
         store_mrn_detail.po_header_id,
         store_mrn_detail.uom,
         store_mrn_detail.gross_consumption,store_mrn_detail.order_qty,store_mrn_detail.requested_qty,usr_login.user_name,item_master.master_code,item_master.master_id,item_master.master_description,org_color.color_code,org_color.color_name,org_size.size_name,org_uom.uom_code,org_uom.uom_id,store_mrn_detail.*,inv_uom.uom_code as inventory_uom,inv_uom.uom_id as inventory_uom_id,store_mrn_detail.requested_qty as pre_qty,store_mrn_header.cut_qty as required_qty,
         (select
         IFNULL(SUM(STK_DETAILS.avaliable_qty),0)
         from store_stock as STK_BALANCE
         join store_stock_details as STK_DETAILS on STK_BALANCE.stock_id=STK_DETAILS.stock_id
         where STK_BALANCE.item_id=item_master.master_id
         AND STK_BALANCE.location='$locId'
         AND STK_DETAILS.inspection_status='$inspect_status'
         AND STK_BALANCE.inventory_type='$mrn_type_code'
         AND STK_DETAILS.issue_status='ISSUABLE'
         GROUP By(item_master.master_id)
         ) as total_qty,
         (
         SELECT IFNULL (SUM(MD.requested_qty_ininventory_uom),0)
         from store_mrn_header as MH
         join store_mrn_detail as MD on MH.mrn_id=MD.mrn_id
         WHERE MH.mrn_type='$mrn_type_code'
         AND MD.item_id=item_master.master_id
         GROUP By(item_master.master_id)
         ) as total_mrn_qty_for_item,
         (
         SELECT IFNULL (SUM(ID.qty),0)
         from store_issue_header as IH
         join store_issue_detail as ID on IH.issue_id=ID.issue_id
         join store_mrn_header as MH on MH.mrn_id=IH.mrn_id
         WHERE ID.item_id=item_master.master_id
         AND IH.mrn_id=MH.mrn_id
         GROUP By(item_master.master_id)
         ) as total_issued_qty_for_item,
         IFNULL((SELECT(total_mrn_qty_for_item-total_issued_qty_for_item)),0) as balance_to_issue_qty

        FROM
       store_mrn_header
       INNER JOIN  store_mrn_detail ON store_mrn_header.mrn_id=store_mrn_detail.mrn_id
       INNER JOIN org_request_type ON store_mrn_header.request_type_id=org_request_type.request_type_id
       INNER JOIN usr_login ON store_mrn_header.updated_by=usr_login.user_id
       INNER JOIN item_master ON store_mrn_detail.item_id=item_master.master_id
       INNER JOIN store_stock oN item_master.master_id=store_stock.item_id
       LEFT JOIN org_color on store_mrn_detail.color_id=org_color.color_id
       LEFT JOIN org_size ON store_mrn_detail.size_id=org_size.size_id
       JOIN org_uom as inv_uom ON item_master.inventory_uom=inv_uom.uom_id
       inner Join org_uom ON  store_mrn_detail.purchase_uom=org_uom.uom_id
       WHERE store_mrn_detail.mrn_id=$id
       AND store_mrn_detail.status=1
      /* AND merc_shop_order_detail.asign_qty>0/*(
         SELECT
           IFNULL(SUM(merc_shop_order_detail.asign_qty),0)
         from merc_shop_order_detail as SOD
         where SOD.shop_order_detail_id=merc_shop_order_detail.shop_order_detail_id
       )*/
      GROUP By(store_mrn_detail.item_id)
       ",[$locId]);

     //dd($mrndetails);


       //  dd($mrnHeader['style_id']);
         $style=StyleCreation::find($mrnHeader['style_id']);
         $reqestType=RequestType::find($mrnHeader['request_type_id']);
         $sction=Section::find($mrnHeader['section_id']);
         $shopOrderId=ShopOrderHeader::find($mrnHeader['shop_order_id']);




      }
        if($mrndetails == null)
          throw new ModelNotFoundException("Requested mrn details not found", 1);
        else if($isAlreadyIssued==true){
          return response([ 'data'  => null
                              ]);
        }
        else
          return response([ 'data'  => ['dataDetails'=>$mrndetails,
                                      'dataHeader'=>$mrnHeader,
                                      'style'=>$style,
                                      'requestType'=>$reqestType,
                                      'section'=>$sction,
                                      'shopOrderId'=>$shopOrderId,
                                      'mrn_type'=>$mrn_type_code,

                                      ]
                              ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
      //dd($request->dataset);
      $header=$request->header;
      $details=$request->dataset;
      $locId=auth()->payload()['loc_id'];
      $grn_type_code=$header['grn_type_code']['grn_type_code'];
      //dd($grn_type);
    if($grn_type_code=="AUTO"){
      for($i=0;$i<sizeof($details);$i++){

        $shopOrderDetail=ShopOrderDetail::find($details[$i]['shop_order_detail_id']);
        //dd($shopOrderDetail);
        if(empty($details[$i]['mrn_detail_id'])==false){
          $qty =(double)($details[$i]['requested_qty'])-(double)($details[$i]['pre_qty']);
        }
        if(empty($details[$i]['mrn_detail_id'])==true){
          $qty =$details[$i]['requested_qty'];
        }
        if($shopOrderDetail->asign_qty<$qty ){
          return response(['data' => [
                  'status' => 0,
                  'message' => 'is Exceed the Shop Order Asign Qty ',
                  'item_code' =>   $details[$i]['master_code'],
                  'detailData'=>$details
              ]
          ], Response::HTTP_CREATED);

        }
      }
    }
        $mrnHeader=MRNHeader::find($id);
        //dd($mrnHeader);
        $mrnHeader->section_id=$header['sec_name']['section_id'];
        $mrnHeader->line_no=$header['line_no'];
        $mrnHeader->request_type_id=$header['request_type']['request_type_id'];
        $mrnHeader->save();

              for($i=0;$i<sizeof($details);$i++){
                if(empty($details[$i]['mrn_detail_id'])==false){
                    //dd("dada");
                    $id=$details[$i]['mrn_detail_id'];
                    $mrndetails= MRNDetail::find($id);
                    $mrndetails->requested_qty=(double)$details[$i]['requested_qty'];
                    $mrndetails->total_qty=$details[$i]['total_qty'];
                    $updatedQty=(double)$details[$i]['requested_qty']-$details[$i]['pre_qty'];

                                 if($details[$i]['purchase_uom']!=$details[$i]['inventory_uom_id']){
                                    $_uom_unit_code=UOM::where('uom_id','=',$details[$i]['inventory_uom_id'])->pluck('uom_code');
                                    $_uom_base_unit_code=UOM::where('uom_id','=',$details[$i]['purchase_uom'])->pluck('uom_code');
                                    $ConversionFactor=ConversionFactor::select('*')
                                                                        ->where('unit_code','=',$_uom_unit_code[0])
                                                                        ->where('base_unit','=',$_uom_base_unit_code[0])
                                                                        ->first();
                                                                        // convert values according to the convertion rate
                                                                        $mrndetails->requested_qty_ininventory_uom=$mrndetails->requested_qty_ininventory_uom+(double)($updatedQty*$ConversionFactor->present_factor);


                                  }
                                  if($details[$i]['purchase_uom']==$details[$i]['inventory_uom_id']){
                                  $mrndetails->requested_qty_ininventory_uom=$mrndetails->requested_qty_ininventory_uom+$updatedQty;
                                  }
                    $qty =$updatedQty;
                    $mrndetails->save();
                    if($grn_type_code=="AUTO"){
                    $shopOrderDetail=ShopOrderDetail::find($details[$i]['shop_order_detail_id']);
                    $shopOrderDetail->mrn_qty=$shopOrderDetail->mrn_qty+$qty;
                    $shopOrderDetail->balance_to_issue_qty=$shopOrderDetail->balance_to_issue_qty+$qty;
                    $shopOrderDetail->save();
                    }
                    //$shopOrderDetail->asign_qty=$shopOrderDetail->asign_qty-$mrndetails->requested_qty;


                }
             else if(empty($details[$i]['mrn_detail_id'])==true){
               //this part worn't be happen for manual mrn since one time only for one item
               $mrndetails=new MRNDetail();
               $mrndetails->mrn_id=$mrnHeader->mrn_id;
               $mrndetails->item_id=$details[$i]['master_id'];
               $mrndetails->color_id=$details[$i]['color_id'];
               $mrndetails->size_id=$details[$i]['size_id'];
               $mrndetails->uom=$details[$i]['inventory_uom_id'];
               $mrndetails->gross_consumption=$details[$i]['gross_consumption'];
               $mrndetails->wastage=$details[$i]['wastage'];
               $mrndetails->order_qty=$details[$i]['order_qty'];
               $mrndetails->required_qty=$details[$i]['required_qty'];
               $mrndetails->cust_order_detail_id=$details[$i]['details_id'];
               $mrndetails->purchase_uom=$details[$i]['purchase_uom'];
               //if requested qty uom is varid from po uom ,shop order asign qty should be changed according to the uom

            if($details[$i]['purchase_uom']!=$details[$i]['inventory_uom_id']){
           //$storeUpdate->uom = $dataset[$i]['inventory_uom'];
            $_uom_unit_code=UOM::where('uom_id','=',$details[$i]['inventory_uom_id'])->pluck('uom_code');
            $_uom_base_unit_code=UOM::where('uom_id','=',$details[$i]['purchase_uom'])->pluck('uom_code');
            $ConversionFactor=ConversionFactor::select('*')
                                               ->where('unit_code','=',$_uom_unit_code[0])
                                               ->where('base_unit','=',$_uom_base_unit_code[0])
                                               ->first();
                                               // convert values according to the convertion rate
                                               $mrndetails->requested_qty_ininventory_uom=(double)($updatedQty*$ConversionFactor->present_factor);


           }
            if($details[$i]['purchase_uom']==$details[$i]['inventory_uom_id']){
            $mrndetails->requested_qty_ininventory_uom =$details[$i]['requested_qty'];
           }


               $mrndetails->requested_qty=$details[$i]['requested_qty'];
               $mrndetails->total_qty=$details[$i]['total_qty'];
               $mrndetails->shop_order_id=$details[$i]['shop_order_id'];
               $mrndetails->shop_order_detail_id=$details[$i]['shop_order_detail_id'];
               $mrndetails->save();
               $item_code=$details[$i]['master_id'];

               $shopOrderDetail=ShopOrderDetail::find($details[$i]['shop_order_detail_id']);
               $shopOrderDetail->mrn_qty=  $shopOrderDetail->mrn_qty+(double)$mrndetails->requested_qty;
               $shopOrderDetail->balance_to_issue_qty=$shopOrderDetail->balance_to_issue_qty+(double)$mrndetails->requested_qty;
               //$shopOrderDetail->asign_qty=$shopOrderDetail->asign_qty-(double)$details[$i]['requested_qty'];
               $shopOrderDetail->save();





        }
            }


      return response(['data' => [
              'status' => 1,
              'message2' => ' Updated sucessfully.',
              'message1'=>'MRN No ',
              'mrnId' => $mrnHeader->mrn_id,
              'mrnNo'=>$mrnHeader->mrn_no,
              'detailData'=>$mrndetails
          ]
      ], Response::HTTP_CREATED);
        //dd($request);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
      $mrnType=MRNHeader::join('store_grn_type','store_mrn_header.mrn_type','=','store_grn_type.grn_type_id')
                          ->where('store_mrn_header.mrn_id','=',$id)
                          ->select('store_grn_type.grn_type_code','store_mrn_header.*')->first();
    $mrn_type_code=$mrnType->grn_type_code;

    $mrnHeader=MRNHeader::find($id);
    //dd($mrnHeader);
    $mrnHeader->status=0;
    $mrnHeader->save();
    $findmrnDetails=MRNDetail::where('mrn_id','=',$id)->get();
  //dd($findmrnDetails[0]['status']);
    for($i=0;$i<sizeof($findmrnDetails);$i++){

      $findmrnDetails[$i]['status']=0;
      $qty=$findmrnDetails[$i]['requested_qty'];
      if($mrn_type_code=="AUTO"){
      $findShopOrderline=ShopOrderDetail::find($findmrnDetails[$i]['shop_order_detail_id']);
      $findShopOrderline->asign_qty=$findShopOrderline->asign_qty+$qty;
      $findShopOrderline->mrn_qty=$findShopOrderline->mrn_qty-$qty;
      $findShopOrderline->balance_to_issue_qty=$findShopOrderline->balance_to_issue_qty-$qty;
      $findShopOrderline->save();
    }
        $findmrnDetails[$i]->save();
        }
        return response([
          'data' => [
            'message' => 'MRN deactivated successfully.',
            'status'=>1
          ]
        ] );
    }

    public function loadMrnList($soId, $fields){

        $mrnList = MRNHeader::getMRNList($soId);

        return response([
            'data' => $mrnList
        ]);

    }

    public function loadMrn($mrnId, $locId){

    }

    public function filterData(Request $request){
    $styleNo=$request['style_id'] ;
    $item_code_filter=$request['item_code_filter'];
    $shop_order_filter=$request['shop_order_filter'];
    $customer_po_filter=$request['customer_po_filter'];
    $locId=auth()->payload()['loc_id'];
    $status="PASS";

          $data=DB::SELECT("SELECT merc_shop_order_detail.*,merc_shop_order_detail.asign_qty,
            item_master.master_description,
            item_master.master_code,
            item_master.master_id,for_inv_uom.uom_code as inventory_uom,for_inv_uom.uom_id as inventory_uom_id,for_po_uom.uom_code,for_po_uom.uom_id,merc_shop_order_detail.gross_consumption,merc_shop_order_detail.wastage,
            merc_customer_order_details.order_qty,merc_shop_order_detail.shop_order_detail_id,merc_shop_order_detail.asign_qty,merc_shop_order_detail.balance_to_issue_qty,merc_shop_order_header.shop_order_id,merc_customer_order_details.details_id,org_color.color_id,org_color.color_name,org_size.size_id,

      ( select
        IFNULL(SUM(SOD.balance_to_issue_qty),0)
       FROM merc_shop_order_detail as SOD
        where
        SOD.shop_order_detail_id=merc_shop_order_detail.shop_order_detail_id
        GROUP BY(SOD.shop_order_detail_id)
      ) as balance_to_issue_qty,

      (select (merc_shop_order_header.order_qty*merc_shop_order_detail.gross_consumption) as required_qty
      from merc_shop_order_detail as SOD2
      INNER JOIN merc_shop_order_header ON SOD2.shop_order_id=merc_shop_order_header.shop_order_id
      where SOD2.shop_order_detail_id=merc_shop_order_detail.shop_order_detail_id
      )as required_qty,
      (select
        IFNULL(SUM(STK_DETAILS.avaliable_qty),0)
        from store_stock as STK_BALANCE
        join store_stock_details as STK_DETAILS on STK_BALANCE.stock_id=STK_DETAILS.stock_id
        where STK_BALANCE.item_id=item_master.master_id
        AND STK_BALANCE.location='$locId'
        AND STK_DETAILS.inspection_status='$status'
        AND STK_DETAILS.issue_status='ISSUABLE'
        GROUP By(item_master.master_id)
      ) as total_qty

    FROM

    merc_shop_order_header
    INNER JOIN merc_shop_order_detail ON merc_shop_order_header.shop_order_id = merc_shop_order_detail.shop_order_id
    INNER JOIN merc_shop_order_delivery on merc_shop_order_header.shop_order_id=merc_shop_order_delivery.shop_order_id
    INNER JOIN merc_customer_order_details ON merc_shop_order_delivery.delivery_id = merc_customer_order_details.details_id
    INNER JOIN merc_customer_order_header ON merc_customer_order_details.order_id = merc_customer_order_header.order_id
    INNER JOIN style_creation on merc_customer_order_header.order_style=style_creation.style_id
    INNER JOIN item_master on merc_shop_order_detail.inventory_part_id=item_master.master_id
    INNER JOIN org_uom as for_inv_uom on item_master.inventory_uom=for_inv_uom.uom_id
    INNER JOIN org_uom as for_po_uom on merc_shop_order_detail.purchase_uom=for_po_uom.uom_id
    INNER JOIN store_stock on item_master.master_id=store_stock.item_id
    LEFT JOIN org_color on item_master.color_id=org_color.color_id
    LEFT JOIN org_size on item_master.size_id=org_size.size_id

    where style_creation.style_id=?
    AND  store_stock.location=?
    AND merc_shop_order_detail.asign_qty>0
    AND merc_shop_order_header.shop_order_id like '%".$shop_order_filter."%'
    AND merc_customer_order_header.order_id like '%".$customer_po_filter."%'
    AND item_master.master_code like '%".$item_code_filter."%'
    GROUP By(merc_shop_order_detail.shop_order_detail_id)",[$styleNo,$locId]);

    return response([
        'data' => $data
    ]);



    }

    public function loadDetails(Request $request ){
      $styleNo=$request->style_no;
      $shopOrderId=$request->shop_order_id;
      $grn_type=$request->grn_type;
      $item_id=$request->item_id;
      $locId=auth()->payload()['loc_id'];
      $status="PASS";
      $data=null;
      if($grn_type=="AUTO"){
      $data=DB::SELECT("SELECT merc_shop_order_detail.*,merc_shop_order_detail.asign_qty,item_master.master_description,item_master.master_code,item_master.master_id,for_inv_uom.uom_code as inventory_uom,for_inv_uom.uom_id as inventory_uom_id,for_po_uom.uom_code,for_po_uom.uom_id,merc_shop_order_detail.gross_consumption,merc_shop_order_detail.wastage,
        merc_customer_order_details.order_qty,merc_shop_order_detail.shop_order_detail_id,merc_shop_order_detail.asign_qty,merc_shop_order_detail.balance_to_issue_qty,merc_shop_order_header.shop_order_id,merc_customer_order_details.details_id,org_color.color_id,org_color.color_name,org_size.size_id,org_size.size_name,
(SELECT
  IFNULL (SUM(SOD.balance_to_issue_qty),0)
 FROM merc_shop_order_detail as SOD
  where
  SOD.shop_order_detail_id=merc_shop_order_detail.shop_order_detail_id
  GROUP BY(SOD.shop_order_detail_id)
) as balance_to_issue_qty,

(select
  IFNULL(SUM(STK_DETAILS.avaliable_qty),0)
  from store_stock as STK_BALANCE
  join store_stock_details as STK_DETAILS on STK_BALANCE.stock_id=STK_DETAILS.stock_id
  where STK_BALANCE.item_id=item_master.master_id
  AND STK_BALANCE.location='$locId'
  AND STK_BALANCE.inventory_type='$grn_type'
  AND STK_DETAILS.inspection_status='$status'
  AND STK_DETAILS.issue_status='ISSUABLE'
  GROUP By(item_master.master_id)
) as total_qty,

(select (merc_shop_order_header.order_qty*merc_shop_order_detail.gross_consumption) as required_qty
from merc_shop_order_detail as SOD2
INNER JOIN merc_shop_order_header ON SOD2.shop_order_id=merc_shop_order_header.shop_order_id
where SOD2.shop_order_detail_id=merc_shop_order_detail.shop_order_detail_id
)as required_qty
FROM
merc_shop_order_header
INNER JOIN merc_shop_order_detail ON merc_shop_order_header.shop_order_id = merc_shop_order_detail.shop_order_id
INNER JOIN merc_shop_order_delivery on merc_shop_order_header.shop_order_id=merc_shop_order_delivery.shop_order_id
INNER JOIN merc_customer_order_details ON merc_shop_order_delivery.delivery_id = merc_customer_order_details.details_id
INNER JOIN merc_customer_order_header ON merc_customer_order_details.order_id = merc_customer_order_header.order_id
INNER JOIN style_creation on merc_customer_order_header.order_style=style_creation.style_id
INNER JOIN item_master on merc_shop_order_detail.inventory_part_id=item_master.master_id
INNER JOIN org_uom as for_inv_uom on item_master.inventory_uom=for_inv_uom.uom_id
INNER JOIN org_uom as for_po_uom on merc_shop_order_detail.purchase_uom=for_po_uom.uom_id
INNER JOIN store_stock on item_master.master_id=store_stock.item_id
LEFT JOIN org_color on item_master.color_id=org_color.color_id
LEFT JOIN org_size on item_master.size_id=org_size.size_id

where store_stock.location='$locId'
AND style_creation.style_id='$styleNo'
AND store_stock.inventory_type='$grn_type'
AND merc_shop_order_header.shop_order_id=$shopOrderId

AND merc_shop_order_detail.asign_qty>0
GROUP By(merc_shop_order_detail.shop_order_detail_id)
");

}
else if($grn_type="MANUAL"){
  $data=DB::SELECT("SELECT merc_po_order_manual_details.*,item_master.master_description,item_master.master_code,item_master.master_id,for_inv_uom.uom_code as inventory_uom,for_inv_uom.uom_id as inventory_uom_id,for_po_uom.uom_code,for_po_uom.uom_id,
    org_color.color_id,org_color.color_name,org_size.size_id,org_size.size_name,

(select
IFNULL(SUM(STK_DETAILS.avaliable_qty),0)
from store_stock as STK_BALANCE
join store_stock_details as STK_DETAILS on STK_BALANCE.stock_id=STK_DETAILS.stock_id
where STK_BALANCE.item_id=item_master.master_id
AND STK_BALANCE.location='$locId'
AND STK_DETAILS.inspection_status='$status'
AND STK_BALANCE.inventory_type='$grn_type'
AND STK_DETAILS.issue_status='ISSUABLE'
GROUP By(item_master.master_id)
) as total_qty,
(
SELECT IFNULL (SUM(MD.requested_qty_ininventory_uom),0)
from store_mrn_header as MH
join store_mrn_detail as MD on MH.mrn_id=MD.mrn_id
WHERE MH.mrn_type='$grn_type'
AND MD.item_id=item_master.master_id
GROUP By(item_master.master_id)
) as total_mrn_qty_for_item,
(
SELECT IFNULL (SUM(ID.qty),0)
from store_issue_header as IH
join store_issue_detail as ID on IH.issue_id=ID.issue_id
join store_mrn_header as MH on MH.mrn_id=IH.mrn_id
WHERE ID.item_id=item_master.master_id
AND IH.mrn_id=MH.mrn_id
GROUP By(item_master.master_id)
) as total_issued_qty_for_item,
IFNULL((SELECT(total_mrn_qty_for_item-total_issued_qty_for_item)),0) as balance_to_issue_qty


FROM

merc_po_order_manual_header
INNER JOIN merc_po_order_manual_details on merc_po_order_manual_header.po_id=merc_po_order_manual_details.po_header_id
INNER JOIN item_master on merc_po_order_manual_details.inventory_part_id=item_master.master_id
INNER JOIN org_uom as for_inv_uom on item_master.inventory_uom=for_inv_uom.uom_id
INNER JOIN org_uom as for_po_uom on merc_po_order_manual_details.uom_id=for_po_uom.uom_id
INNER JOIN store_stock on item_master.master_id=store_stock.item_id
LEFT JOIN org_color on item_master.color_id=org_color.color_id
LEFT JOIN org_size on item_master.size_id=org_size.size_id

where store_stock.location='$locId'
AND store_stock.inventory_type='$grn_type'
AND item_master.master_id='$item_id'
GROUP By(merc_po_order_manual_details.inventory_part_id)
");



}
//dd($deta);
return response(['data' => $data]);
    }
}
