<?php

namespace App\Http\Controllers\IE;
use App\Libraries\UniqueIdGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Http\Controllers\Controller;
use App\Models\IE\OperationComponent;
use App\Models\IE\OperationSubComponentHeader;
use App\Models\IE\OperationSubComponentDetails;
use App\Models\IE\MachineType;
use Exception;
use App\Libraries\CapitalizeAllFields;
use Illuminate\Support\Facades\DB;
use App\Libraries\AppAuthorize;
use App\Libraries\Approval;
use App\Jobs\MailSendJob;
class OperationSubComponentController extends Controller
{
  var $authorize = null;
    public function __construct()
    {
      //add functions names to 'except' paramert to skip authentication
      $this->middleware('jwt.verify', ['except' => ['index']]);
        $this->authorize = new AppAuthorize();
    }

    //get Service Type list
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
      else if($type == 'auto_o_component_wise')    {
        $search = $request->search;
        $o_componet=$request->o_component;
        return response($this->autocomplete_search_o_component_wise($search,$o_componet));
      }
      else if($type == 'auto_o_component_wise_all')    {
       $o_componet=$request->o_component;
        return response($this->autocomplete_search_o_component_wise_all($o_componet));
      }
      else if($type == 'handsontable')    {
        $search = $request->search;
        return response([
          'data' => $this->handsontable_search($search)
        ]);
      }
      else {
        $active = $request->active;
        $fields = $request->fields;
        return response([
          'data' => $this->list($active , $fields)
        ]);
      }
    }


    //create a Service Type
    public function store(Request $request)
    {
      $header=$request->header;
      $details=$request->detail;
      if($this->authorize->hasPermission('OPERATION_SUB_COMPONENT_CREATE'))//check permission
      {
      $operationSubComponentHeader = new OperationSubComponentHeader();
      if($operationSubComponentHeader->validate($header))
      {

        foreach ($details as $key => $value) {
          $machine_type = DB::table('ie_machine_type')->where('machine_type_name','=',$value['machine_type_name'])->first();
          //dd($machine_type);
          if($machine_type==null){
            return response([ 'data' => [
            'message' => 'Invalid Machine Type',
            'status'=>'0',
            'details'=>'d'
            ]
          ], Response::HTTP_CREATED );
          }
        }
        if($this->details_list_validation($details)==true){

        $operationSubComponentHeader->fill($header);
        $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($operationSubComponentHeader);
        $operationSubComponentHeader->status = 1;
        $operationSubComponentHeader->operation_sub_component_id=$operationSubComponentHeader->operation_sub_component_code;
        $operationSubComponentHeader->approval_status="PENDING";
        $operationSubComponentHeader->save();

        foreach ($details as $value) {
          $operationSubComponentDetails=new  OperationSubComponentDetails();
          $operationSubComponentDetails->fill($value);
          $machine_type = MachineType::where('machine_type_name','=',$value['machine_type_name'])->first();
          $operationSubComponentDetails->machine_type_id=$machine_type->machine_type_id;
          $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($operationSubComponentDetails);
          $operationSubComponentDetails->status=1;
          $operationSubComponentDetails->operation_sub_component_id=$operationSubComponentHeader->operation_sub_component_id;
          $operationSubComponentDetails->approval_status="PENDING";
          $operationSubComponentDetails->save();
          // code...
        }
        $approval = new Approval();
        $approval->start('OPERATION_SUB_COMPONENT', $operationSubComponentHeader->operation_sub_component_id, $operationSubComponentHeader->created_by);//start costing approval process
        return response([ 'data' => [
          'message' => 'Operation Sub Component Saved successfully',
          'garmentOperation' => $operationSubComponentHeader,
          'status'=>'1'
          ]
        ], Response::HTTP_CREATED );

      }
      else if($this->details_list_validation($details)==false){
          return response([ 'data' => [
          'message' => 'Validation Error',
          'garmentOperation' => $operationSubComponentHeader,
          'status'=>'0',
          'details'=>'d'
          ]
        ], Response::HTTP_CREATED );
      }
    }
      else
      {
          $errors = $operationComponent->errors();// failure, get errors
          $errors_str = $operationComponent->errors_tostring();
          return response(['errors' => ['validationErrors' => $errors, 'validationErrorsText' => $errors_str]], Response::HTTP_UNPROCESSABLE_ENTITY);
      }

    }
    else{
      return response($this->authorize->error_response(), 401);
    }
    }

//details data set validation
public function details_list_validation($dataSet){
  $status=false;
  $i=0;
  foreach ($dataSet as  $value) {
    $i++;
    $operationSubComponentDetails=new OperationSubComponentDetails();
    if($operationSubComponentDetails->validate($value)){
      $status= true;
      $machine_type = DB::table('ie_machine_type')->where('machine_type_name','=',$value['machine_type_name'])->first();
      if($machine_type!=null){

        $status=true;
        //return $status;
      }
      if($machine_type==null){
         $status=false;
        return $status;
      }

     }
     else
     {
         $status=false;
         return $status;
     }
    }
  return $status;
}

  public function xlUpload(Request $request){
   $data=$request->data;
   $p_sub_category=null;
   $p_category=null;
   $cat=null;
   $sub_cat=null;
   $document_id=null;
   $unId=null;
   $approval_status="APPROVED";
   $createdSubComponents=[];
   if(empty($data[0]['Category'])==true){
     return response([ 'data' => [
     'message' => 'Category Cannot Be Empty',
     'data' => $data,
     'status'=>'0'
     ]
   ], Response::HTTP_CREATED );
   }
   if(empty($data[0]['Sub Category'])==true){
     return response([ 'data' => [
     'message' => 'Sub Category Cannot Be Empty',
     'data' => $data,
     'status'=>'0'
     ]
   ], Response::HTTP_CREATED );
   }
   foreach ($data as $ind=>  $key) {
     if(empty($key['Category'])==false){
       $opertionComponent=OperationComponent::where('operation_component_name','=',strtoupper($key['Category']))
       ->where('status','=',1)
       ->where('approval_status','=',$approval_status)
       ->first();
       if($opertionComponent==null){
         return response([ 'data' => [
         'message' => 'Invalid Category',
         'data' => $data,
         'line'=>$ind,
         'status'=>'0'
         ]
       ], Response::HTTP_CREATED );
       }
     }
     if(empty($key['Category'])==true){
       $cat=strtoupper($p_category);
     }
     if(empty($key['Category'])==false){
       $p_category=strtoupper($key['Category']);
        $cat=strtoupper($key['Category']);
     }


      if(empty($key['Sub Category'])==false){
       $opertionComponent=OperationComponent::join('ie_operation_sub_component_header','ie_operation_component.operation_component_id','=','ie_operation_sub_component_header.operation_component_id')
       ->where('ie_operation_component.operation_component_name','=',$cat)
       ->where('ie_operation_sub_component_header.operation_sub_component_name','=',strtoupper($key['Sub Category']))
       ->first();
       if($opertionComponent!=null){
       return response([ 'data' => [
       'message' => 'Operation Sub Category Already Exsits',
       'data' => $data,
       'status'=>'0'
       ]
     ], Response::HTTP_CREATED );
     }
     }
     if(empty($key['Operations'])==true){
       return response([ 'data' => [
       'message' => 'Operation Cannot Be Empty',
       'data' => $data,
       'line'=>$ind,
       'status'=>'0'
       ]
     ], Response::HTTP_CREATED );
     }
    if(empty($key['Machine'])==true){
      return response([ 'data' => [
      'message' => 'Machine Type Cannot Be Empty',
      'data' => $data,
      'status'=>'0'
      ]
    ], Response::HTTP_CREATED );
    }
      if(empty($key['Machine'])==false){

        $machineType=MachineType::where('machine_type_name','=',strtoupper($key['Machine']))->first();
        if($machineType==null){
          return response([ 'data' => [
          'message' => 'Invalid Machine Type',
          'data' => $data,
          'line'=>$ind,
          'status'=>'0'
          ]
        ], Response::HTTP_CREATED );
        }
      }
      if(empty($key['Cost SMV'])==true){
        return response([ 'data' => [
        'message' => 'Cost SMV Cannot Be Empty',
        'data' => $data,
        'line'=>$ind,
        'status'=>'0'
        ]
      ], Response::HTTP_CREATED );
      }
      if(empty($key['Cost SMV'])==true){
        return response([ 'data' => [
        'message' => 'Cost SMV Cannot Be Empty',
        'data' => $data,
        'line'=>$ind,
        'status'=>'0'
        ]
      ], Response::HTTP_CREATED );
      }
      if(empty($key['GSD SMV'])==true){
        return response([ 'data' => [
        'message' => 'GSD SMV Cannot Be Empty',
        'data' => $data,
        'status'=>'0'
        ]
      ], Response::HTTP_CREATED );
      }
      if(empty($key['Code No'])==true){
        return response([ 'data' => [
        'message' => 'Code No Cannot Be Empty',
        'data' => $data,
        'status'=>'0'
        ]
      ], Response::HTTP_CREATED );
      }

   }
   $p_sub_category=null;
   $cat=null;
   $p_category=null;
   $sub_cat=null;
    foreach ($data as  $index => $key) {
     if(empty($key['Category'])==true){
       $cat=strtoupper($p_category);
     }
     if(empty($key['Category'])==false){
       $p_category=strtoupper($key['Category']);
        $cat=strtoupper($key['Category']);
     }
     if(empty($key['Sub Category'])==true){
       $sub_cat=strtoupper($p_sub_category);
     }
     if(empty($key['Sub Category'])==false){
       //$p_sub_category=strtoupper($key['Sub Category']);
        $sub_cat=strtoupper($key['Sub Category']);
     }

     if($p_sub_category!=$sub_cat){
    $opetaion_sub_component_id=null;
    $unId = UniqueIdGenerator::generateUniqueId('OPERATION_SUB_COMPONENT', auth()->payload()['company_id']);
    //dd($unId);
    $opeationComponent=OperationComponent::where('operation_component_name','=',$cat)->first();
    $operationSubComponentHeader = new OperationSubComponentHeader();
    $operationSubComponentHeader->operation_sub_component_id=$unId;
    $operationSubComponentHeader->operation_component_id=$opeationComponent->operation_component_id;
    $opetaion_sub_component_id=$unId;
    $operationSubComponentHeader->operation_sub_component_name=$sub_cat;
    $operationSubComponentHeader->operation_sub_component_code=$unId;
    $operationSubComponentHeader->status=1;
    $operationSubComponentHeader->approval_status="PENDING";
    CapitalizeAllFields::setCapitalAll($operationSubComponentHeader);
    $operationSubComponentHeader->save();
    $createdSubComponents[$index]=$operationSubComponentHeader;
    $p_sub_category=strtoupper($sub_cat);
    }
    if($p_sub_category==strtoupper($sub_cat)){
    $operationSubComponentDetails=new OperationSubComponentDetails();
    $operationSubComponentDetails->operation_sub_component_id=$opetaion_sub_component_id;
    $operationSubComponentDetails->operation_name=$key['Operations'];
    $operationSubComponentDetails->operation_code=$key['Code No'];
    $machineType=MachineType::where('machine_type_name','=',strtoupper($key['Machine']))->first();
    $operationSubComponentDetails->machine_type_id=$machineType->machine_type_id;
    $operationSubComponentDetails->cost_smv=round($key['Cost SMV'], 4, PHP_ROUND_HALF_UP );
    $operationSubComponentDetails->gsd_smv=round($key['GSD SMV'], 4, PHP_ROUND_HALF_UP );
     if(empty($key['Options'])==false){
    $operationSubComponentDetails->options=$key['Options'];
    }
    $operationSubComponentDetails->status=1;
    $operationSubComponentDetails->approval_status="PENDING";
    CapitalizeAllFields::setCapitalAll($operationSubComponentDetails);
    $operationSubComponentDetails->save();
    }
   }
   foreach ($createdSubComponents as $key => $value) {
     $approval = new Approval();
     $approval->start('OPERATION_SUB_COMPONENT', $value['operation_sub_component_id'], $value['created_by']);//start costing approval process
   }

   return response([ 'data' => [
     'message' => 'Operation Sub Component Uploaded Successfully',
     'garmentOperation' => $operationSubComponentHeader,
     'status'=>'1'
     ]
   ], Response::HTTP_CREATED );

  }

    //get a Service Type
    public function show($id)
    {
      if($this->authorize->hasPermission('OPERATION_SUB_COMPONENT_VIEW'))//check permission
      {
      $status=1;
      $header = OperationSubComponentHeader::join('ie_operation_component','ie_operation_component.operation_component_id','=','ie_operation_sub_component_header.operation_component_id')
      ->where('ie_operation_sub_component_header.operation_sub_component_id','=',$id)->first();
      $details=OperationSubComponentDetails::join('ie_operation_sub_component_header','ie_operation_sub_component_header.operation_sub_component_id','=','ie_operation_sub_component_details.operation_sub_component_id')
      ->join('ie_machine_type','ie_operation_sub_component_details.machine_type_id','=','ie_machine_type.machine_type_id')
      ->where('ie_operation_sub_component_details.operation_sub_component_id','=',$id)
      ->where('ie_operation_sub_component_details.status','=',$status)
      ->select('ie_operation_sub_component_details.*','ie_machine_type.*')
      ->get();
      $opeartionComponent=OperationComponent::find($header->operation_component_id);
      if($header == null)
        throw new ModelNotFoundException("Requested Operation Component Not Found", 1);
    //}
     else
        return response([ 'data'=>['header' => $header,
                          'detail'=> $details,
                          'oprationComponent'=>$opeartionComponent ]]);
      }
      else{
        return response($this->authorize->error_response(), 401);
      }
    }


public function update(Request $request, $id){
  if($this->authorize->hasPermission('OPERATION_SUB_COMPONENT_EDIT'))//check permission
  {
    $header=$request->header;
    //dd($header);
    $details=$request->detail;
      $operationSubComponentHeader = OperationSubComponentHeader::find($id);
      $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($operationSubComponentHeader);
        if ($operationSubComponentHeader->validate($header)) {

          foreach ($details as $key => $value) {
            $machine_type = DB::table('ie_machine_type')->where('machine_type_name','=',$value['machine_type_name'])->first();
            //dd($machine_type);
            if($machine_type==null){
              return response([ 'data' => [
              'message' => 'Invalid Machine Type',
              'status'=>'0',
              'details'=>'d'
              ]
            ], Response::HTTP_CREATED );
            }
          }


          if($this->details_list_validation($details)==true){

            $is_exsits_reading_details=DB::table('ie_smv_reading_details')->join('ie_operation_sub_component_details','ie_smv_reading_details.sub_component_detail_id','=','ie_operation_sub_component_details.detail_id')
                                                         ->join('ie_operation_sub_component_header','ie_operation_sub_component_details.operation_sub_component_id','=','ie_operation_sub_component_header.operation_sub_component_id')
                                                         ->where('ie_operation_sub_component_header.operation_sub_component_id','=',$id)->exists();

            $is_exsits_reading_history_details=DB::table('ie_smv_reading_details_history')->join('ie_operation_sub_component_details','ie_smv_reading_details_history.sub_component_detail_id','=','ie_operation_sub_component_details.detail_id')
                                                        ->join('ie_operation_sub_component_header','ie_operation_sub_component_details.operation_sub_component_id','=','ie_operation_sub_component_header.operation_sub_component_id')
                                                       ->where('ie_operation_sub_component_header.operation_sub_component_id','=',$id)->exists();
             if($is_exsits_reading_details==true||$is_exsits_reading_history_details){
               return response([
                 'data' => [
                   'message' => 'Operation Sub Component Already In use.',
                   'status'=>'0'
                 ]
               ]);
             }

          else{
          $operationSubComponentHeader->operation_sub_component_name=$header['operation_sub_component_name'];
          $operationSubComponentHeader->approval_status="PENDING";
          $operationSubComponentHeader->save();
          foreach ($details as $value) {
            $operationSubComponentDetails=null;
            if($value['detail_id']!=''){
            $operationSubComponentDetails=OperationSubComponentDetails::find($value['detail_id']);
            }
            else if($value['detail_id']==''){
            $operationSubComponentDetails=new  OperationSubComponentDetails();
            }
            $operationSubComponentDetails->fill($value);
            $machine_type = MachineType::where('machine_type_name','=',$value['machine_type_name'])->first();
            $operationSubComponentDetails->machine_type_id=$machine_type->machine_type_id;
            $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($operationSubComponentDetails);
            $operationSubComponentDetails->status=1;
            $operationSubComponentDetails->operation_sub_component_id=$operationSubComponentHeader->operation_sub_component_id;
            $operationSubComponentDetails->approval_status="PENDING";
            $operationSubComponentDetails->save();
            // code...
          }
          $approval = new Approval();
          $approval->start('OPERATION_SUB_COMPONENT', $operationSubComponentHeader->operation_sub_component_id, $operationSubComponentHeader->created_by);//start costing approval process
          return response(['data' => [
                  'message' => 'Operation Sub Component Updated Successfully',
                  'garmentOperation' => $operationSubComponentHeader,
                  'status'=>'1'
          ]]);
        }
        }
        else if($this->details_list_validation($details)==false){
            return response([ 'data' => [
            'message' => 'Validation Error',
            'garmentOperation' => $operationSubComponentHeader,
            'status'=>'0'
            ]
          ], Response::HTTP_CREATED );
        }

        }
        else {
         $errors = $operationComponent->errors();// failure, get errors
         $errors_str = $operationComponent->errors_tostring();
         return response(['errors' => ['validationErrors' => $errors, 'validationErrorsText' => $errors_str]], Response::HTTP_UNPROCESSABLE_ENTITY);
       }

  }
  else{
    return response($this->authorize->error_response(), 401);
  }
}



    //deactivate a Service Type
    public function destroy($id)
    {
      if($this->authorize->hasPermission('OPERATION_SUB_COMPONENT_DELETE'))//check permission
      {

     $is_exsits_reading_details=DB::table('ie_smv_reading_details')->join('ie_operation_sub_component_details','ie_smv_reading_details.sub_component_detail_id','=','ie_operation_sub_component_details.detail_id')
                                                  ->join('ie_operation_sub_component_header','ie_operation_sub_component_details.operation_sub_component_id','=','ie_operation_sub_component_header.operation_sub_component_id')
                                                  ->where('ie_operation_sub_component_header.operation_sub_component_id','=',$id)->exists();

    $is_exsits_reading_history_details=DB::table('ie_smv_reading_details_history')->join('ie_operation_sub_component_details','ie_smv_reading_details_history.sub_component_detail_id','=','ie_operation_sub_component_details.detail_id')
                                                 ->join('ie_operation_sub_component_header','ie_operation_sub_component_details.operation_sub_component_id','=','ie_operation_sub_component_header.operation_sub_component_id')
                                                ->where('ie_operation_sub_component_header.operation_sub_component_id','=',$id)->exists();
      if($is_exsits_reading_details==true||$is_exsits_reading_history_details){
        return response([
          'data' => [
            'message' => 'Operation Sub Component Already In use.',
            'status'=>'0'
          ]
        ]);
      }

      $operationSubComponentHeader = OperationSubComponentHeader::where('operation_sub_component_id', $id)->update(['status' => 0]);
      if($operationSubComponentHeader==1)
      $operationSubComponentDetails=OperationSubComponentDetails::where('operation_sub_component_id','=',$id)->update(['status' => 0]);
      return response([
        'data' => [
          'message' => 'operation subcomponent Deactivated Successfully.',
          'garmentSubOperation' => $operationSubComponentHeader,
          'status'=>'1'
        ]
      ]);
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

        return response($this->validate_duplicate_code($request->operation_sub_component_id , $request->operation_component_id,$request->operation_sub_component_code));
      }
    }


    //check Service Type code already exists
    private function validate_duplicate_code($id ,$component_code, $sub_compoent_code)
    {

      $OperationSubComponent = OperationSubComponentHeader::where('operation_sub_component_code','=',$sub_compoent_code)->first();
      if($OperationSubComponent == null){
        return ['status' => 'success'];
      }
      else if($OperationSubComponent->operation_component_id == $component_code&&$OperationSubComponent->operation_sub_component_id==$id){
        return ['status' => 'success'];
      }
      else {
        return ['status' => 'error','message' => 'Operation Sub Component Code Already Exists'];
      }
    }

    public function delete_operation(Request $request){
      if($this->authorize->hasPermission('OPERATION_SUB_COMPONENT_DELETE'))//check permission
      {
        $is_exsits_reading_details=DB::table('ie_smv_reading_details')->where('sub_component_detail_id','=',$request->id)->exists();

       $is_exsits_reading_history_details=DB::table('ie_smv_reading_details_history')->where('sub_component_detail_id','=',$request->id)->exists();
         if($is_exsits_reading_details==true||$is_exsits_reading_history_details){
           return response([
             'data' => [
               'message' => 'Operation already in Use',
               'status'=>'0'
             ]
           ]);
         }


         $status=1;
          $operationSubComponentDetails=OperationSubComponentDetails::where('detail_id','=',$request->id)->update(['status' => 0]);
          $details=OperationSubComponentDetails::join('ie_operation_sub_component_header','ie_operation_sub_component_header.operation_sub_component_id','=','ie_operation_sub_component_details.operation_sub_component_id')
          ->where('ie_operation_sub_component_details.operation_sub_component_id','=',$request->header_id)
          ->where('ie_operation_sub_component_details.status','=',$status)
          ->select('ie_operation_sub_component_details.*')
          ->get();

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
    //get filtered fields only
    private function list($active = 0 , $fields = null)
    {
      $query = null;
      if($fields == null || $fields == '') {
        $query = OperationSubComponentHeader::select('*');
      }
      else{
        $fields = explode(',', $fields);
        $query = OperationSubComponentHeader::select($fields);
        if($active != null && $active != ''){
          $query->where([['status', '=', $active]]);
        }
      }
      return $query->get();
    }

    //search Service Type for autocomplete
    private function autocomplete_search($search)
  	{
      $approval_status="APPROVED";
  		$lists = OperationSubComponentHeader::where([['operation_sub_component_code', 'like', '%' . $search . '%'],])
       ->where('status','1')
       ->where('approval_status','=',$approval_status)
      ->pluck('operation_sub_component_code')
      ->toArray();
  		return  json_encode($lists);
  	}
    private function autocomplete_search_o_component_wise($search,$o_componet)
    {
      $approval_status="APPROVED";
      $lists = OperationSubComponentHeader::where([['operation_sub_component_code', 'like', '%' . $search . '%'],])
      ->where('operation_component_id','=',$o_componet)
       ->where('status','1')
      ->where('approval_status','=',$approval_status)
      ->get();
      return $lists;
    }

    private function autocomplete_search_o_component_wise_all($o_componet)
    {
      $approval_status="APPROVED";
      $lists = OperationSubComponentHeader::where('operation_component_id','=',$o_componet)
       ->where('status','1')
       ->where('approval_status','=',$approval_status)
       ->get();

      return $lists;
    }

    //get searched Service Types for datatable plugin format
    private function datatable_search($data)
    {
      $start = $data['start'];
      $length = $data['length'];
      $draw = $data['draw'];
      $search = $data['search']['value'];
      $order = $data['order'][0];
      $order_column = $data['columns'][$order['column']]['data'];
      $order_type = $order['dir'];
      //dd($search);
      $list = OperationSubComponentHeader::select('ie_operation_sub_component_header.*','ie_operation_component.operation_component_code')
      ->join('ie_operation_component','ie_operation_sub_component_header.operation_component_id','=','ie_operation_component.operation_component_id')
      ->where('ie_operation_component.operation_component_code'  , 'like', $search.'%' )
      ->Orwhere('ie_operation_sub_component_header.operation_sub_component_name'  , 'like', $search.'%' )
      ->Orwhere('ie_operation_sub_component_header.operation_sub_component_code'  , 'like', $search.'%' )
      ->Orwhere('ie_operation_sub_component_header.status'  , 'like', $search.'%' )
      ->Orwhere('ie_operation_sub_component_header.approval_status'  , 'like', $search.'%' )
      ->Orwhere('ie_operation_sub_component_header.status'  , 'like', $search.'%' )
      ->Orwhere('ie_operation_sub_component_header.approval_status'  , 'like', $search.'%' )
      ->orderBy($order_column, $order_type)
      ->offset($start)->limit($length)->get();

      $count = OperationSubComponentHeader::join('ie_operation_component','ie_operation_sub_component_header.operation_component_id','=','ie_operation_component.operation_component_id')
      ->where('ie_operation_component.operation_component_code'  , 'like', $search.'%' )
      ->Orwhere('ie_operation_sub_component_header.operation_sub_component_name'  , 'like', $search.'%' )
      ->Orwhere('ie_operation_sub_component_header.operation_sub_component_code'  , 'like', $search.'%' )
      ->count();

      return [
          "draw" => $draw,
          "recordsTotal" => $count,
          "recordsFiltered" => $count,
          "data" => $list
      ];
    }


    private function handsontable_search($search){
      $garment_operation_lists = GarmentOperationMaster::where([['garment_operation_name', 'like', '%' . $search . '%'],])
      ->where('status','1')
      ->get()->pluck('garment_operation_name');
  		return  $garment_operation_lists;
    }

}
