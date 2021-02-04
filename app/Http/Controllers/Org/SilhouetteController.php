<?php

namespace App\Http\Controllers\Org;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Http\Controllers\Controller;
use App\Models\Org\Silhouette;
use App\Models\Merchandising\StyleCreation;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Libraries\CapitalizeAllFields;
use App\Models\Org\Component;

use App\Libraries\AppAuthorize;

class SilhouetteController extends Controller
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
      else if($type == 'pc-list') {
        $active = $request->active;
        $fields = $request->fields;
        return response([
          'data' => $this->load_pc_list($active , $fields)
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

    private function load_pc_list($active = 0 , $fields = null)
    {
      $fields = explode(',', $fields);
      $query = Component::select('product_component_id','product_component_description');
      return $query->get();
    }

    //create a Silhouette
    public function store(Request $request)
    {
      if($this->authorize->hasPermission('PROD_SILHOUETTE_CREATE'))//check permission
      {
      $silhouette = new Silhouette();

      $dataArr = array(
        "product_silhouette_id"=>$request->product_silhouette_id,
        "silhouette_code"=>$request->silhouette_code,
        "product_silhouette_description"=>$request->product_silhouette_description,
        "product_component"=>$request->product_component['product_component_id']
      );

      if($silhouette->validate($dataArr))
      {
        $silhouette->fill($dataArr);
        $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($silhouette);
        $silhouette->status = 1;
        $silhouette->product_silhouette_id=$silhouette->silhouette_code;
        $silhouette->save();

        return response([ 'data' => [
          'message' => 'Product Silhouette Saved Successfully',
          'silhouette' => $silhouette,
          'status'=>'1'
          ]
        ], Response::HTTP_CREATED );
      }
      else
      {
        $errors = $silhouette->errors();// failure, get errors
        $errors_str = $silhouette->errors_tostring();
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
      if($this->authorize->hasPermission('PROD_SILHOUETTE_VIEW'))//check permission
      {
      $query = DB::table('product_silhouette');
      $query->join('product_component','product_silhouette.product_component','=','product_component.product_component_id');
      $query->where('product_silhouette.product_silhouette_id', $id);
      $data = $query->first();
      if($data == null)
        throw new ModelNotFoundException("Requested silhouette not found", 1);
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
      if($this->authorize->hasPermission('PROD_SILHOUETTE_EDIT'))//check permission
      {
        //dd($id);
      //$styleCreation=StyleCreation::where([['product_silhouette_id','=',$id]]);
      $is_exsits_in_style=DB::table('style_creation')
      ->join('product_feature_component', 'style_creation.product_feature_id', '=', 'product_feature_component.product_feature_id')
      ->where('product_feature_component.product_silhouette_id',$id)
      ->where('style_creation.status','=',1)
      ->exists();
      $silhouette = Silhouette::find($id);

      $dataArr = array(
        "product_silhouette_id"=>$request->product_silhouette_id,
        "silhouette_code"=>$request->silhouette_code,
        "product_silhouette_description"=>$request->product_silhouette_description,
        "product_component"=>$request->product_component['product_component_id']
      );

      if($silhouette->validate($dataArr))
      {
        if($is_exsits_in_style==true){
          return response([ 'data' => [
            'message' => 'Product Silhouette Already in Use',
            'status'=>'0'
          ]]);
        }
        else if($is_exsits_in_style==false){
        $silhouette->fill($dataArr);
        $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($silhouette);
        $silhouette->save();

        return response([ 'data' => [
          'message' => 'Product Silhouette Updated Successfully',
          'silhouette' => $silhouette,
          'status'=>'1'
        ]]);
      }
    }
      else
      {
        $errors = $silhouette->errors();// failure, get errors
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
      if($this->authorize->hasPermission('PROD_SILHOUETTE_DELETE'))//check permission
      {
      $styleCreation=StyleCreation::where([['product_silhouette_id','=',$id]]);
      $is_exsits_in_style=DB::table('style_creation')
      ->join('product_feature_component', 'style_creation.product_feature_id', '=', 'product_feature_component.product_feature_id')
      ->where('product_feature_component.product_silhouette_id',$id)
      ->where('style_creation.status','=',1)
      ->exists();
      if($is_exsits_in_style==true){
        return response([
          'data'=>[
            'message'=>'Product Silhouette Already in Use.',
            'status'=>'0'
          ]
        ]);
      }
      else {
      $silhouette = Silhouette::where('product_silhouette_id', $id)->update(['status' => 0]);

      return response([
        'data' => [
          'message' => 'Product Silhouette Deactivated Successfully.',
          'silhouette' => $silhouette,
          'status'=>'1'
        ]
      ]);
    }
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
        return response($this->validate_duplicate_name($request->product_silhouette_id , $request->product_silhouette_description));
      }
      if($for == 'duplicate-code')
      {
        return response($this->validate_duplicate_code($request->product_silhouette_id , $request->silhouette_code));
      }
    }


    //check Silhouette code already exists
    private function validate_duplicate_name($id , $code)
    {
      $silhouette = Silhouette::where('product_silhouette_description','=',$code)->first();
      if($silhouette == null){
        return ['status' => 'success'];
      }
      else if($silhouette->product_silhouette_id == $id){
        return ['status' => 'success'];
      }
      else {
        return ['status' => 'error','message' => 'Product Silhouette Description Already Exists'];
      }
    }

    private function validate_duplicate_code($id , $code)
    {
      $silhouette = Silhouette::where('silhouette_code','=',$code)->first();
      if($silhouette == null){
        return ['status' => 'success'];
      }
      else if($silhouette->product_silhouette_id == $id){
        return ['status' => 'success'];
      }
      else {
        return ['status' => 'error','message' => 'Product Silhouette Code Already Exists'];
      }
    }


    //get filtered fields only
    private function list($active = 0 , $fields = null)
    {
      $query = null;
      if($fields == null || $fields == '') {
        $query = Silhouette::select('*');
      }
      else{
        $fields = explode(',', $fields);
        $query = Silhouette::select($fields);
        if($active != null && $active != ''){
          $query->where([['status', '=', $active]]);
        }
      }
      return $query->get();
    }

    //search Silhouette for autocomplete
    private function autocomplete_search($search)
  	{
      $active=1;
  		$silhouette_lists = Silhouette::select('product_silhouette_id','product_silhouette_description')
  		->where([['product_silhouette_description', 'like', '%' . $search . '%']])
      ->where('status','=',$active)
      ->get();
  		return $silhouette_lists;
  	}


    //get searched Silhouette for datatable plugin format
    private function datatable_search($data)
    {
      $start = $data['start'];
      $length = $data['length'];
      $draw = $data['draw'];
      $search = $data['search']['value'];
      $order = $data['order'][0];
      $order_column = $data['columns'][$order['column']]['data'];
      $order_type = $order['dir'];

      $silhouette_list = Silhouette::select('product_silhouette.*','product_component.product_component_description')
      ->join('product_component','product_silhouette.product_component','=','product_component.product_component_id')
      ->Where(function ($query) use ($search) {
  			$query->orWhere('product_silhouette_description'  , 'like', $search.'%' )
              ->orWhere('silhouette_code', 'like', $search.'%')
  				    ->orWhere('product_component.product_component_description', 'like', $search.'%');
  		        })
      ->orderBy($order_column, $order_type)
      ->offset($start)->limit($length)->get();

      $silhouette_count = Silhouette::select('product_silhouette.*','product_component.product_component_description')
      ->join('product_component','product_silhouette.product_component','=','product_component.product_component_id')
      ->Where(function ($query) use ($search) {
        $query->orWhere('product_silhouette_description'  , 'like', $search.'%' )
              ->orWhere('silhouette_code', 'like', $search.'%')
  				    ->orWhere('product_component.product_component_description', 'like', $search.'%');
  		        })
      ->count();

      return [
          "draw" => $draw,
          "recordsTotal" => $silhouette_count,
          "recordsFiltered" => $silhouette_count,
          "data" => $silhouette_list
      ];
    }

}
