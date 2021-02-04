<?php

namespace App\Http\Controllers\IncentiveCalculationSystem;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Http\Controllers\Controller;
use App\Models\IncentiveCalculationSystem\CniIncentive;
use App\Libraries\AppAuthorize;
use App\Libraries\CapitalizeAllFields;
use Illuminate\Support\Facades\DB;

class  CniIncentiveController extends Controller
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
      if($this->authorize->hasPermission('CNI_CREATE'))//check permission
      {

        $cniincentive = new  CniIncentive ();
        if($cniincentive->validate($request->all()))
        {
          $cniincentive->fill($request->all());
          $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($cniincentive);
          $cniincentive->status = 1;
          $cniincentive->inc_cni_id=$cniincentive->cni;
          $cniincentive->save();

          return response([ 'data' => [
            'message' => 'CNI Incentive Factor saved successfully',
            'aqlincentive' => $cniincentive,
            'status'=>1
            ]
          ], Response::HTTP_CREATED );
        }else{
          $errors = $cniincentive->errors();// failure, get errors
          $errors_str = $cniincentive->errors_tostring();
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
      if($this->authorize->hasPermission('CNI_VIEW'))//check permission
      {
        $cniincentive = CniIncentive::find($id);
        if($cniincentive == null)
          throw new ModelNotFoundException("Requested CNI Incentive Factor not found", 1);
        else
          return response([ 'data' => $cniincentive]);
      }
      else{
        return response($this->authorize->error_response(), 401);
      }
    }


    //update a shipment term
    public function update(Request $request, $id)
    {
      if($this->authorize->hasPermission('CNI_EDIT'))//check permission
      {
        $check_emp = CniIncentive::join('inc_production_incentive','inc_production_incentive.cni','=','inc_cni.paid_rate')
                  -> where('inc_cni_id'  , '=',  $id )->count();
        if($check_emp > 0){
          $err = 'CNI Incentive Factor Already in Use.';
          return response([ 'data' => ['status' => '0','message' => $err]]);

        }
        $cniincentive =  CniIncentive::find($id);
        $cniincentive->fill($request->all());
        $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($cniincentive);
        $cniincentive->save();

        return response([ 'data' => [
          'message' => 'CNI Incentive Factor updated successfully',
          'transaction' => $cniincentive,
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
      if($this->authorize->hasPermission('CNI_DELETE'))//check permission
      {
        $check_emp = CniIncentive::join('inc_production_incentive','inc_production_incentive.cni','=','inc_cni.paid_rate')
                  -> where('inc_cni_id'  , '=',  $id )->count();
        if($check_emp > 0){
          $err = 'CNI Incentive Factor Already in Use.';
          return response([ 'data' => ['status' => '0','message' => $err]]);

        }
        $cniincentive =CniIncentive::where('inc_cni_id', $id)->update(['status' => 0]);
        return response([ 'data' => [
          'message' => 'CNI Incentive Factor Deactived sucessfully',
          'status' => '1',
          'productSpesication'=>$cniincentive
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
        return response($this->validate_duplicate_name($request->inc_cni_id , $request->paid_rate));
      }
      else if($for == 'duplicate-code')
      {
        return response($this->validate_duplicate_code($request->inc_cni_id , $request->cni));
      }
    }

    //check shipment cterm code code already exists
    private function validate_duplicate_code($id , $code)
    {
       $cniincentive = CniIncentive::where([['cni','=',$code]])->first();

      if( $cniincentive  == null){
         echo json_encode(array('status' => 'success'));
      }
      else if( $cniincentive ->inc_cni_id == $id){
         echo json_encode(array('status' => 'success'));
      }
      else {
       echo json_encode(array('status' => 'error','message' => 'CNI Code Already Exists'));
      }
    }


    //check shipment cterm code code already exists
    private function validate_duplicate_name($id , $code)
    {
       $cniincentive = CniIncentive::where([['paid_rate','=',$code]])->first();

      if( $cniincentive  == null){
         echo json_encode(array('status' => 'success'));
      }
      else if( $cniincentive ->inc_cni_id == $id){
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
        $query = CniIncentive::select('*');
      }
      else{
        $fields = explode(',', $fields);
        $query = CniIncentive::select($fields);
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
      if($this->authorize->hasPermission('CNI_VIEW'))//check permission
      {
        $start = $data['start'];
        $length = $data['length'];
        $draw = $data['draw'];
        $search = $data['search']['value'];
        $order = $data['order'][0];
        $order_column = $data['columns'][$order['column']]['data'];
        $order_type = $order['dir'];

        $transaction_list = CniIncentive::select('*')
        ->Where(function ($query) use ($search) {
    			$query->orWhere('cni', 'like', $search.'%')
    				    ->orWhere('paid_rate', 'like', $search.'%');
    		        })
        ->orderBy($order_column, $order_type)
        ->offset($start)->limit($length)->get();

        $transaction_count = CniIncentive::where('cni'  , 'like', $search.'%' )
        ->where('paid_rate'  , 'like', $search.'%' )
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
