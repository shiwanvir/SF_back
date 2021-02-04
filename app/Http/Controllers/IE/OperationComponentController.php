<?php

namespace App\Http\Controllers\IE;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Http\Controllers\Controller;
use App\Models\IE\OperationComponent;
use Exception;
use App\Libraries\CapitalizeAllFields;
use Illuminate\Support\Facades\DB;
use App\Libraries\AppAuthorize;
use App\Libraries\Approval;
use App\Jobs\MailSendJob;
class OperationComponentController extends Controller
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

      if($this->authorize->hasPermission('OPERATION_COMPONENT_CREATE'))//check permission
      {
      $operationComponent = new OperationComponent();
      if($operationComponent->validate($request->all()))
      {
        $operationComponent->fill($request->all());
        $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($operationComponent);
        $operationComponent->status = 1;
        $operationComponent->approval_status="PENDING";
        $operationComponent->operation_component_id=$operationComponent->operation_component_code;
        $operationComponent->save();
        
        $approval = new Approval();
        $approval->start('OPERATION_COMPONENT', $operationComponent->operation_component_id, $operationComponent->created_by);//start costing approval process

        return response([ 'data' => [
          'message' => 'Operation Component Saved successfully',
          'garmentOperation' => $operationComponent,
          'status'=>'1'
          ]
        ], Response::HTTP_CREATED );
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


    //get a Service Type
    public function show($id)
    {
      if($this->authorize->hasPermission('OPERATION_COMPONENT_VIEW'))//check permission
      {

      $operationComponent = OperationComponent::find($id);
      if($operationComponent == null)
        throw new ModelNotFoundException("Requested Operation Component Not Found", 1);
    //}
     else
        return response([ 'data' => $operationComponent ]);
      }
      else{
        return response($this->authorize->error_response(), 401);
      }
    }


public function update(Request $request, $id){
  if($this->authorize->hasPermission('OPERATION_COMPONENT_EDIT'))//check permission
  {
      $operationComponent = OperationComponent::find($id);
        if ($operationComponent->validate($request->all())) {
          $is_exsits=DB::table('ie_operation_sub_component_header')->where('operation_component_id','=',$id)->exists();
          if($is_exsits==true){
            return response([
              'data' => [
                'message' => 'Operation Component Already In Use',
                'status'=>'0'
              ]
            ]);
          }

          $operationComponent->operation_component_name=$request->operation_component_name;
          $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($operationComponent);
          $operationComponent->approval_status="PENDING";
          $operationComponent->save();
          $approval = new Approval();
          $approval->start('OPERATION_COMPONENT', $operationComponent->operation_component_id, $operationComponent->created_by);//start costing approval process
          return response(['data' => [
                  'message' => 'Operation Component Updated Successfully',
                  'garmentOperation' => $operationComponent,
                  'status'=>'1'
          ]]);
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
      if($this->authorize->hasPermission('OPERATION_COMPONENT_DELETE'))//check permission
      {
        $is_exsits=DB::table('ie_operation_sub_component_header')->where('operation_component_id','=',$id)->exists();
        if($is_exsits==true){
          return response([
            'data' => [
              'message' => 'Operation Component Already In Use',
              'status'=>'0'
            ]
          ]);
        }

      $operationComponent = OperationComponent::where('operation_component_id', $id)->update(['status' => 0]);
      return response([
        'data' => [
          'message' => 'Garment Operation Was Deactivated Successfully.',
          'garmentOperation' => $operationComponent,
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
        return response($this->validate_duplicate_code($request->operation_component_id , $request->operation_component_code));
      }
    }


    //check Service Type code already exists
    private function validate_duplicate_code($id , $code)
    {

      $OperationComponent = OperationComponent::where('operation_component_code','=',$code)->first();
      if($OperationComponent == null){
        return ['status' => 'success'];
      }
      else if($OperationComponent->operation_component_id == $id){
        return ['status' => 'success'];
      }
      else {
        return ['status' => 'error','message' => 'Operation Component Code Already Exists'];
      }
    }


    //get filtered fields only
    private function list($active = 0 , $fields = null)
    {
      $query = null;
      if($fields == null || $fields == '') {
        $query = OperationComponent::select('*');
      }
      else{
        $fields = explode(',', $fields);
        $query = OperationComponent::select($fields);
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
  		$operation_lists = OperationComponent::where([['operation_component_name', 'like', '%' . $search . '%'],])
       ->where('status','1')
       ->where('approval_status','=',$approval_status)
      ->select('operation_component_code','operation_component_name','operation_component_id')
      ->get();
      $operation_lists->toArray();
  		return $operation_lists;
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
      $operation_list = OperationComponent::select('*')
      ->where('operation_component_code'  , 'like', $search.'%' )
      ->Orwhere('operation_component_name'  , 'like', $search.'%' )
      ->Orwhere('status'  , 'like', $search.'%' )
      ->Orwhere('approval_status'  , 'like', $search.'%' )
      ->orderBy($order_column, $order_type)
      ->offset($start)->limit($length)->get();

      $operation_count = OperationComponent::where('operation_component_code'  , 'like', $search.'%' )
      ->Orwhere('operation_component_name'  , 'like', $search.'%' )
      ->Orwhere('status'  , 'like', $search.'%' )
      ->Orwhere('approval_status'  , 'like', $search.'%' )
      ->count();

      return [
          "draw" => $draw,
          "recordsTotal" => $operation_count,
          "recordsFiltered" => $operation_count,
          "data" => $operation_list
      ];
    }


    private function handsontable_search($search){
      $garment_operation_lists = GarmentOperationMaster::where([['garment_operation_name', 'like', '%' . $search . '%'],])
      ->where('status','1')
      ->get()->pluck('garment_operation_name');
  		return  $garment_operation_lists;
    }

}
