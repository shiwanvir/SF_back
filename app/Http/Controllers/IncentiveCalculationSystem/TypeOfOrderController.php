<?php

namespace App\Http\Controllers\IncentiveCalculationSystem;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Http\Controllers\Controller;
use App\Models\IncentiveCalculationSystem\Typeoforder;
use App\Libraries\AppAuthorize;
use App\Libraries\CapitalizeAllFields;
use Illuminate\Support\Facades\DB;

class  TypeOfOrderController extends Controller
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
      if($this->authorize->hasPermission('TYPE_OF_ORDER_CREATE'))//check permission
      {

        $typeofOrder = new  Typeoforder ();
        if($typeofOrder->validate($request->all()))
        {
          $typeofOrder->fill($request->all());
          $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($typeofOrder);
          $typeofOrder->status = 1;
          $typeofOrder->inc_order_id=$typeofOrder->order_type;
          $typeofOrder->save();

          return response([ 'data' => [
            'message' => 'Order Type saved successfully',
            'aqlincentive' => $typeofOrder,
            'status'=>1
            ]
          ], Response::HTTP_CREATED );
        }else{
          $errors = $typeofOrder->errors();// failure, get errors
          $errors_str = $typeofOrder->errors_tostring();
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
      if($this->authorize->hasPermission('TYPE_OF_ORDER_VIEW'))//check permission
      {
        $typeofOrder = Typeoforder::find($id);
        if($typeofOrder == null)
          throw new ModelNotFoundException("Requested Order Type not found", 1);
        else
          return response([ 'data' => $typeofOrder]);
      }
      else{
        return response($this->authorize->error_response(), 401);
      }
    }


    //update a shipment term
    public function update(Request $request, $id)
    {
      if($this->authorize->hasPermission('TYPE_OF_ORDER_EDIT'))//check permission
      {
        $check_ = Typeoforder::join('inc_efficiency_ladder','inc_efficiency_ladder.order_type','=','inc_type_of_order.inc_order_id')
                  -> where('inc_type_of_order.inc_order_id'  , '=',  $id )->count();
        if($check_ > 0){
          $err = 'Order Type Already in Use.';
          return response([ 'data' => ['status' => '0','message' => $err]]);

        }

        $typeofOrder =  Typeoforder::find($id);
        $typeofOrder->fill($request->all());
        $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($typeofOrder);
        $typeofOrder->save();

        return response([ 'data' => [
          'message' => 'Order Type updated successfully',
          'transaction' => $typeofOrder,
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
      if($this->authorize->hasPermission('TYPE_OF_ORDER_DELETE'))//check permission
      {
        $check_ = Typeoforder::join('inc_efficiency_ladder','inc_efficiency_ladder.order_type','=','inc_type_of_order.inc_order_id')
                  -> where('inc_type_of_order.inc_order_id'  , '=',  $id )->count();
        if($check_ > 0){
          $err = 'Order Type Already in Use.';
          return response([ 'data' => ['status' => '0','message' => $err]]);

        }
        
        $typeofOrder =Typeoforder::where('inc_order_id', $id)->update(['status' => 0]);
        return response([ 'data' => [
          'message' => 'Order Type Deactived sucessfully',
          'status' => '1',
          'productSpesication'=>$typeofOrder
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
        return response($this->validate_duplicate_name($request->inc_order_id , $request->order_type));
      }
      else if($for == 'duplicate-code')
      {
        //return response($this->validate_duplicate_code($request->inc_special_factor_id , $request->special_factor));
      }
    }

    //check shipment cterm code code already exists
    // private function validate_duplicate_code($id , $code)
    // {
    //    $specialfac = SpecialFactor::where([['special_factor','=',$code]])->first();
    //
    //   if( $specialfac  == null){
    //      echo json_encode(array('status' => 'success'));
    //   }
    //   else if( $specialfac ->inc_special_factor_id == $id){
    //      echo json_encode(array('status' => 'success'));
    //   }
    //   else {
    //    echo json_encode(array('status' => 'error','message' => 'Special Factor Already Exists'));
    //   }
    // }


    //check shipment cterm code code already exists
    private function validate_duplicate_name($id , $code)
    {
       $typeofOrder = Typeoforder::where([['order_type','=',$code]])->first();

      if( $typeofOrder  == null){
         echo json_encode(array('status' => 'success'));
      }
      else if( $typeofOrder ->inc_order_id == $id){
         echo json_encode(array('status' => 'success'));
      }
      else {
       echo json_encode(array('status' => 'error','message' => 'Order Type Already Exists'));
      }
    }

    //get filtered fields only
    private function list($active = 0 , $fields = null)
    {
      $query = null;
      if($fields == null || $fields == '') {
        $query = Typeoforder::select('*');
      }
      else{
        $fields = explode(',', $fields);
        $query = Typeoforder::select($fields);
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


    //get searched ship terms for datatable plugin format
    private function datatable_search($data)
    {
      if($this->authorize->hasPermission('TYPE_OF_ORDER_VIEW'))//check permission
      {
        $start = $data['start'];
        $length = $data['length'];
        $draw = $data['draw'];
        $search = $data['search']['value'];
        $order = $data['order'][0];
        $order_column = $data['columns'][$order['column']]['data'];
        $order_type = $order['dir'];

        $type_list = Typeoforder::select('*')
        ->where('order_type'  , 'like', $search.'%' )
        ->orderBy($order_column, $order_type)
        ->offset($start)->limit($length)->get();

        $type_count = Typeoforder::where('order_type'  , 'like', $search.'%' )->count();

        return [
            "draw" => $draw,
            "recordsTotal" => $type_count,
            "recordsFiltered" => $type_count,
            "data" => $type_list
        ];
      }
      else{
        return response($this->authorize->error_response(), 401);
      }
    }

}
