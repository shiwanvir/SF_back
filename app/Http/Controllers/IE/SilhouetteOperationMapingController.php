<?php

namespace App\Http\Controllers\IE;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Http\Controllers\Controller;
use App\Models\IE\SilhouetteOperationMappingDetails;
use App\Models\IE\SilhouetteOperationMappingheader;
use App\Models\Merchandising\ProductSilhouette;
use App\Libraries\AppAuthorize;
use Illuminate\Support\Facades\DB;


class  SilhouetteOperationMapingController extends Controller
{
  var $authorize = null;

  public function __construct()
  {
    //add functions names to 'except' paramert to skip authentication
    $this->middleware('jwt.verify', ['except' => ['index']]);
    $this->authorize = new AppAuthorize();
  }

  //get shipment term list
  public function index(Request $request)
  {
    $type = $request->type;

    if($type == 'datatable')   {
      $data = $request->all();
      return response($this->datatable_search($data));
    }
    else if($type == 'auto')    {
      $search = $request->search;
      return response($this->autocomplete_search($search));
    }
    else{
      $active = $request->active;
      $fields = $request->fields;
      return response([
        'data' => $this->list($active , $fields)
      ]);
    }
  }

  //create a shipment term
  public function store(Request $request)
  {
    if($this->authorize->hasPermission('SIL_OPE_MAPPING_CREATE'))//check permission
    {
      $details=$request->detail;
      $header=$request->header;
      $silhouetteOperationMappingheader=new SilhouetteOperationMappingheader();
      $silhouetteOperationMappingheader->product_silhouette_id=$header['product_silhouette_id'];
      $silhouetteOperationMappingheader->status=1;
      $silhouetteOperationMappingheader->save();
      foreach ($details as $key => $value) {
        $silhouetteOperationMapingDetails= new  SilhouetteOperationMappingDetails();
        $silhouetteOperationMapingDetails->mapping_header_id=$silhouetteOperationMappingheader->mapping_header_id;
        $silhouetteOperationMapingDetails->product_silhouette_id=$silhouetteOperationMappingheader->product_silhouette_id;
        $silhouetteOperationMapingDetails->operation_component_id=$value['operation_component_id'];
        $silhouetteOperationMapingDetails->status = 1;
        $silhouetteOperationMapingDetails->save();
      }


      return response([ 'data' => [
        'message' => 'Silhouette Operation Mapping Saved Successfully',
        'silhouetteOperationMaping' => $silhouetteOperationMappingheader,
        'status'=>1,
      ]
    ], Response::HTTP_CREATED );
  }
  else{
    return response($this->authorize->error_response(), 401);
  }
}

//get shipment term
public function show($id)
{
  if($this->authorize->hasPermission('SIL_OPE_MAPPING_VIEW'))//check permission
  {
    $header=SilhouetteOperationMappingheader::where('mapping_header_id',$id)->join('product_silhouette','ie_silhouette_operation_mapping_header.product_silhouette_id','=','product_silhouette.product_silhouette_id')->first();
    $details =SilhouetteOperationMappingDetails::where('mapping_header_id',$id)
    ->where('ie_silhouette_operation_mapping_details.status','=',1)->join('ie_operation_component','ie_silhouette_operation_mapping_details.operation_component_id','=','ie_operation_component.operation_component_id')->get();
    $productSilhouette=ProductSilhouette::find($header->product_silhouette_id);
    if($header== null)
    throw new ModelNotFoundException("Requested Silhouette Operation Mapping not found", 1);
    else
    return response([ 'data' => ['details'=>$details,
                                  'header'=>$header,
                                  'product_silhouette'=>$productSilhouette]]);
  }
  else{
    return response($this->authorize->error_response(), 401);
  }
}



public function delete_operation(Request $request){
  if($this->authorize->hasPermission('SIL_OPE_MAPPING_DELETE'))//check permission
  {
  /* $is_exsits_operation_sub_component=DB::table('ie_operation_sub_component_header')->where('operation_component_id','=',$request->operation_component_id)->exists();

     //$is_exsits_operation_sub_component=false;
     if($is_exsits_operation_sub_component==true){
       return response([
         'data' => [
           'message' => 'Operation Componet already in Use',
           'status'=>'0'
         ]
       ]);
     }
*/

      $operationSubComponentDetails=SilhouetteOperationMappingDetails::where('mapping_id','=',$request->id)->update(['status' => 0]);
      $data=$operationSubComponentDetails=SilhouetteOperationMappingDetails::find($request->id);

      $details =SilhouetteOperationMappingDetails::where('mapping_header_id',$data->mapping_header_id)
      ->where('ie_silhouette_operation_mapping_details.status','=',1)->join('ie_operation_component','ie_silhouette_operation_mapping_details.operation_component_id','=','ie_operation_component.operation_component_id')->get();
      //dd(count($details));
      if(count($details)==0){
        $header =SilhouetteOperationMappingheader::where('mapping_header_id', $data->mapping_header_id)->update(['status' => 0]);
      }
      return response(['data' => [
              'message' => 'Operation deleted Successfully',
              'activeLines' => $details,
              'status'=>'1'
      ]]);
  }
  else{
    return response($this->authorize->error_response(), 401);
  }

}

//update a shipment term
public function update(Request $request, $id)
{

  $details=$request->detail;
  $header=$request->header;
  if($this->authorize->hasPermission('SIL_OPE_MAPPING_EDIT'))//check permission
  {
  /*  foreach ($details as $key => $value) {
    if(!isset($value['mapping_id'])){
    $is_exsits_operation_sub_component=DB::table('ie_operation_sub_component_header')->where('operation_component_id','=',$value['operation_component_id'])->exists();
    if($is_exsits_operation_sub_component==true){
        return response([
          'data' => [
            'message' => 'Operation Componet already in Use',
            'status'=>'0'
          ]
        ]);
      }
    }
    }
*/

    foreach ($details as $key => $value) {
      if(!isset($value['mapping_id'])){
      $silhouetteOperationMapingDetails= new  SilhouetteOperationMappingDetails();
      $silhouetteOperationMapingDetails->mapping_header_id=$header['mapping_header_id'];
      $silhouetteOperationMapingDetails->product_silhouette_id=$header['product_silhouette_id'];
      $silhouetteOperationMapingDetails->operation_component_id=$value['operation_component_id'];
      $silhouetteOperationMapingDetails->status = 1;
      $silhouetteOperationMapingDetails->save();
    }
    }

    return response([ 'data' => [
      'message' => 'Silhouette Operation Mapping Updated Successfully',
      'silhouetteOperationMaping' => $header,
      'status'=>1,
    ]
  ], Response::HTTP_CREATED );
    }
    else{
      return response($this->authorize->error_response(), 401);
    }
  }



  //deactivate a ship term
  public function destroy($id)
  {
    if($this->authorize->hasPermission('SIL_OPE_MAPPING_DELETE'))//check permission
    {
      $header =SilhouetteOperationMappingheader::where('mapping_header_id', $id)->update(['status' => 0]);
      $details=SilhouetteOperationMappingDetails::where('mapping_header_id',$id)->update(['status' => 0]);
      return response([
        'data' => [
          'message' => 'Silhouette Operation Mapping Deactivated Successfully.',
          'mapping' => $header,
          'status'=>1,
        ]
        ] , Response::HTTP_CREATED);
      }
      else{
        return response($this->authorize->error_response(), 401);
      }
    }


    //validate anything based on requirements
    public function validate_data(Request $request){
      $for = $request->for;
      if($for == 'duplicate')
      {
        return response($this->validate_duplicate_code($request->mapping_header_id, $request->product_silhouette_id));
      }
    }


    //check silhouetteClassification already exists
    private function validate_duplicate_code($id , $code)
    {

      $silhouetteCode =SilhouetteOperationMappingheader::where([['product_silhouette_id','=',$code]])->first();
      //dd($silhouetteCode);
      if($silhouetteCode == null){
        echo json_encode(array('status' => 'success'));
      }
      else if($silhouetteCode->mapping_header_id==$id){
        echo json_encode(array('status' => 'success'));
      }
      else {
        echo json_encode(array('status' => 'error','message' => 'Product Silhouette Already Exists'));
      }
    }


    //get filtered fields only
    private function list($active = 0 , $fields = null)
    {
      $query = null;
      if($fields == null || $fields == '') {
        $query = SilhouetteClassification::select('*');
      }
      else{
        $fields = explode(',', $fields);
        $query = SilhouetteClassification::select($fields);
        if($active != null && $active != ''){
          $query->where([['status', '=', $active]]);
        }
      }
      return $query->get();
    }


    //search shipment terms for autocomplete
    private function autocomplete_search($search)
    {
      $silhouetteClassification_lists = SilhouetteClassification::select('sil_class_description')
      ->where([['sil_class_description', 'like', '%' . $search . '%'],]) ->get();
      return $silhouetteClassification_lists;
    }


    //get searched ship terms for datatable plugin format
    private function datatable_search($data)
    {
        $start = $data['start'];
        $length = $data['length'];
        $draw = $data['draw'];
        $search = $data['search']['value'];
        $order = $data['order'][0];
        $order_column = $data['columns'][$order['column']]['data'];
        $order_type = $order['dir'];

        $lists = SilhouetteOperationMappingheader::join('product_silhouette','ie_silhouette_operation_mapping_header.product_silhouette_id','=','product_silhouette.product_silhouette_id')
        ->select('ie_silhouette_operation_mapping_header.mapping_header_id','ie_silhouette_operation_mapping_header.created_date as created_date_','product_silhouette.silhouette_code','product_silhouette.product_silhouette_description','ie_silhouette_operation_mapping_header.status')
        ->where('product_silhouette_description'  , 'like', $search.'%' )
        ->orWhere('silhouette_code'  , 'like', $search.'%' )
        ->orWhere('ie_silhouette_operation_mapping_header.status'  , 'like', $search.'%' )
        ->orderBy($order_column, $order_type)
        ->offset($start)->limit($length)->get();

        $count = SilhouetteOperationMappingheader::join('product_silhouette','ie_silhouette_operation_mapping_header.product_silhouette_id','=','product_silhouette.product_silhouette_id')
        ->select('ie_silhouette_operation_mapping_header.mapping_header_id','ie_silhouette_operation_mapping_header.created_date as created_date_','product_silhouette.silhouette_code','product_silhouette.product_silhouette_description','ie_silhouette_operation_mapping_header.status')
        ->where('product_silhouette_description'  , 'like', $search.'%' )
        ->orWhere('silhouette_code'  , 'like', $search.'%' )
        ->orWhere('ie_silhouette_operation_mapping_header.status'  , 'like', $search.'%' )
        ->count();
          return [
          "draw" => $draw,
          "recordsTotal" => $count,
          "recordsFiltered" => $count,
          "data" =>   $lists
        ];

    }

  }
