<?php

namespace App\Http\Controllers\IE;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Http\Controllers\Controller;
use App\Models\IE\MachineType;
use Exception;
use App\Libraries\CapitalizeAllFields;
use Illuminate\Support\Facades\DB;
use App\Libraries\AppAuthorize;
use App\Libraries\Approval;
class MachineTypeController extends Controller
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
      //dd($type);
      if($type == 'datatable')   {
        $data = $request->all();
        return response($this->datatable_search($data));
      }
      else if($type == 'auto')    {
        $search = $request->search;
        return response($this->autocomplete_search($search));
      }
      else if($type == 'auto_h_table')    {
        $search = $request->request->all();
        //dd($search['query']);
        return response($this->autocomplete_h_table_search($search['query']));
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

      if($this->authorize->hasPermission('MACHINE_TYPE_CREATE'))//check permission
      {
      $machineType = new MachineType();
      if($machineType->validate($request->all()))
      {
        $machineType->fill($request->all());
        $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($machineType);
        $machineType->status = 1;
        $machineType->approval_status="PENDING";
        $machineType->machine_type_id=$machineType->machine_type_code;
        $machineType->save();
        $approval = new Approval();
        $approval->start('MACHINE_TYPE', $machineType->machine_type_id, $machineType->created_by);//start costing approval process
        return response([ 'data' => [
          'message' => 'Machine Type Saved successfully',
          'machineType' => $machineType,
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
      if($this->authorize->hasPermission('MACHINE_TYPE_VIEW'))//check permission
      {

      $machineType = MachineType::find($id);
      if($machineType == null)
        throw new ModelNotFoundException("Requested Operation Component Not Found", 1);
    //}
     else
        return response([ 'data' => $machineType ]);
      }
      else{
        return response($this->authorize->error_response(), 401);
      }
    }


public function update(Request $request, $id){
  if($this->authorize->hasPermission('MACHINE_TYPE_EDIT'))//check permission
  {
      $machineType = MachineType::find($id);
        if ($machineType->validate($request->all())) {
          $is_exsits=DB::table('ie_operation_sub_component_details')->where('machine_type_id','=',$id)->exists();
          if($is_exsits==true){
            return response([
              'data' => [
                'message' => 'Machine Type Already In use.',
                'status'=>'0'
              ]
            ]);
          }


          $machineType->machine_type_name=$request->machine_type_name;
          $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($machineType);
          $machineType->approval_status="PENDING";
          $machineType->save();
          $approval = new Approval();
          $approval->start('MACHINE_TYPE', $machineType->machine_type_id, $machineType->created_by);//start costing approval process
          return response(['data' => [
                  'message' => 'Machine Type Updated Successfully',
                  'machineType' => $machineType,
                  'status'=>'1'
          ]]);
        }
        else {
         $errors = $machineType->errors();// failure, get errors
         $errors_str = $machineType->errors_tostring();
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
      if($this->authorize->hasPermission('MACHINE_TYPE_DELETE'))//check permission
      {
        //yet to validate with sub component
      $is_exsits=DB::table('ie_operation_sub_component_details')->where('machine_type_id','=',$id)->exists();
      if($is_exsits==true){
        return response([
          'data' => [
            'message' => 'Machine Type Already In use.',
            'status'=>'0'
          ]
        ]);
      }

      $machineType = MachineType::where('machine_type_id', $id)->update(['status' => 0]);
      return response([
        'data' => [
          'message' => 'Machine Type Was Deactivated Successfully.',
          'machineType' => $machineType,
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
      //dd(dada);
      $for = $request->for;
      if($for == 'duplicate')
      {
        return response($this->validate_duplicate_code($request->machine_type_id, $request->machine_type_code));
      }
    }


    //check Service Type code already exists
    private function validate_duplicate_code($id , $code)
    {
      //echo("asas");
      $machineType = MachineType::where('machine_type_code','=',$code)->first();
      if($machineType == null){
        return ['status' => 'success'];
      }
      else if($machineType->machine_type_id == $id){
        return ['status' => 'success'];
      }
      else {
        return ['status' => 'error','message' => 'Machine type Code Already Exists'];
      }
    }


    //get filtered fields only
    private function list($active = 0 , $fields = null)
    {
      $query = null;
      if($fields == null || $fields == '') {
        $query = MachineType::select('*');
      }
      else{
        $fields = explode(',', $fields);
        $query = MachineType::select($fields);
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
  		$type_lists = MachineType::where([['machine_type_code', 'like', '%' . $search . '%'],])
       ->where('status','1')
       ->where('approval_status','=',$approval_status)
      ->pluck('machine_type_code')
      ->toArray();
  		return  json_encode($type_lists);
  	}

    private function autocomplete_h_table_search($search){
      $approval_status="APPROVED";
      $type_lists = MachineType::where([['machine_type_name', 'like', '%' . $search . '%'],])
       ->where('status','1')
       ->where('approval_status','=',$approval_status)
      ->pluck('machine_type_name');
      return  json_encode($type_lists);

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
      $list = MachineType::select('*')
      ->where('machine_type_code'  , 'like', $search.'%' )
      ->Orwhere('machine_type_name'  , 'like', $search.'%' )
      ->Orwhere('status'  , 'like', $search.'%' )
      ->Orwhere('approval_status'  , 'like', $search.'%' )
      ->orderBy($order_column, $order_type)
      ->offset($start)->limit($length)->get();

      $count = MachineType::where('machine_type_code'  , 'like', $search.'%' )
      ->Orwhere('machine_type_name'  , 'like', $search.'%' )
      ->Orwhere('status'  , 'like', $search.'%' )
      ->Orwhere('approval_status'  , 'like', $search.'%' )
      ->count();

      return [
          "draw" => $draw,
          "recordsTotal" => $count,
          "recordsFiltered" => $count,
          "data" => $list
      ];
    }




}
