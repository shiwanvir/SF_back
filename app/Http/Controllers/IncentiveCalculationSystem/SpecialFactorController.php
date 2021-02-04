<?php

namespace App\Http\Controllers\IncentiveCalculationSystem;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Http\Controllers\Controller;
use App\Models\IncentiveCalculationSystem\SpecialFactor;
use App\Libraries\AppAuthorize;
use App\Libraries\CapitalizeAllFields;
use Illuminate\Support\Facades\DB;

class  SpecialFactorController extends Controller
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
      if($this->authorize->hasPermission('SPECIAL_FACTOR_CREATE'))//check permission
      {

        $specialfac = new  SpecialFactor ();
        if($specialfac->validate($request->all()))
        {
          $specialfac->fill($request->all());
          $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($specialfac);
          $specialfac->status = 1;
          $specialfac->inc_special_factor_id=$specialfac->special_factor;
          $specialfac->save();

          return response([ 'data' => [
            'message' => 'Special Factor saved successfully',
            'aqlincentive' => $specialfac,
            'status'=>1
            ]
          ], Response::HTTP_CREATED );
        }else{
          $errors = $specialfac->errors();// failure, get errors
          $errors_str = $specialfac->errors_tostring();
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
      if($this->authorize->hasPermission('SPECIAL_FACTOR_VIEW'))//check permission
      {
        $specialfac = SpecialFactor::find($id);
        if($specialfac == null)
          throw new ModelNotFoundException("Requested Special Facto not found", 1);
        else
          return response([ 'data' => $specialfac]);
      }
      else{
        return response($this->authorize->error_response(), 401);
      }
    }


    //update a shipment term
    public function update(Request $request, $id)
    {
      if($this->authorize->hasPermission('SPECIAL_FACTOR_EDIT'))//check permission
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
        $specialfac =  SpecialFactor::find($id);
        $specialfac->fill($request->all());
        $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($specialfac);
        $specialfac->save();

        return response([ 'data' => [
          'message' => 'Special Factor updated successfully',
          'transaction' => $specialfac,
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
      if($this->authorize->hasPermission('SPECIAL_FACTOR_DELETE'))//check permission
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
        $specialfac =SpecialFactor::where('inc_special_factor_id', $id)->update(['status' => 0]);
        return response([ 'data' => [
          'message' => 'Special Factor Deactived sucessfully',
          'status' => '1',
          'productSpesication'=>$specialfac
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
        return response($this->validate_duplicate_name($request->inc_special_factor_id , $request->paid_rate));
      }
      else if($for == 'duplicate-code')
      {
        return response($this->validate_duplicate_code($request->inc_special_factor_id , $request->special_factor));
      }
    }

    //check shipment cterm code code already exists
    private function validate_duplicate_code($id , $code)
    {
       $specialfac = SpecialFactor::where([['special_factor','=',$code]])->first();

      if( $specialfac  == null){
         echo json_encode(array('status' => 'success'));
      }
      else if( $specialfac ->inc_special_factor_id == $id){
         echo json_encode(array('status' => 'success'));
      }
      else {
       echo json_encode(array('status' => 'error','message' => 'Special Factor Already Exists'));
      }
    }


    //check shipment cterm code code already exists
    private function validate_duplicate_name($id , $code)
    {
       $specialfac = SpecialFactor::where([['paid_rate','=',$code]])->first();

      if( $specialfac  == null){
         echo json_encode(array('status' => 'success'));
      }
      else if( $specialfac ->inc_special_factor_id == $id){
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
        $query = SpecialFactor::select('*');
      }
      else{
        $fields = explode(',', $fields);
        $query = SpecialFactor::select($fields);
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
      if($this->authorize->hasPermission('SPECIAL_FACTOR_VIEW'))//check permission
      {
        $start = $data['start'];
        $length = $data['length'];
        $draw = $data['draw'];
        $search = $data['search']['value'];
        $order = $data['order'][0];
        $order_column = $data['columns'][$order['column']]['data'];
        $order_type = $order['dir'];

        $transaction_list = SpecialFactor::select('*')
        ->Where(function ($query) use ($search) {
    			$query->orWhere('special_factor', 'like', $search.'%')
    				    ->orWhere('paid_rate', 'like', $search.'%');
    		        })
        ->orderBy($order_column, $order_type)
        ->offset($start)->limit($length)->get();

        $transaction_count = SpecialFactor::where('special_factor'  , 'like', $search.'%' )
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
