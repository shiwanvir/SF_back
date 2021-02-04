<?php

namespace App\Http\Controllers\IncentiveCalculationSystem;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Http\Controllers\Controller;
use App\Models\IncentiveCalculationSystem\LadderUploadHeader;
use App\Models\IncentiveCalculationSystem\Typeoforder;
use App\Models\IncentiveCalculationSystem\ProductionIncentiveHeader;
use App\Models\IncentiveCalculationSystem\ProductionIncentive;
use App\Models\IncentiveCalculationSystem\ProductionIncentiveLine;
use App\Models\IncentiveCalculationSystem\EmployeeHeader;
use App\Models\IncentiveCalculationSystem\EmployeeDetails;
use App\Models\IncentiveCalculationSystem\EfficiencyHeader;
use App\Models\IncentiveCalculationSystem\EfficiencyDetails;
use App\Models\IncentiveCalculationSystem\AqlIncentive;
use App\Models\IncentiveCalculationSystem\CniIncentive;
use App\Models\IncentiveCalculationSystem\CadreHeader;
use App\Models\IncentiveCalculationSystem\CadreDetail;
use App\Models\IncentiveCalculationSystem\SpecialFactor;
use App\Models\IncentiveCalculationSystem\IncentivePolicy;
use App\Models\IncentiveCalculationSystem\BufferPolicy;
use App\Models\IncentiveCalculationSystem\EmployeeAttendance;
use App\Models\IncentiveCalculationSystem\EmailStatus;
use App\Libraries\AppAuthorize;
use App\Libraries\CapitalizeAllFields;
use Illuminate\Support\Facades\DB;

class IncReportController extends Controller
{
    var $authorize = null;

    public function __construct()
    {
      //add functions names to 'except' paramert to skip authentication
      $this->middleware('jwt.verify', ['except' => ['index','IncentiveDataExport']]);
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
      }else if($type == 'inc-list') {
        //dd('d');
        $active = $request->active;
        $fields = $request->fields;
        return response([
          'data' => $this->load_inc_list($active , $fields)
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
      if($this->authorize->hasPermission('PROD_SPEC_CREATE'))//check permission
      {



      }
      else{
        return response($this->authorize->error_response(), 401);
      }
    }

    //get shipment term
    public function show($id)
    {
      if($this->authorize->hasPermission('PROD_SPEC_VIEW'))//check permission
      {
        // $equation = Equation::find($id);
        // if($equation == null)
        //   throw new ModelNotFoundException("Requested Equation not found", 1);
        // else
        //   return response([ 'data' => $equation]);
      }
      else{
        return response($this->authorize->error_response(), 401);
      }
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
        // $equation =  Equation::find($id);
        // $equation->fill($request->all());
        // $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($equation);
        // $equation->save();
        //
        // return response([ 'data' => [
        //   'message' => 'Equation updated successfully',
        //   'transaction' => $equation,
        //   'status'=>'1'
        // ]]);
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
        // $equation =Equation::where('inc_equation_id', $id)->update(['status' => 0]);
        // return response([ 'data' => [
        //   'message' => 'Equation Deactived sucessfully',
        //   'status' => '1',
        //   'productSpesication'=>$equation
        // ]]);
      // }
    }
      else{
        return response($this->authorize->error_response(), 401);
      }
    }

    //validate anything based on requirements
    public function inc_report_data_load(Request $request){
      //dd($request['formData']['month_of_year']['month_of_year']);

      $user = auth()->payload();
      $user_loc = $user['loc_id'];
      $current_month = $request['formData']['month_of_year']['month_of_year'];

      $dates = ProductionIncentiveHeader::where(DB::raw("DATE_FORMAT(inc_production_incentive_header.incentive_date,'%Y-%m')")  , '=', $current_month )
      ->where('user_loc_id'  , '=', $user_loc )
      ->where('incentive_status'  , '<>', 'HOLIDAY' )
      ->select(DB::raw("DATE_FORMAT(inc_production_incentive_header.incentive_date,'%d') as dates"))
      ->get();

    //  echo $dates;die();

      $Month = ProductionIncentiveHeader::where(DB::raw("DATE_FORMAT(inc_production_incentive_header.incentive_date,'%Y-%m')")  , '=', $current_month )
      ->where('user_loc_id'  , '=', $user_loc )
      ->where('incentive_status'  , '<>', 'HOLIDAY' )
      ->select(DB::raw("DATE_FORMAT(inc_production_incentive_header.incentive_date,'%Y-%M') as month"))
      ->groupBy(DB::raw("DATE_FORMAT(inc_production_incentive_header.incentive_date,'%Y-%m')") )
      ->get();

      $user_list = ProductionIncentiveLine::join('inc_employee','inc_production_incentive_line.emp_detail_id','=','inc_employee.emp_detail_id')
      ->where(DB::raw("DATE_FORMAT(inc_production_incentive_line.incentive_date,'%Y-%m')")  , '=', $current_month )
      ->where('inc_production_incentive_line.user_loc_id'  , '=', $user_loc )
      ->whereNotNull('inc_production_incentive_line.final_incentive_payment')
      ->select('inc_production_incentive_line.emp_no','inc_employee.emp_name','inc_employee.line_no','inc_employee.emp_designation','inc_employee.department',
        DB::raw("round(sum(inc_production_incentive_line.final_incentive_payment),2)AS total"))
      ->groupBy('inc_production_incentive_line.emp_no')
      ->get();



      $user_list_date_wise = ProductionIncentiveLine::join('inc_employee','inc_production_incentive_line.emp_detail_id','=','inc_employee.emp_detail_id')
      ->where(DB::raw("DATE_FORMAT(inc_production_incentive_line.incentive_date,'%Y-%m')")  , '=', $current_month )
      ->where('inc_production_incentive_line.user_loc_id'  , '=', $user_loc )
      ->whereNotNull('inc_production_incentive_line.final_incentive_payment')
      ->select('inc_production_incentive_line.emp_no',DB::raw("DATE_FORMAT(inc_production_incentive_line.incentive_date,'%d') as date"),
        DB::raw("round(Sum(inc_production_incentive_line.final_incentive_payment),2) as total"))
      ->groupBy('inc_production_incentive_line.emp_no', 'inc_production_incentive_line.incentive_date')
      ->get();

      $arr['dates'] = $dates;
      $arr['count_dates'] = sizeof($dates);
      $arr['month'] = $Month[0];
      $arr['user_list'] = $user_list;
      $arr['date_wise_user_list'] = $user_list_date_wise;

      //dd($arr);
      //$arr['ladder_count'] = sizeof($ladder);

      if($arr == null)
          throw new ModelNotFoundException("Requested section not found", 1);
      else
          return response([ 'data' => $arr ]);

    }


    public function IncentiveDataExport(Request $request){

       $user_loc = $request->loc;
       $current_month = $request->date;

      //dd($current_month);
      $dates = ProductionIncentiveHeader::where(DB::raw("DATE_FORMAT(inc_production_incentive_header.incentive_date,'%Y-%m')")  , '=', $current_month )
      ->where('user_loc_id'  , '=', $user_loc )
      ->where('incentive_status'  , '<>', 'HOLIDAY' )
      ->select(DB::raw("DATE_FORMAT(inc_production_incentive_header.incentive_date,'%d') as dates"))
      ->get();

    //  echo $dates;die();

      $Month = ProductionIncentiveHeader::where(DB::raw("DATE_FORMAT(inc_production_incentive_header.incentive_date,'%Y-%m')")  , '=', $current_month )
      ->where('user_loc_id'  , '=', $user_loc )
      ->where('incentive_status'  , '<>', 'HOLIDAY' )
      ->select(DB::raw("DATE_FORMAT(inc_production_incentive_header.incentive_date,'%Y-%M') as month"))
      ->groupBy(DB::raw("DATE_FORMAT(inc_production_incentive_header.incentive_date,'%Y-%m')") )
      ->get();

      $user_list = ProductionIncentiveLine::join('inc_employee','inc_production_incentive_line.emp_detail_id','=','inc_employee.emp_detail_id')
      ->where(DB::raw("DATE_FORMAT(inc_production_incentive_line.incentive_date,'%Y-%m')")  , '=', $current_month )
      ->where('inc_production_incentive_line.user_loc_id'  , '=', $user_loc )
      ->whereNotNull('inc_production_incentive_line.final_incentive_payment')
      ->select('inc_production_incentive_line.emp_no','inc_employee.emp_name','inc_employee.line_no','inc_employee.emp_designation','inc_employee.department',
        DB::raw("round(sum(inc_production_incentive_line.final_incentive_payment),2)AS total"))
      ->groupBy('inc_production_incentive_line.emp_no')
      ->get();



      $user_list_date_wise = ProductionIncentiveLine::join('inc_employee','inc_production_incentive_line.emp_detail_id','=','inc_employee.emp_detail_id')
      ->where(DB::raw("DATE_FORMAT(inc_production_incentive_line.incentive_date,'%Y-%m')")  , '=', $current_month )
      ->where('inc_production_incentive_line.user_loc_id'  , '=', $user_loc )
      ->whereNotNull('inc_production_incentive_line.final_incentive_payment')
      ->select('inc_production_incentive_line.emp_no',DB::raw("DATE_FORMAT(inc_production_incentive_line.incentive_date,'%d') as date"),
        DB::raw("round(Sum(inc_production_incentive_line.final_incentive_payment),2) as total"))
      ->groupBy('inc_production_incentive_line.emp_no', 'inc_production_incentive_line.incentive_date')
      ->get();





      //echo $user_list_date_wise;die();

      // $arr['dates'] = $dates;
      // $arr['count_dates'] = sizeof($dates);
      // $arr['month'] = $Month[0];
      // $arr['user_list'] = $user_list;
      // $arr['date_wise_user_list'] = $user_list_date_wise;

      return view('incentive/export_incentive_data', array(
        'dates'=>$dates,
        'count_dates'=>sizeof($dates),
        'month'=>$Month[0],
        'user_list'=>$user_list,
        'date_wise_user_list'=>$user_list_date_wise,
      ));
    }

    //check shipment cterm code code already exists
    private function validate_duplicate_code($id , $code)
    {
      //  $equation = Equation::where([['present_factor','=',$code]])->first();
      //
      // if( $equation  == null){
      //    echo json_encode(array('status' => 'success'));
      // }
      // else if( $equation ->inc_equation_id == $id){
      //    echo json_encode(array('status' => 'success'));
      // }
      // else {
      //  echo json_encode(array('status' => 'error','message' => 'Present Factor Already Exists'));
      // }
    }


    //check shipment cterm code code already exists
    private function validate_duplicate_name($id , $code)
    {
      //  $equation = Equation::where([['equation','=',$code]])->first();
      //
      // if( $equation  == null){
      //    echo json_encode(array('status' => 'success'));
      // }
      // else if( $equation ->inc_equation_id == $id){
      //    echo json_encode(array('status' => 'success'));
      // }
      // else {
      //  echo json_encode(array('status' => 'error','message' => 'Equation Already Exists'));
      // }
    }

    //get filtered fields only
    private function list($active = 0 , $fields = null)
    {
      // $query = null;
      // if($fields == null || $fields == '') {
      //   $query = Equation::select('*');
      // }
      // else{
      //   $fields = explode(',', $fields);
      //   $query = Equation::select($fields);
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

    private function load_inc_list($active = 0 , $fields = null)
    {
      $user = auth()->payload();
      $user_loc = $user['loc_id'];
      $fields = explode(',', $fields);
      //dd($fields);
      $query = ProductionIncentiveHeader::select(DB::raw("DATE_FORMAT(inc_production_incentive_header.incentive_date,'%Y-%m') as month_of_year")
      ,DB::raw("DATE_FORMAT(inc_production_incentive_header.incentive_date,'%Y-%b') as month_of_year_M"),"inc_production_incentive_header.user_loc_id")
      ->where('user_loc_id'  , '=', $user_loc )
      ->where('incentive_status'  , '=', 'SENT FOR APPROVAL' )
      ->where('incentive_status'  , '<>', 'HOLIDAY' )
      ->groupBy(DB::raw("DATE_FORMAT(inc_production_incentive_header.incentive_date,'%Y-%m')") )
      ->get();

      //dd($query);
      return $query;



    }


    //get searched ship terms for datatable plugin format
    private function datatable_search($data)
    {
      // if($this->authorize->hasPermission('PROD_SPEC_VIEW'))//check permission
      // {
      //   $start = $data['start'];
      //   $length = $data['length'];
      //   $draw = $data['draw'];
      //   $search = $data['search']['value'];
      //   $order = $data['order'][0];
      //   $order_column = $data['columns'][$order['column']]['data'];
      //   $order_type = $order['dir'];
      //
      //   $transaction_list = Equation::select('*')
      //   ->Where(function ($query) use ($search) {
    	// 		$query->orWhere('equation', 'like', $search.'%')
    	// 			    ->orWhere('present_factor', 'like', $search.'%');
    	// 	        })
      //   ->orderBy($order_column, $order_type)
      //   ->offset($start)->limit($length)->get();
      //
      //   $transaction_count = Equation::where('equation'  , 'like', $search.'%' )
      //   ->where('present_factor'  , 'like', $search.'%' )
      //   ->count();
      //
      //   return [
      //       "draw" => $draw,
      //       "recordsTotal" => $transaction_count,
      //       "recordsFiltered" => $transaction_count,
      //       "data" => $transaction_list
      //   ];
      // }
      // else{
      //   return response($this->authorize->error_response(), 401);
      // }
    }

}
