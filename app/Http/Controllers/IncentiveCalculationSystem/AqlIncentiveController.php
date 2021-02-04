<?php

namespace App\Http\Controllers\IncentiveCalculationSystem;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Http\Controllers\Controller;
use App\Models\IncentiveCalculationSystem\AqlIncentive;
use App\Libraries\AppAuthorize;
use App\Libraries\CapitalizeAllFields;
use Illuminate\Support\Facades\DB;

class  AqlIncentiveController extends Controller
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
      if($this->authorize->hasPermission('AQL_CREATE'))//check permission
      {

        $aqlincentive = new  AqlIncentive ();
        if($aqlincentive->validate($request->all()))
        {
          $aqlincentive->fill($request->all());
          $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($aqlincentive);
          $aqlincentive->status = 1;
          $aqlincentive->inc_aql_id=$aqlincentive->aql;
          $aqlincentive->save();

          return response([ 'data' => [
            'message' => 'AQL Incentive Factor saved successfully',
            'aqlincentive' => $aqlincentive,
            'status'=>1
            ]
          ], Response::HTTP_CREATED );
        }else{
          $errors = $aqlincentive->errors();// failure, get errors
          $errors_str = $aqlincentive->errors_tostring();
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
      if($this->authorize->hasPermission('AQL_VIEW'))//check permission
      {
        $aqlincentive = AqlIncentive::find($id);
        if($aqlincentive == null)
          throw new ModelNotFoundException("Requested AQL Incentive Factor not found", 1);
        else
          return response([ 'data' => $aqlincentive]);
      }
      else{
        return response($this->authorize->error_response(), 401);
      }
    }


    //update a shipment term
    public function update(Request $request, $id)
    {
      if($this->authorize->hasPermission('AQL_EDIT'))//check permission
      {

        $check_emp = AqlIncentive::join('inc_production_incentive','inc_production_incentive.aql','=','inc_aql.paid_rate')
                  -> where('inc_aql_id'  , '=',  $id )->count();
        if($check_emp > 0){
          $err = 'AQL Incentive Factor Already in Use.';
          return response([ 'data' => ['status' => '0','message' => $err]]);

        }


        $aqlincentive =  AqlIncentive::find($id);
        $aqlincentive->fill($request->all());
        $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($aqlincentive);
        $aqlincentive->save();

        return response([ 'data' => [
          'message' => 'AQL Incentive Factor updated successfully',
          'transaction' => $aqlincentive,
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
      if($this->authorize->hasPermission('AQL_DELETE'))//check permission
      {
        $check_emp = AqlIncentive::join('inc_production_incentive','inc_production_incentive.aql','=','inc_aql.paid_rate')
                  -> where('inc_aql_id'  , '=',  $id )->count();
        if($check_emp > 0){
          $err = 'AQL Incentive Factor Already in Use.';
          return response([ 'data' => ['status' => '0','message' => $err]]);

        }
        $aqlincentive =AqlIncentive::where('inc_aql_id', $id)->update(['status' => 0]);
        return response([ 'data' => [
          'message' => 'AQL Incentive Factor Deactived sucessfully',
          'status' => '1',
          'productSpesication'=>$aqlincentive
        ]]);
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
        return response($this->validate_duplicate_name($request->inc_aql_id , $request->paid_rate));
      }
      else if($for == 'duplicate-code')
      {
        return response($this->validate_duplicate_code($request->inc_aql_id , $request->aql));
      }
    }

    //check shipment cterm code code already exists
    private function validate_duplicate_code($id , $code)
    {
       $aqlincentive = AqlIncentive::where([['aql','=',$code]])->first();

      if( $aqlincentive  == null){
         echo json_encode(array('status' => 'success'));
      }
      else if( $aqlincentive ->inc_aql_id == $id){
         echo json_encode(array('status' => 'success'));
      }
      else {
       echo json_encode(array('status' => 'error','message' => 'AQL Code Already Exists'));
      }
    }


    //check shipment cterm code code already exists
    private function validate_duplicate_name($id , $code)
    {
       $aqlincentive = AqlIncentive::where([['paid_rate','=',$code]])->first();

      if( $aqlincentive  == null){
         echo json_encode(array('status' => 'success'));
      }
      else if( $aqlincentive ->inc_aql_id == $id){
         echo json_encode(array('status' => 'success'));
      }
      else {
       echo json_encode(array('status' => 'error','message' => 'Percentage of Amount Paid Already Exists'));
      }
    }

    //get filtered fields only
    private function list($active = 0 , $fields = null)
    {
      $query = null;
      if($fields == null || $fields == '') {
        $query = AqlIncentive::select('*');
      }
      else{
        $fields = explode(',', $fields);
        $query = AqlIncentive::select($fields);
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
      if($this->authorize->hasPermission('AQL_VIEW'))//check permission
      {
        $start = $data['start'];
        $length = $data['length'];
        $draw = $data['draw'];
        $search = $data['search']['value'];
        $order = $data['order'][0];
        $order_column = $data['columns'][$order['column']]['data'];
        $order_type = $order['dir'];

        $transaction_list = AqlIncentive::select('*')
        ->Where(function ($query) use ($search) {
    			$query->orWhere('aql', 'like', $search.'%')
    				    ->orWhere('paid_rate', 'like', $search.'%')
                ->orWhere('created_date'  , 'like', $search.'%' );
    		        })
        ->orderBy($order_column, $order_type)
        ->offset($start)->limit($length)->get();

        $transaction_count = AqlIncentive::where('aql'  , 'like', $search.'%' )
        ->where('paid_rate'  , 'like', $search.'%' )->where('created_date'  , 'like', $search.'%' )
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
