<?php

namespace App\Http\Controllers\IncentiveCalculationSystem;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Http\Controllers\Controller;
use App\Models\IncentiveCalculationSystem\IndirectLadderUpload;
use App\Models\IncentiveCalculationSystem\IndirectLadderUploadHeader;
use App\Models\IncentiveCalculationSystem\TempIndirectLadderUpload;
use App\Models\Org\Location\Location;
use App\Libraries\AppAuthorize;
use App\Libraries\CapitalizeAllFields;
use Illuminate\Support\Facades\DB;

class  IndirectLadderUploadController extends Controller
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
      $user = auth()->user();

      if($this->authorize->hasPermission('INDIRECT_LADDER_CREATE'))//check permission
      {
        $check_year = IndirectLadderUploadHeader::where('indirect_ladder_year'  , '=',  date("Y") )->count();
        if($check_year > 0){
          $err2 = 'This Year ('.date("Y").') already exists.';
          return response([ 'data' => ['status' => '0','message' => $err2]]);

        }

        $max_no = TempIndirectLadderUpload::max('serial');
  	    if($max_no == NULL){ $max_no= 1;}else{$max_no= $max_no+1;}

        if($request['length'] != null && $request['length'] >= 1){
        for($y = 0 ; $y < $request['length'] ; $y++){

          $EFF = explode("%",$request['data'][$y]['Efficiency']);
          $temp_ladder = new TempIndirectLadderUpload();
          $temp_ladder->indirect_location = $request['data'][$y]['Location'];
          $temp_ladder->efficeincy_rate = $EFF[0];
          $temp_ladder->incentive_payment = $request['data'][$y]['Incentive Amount'];
          $temp_ladder->ladder_year = date("Y");
          $temp_ladder->serial = $max_no;
          $temp_ladder->status = 1;
          $temp_ladder->save();

        }

        $load_temp_ladder =TempIndirectLadderUpload::select('indirect_location')
                         ->where('user_loc_id' , '=', $user['user_loc_id'] )
                         ->where('created_by', '=', $user['user_id'] )
                         ->where('serial', '=', $max_no )
                         ->groupby('indirect_location')
                         ->get();


      for($x = 0 ; $x < sizeof($load_temp_ladder) ; $x++){

        $indirect_location  = $load_temp_ladder[$x]['indirect_location'];

        $check_hold = Location::where('loc_id'  , '=', $indirect_location )->where('status', '<>', 0 )->count();
        if($check_hold == 0){
          $err = 'This Type of Order ('.$indirect_location.') is not in the System.';

          TempIndirectLadderUpload::where('user_loc_id' , '=', $user['user_loc_id'] )
                           ->where('created_by', '=', $user['user_id'] )
                           ->where('serial', '=', $max_no )
                           ->delete();

          return response([ 'data' => ['status' => '0','message' => $err]]);

        }


      }

      TempIndirectLadderUpload::where('user_loc_id' , '=', $user['user_loc_id'] )
                       ->where('created_by', '=', $user['user_id'] )
                       ->where('serial', '=', $max_no )
                       ->delete();

          $ladder_header = new IndirectLadderUploadHeader();
          $ladder_header->indirect_ladder_year = date("Y");
          $ladder_header->status = 1;
          $ladder_header->save();

      for($z = 0 ; $z < $request['length'] ; $z++){

          $EFF = explode("%",$request['data'][$z]['Efficiency']);
          $ladder = new IndirectLadderUpload();
          $ladder->indirect_location = $request['data'][$z]['Location'];
          $ladder->efficeincy_rate = $EFF[0];
          $ladder->incentive_payment = $request['data'][$z]['Incentive Amount'];
          $ladder->ladder_year = date("Y");
          $ladder->serial = $ladder_header->indirect_ladder_id;
          $ladder->status = 1;
          $ladder->save();

        }




      return response([ 'data' => [
        'message' => 'Indirect Ladder uploaded Successfully',
        'status'=>1
        ]
      ], Response::HTTP_CREATED );


      }



      }
      else{
        return response($this->authorize->error_response(), 401);
      }
    }

    //get shipment term
    public function show($id)
    {
      // if($this->authorize->hasPermission('PROD_SPEC_VIEW'))//check permission
      // {
      //   $bufferPolicy = BufferPolicy::find($id);
      //   if($bufferPolicy == null)
      //     throw new ModelNotFoundException("Requested Special Facto not found", 1);
      //   else
      //     return response([ 'data' => $bufferPolicy]);
      // }
      // else{
      //   return response($this->authorize->error_response(), 401);
      // }
    }


    //update a shipment term
    public function update(Request $request, $id)
    {
      if($this->authorize->hasPermission('PROD_SPEC_EDIT'))//check permission
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
            // $bufferPolicy =  BufferPolicy::find($id);
            // $bufferPolicy->fill($request->all());
            // $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($bufferPolicy);
            // $bufferPolicy->save();
            //
            // return response([ 'data' => [
            //   'message' => 'Buffer Policy updated successfully',
            //   'transaction' => $bufferPolicy,
            //   'status'=>'1'
            //]]);
      // }
      }
      else{
        return response($this->authorize->error_response(), 401);
      }
    }



    //deactivate a ship term
    public function destroy($id)
    {
      if($this->authorize->hasPermission('PROD_SPEC_DELETE'))//check permission
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
              // $bufferPolicy =BufferPolicy::where('inc_buffer_id', $id)->update(['status' => 0]);
              // return response([ 'data' => [
              //   'message' => 'Buffer Policy Deactived sucessfully',
              //   'status' => '1',
              //   'productSpesication'=>$bufferPolicy
              // ]]);
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
        //return response($this->validate_duplicate_name($request->inc_buffer_id , $request->hours));
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
      //  $bufferPolicy = BufferPolicy::where([['hours','=',$code]])->first();
      //
      // if( $bufferPolicy  == null){
      //    echo json_encode(array('status' => 'success'));
      // }
      // else if( $bufferPolicy ->inc_buffer_id == $id){
      //    echo json_encode(array('status' => 'success'));
      // }
      // else {
      //  echo json_encode(array('status' => 'error','message' => 'Hours Already Exists'));
      // }
    }

    //get filtered fields only
    private function list($active = 0 , $fields = null)
    {
      // $query = null;
      // if($fields == null || $fields == '') {
      //   $query = BufferPolicy::select('*');
      // }
      // else{
      //   $fields = explode(',', $fields);
      //   $query = BufferPolicy::select($fields);
      //   if($active != null && $active != ''){
      //     $query->where([['status', '=', $active]]);
      //   }
      // }
      // return $query->get();
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
      if($this->authorize->hasPermission('INDIRECT_LADDER_VIEW'))//check permission
      {
        $start = $data['start'];
        $length = $data['length'];
        $draw = $data['draw'];
        $search = $data['search']['value'];
        $order = $data['order'][0];
        $order_column = $data['columns'][$order['column']]['data'];
        $order_type = $order['dir'];

        $type_list = IndirectLadderUploadHeader::select('*')
        ->where('indirect_ladder_year'  , 'like', $search.'%' )
        ->orderBy($order_column, $order_type)
        ->offset($start)->limit($length)->get();

        $type_count = IndirectLadderUploadHeader::where('indirect_ladder_year'  , 'like', $search.'%' )->count();

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


    public function indirect_view_data(Request $request){
      if($this->authorize->hasPermission('INDIRECT_LADDER_VIEW'))//check permission
      {

      $ladder_id  = $request->ladder_id;

      $ladder= IndirectLadderUpload::select('inc_efficiency_indirect_ladder.*' )
                   ->where('inc_efficiency_indirect_ladder.serial', '=', $ladder_id)
                   ->where('inc_efficiency_indirect_ladder.status', '=',1)
                   ->orderBy('inc_efficiency_indirect_ladder.inc_efficiency_indirect_ladder_id', 'desc')
                   ->get();

      $arr['ladder_data'] = $ladder;
      $arr['ladder_count'] = sizeof($ladder);

      if($arr == null)
          throw new ModelNotFoundException("Requested section not found", 1);
      else
          return response([ 'data' => $arr ]);

        }
        else{
          return response($this->authorize->error_response(), 401);
        }

    }

}
