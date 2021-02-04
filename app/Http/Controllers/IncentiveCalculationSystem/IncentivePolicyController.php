<?php

namespace App\Http\Controllers\IncentiveCalculationSystem;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Http\Controllers\Controller;
use App\Models\IncentiveCalculationSystem\IncentivePolicy;
use App\Libraries\AppAuthorize;
use App\Libraries\CapitalizeAllFields;
use Illuminate\Support\Facades\DB;

class  IncentivePolicyController extends Controller
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
      if($this->authorize->hasPermission('INCENTIVE_POLICY_CREATE'))//check permission
      {

        $IncentivePolicy = new  IncentivePolicy ();
        if($IncentivePolicy->validate($request->all()))
        {
          $IncentivePolicy->fill($request->all());
          $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($IncentivePolicy);
          $IncentivePolicy->status = 1;
          $IncentivePolicy->inc_inc_policy_id=$IncentivePolicy->inc_policy_paid_rate;
          $IncentivePolicy->save();

          return response([ 'data' => [
            'message' => 'Incentive Policy saved successfully',
            'aqlincentive' => $IncentivePolicy,
            'status'=>1
            ]
          ], Response::HTTP_CREATED );
        }else{
          $errors = $IncentivePolicy->errors();// failure, get errors
          $errors_str = $IncentivePolicy->errors_tostring();
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
      if($this->authorize->hasPermission('INCENTIVE_POLICY_VIEW'))//check permission
      {
        $IncentivePolicy = IncentivePolicy::find($id);
        if($IncentivePolicy == null)
          throw new ModelNotFoundException("Requested Special Facto not found", 1);
        else
          return response([ 'data' => $IncentivePolicy]);
      }
      else{
        return response($this->authorize->error_response(), 401);
      }
    }


    //update a shipment term
    public function update(Request $request, $id)
    {
      if($this->authorize->hasPermission('INCENTIVE_POLICY_EDIT'))//check permission
      {
        // $is_exists=DB::table('style_creation')->where('product_category_id',$id)->exists();
        // if($is_exists==true){
        //
        //   return response([ 'data' => [
        //     'message' => 'Product Type Already in Use',
        //     'status' => '0'
        //   ]]);
        // }
        // else {
        $IncentivePolicy =  IncentivePolicy::find($id);
        $IncentivePolicy->fill($request->all());
        $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($IncentivePolicy);
        $IncentivePolicy->save();

        return response([ 'data' => [
          'message' => 'Incentive Policy updated successfully',
          'transaction' => $IncentivePolicy,
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
      if($this->authorize->hasPermission('INCENTIVE_POLICY_DELETE'))//check permission
      {
        //   $is_exists=DB::table('style_creation')->where('product_category_id',$id)->exists();
        // if($is_exists==true){
        //   return response([ 'data' => [
        //     'message' => 'Product Type Already in Use',
        //     'status' => '0'
        //   ]]);
        // }
        //
        // else {
        $IncentivePolicy =IncentivePolicy::where('inc_inc_policy_id', $id)->update(['status' => 0]);
        return response([ 'data' => [
          'message' => 'Incentive Policy Deactived sucessfully',
          'status' => '1',
          'productSpesication'=>$IncentivePolicy
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
        return response($this->validate_duplicate_name($request->inc_buffer_id , $request->hours));
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
       $IncentivePolicy = IncentivePolicy::where([['inc_policy_paid_rate','=',$code]])->first();

      if( $IncentivePolicy  == null){
         echo json_encode(array('status' => 'success'));
      }
      else if( $IncentivePolicy ->inc_buffer_id == $id){
         echo json_encode(array('status' => 'success'));
      }
      else {
       echo json_encode(array('status' => 'error','message' => 'Incentive Already Exists'));
      }
    }

    //get filtered fields only
    private function list($active = 0 , $fields = null)
    {
      $query = null;
      if($fields == null || $fields == '') {
        $query = IncentivePolicy::select('*');
      }
      else{
        $fields = explode(',', $fields);
        $query = IncentivePolicy::select($fields);
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
      if($this->authorize->hasPermission('INCENTIVE_POLICY_VIEW'))//check permission
      {
        $start = $data['start'];
        $length = $data['length'];
        $draw = $data['draw'];
        $search = $data['search']['value'];
        $order = $data['order'][0];
        $order_column = $data['columns'][$order['column']]['data'];
        $order_type = $order['dir'];

        $type_list = IncentivePolicy::select('*')
        ->where('inc_policy_paid_rate'  , 'like', $search.'%' )
        ->orderBy($order_column, $order_type)
        ->offset($start)->limit($length)->get();

        $type_count = IncentivePolicy::where('inc_policy_paid_rate'  , 'like', $search.'%' )->count();

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
