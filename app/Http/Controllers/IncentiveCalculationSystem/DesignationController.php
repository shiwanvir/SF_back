<?php

namespace App\Http\Controllers\IncentiveCalculationSystem;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Libraries\CapitalizeAllFields;
use App\Models\IncentiveCalculationSystem\Equation;
use App\Models\IncentiveCalculationSystem\Designation;


use App\Libraries\AppAuthorize;

class DesignationController extends Controller
{
  var $authorize = null;
    public function __construct()
    {
      //add functions names to 'except' paramert to skip authentication
      $this->middleware('jwt.verify', ['except' => ['index']]);
        $this->authorize = new AppAuthorize();
    }

    //get Silhouette list
    public function index(Request $request)
    {
      $type = $request->type;
      if($type == 'datatable')   {
        $data = $request->all();
        return response($this->datatable_search($data));
      }
      else if($type == 'auto') {
        $search = $request->search;
        return response($this->autocomplete_search($search));
      }

      else {
        $active = $request->active;
        $fields = $request->fields;
        return response([
          'data' => $this->list($active , $fields)
        ]);
      }
    }



    //create a Silhouette
    public function store(Request $request)
    {
      if($this->authorize->hasPermission('INC_DESIGNATION_CREATE'))//check permission
      {
      $designation= new Designation();

      $dataArr = array(
        "inc_designation_equation_id"=>$request->inc_designation_equation_id,
        "emp_designation"=>$request->emp_designation['des_name'],
        "inc_equation_id"=>$request->inc_equation_id['inc_equation_id']
      );

      //dd($dataArr);

      if($designation->validate($dataArr))
      {
        $designation->fill($dataArr);
        $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($designation);
        $designation->status = 1;
        $designation->inc_designation_equation_id=$designation->emp_designation;
        $designation->save();

        return response([ 'data' => [
          'message' => 'Designation Saved Successfully',
          'silhouette' => $designation,
          'status'=>'1'
          ]
        ], Response::HTTP_CREATED );
      }
      else
      {
        $errors = $designation->errors();// failure, get errors
        $errors_str = $designation->errors_tostring();
        return response(['errors' => ['validationErrors' => $errors, 'validationErrorsText' => $errors_str]], Response::HTTP_UNPROCESSABLE_ENTITY);
      }
    }
    else{
      return response($this->authorize->error_response(), 401);
    }
    }

    //get a Silhouette
    public function show($id)
    {
      if($this->authorize->hasPermission('INC_DESIGNATION_VIEW'))//check permission
      {
      $query = DB::table('inc_designation_equation');
      $query->join('inc_equation','inc_designation_equation.inc_equation_id','=','inc_equation.inc_equation_id');
      $query->where('inc_designation_equation.inc_designation_equation_id', $id);
      $data = $query->first();
      if($data == null)
        throw new ModelNotFoundException("Requested Designation not found", 1);
      else
        return response([ 'data' => $data ]);
      }
      else{
        return response($this->authorize->error_response(), 401);
      }
    }

    //update a Silhouette
    public function update(Request $request, $id)
    {
      if($this->authorize->hasPermission('INC_DESIGNATION_EDIT'))//check permission
      {

        $check_ = Designation::join('inc_employee','inc_employee.emp_designation','=','inc_designation_equation.inc_designation_equation_id')
                  -> where('inc_designation_equation.inc_designation_equation_id'  , '=',  $id )->count();
        if($check_ > 0){
          $err = 'Designation Already in Use.';
          return response([ 'data' => ['status' => '0','message' => $err]]);

        }

      $designation = Designation::find($id);

      $dataArr = array(
        "inc_designation_equation_id"=>$request->inc_designation_equation_id,
        "emp_designation"=>$request->emp_designation,
        "inc_equation_id"=>$request->inc_equation_id['inc_equation_id']
      );

      if($designation->validate($dataArr))
      {
        // if($is_exsits_in_style==true){
        //   return response([ 'data' => [
        //     'message' => 'Product Silhouette Already in Use',
        //     'status'=>'0'
        //   ]]);
        // }
        // else if($is_exsits_in_style==false){
        $designation->fill($dataArr);
        $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($designation);
        $designation->save();

        return response([ 'data' => [
          'message' => 'Designation Updated Successfully',
          'silhouette' => $designation,
          'status'=>'1'
        ]]);
      //}
    }
      else
      {
        $errors = $designation->errors();// failure, get errors
        return response(['errors' => ['validationErrors' => $errors]], Response::HTTP_UNPROCESSABLE_ENTITY);
      }

    }
    else{
      return response($this->authorize->error_response(), 401);
    }
    }


    //deactivate a Silhouette
    public function destroy($id)
    {
      if($this->authorize->hasPermission('INC_DESIGNATION_DELETE'))//check permission
      {
        $check_ = Designation::join('inc_employee','inc_employee.emp_designation','=','inc_designation_equation.inc_designation_equation_id')
                  -> where('inc_designation_equation.inc_designation_equation_id'  , '=',  $id )->count();
        if($check_ > 0){
          $err = 'Designation Already in Use.';
          return response([ 'data' => ['status' => '0','message' => $err]]);

        }

        
      $designation = Designation::where('inc_designation_equation_id', $id)->update(['status' => 0]);

      return response([
        'data' => [
          'message' => 'Designation Deactivated Successfully.',
          'silhouette' => $designation,
          'status'=>'1'
        ]
      ]);
  //  }
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
        //dd($request);
        return response($this->validate_duplicate_name($request->inc_designation_equation_id , $request->emp_designation_2));
      }
      if($for == 'duplicate-code')
      {
        //return response($this->validate_duplicate_code($request->product_silhouette_id , $request->silhouette_code));
      }
    }


    //check Silhouette code already exists
    private function validate_duplicate_name($id , $code)
    {
      $designation = Designation::where('emp_designation','=',$code)->where('status','<>',0)->first();
      if($designation == null){
        return ['status' => 'success'];
      }
      else if($designation->inc_designation_equation_id == $id){
        return ['status' => 'success'];
      }
      else {
        return ['status' => 'error','message' => 'Designation Already Exists'];
      }
    }

    // private function validate_duplicate_code($id , $code)
    // {
    //   $designation = Silhouette::where('silhouette_code','=',$code)->first();
    //   if($designation == null){
    //     return ['status' => 'success'];
    //   }
    //   else if($designation->product_silhouette_id == $id){
    //     return ['status' => 'success'];
    //   }
    //   else {
    //     return ['status' => 'error','message' => 'Product Silhouette Code Already Exists'];
    //   }
    // }


    //get filtered fields only
    private function list($active = 0 , $fields = null)
    {
      $query = null;
      if($fields == null || $fields == '') {
        $query = Designation::select('*');
      }
      else{
        $fields = explode(',', $fields);
        $query = Designation::select($fields);
        if($active != null && $active != ''){
          $query->where([['status', '=', $active]]);
        }
      }
      return $query->get();
    }

    //search Silhouette for autocomplete
    private function autocomplete_search($search)
  	{
      // $active=1;
  		// $silhouette_lists = Silhouette::select('product_silhouette_id','product_silhouette_description')
  		// ->where([['product_silhouette_description', 'like', '%' . $search . '%']])
      // ->where('status','=',$active)
      // ->get();
  		// return $silhouette_lists;
  	}


    //get searched Silhouette for datatable plugin format
    private function datatable_search($data)
    {
      if($this->authorize->hasPermission('INC_DESIGNATION_VIEW'))//check permission
      {
      $start = $data['start'];
      $length = $data['length'];
      $draw = $data['draw'];
      $search = $data['search']['value'];
      $order = $data['order'][0];
      $order_column = $data['columns'][$order['column']]['data'];
      $order_type = $order['dir'];

      $list = Designation::select('inc_designation_equation.*','inc_equation.equation')
      ->join('inc_equation','inc_designation_equation.inc_equation_id','=','inc_equation.inc_equation_id')
      ->Where(function ($query) use ($search) {
  			$query->orWhere('emp_designation'  , 'like', $search.'%' )
              ->orWhere('inc_designation_equation.inc_equation_id', 'like', $search.'%');
  		        })
      ->orderBy($order_column, $order_type)
      ->offset($start)->limit($length)->get();

      $count = Designation::select('inc_designation_equation.*','inc_equation.equation')
      ->join('inc_equation','inc_designation_equation.inc_equation_id','=','inc_equation.inc_equation_id')
      ->Where(function ($query) use ($search) {
        $query->orWhere('emp_designation'  , 'like', $search.'%' )
              ->orWhere('inc_designation_equation.inc_equation_id', 'like', $search.'%');
  		        })
      ->count();

      return [
          "draw" => $draw,
          "recordsTotal" => $count,
          "recordsFiltered" => $count,
          "data" => $list
      ];
    }
    else{
      return response($this->authorize->error_response(), 401);
    }
  }

}
