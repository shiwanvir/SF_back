<?php

namespace App\Http\Controllers\IncentiveCalculationSystem;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Libraries\CapitalizeAllFields;
use App\Models\IncentiveCalculationSystem\Section;
use App\Models\Org\Location\Location;


use App\Libraries\AppAuthorize;

class SectionController extends Controller
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
      }else if($type == 'pc-list') {
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
      $query = Location::select('loc_id','loc_name','raise_intCompanyID')->where('status','<>', 0);
      return $query->get();
    }


    //create a Silhouette
    public function store(Request $request)
    {
      if($this->authorize->hasPermission('INC_SECTION_CREATE'))//check permission
      {
        //dd($request);
        $section = new  Section ();
        if($section->validate($request->all()))
        {
          $section->fill($request->all());
          $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($section);
          $section->status = 1;
          $section->loc_id=$request['loc_id']['loc_id'];
          $section->raise_intCompanyID=$request['loc_id']['raise_intCompanyID'];
          $section->save();

          return response([ 'data' => [
            'message' => 'Section saved successfully',
            'aqlincentive' => $section,
            'status'=>1
            ]
          ], Response::HTTP_CREATED );
        }else{
          $errors = $section->errors();// failure, get errors
          $errors_str = $section->errors_tostring();
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
      if($this->authorize->hasPermission('INC_SECTION_VIEW'))//check permission
      {
        $query = DB::table('inc_section');
        $query->join('org_location','inc_section.loc_id','=','org_location.loc_id');
        $query->where('inc_section.inc_section_id', $id);
        $data = $query->first();
        if($data == null)
          throw new ModelNotFoundException("Requested Section not found", 1);
        else
          return response([ 'data' => $data]);
      }
      else{
        return response($this->authorize->error_response(), 401);
      }
    }

    //update a Silhouette
    public function update(Request $request, $id)
    {
      if($this->authorize->hasPermission('INC_SECTION_EDIT'))//check permission
      {

        $check_ = Section::join('inc_employee','inc_employee.line_no','=','inc_section.line_no')
                  -> where('inc_section.inc_section_id'  , '=',  $id )->count();
        if($check_ > 0){
          $err = 'Section Already in Use.';
          return response([ 'data' => ['status' => '0','message' => $err]]);

        }


        $section =  Section::find($id);
        $section->fill($request->all());
        $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($section);
        $section->loc_id=$request['loc_id']['loc_id'];
        $section->save();

        return response([ 'data' => [
          'message' => 'Section updated successfully',
          'transaction' => $section,
          'status'=>'1'
        ]]);
      // }
    }
      else
      {
        $errors = $designation->errors();// failure, get errors
        return response(['errors' => ['validationErrors' => $errors]], Response::HTTP_UNPROCESSABLE_ENTITY);
      }


    }


    //deactivate a Silhouette
    public function destroy($id)
    {
      if($this->authorize->hasPermission('INC_SECTION_DELETE'))//check permission
      {
        $check_ = Section::join('inc_employee','inc_employee.line_no','=','inc_section.line_no')
                  -> where('inc_section.inc_section_id'  , '=',  $id )->count();
        if($check_ > 0){
          $err = 'Section Already in Use.';
          return response([ 'data' => ['status' => '0','message' => $err]]);

        }
        
      $designation = Section::where('inc_section_id', $id)->update(['status' => 0]);

      return response([
        'data' => [
          'message' => 'Section Deactivated Successfully.',
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
        return response($this->validate_duplicate_name($request->inc_section_id , $request->line_no , $request->loc_id));
      }
      if($for == 'duplicate-code')
      {
        //return response($this->validate_duplicate_code($request->product_silhouette_id , $request->silhouette_code));
      }
    }


    //check Silhouette code already exists
    private function validate_duplicate_name($id , $code , $loc)
    {
      $section = Section::where('line_no','=',$code)->where('loc_id','=',$loc)->first();
      if($section == null){
        return ['status' => 'success'];
      }
      else if($section->inc_section_id == $id){
        return ['status' => 'success'];
      }
      else {
        return ['status' => 'error','message' => 'Section Already Exists'];
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
        $query = Section::select('*');
      }
      else{
        $fields = explode(',', $fields);
        $query = Section::select($fields);
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
      if($this->authorize->hasPermission('INC_SECTION_VIEW'))//check permission
      {
      $start = $data['start'];
      $length = $data['length'];
      $draw = $data['draw'];
      $search = $data['search']['value'];
      $order = $data['order'][0];
      $order_column = $data['columns'][$order['column']]['data'];
      $order_type = $order['dir'];

      $list = Section::join('org_location','inc_section.loc_id','=','org_location.loc_id')->select('inc_section.*','org_location.loc_name')
      ->Where(function ($query) use ($search) {
  			$query->orWhere('org_location.loc_name'  , 'like', $search.'%' )
              ->orWhere('inc_section.line_no', 'like', $search.'%');
  		        })
      ->orderBy($order_column, $order_type)
      ->offset($start)->limit($length)->get();

      $count = Section::join('org_location','inc_section.loc_id','=','org_location.loc_id')->select('inc_section.*','org_location.loc_name')
      ->Where(function ($query) use ($search) {
        $query->orWhere('org_location.loc_name'  , 'like', $search.'%' )
              ->orWhere('inc_section.line_no', 'like', $search.'%');
  		        })
      ->count();

      return [
          "draw" => $draw,
          "recordsTotal" => $count,
          "recordsFiltered" => $count,
          "data" => $list
      ];
    }else{
        return response($this->authorize->error_response(), 401);
      }
    }

}
