<?php

namespace App\Http\Controllers\IncentiveCalculationSystem;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Http\Controllers\Controller;
use App\Models\IncentiveCalculationSystem\Equation;
use App\Libraries\AppAuthorize;
use App\Libraries\CapitalizeAllFields;
use Illuminate\Support\Facades\DB;

class  EquationController extends Controller
{
    var $authorize = null;

    public function __construct()
    {
      //add functions names to 'except' paramert to skip authentication
      $this->middleware('jwt.verify', ['except' => ['index']]);
      $this->authorize = new AppAuthorize();
    }

    //get product specification listerm list
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
      }else if($type == 'pc-list') {
        $active = $request->active;
        $fields = $request->fields;
        return response([
          'data' => $this->load_pc_list($active , $fields)
        ]);
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
      //dd($request);
      if($this->authorize->hasPermission('EQUATION_CREATE'))//check permission
      {

        $equation = new  Equation ();
        if($equation->validate($request->all()))
        {
          $equation->fill($request->all());
          $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($equation);
          $equation->status = 1;
          $equation->inc_equation_id=$equation->equation;
          $equation->save();

          return response([ 'data' => [
            'message' => 'Equation saved successfully',
            'aqlincentive' => $equation,
            'status'=>1
            ]
          ], Response::HTTP_CREATED );
        }else{
          $errors = $equation->errors();// failure, get errors
          $errors_str = $equation->errors_tostring();
          return response(['errors' => ['validationErrors' => $errors, 'validationErrorsText' => $errors_str]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

      }
      else{
        return response($this->authorize->error_response(), 401);
      }
    }

    //get shipment term
    public function show($id)
    {
      if($this->authorize->hasPermission('EQUATION_VIEW'))//check permission
      {
        $equation = Equation::find($id);
        if($equation == null)
          throw new ModelNotFoundException("Requested Equation not found", 1);
        else
          return response([ 'data' => $equation]);
      }
      else{
        return response($this->authorize->error_response(), 401);
      }
    }


    //update a shipment term
    public function update(Request $request, $id)
    {
      if($this->authorize->hasPermission('EQUATION_EDIT'))//check permission
      {
        $check_ = Equation::join('inc_designation_equation','inc_designation_equation.inc_equation_id','=','inc_equation.inc_equation_id')
                  -> where('inc_equation.inc_equation_id'  , '=',  $id )->count();
        if($check_ > 0){
          $err = 'Equation Already in Use.';
          return response([ 'data' => ['status' => '0','message' => $err]]);

        }

        $equation =  Equation::find($id);
        $equation->fill($request->all());
        $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($equation);
        $equation->save();

        return response([ 'data' => [
          'message' => 'Equation updated successfully',
          'transaction' => $equation,
          'status'=>'1'
        ]]);
      // }
      }
      else{
        return response($this->authorize->error_response(), 401);
      }
    }



    //deactivate a ship term
    public function destroy($id)
    {
      if($this->authorize->hasPermission('EQUATION_DELETE'))//check permission
      {
        $check_ = Equation::join('inc_designation_equation','inc_designation_equation.inc_equation_id','=','inc_equation.inc_equation_id')
                  -> where('inc_equation.inc_equation_id'  , '=',  $id )->count();
        if($check_ > 0){
          $err = 'Equation Already in Use.';
          return response([ 'data' => ['status' => '0','message' => $err]]);

        }
        $equation =Equation::where('inc_equation_id', $id)->update(['status' => 0]);
        return response([ 'data' => [
          'message' => 'Equation Deactived sucessfully',
          'status' => '1',
          'productSpesication'=>$equation
        ]]);
      // }
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
        return response($this->validate_duplicate_name($request->inc_equation_id , $request->equation));
      }
      else if($for == 'duplicate-code')
      {
        return response($this->validate_duplicate_code($request->inc_equation_id , $request->present_factor));
      }
    }

    //check shipment cterm code code already exists
    private function validate_duplicate_code($id , $code)
    {
       $equation = Equation::where([['present_factor','=',$code]])->first();

      if( $equation  == null){
         echo json_encode(array('status' => 'success'));
      }
      else if( $equation ->inc_equation_id == $id){
         echo json_encode(array('status' => 'success'));
      }
      else {
       echo json_encode(array('status' => 'error','message' => 'Present Factor Already Exists'));
      }
    }


    //check shipment cterm code code already exists
    private function validate_duplicate_name($id , $code)
    {
       $equation = Equation::where([['equation','=',$code]])->first();

      if( $equation  == null){
         echo json_encode(array('status' => 'success'));
      }
      else if( $equation ->inc_equation_id == $id){
         echo json_encode(array('status' => 'success'));
      }
      else {
       echo json_encode(array('status' => 'error','message' => 'Equation Already Exists'));
      }
    }

    //get filtered fields only
    private function list($active = 0 , $fields = null)
    {
      $query = null;
      if($fields == null || $fields == '') {
        $query = Equation::select('*');
      }
      else{
        $fields = explode(',', $fields);
        $query = Equation::select($fields);
        if($active != null && $active != ''){
          $query->where([['status', '=', $active]]);
        }
      }
      return $query->get();
    }


    //search shipment terms for autocomplete
    private function autocomplete_search($search)
  	{
  		// $transaction_lists = AqlIncentive::select('prod_cat_description')
  		// ->where([['prod_cat_description', 'like', '%' . $search . '%'],]) ->get();
  		// return $transaction_lists;
  	}

    private function load_pc_list($active = 0 , $fields = null)
    {
      $fields = explode(',', $fields);
      $query = Equation::select('inc_equation_id','equation')->where('status','<>', 0);
      return $query->get();
    }


    //get searched ship terms for datatable plugin format
    private function datatable_search($data)
    {
      if($this->authorize->hasPermission('EQUATION_VIEW'))//check permission
      {
        $start = $data['start'];
        $length = $data['length'];
        $draw = $data['draw'];
        $search = $data['search']['value'];
        $order = $data['order'][0];
        $order_column = $data['columns'][$order['column']]['data'];
        $order_type = $order['dir'];

        $transaction_list = Equation::select('*')
        ->Where(function ($query) use ($search) {
    			$query->orWhere('equation', 'like', $search.'%')
    				    ->orWhere('present_factor', 'like', $search.'%');
    		        })
        ->orderBy($order_column, $order_type)
        ->offset($start)->limit($length)->get();

        $transaction_count = Equation::where('equation'  , 'like', $search.'%' )
        ->where('present_factor'  , 'like', $search.'%' )
        ->count();

        return [
            "draw" => $draw,
            "recordsTotal" => $transaction_count,
            "recordsFiltered" => $transaction_count,
            "data" => $transaction_list
        ];
      }
      else{
        return response($this->authorize->error_response(), 401);
      }
    }

}
