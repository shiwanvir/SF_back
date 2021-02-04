<?php

namespace App\Http\Controllers\IncentiveCalculationSystem;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Http\Controllers\Controller;
use App\Models\IncentiveCalculationSystem\LadderUpload;
use App\Models\IncentiveCalculationSystem\Section;
use App\Models\Org\Location\Location;
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
use App\Models\IncentiveCalculationSystem\Designation;
use App\Libraries\Approval;
use App\Libraries\AppAuthorize;
use App\Libraries\CapitalizeAllFields;
use Illuminate\Support\Facades\DB;

class  ProductionIncentiveController extends Controller
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

      if($type == 'calender')   {
        $data = $request->all();
        return response($this->calender($data));
      }
      else if($type == 'auto')    {
        $search = $request->search;
        return response($this->autocomplete_search($search));
      }else if($type == 'to-list') {
        $active = $request->active;
        $fields = $request->fields;
        return response([
          'data' => $this->load_to_list($active , $fields)
        ]);
      }else if($type == 'section-list') {
        $active = $request->active;
        $fields = $request->fields;
        return response([
          'data' => $this->load_section_list($active , $fields)
        ]);
      }else if($type == 'emp-list') {
        $inc_date = $request->inc_date;
        $desig= $request->desig;
        $active = $request->active;
        $fields = $request->fields;
        return response([
          'data' => $this->load_all_emp_list($inc_date,$desig ,$active , $fields)
        ]);
      }else if($type == 'desig-list') {
        $inc_date = $request->inc_date;
        $caderdata = $request->caderdata;
        $active = $request->active;
        $fields = $request->fields;
        return response([
          'data' => $this->load_all_desig_list($inc_date ,$active , $fields ,$caderdata)
        ]);
      }else if($type == 'section-daylist') {
        $typeof_order = $request->typeof_order;
        return response([
          'data' => $this->load_section_daylist($typeof_order)
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



    private function load_to_list($active = 0 , $fields = null)
    {
      $fields = explode(',', $fields);
      $query = Typeoforder::select('inc_order_id','order_type')->where('status','<>', 0);
      return $query->get();
    }

    private function load_section_list($active = 0 , $fields = null)
    {
      $user = auth()->payload();
      //dd($user['loc_id']);
      $fields = explode(',', $fields);
      $query = Section::select('inc_section_id','line_no','loc_id','raise_intCompanyID')->where('status','<>', 0)->where('loc_id','=', $user['loc_id']);
      return $query->get();
    }

    private function load_all_emp_list($inc_date,$desig ,$active = 0 , $fields = null)
    {
      $user = auth()->payload();
      //dd($user);
      $fields = explode(',', $fields);
      $query = EmployeeHeader::join('inc_employee','inc_employee_header.emp_header_id','=','inc_employee.emp_header_id')
                              ->select('emp_detail_id','inc_employee.emp_no','inc_employee.emp_name','inc_employee.shift_duration')
                              ->where('inc_employee_header.incentive_date','=', $inc_date)
                              ->where('inc_employee.emp_designation','=', $desig)
                              ->where('inc_employee_header.user_loc_id','=', $user['loc_id']);
      return $query->get();
    }


    private function load_all_desig_list($inc_date ,$active = 0 , $fields = null,$caderdata)
    {
      $user = auth()->payload();
      //dd($caderdata);
      $fields = explode(',', $fields);
      if($caderdata == 'DIRECT'){
        $query = Designation::select('inc_designation_equation_id','emp_designation')
                ->where('inc_equation_id','<>', 'EQUATION 01')
                ->where('inc_equation_id','<>', 'EQUATION 02')
                ->where('inc_equation_id','<>', 'EQUATION 05')
                ->get();
      }else if($caderdata == 'INDIRECT'){
        $query = Designation::select('inc_designation_equation_id','emp_designation')
                ->where('inc_equation_id','=', 'EQUATION 05')
                ->get();
      }

      return $query;
    }


    private function load_section_daylist($typeof_order)
    {

      $user = auth()->payload();
      $query = LadderUploadHeader::join('inc_efficiency_ladder','inc_efficiency_ladder_header.ladder_id','=','inc_efficiency_ladder.serial')
                  ->where('inc_efficiency_ladder.order_type' , '=', $typeof_order )
                  ->groupBy('inc_efficiency_ladder.qco_date');
                  //dd($query);
      return $query->get();
    }

    //create a shipment term
    public function store(Request $request)
    {
      //$user = auth()->user();

        $user = auth()->payload();
        $user_loc = $user['loc_id'];
        $current_month = $request['current_month'];

        $check_date = ProductionIncentiveHeader::where(DB::raw("DATE_FORMAT(inc_production_incentive_header.incentive_date,'%Y-%m')")  , '=', $current_month )
        ->where('user_loc_id'  , '=', $user_loc )
        ->where('incentive_status'  , '<>', 'HOLIDAY' )
        ->where('incentive_status'  , '<>', 'NEW' )
        ->where('incentive_status'  , '<>', 'PENDING' )
        ->where('incentive_status'  , '<>', 'READY TO CALCULATE' )
        ->where('incentive_status'  , '<>', 'CALCULATED' )
        ->count();

        //echo $check_date;die()

        if($check_date > 0){
          $err = 'This Month Already Calculated.';
          return response([ 'data' => ['status' => '0','message' => $err]]);

        }

        if($request['status']== 'NEW'){

          if($this->authorize->hasPermission('CALENDER_CREATE_NEW'))//check permission
          {

            $list = array();
            $d = date('d', strtotime('last day of this month', strtotime($current_month))); // get max date of current month: 28, 29, 30 or 31.

            for ($i = 1; $i <= $d; $i++) {
                $list[] = $current_month . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
            }

            for($x = 0 ; $x < sizeof($list) ; $x++){

              $incentive_date  = $list[$x];
              $incentive_date_format_change  =date('d-M-Y', strtotime($incentive_date));
              $check_hold = ProductionIncentiveHeader::where('user_loc_id'  , '=', $user_loc )->where('incentive_date'  , '=', $incentive_date )->count();
              if($check_hold > 0){

                //$check_status = ProductionIncentiveHeader::where('user_loc_id'  , '=', $user_loc )->where([['incentive_date','=',$incentive_date]])->first();
                // if($check_status['incentive_status'] == 'NEW' || $check_status['incentive_status'] == 'HOLIDAY'){
                //
                //   ProductionIncentiveHeader::where('user_loc_id'  , '=', $user_loc )->where('incentive_date' , '=', $incentive_date )->delete();
                // }else{

                     $err = 'Already added data for ('.$incentive_date_format_change.') ';
                     return response([ 'data' => ['status' => '0','message' => $err]]);
              //  }
              }
            }

            if($request['length'] != null && $request['length'] >= 1){

            for($y = 0 ; $y < sizeof($list) ; $y++){
                $ladder_header = new ProductionIncentiveHeader();
                $ladder_header->incentive_date = $list[$y];
                $ladder_header->incentive_status = $request['status'];
                $ladder_header->calender_colour = $request['colour'];
                $ladder_header->save();
            }

          return response([ 'data' => [
            'message' => 'Calendar Updated Successfully',
            'status'=>1
            ]
          ], Response::HTTP_CREATED );


          }


          }
          else{
            return response($this->authorize->error_response(), 401);
          }





        }

        if($request['status']== 'HOLIDAY'){

          if($this->authorize->hasPermission('CALENDER_CREATE_HOLIDAY'))//check permission
          {

            for($x = 0 ; $x < $request['length'] ; $x++){

                $incentive_date  = $request['dates'][$x];
                $incentive_date_format_change  =date('d-M-Y', strtotime($incentive_date));
                $check_hold = ProductionIncentiveHeader::where('user_loc_id'  , '=', $user_loc )->where('incentive_date'  , '=', $incentive_date )->count();
                if($check_hold > 0){

                  $check_status = ProductionIncentiveHeader::where('user_loc_id'  , '=', $user_loc )->where([['incentive_date','=',$incentive_date]])->first();
                   if($check_status['incentive_status'] == 'NEW'){
                     DB::table('inc_production_incentive_header')
                         ->where('incentive_date'  , '=', $incentive_date )
                         ->where('user_loc_id'  , '=', $user_loc )
                         ->update(['incentive_status' => 'HOLIDAY','calender_colour' => '#ed4135']);
                   }else if($check_status['incentive_status'] == 'HOLIDAY'){
                     DB::table('inc_production_incentive_header')
                         ->where('incentive_date'  , '=', $incentive_date )
                         ->where('user_loc_id'  , '=', $user_loc )
                         ->update(['incentive_status' => 'NEW','calender_colour' => '#00bcd4']);


                  }else{

                    $err = 'Already added data for ('.$incentive_date_format_change.') ';
                    return response([ 'data' => ['status' => '0','message' => $err]]);
                  }
                }else{

                  return response([ 'data' => [ 'message' => 'Please Update calendar as NEW ','status'=>0]], Response::HTTP_CREATED );
                }

            }

            return response([ 'data' => [ 'message' => 'Calendar Updated Successfully','status'=>1]], Response::HTTP_CREATED );



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
      if($this->authorize->hasPermission('PROD_SPEC_VIEW'))//check permission
      {
        // $start = $data['start'];
        // $length = $data['length'];
        // $draw = $data['draw'];
        // $search = $data['search']['value'];
        // $order = $data['order'][0];
        // $order_column = $data['columns'][$order['column']]['data'];
        // $order_type = $order['dir'];
        //
        // $type_list = LadderUploadHeader::select('*')
        // ->where('ladder_year'  , 'like', $search.'%' )
        // ->orderBy($order_column, $order_type)
        // ->offset($start)->limit($length)->get();
        //
        // $type_count = LadderUploadHeader::where('ladder_year'  , 'like', $search.'%' )->count();
        //
        // return [
        //     "draw" => $draw,
        //     "recordsTotal" => $type_count,
        //     "recordsFiltered" => $type_count,
        //     "data" => $type_list
        // ];
      }
      else{
        return response($this->authorize->error_response(), 401);
      }
    }


    private function calender($data)
      {
        if($this->authorize->hasPermission('CALENDER_VIEW'))//check permission
        {
          $start_date = $data['start'];
          $end_date = $data['end'];
          $data = array();

          $user = auth()->payload();
          $user_loc = $user['loc_id'];

          $query =ProductionIncentiveHeader::where('user_loc_id'  , '=', $user_loc )->whereBetween('incentive_date', [$start_date, $end_date])->get();

          foreach($query as $row)
          {
           $data[] = array(
            'id'   => $row["production_incentive_header_id"],
            'title'   => $row["incentive_status"],
            'start'   => $row["incentive_date"],
            'end'   => $row["incentive_date"],
            'color' => $row["calender_colour"]
           );
          }

          echo json_encode($data);
        }
        else{
          return response($this->authorize->error_response(), 401);
        }



      }


    public function load_calender(Request $request){

      if($this->authorize->hasPermission('CALENDER_EVENT_OPEN'))//check permission
            {
              $id  = $request->id;
              $user = auth()->payload();
              $user_loc = $user['loc_id'];


              $ladder= ProductionIncentiveHeader::select('inc_production_incentive_header.*','org_location.*',
                            DB::raw("DATE_FORMAT(inc_production_incentive_header.incentive_date, '%d-%b-%Y') AS incentive_date_2"))
                           ->join('org_location','inc_production_incentive_header.user_loc_id','=','org_location.loc_id')
                           ->where('production_incentive_header_id', '=', $id)
                           ->where('inc_production_incentive_header.user_loc_id'  , '=', $user_loc )
                           ->get();
                           //dd($ladder);

              $lines = ProductionIncentive::where('production_incentive_header_id'  , '=',  $id )
                           ->where('incentive_date'  , '=', $ladder[0]['incentive_date'] )
                           ->where('user_loc_id'  , '=', $user_loc )
                           // ->where('status'  , '=', 'PLANNED' )
                           ->select('line_no')
                           ->get();

                           //dd($lines);

              $arr['ladder_data'] = $ladder;
              $arr['saved_lines'] = $lines;
              $arr['saved_lines_count'] = sizeof($lines);

              if($arr == null)
                  throw new ModelNotFoundException("Requested section not found", 1);
              else
                  return response([ 'data' => $arr ]);
            }
            else{
              return response($this->authorize->error_response(), 401);
            }



    }

    public function upload_employee(Request $request){

      if($this->authorize->hasPermission('CALENDER_IMPORT_EMPLOYEE'))//check permission
            {

              $id  = $request->id;
              $incentive_date = $request->incentive_date;
              $user = auth()->payload();
              $user_loc = $user['loc_id'];
              $loc_details = Location::find($user_loc);
              $raise_loc = $loc_details['raise_intCompanyID'];
              $d2d_loc = $loc_details['d2d_loc_id'];

              $check_emp = EmployeeHeader::where('production_incentive_header_id'  , '=',  $id )->count();
              if($check_emp > 0){
                $err = 'Employees Already Imported from Raise.';
                return response([ 'data' => ['status' => '0','message' => $err]]);

              }
              //dd($raise_loc);
              $load_data = DB::connection('raise')->select("SELECT employee.strEmpNo, employee.strEPFNo, employee.bytCompany, employee.strNameWithInitial,
                         designation.strDesigantion, teams.strName AS line_no, department.strName AS department, shift.dtmTimeIn, shift.dtmTimeOut
                         FROM employee
                         INNER JOIN designation ON employee.bytDesignation = designation.bytDesigCode AND employee.bytCompany = designation.intCompanyID
                         INNER JOIN teams ON employee.bytTeam = teams.bytTeamCode AND employee.bytCompany = teams.intCompanyID
                         INNER JOIN department ON employee.bytDeptCode = department.bytDeptCode AND employee.bytCompany = department.intCompanyID
                         INNER JOIN shift ON employee.strShift = shift.intShiftID AND employee.bytCompany = shift.intCompanyID
                         WHERE employee.bytCompany = '$raise_loc' AND employee.booActive = 1 AND shift.dtmTimeIn != '' AND shift.dtmTimeOut != '' ");

              if(sizeof($load_data) == 0){
                $err = 'No Employee data Available.';
                return response([ 'data' => ['status' => '0','message' => $err]]);

              }
              $production_incentive_header=ProductionIncentiveHeader::find($id);
              $production_incentive_header->incentive_status='PENDING';
              $production_incentive_header->calender_colour='#f89f12';
              $production_incentive_header->save();

              $emp_header = new EmployeeHeader();
              $emp_header->production_incentive_header_id = $id;
              $emp_header->incentive_date = $incentive_date;
              $emp_header->incentive_status = 'PENDING';
              $emp_header->save();

              for($r = 0 ; $r < sizeof($load_data) ; $r++)
               {
                 $time1 = $load_data[$r]->dtmTimeIn;
                 $time2 = $load_data[$r]->dtmTimeOut;
                 $array1 = explode('.', $time1);
                 $array2 = explode('.', $time2);

                 $minutes1 = (float)$array1[0] * 60.0 + (float)$array1[1];
                 $minutes2 = (float)$array2[0] * 60.0 + (float)$array2[1];

                 $diff = $minutes2 - $minutes1;

                 $emp_detail = new EmployeeDetails();
                 $emp_detail->emp_header_id = $emp_header->emp_header_id;
                 $emp_detail->emp_no = $load_data[$r]->strEmpNo;
                 $emp_detail->epf_no = $load_data[$r]->strEPFNo;
                 $emp_detail->raise_location = $load_data[$r]->bytCompany;
                 $emp_detail->emp_name = $load_data[$r]->strNameWithInitial;
                 $emp_detail->emp_designation = $load_data[$r]->strDesigantion;
                 $emp_detail->line_no = ltrim($load_data[$r]->line_no);
                 $emp_detail->shift_start_time = $load_data[$r]->dtmTimeIn;
                 $emp_detail->shift_end_time = $load_data[$r]->dtmTimeOut;
                 $emp_detail->department = $load_data[$r]->department;
                 $emp_detail->shift_duration = (float)$diff/60;
                 $emp_detail->button_colour = 'btn-success';
                 $emp_detail->save();

               }

               $emp_header_update=EmployeeHeader::find($emp_header->emp_header_id);
               $emp_header_update->incentive_status = 'UPLOADED';
               $emp_header_update->save();

               $production_incentive_header_update=ProductionIncentiveHeader::find($id);
               $production_incentive_header_update->import_employee='UPLOADED';
               $production_incentive_header_update->save();

               return response([ 'data' => [
                 'message' => 'Employees Successfully Imported from Raise',
                 'status'=>1
                 ]
               ], Response::HTTP_CREATED );



            }
            else{
              return response($this->authorize->error_response(), 401);
            }


    }



    public function upload_efficiency(Request $request){

      if($this->authorize->hasPermission('CALENDER_IMPORT_EFFICIENCY'))//check permission
            {
              $id  = $request->id;
              $incentive_date = $request->incentive_date;
              $user = auth()->payload();
              $user_loc = $user['loc_id'];
              $loc_details = Location::find($user_loc);
              $raise_loc = $loc_details['raise_intCompanyID'];
              $d2d_loc = $loc_details['d2d_loc_id'];
              //dd($d2d_loc);

              $check_emp = EfficiencyHeader::where('production_incentive_header_id'  , '=',  $id )->count();
              if($check_emp > 0){
                $err = 'Efficiency Already Imported from D2D.';
                return response([ 'data' => ['status' => '0','message' => $err]]);

              }

              $load_data = DB::connection('d2d')->select("SELECT d2d_efficincy.location, d2d_efficincy.createdate, d2d_master_section.raise_line,
                           IFNULL(ROUND(((Sum(d2d_efficincy.produceminits)/Sum(d2d_efficincy.useminits))*100),2),0) AS line_eff
                           FROM (((d2d_efficincy)))
                           INNER JOIN view_sumeff ON d2d_efficincy.sc = view_sumeff.sc AND d2d_efficincy.lineno = view_sumeff.lineno AND d2d_efficincy.location = view_sumeff.location
                           INNER JOIN d2d_master_section ON d2d_efficincy.sec_id = d2d_master_section.sectionId
                           WHERE d2d_efficincy.createdate = '$incentive_date' AND d2d_efficincy.location = '$d2d_loc'
                           GROUP BY d2d_efficincy.location, d2d_efficincy.lineno
                           HAVING d2d_master_section.raise_line IS NOT NULL ");

              if(sizeof($load_data) == 0){
              $err = 'No Efficiency data Available.';
              return response([ 'data' => ['status' => '0','message' => $err]]);

              }

              $production_incentive_header=ProductionIncentiveHeader::find($id);
              $production_incentive_header->incentive_status='PENDING';
              $production_incentive_header->calender_colour='#f89f12';
              $production_incentive_header->save();

              $eff_header = new EfficiencyHeader();
              $eff_header->production_incentive_header_id = $id;
              $eff_header->incentive_date = $incentive_date;
              $eff_header->incentive_status = 'PENDING';
              $eff_header->save();

              for($r = 0 ; $r < sizeof($load_data) ; $r++)
               {

                 $eff_detail = new EfficiencyDetails();
                 $eff_detail->eff_header_id = $eff_header->eff_header_id;
                 $eff_detail->d2d_location = $load_data[$r]->location;
                 $eff_detail->line_no = $load_data[$r]->raise_line;
                 $eff_detail->efficiency_date = $incentive_date;
                 $eff_detail->efficiency_rate = $load_data[$r]->line_eff;
                 $eff_detail->save();

               }

               $eff_header_update=EfficiencyHeader::find($eff_header->eff_header_id);
               $eff_header_update->incentive_status = 'UPLOADED';
               $eff_header_update->save();

               $production_incentive_header_update=ProductionIncentiveHeader::find($id);
               $production_incentive_header_update->import_efficiency='UPLOADED';
               $production_incentive_header_update->save();

               return response([ 'data' => [
                 'message' => 'Efficiency Successfully Imported from D2D',
                 'status'=>1
                 ]
               ], Response::HTTP_CREATED );


            }
            else{
              return response($this->authorize->error_response(), 401);
            }



    }

      public function load_emp_list(Request $request){
        $user = auth()->payload();
        $user_loc = $user['loc_id'];
        $loc_details = Location::find($user_loc);
        $raise_loc = $loc_details['raise_intCompanyID'];
        $d2d_loc = $loc_details['d2d_loc_id'];

        //dd($request);

        $line_no  = $request['formData']['inc_section_id']['line_no'];
        $raise_intCompanyID  = $request['formData']['inc_section_id']['raise_intCompanyID'];


        $check = ProductionIncentive::where('incentive_date' , '=', $request['incentive_date'] )
                    ->where('line_no' , '=', $line_no )
                    ->where('user_loc_id' , '=', $user_loc )
                    ->count();

        if($check > 0)
        {

          $line_emp    = ProductionIncentive::join('inc_production_incentive_line','inc_production_incentive.inc_production_incentive_id','=','inc_production_incentive_line.inc_production_incentive_id')
                      ->where('inc_production_incentive.incentive_date' , '=', $request['incentive_date'] )
                      ->where('inc_production_incentive_line.to_line_no' , '=', $line_no )
                      ->where('inc_production_incentive.user_loc_id' , '=', $user_loc )
                      ->where('inc_production_incentive_line.emp_status' , '=', 'TEAM MEMBER' )
                      ->get();

          $load_header = ProductionIncentive::where('inc_production_incentive.incentive_date' , '=', $request['incentive_date'] )
                      ->where('inc_production_incentive.line_no' , '=', $line_no )
                      ->where('inc_production_incentive.user_loc_id' , '=', $user_loc )
                      ->get();

          $arr['STATUS']=$load_header[0]['status'];
          $arr['AQL']=$load_header[0]['aql'];
          $arr['CNI']=$load_header[0]['cni'];
          $arr['qco_date']=$load_header[0]['qco_date'];
          $arr['order_type']=$load_header[0]['order_type'];

          //dd($load_header[0]);

        }else{

          $line_emp = EmployeeHeader::join('inc_employee','inc_employee_header.emp_header_id','=','inc_employee.emp_header_id')
                      ->where('inc_employee_header.production_incentive_header_id' , '=', $request['id'] )
                      ->where('inc_employee.raise_location' , '=', $raise_intCompanyID )
                      ->where('inc_employee.line_no' , '=', $line_no )
                      ->orderBy('inc_employee.emp_no', 'ASC')
                      ->get();

          $arr['STATUS'] = 'PENDING';
          $arr['AQL'] = '';
          $arr['CNI'] = '';
        }



        $load_eff = EfficiencyHeader::join('inc_efficiency','inc_efficiency_header.eff_header_id','=','inc_efficiency.eff_header_id')
                    ->where('inc_efficiency_header.incentive_date' , '=', $request['incentive_date'] )
                    ->where('inc_efficiency.line_no' , '=', $line_no )
                    ->where('inc_efficiency.d2d_location' , '=', $d2d_loc )
                    ->get();

        if(sizeof($load_eff) == 0){
                $load_eff =[];
                $ladder_details =[];

                $load_eff[0]['efficiency_rate'] = 0;
                $ladder_details[0]['incentive_payment'] = 0;
                //dd(sizeof($load_eff));
        }else{

          $line_eff = $load_eff[0]['efficiency_rate'];
          $order_type = $request['formData']['inc_order_id']['inc_order_id'];
          $qco_date = $request['formData']['qco_date_id']['qco_date'];

          $ladder_details = DB::select("SELECT IFNULL(e.incentive_payment,0) as incentive_payment FROM inc_efficiency_ladder_header AS eh
                            INNER JOIN inc_efficiency_ladder AS e ON eh.ladder_id = e.serial
                            WHERE e.efficeincy_rate = '$line_eff' AND e.order_type = '$order_type' AND e.qco_date = '$qco_date'
                            AND e.serial = (SELECT Max(ted.serial) FROM inc_efficiency_ladder AS ted)");

          if(sizeof($ladder_details) == 0){
            $ladder_details[0]['incentive_payment'] = 0;
          }
        }

        return response([ 'count' => sizeof($line_emp), 'line_emp'=> $line_emp, 'load_eff'=> $load_eff[0], 'order_type'=> $ladder_details[0], 'load_header'=> $arr ]);


      }




      public function update_production_inc(Request $request){


        if($this->authorize->hasPermission('CALENDER_SAVE_LINE_DETAILS'))//check permission
            {
              //dd($request);
              $id  = $request->id;
              $incentive_date = $request->incentive_date;
              $user = auth()->payload();
              $user_loc = $user['loc_id'];
              $loc_details = Location::find($user_loc);
              $raise_loc = $loc_details['raise_intCompanyID'];
              $d2d_loc = $loc_details['d2d_loc_id'];

              $formData = $request->formData;
              $emp_list = $request->emp_list;

              $AQL = AqlIncentive::where('aql'  ,$formData['aql'] )->get();
              if(sizeof($AQL)!=0){
                $AQL_CONVERT = $AQL[0]['paid_rate'];
              }else{
                $AQL_CONVERT = 0;
              }
              $CNI = CniIncentive::where('cni'  ,$formData['cni'] )->get();
              if(sizeof($CNI)!=0){
                $CNI_CONVERT = $CNI[0]['paid_rate'];
              }else{
                $CNI_CONVERT = 0;
              }

              $inc_production_id = ProductionIncentive::where('production_incentive_header_id', $id)
              ->where('line_no', $formData['inc_section_id']['line_no'])
              ->where('incentive_date', $incentive_date)->get();
              //dd($inc_production_id[0]['inc_production_incentive_id']);

              ProductionIncentive::where('inc_production_incentive_id', $inc_production_id[0]['inc_production_incentive_id'])
                  ->where('production_incentive_header_id', $id)
                  ->where('line_no', $formData['inc_section_id']['line_no'])
                  ->where('incentive_date', $incentive_date)
                  ->update([
                    'aql' => $AQL_CONVERT,
                    'cni' => $CNI_CONVERT,
                    'order_type' => $formData['inc_order_id']['order_type'],
                    'qco_date' => $formData['qco_date_id']['qco_date'],
                    'efficiency_rate' => $formData['efficiency_rate'],
                    'incentive_payment' => $formData['incentive']
                  ]);

               ProductionIncentiveLine::where('inc_production_incentive_id',  $inc_production_id[0]['inc_production_incentive_id'])
                      ->where('incentive_date', $incentive_date)
                      ->where('emp_status' , '=', 'TEAM MEMBER' )
                      ->update([
                        'incentive_payment' => $formData['incentive']
                      ]);


            $load_lines= ProductionIncentiveLine::where('incentive_date' , '=', $incentive_date )
                                ->where('inc_production_incentive_id', $inc_production_id[0]['inc_production_incentive_id'])
                                ->where('to_line_no' , '=', $formData['inc_section_id']['line_no'] )
                                ->where('user_loc_id' , '=', $user_loc )
                                ->get();
            $load_header= ProductionIncentive::where('production_incentive_header_id', $id)
            ->where('inc_production_incentive_id', $inc_production_id[0]['inc_production_incentive_id'])
            ->where('line_no', $formData['inc_section_id']['line_no'])
            ->where('incentive_date', $incentive_date)->get();


            return response([ 'data' => [
                      'message' => 'Incentive Line Details Updated successfully',
                      'status'=>1,
                      'header'=>$load_header[0],
                      'details'=>$load_lines
                      ]
                    ], Response::HTTP_CREATED );
            }
            else{
              return response($this->authorize->error_response(), 401);
            }


      }

      public function save_production_inc(Request $request){

        if($this->authorize->hasPermission('CALENDER_SAVE_LINE_DETAILS'))//check permission
            {

        $id  = $request->id;
        $incentive_date = $request->incentive_date;
        $user = auth()->payload();
        $user_loc = $user['loc_id'];
        $loc_details = Location::find($user_loc);
        $raise_loc = $loc_details['raise_intCompanyID'];
        $d2d_loc = $loc_details['d2d_loc_id'];

        $formData = $request->formData;
        $emp_list = $request->emp_list;
        //dd($formData);

        $check_emp = ProductionIncentive::where('production_incentive_header_id'  , '=',  $id )
        ->where('line_no'  , '=',  $formData['inc_section_id']['line_no'] )->count();
        if($check_emp > 0){
          $err = 'Incentive already Saved.';
          return response([ 'data' => ['status' => '0','message' => $err]]);

        }

        $AQL = AqlIncentive::where('aql'  ,$formData['aql'] )->get();
        if(sizeof($AQL)!=0){
          $AQL_CONVERT = $AQL[0]['paid_rate'];
        }else{
          $AQL_CONVERT = 0;
        }
        $CNI = CniIncentive::where('cni'  ,$formData['cni'] )->get();
        if(sizeof($CNI)!=0){
          $CNI_CONVERT = $CNI[0]['paid_rate'];
        }else{
          $CNI_CONVERT = 0;
        }


        //dd($emp_list);
        // $production_incentive_header=ProductionIncentiveHeader::find($id);
        // $production_incentive_header->incentive_status='PENDING';
        // $production_incentive_header->calender_colour='#f89f12';
        // $production_incentive_header->save();

        $pro_header = new ProductionIncentive();
        $pro_header->production_incentive_header_id = $id;
        $pro_header->order_type = $formData['inc_order_id']['order_type'];
        $pro_header->qco_date = $formData['qco_date_id']['qco_date'];
        $pro_header->line_no = $formData['inc_section_id']['line_no'];
        $pro_header->incentive_date = $incentive_date;
        $pro_header->efficiency_rate = $formData['efficiency_rate'];
        $pro_header->aql = $AQL_CONVERT;
        $pro_header->cni = $CNI_CONVERT;
        $pro_header->incentive_payment = $formData['incentive'];
        $pro_header->status = 'PENDING';
        $pro_header->save();

        for($r = 0 ; $r < sizeof($emp_list) ; $r++)
         {

           $pro_detail = new ProductionIncentiveLine();
           $pro_detail->inc_production_incentive_id = $pro_header->inc_production_incentive_id;
           $pro_detail->emp_no = $emp_list[$r]['emp_no'];
           $pro_detail->emp_detail_id = $emp_list[$r]['emp_detail_id'];
           $pro_detail->line = 1;
           $pro_detail->incentive_date = $incentive_date;
           $pro_detail->from_line_no = $emp_list[$r]['line_no'];
           $pro_detail->to_line_no = $emp_list[$r]['line_no'];
           $pro_detail->work_duration = $emp_list[$r]['shift_duration'];
           $pro_detail->shift_duration = $emp_list[$r]['shift_duration'];
           $pro_detail->incentive_payment =  $formData['incentive'];
           $pro_detail->status = 'PLANNED';
           $pro_detail->emp_status = 'TEAM MEMBER';
           $pro_detail->button_colour= 'btn-success';
           $pro_detail->incentive_attendance= 1;
           $pro_detail->save();

         }

         $pro_header_update=ProductionIncentive::find($pro_header->inc_production_incentive_id);
         $pro_header_update->status = 'PLANNED';
         $pro_header_update->save();

         // $production_incentive_header=ProductionIncentiveHeader::find($id);
         // $production_incentive_header->incentive_status='PLANNED';
         // $production_incentive_header->calender_colour='#4CAF50';
         // $production_incentive_header->save();

         $load_lines= ProductionIncentiveLine::where('incentive_date' , '=', $incentive_date )
                     ->where('to_line_no' , '=', $formData['inc_section_id']['line_no'] )
                     ->where('user_loc_id' , '=', $user_loc )
                     ->get();
         $load_header= ProductionIncentive::find($pro_header->inc_production_incentive_id);

         return response([ 'data' => [
           'message' => 'Incentive Line Details Saved successfully',
           'status'=>1,
           'header'=>$load_header,
           'details'=>$load_lines
           ]
         ], Response::HTTP_CREATED );

       }
       else{
         return response($this->authorize->error_response(), 401);
       }

      }





      public function load_transfer_list(Request $request){



        if($this->authorize->hasPermission('CALENDER_LINE_TRANSFER'))//check permission
            {
              //dd($request);
              $id  = $request->id;
              $incentive_date = $request->incentive_date;
              $user = auth()->payload();
              $user_loc = $user['loc_id'];
              $loc_details = Location::find($user_loc);
              $raise_loc = $loc_details['raise_intCompanyID'];
              $d2d_loc = $loc_details['d2d_loc_id'];
              $emp_no  = $request->emp_no;
              $emp_detail_id  = $request->emp_detail_id;
              $inc_production_incentive_line_id = $request->inc_production_incentive_line_id;
              //dd($inc_production_incentive_line_id);
              $line_no  = $request['formData']['inc_section_id']['line_no'];

              $line_emp    = ProductionIncentive::join('inc_production_incentive_line','inc_production_incentive.inc_production_incentive_id','=','inc_production_incentive_line.inc_production_incentive_id')
                          ->where('inc_production_incentive.incentive_date' , '=', $request['incentive_date'] )
                          //->where('inc_production_incentive.line_no' , '=', $line_no )
                          ->where('inc_production_incentive.user_loc_id' , '=', $user_loc )
                          ->where('inc_production_incentive_line.emp_no' , '=', $emp_no )
                          ->where('inc_production_incentive_line.emp_status' , '=', 'TEAM MEMBER' )
                          ->get();

              $header_emp = EmployeeHeader::join('inc_employee','inc_employee.emp_header_id','=','inc_employee_header.emp_header_id')
                          ->where('inc_employee_header.production_incentive_header_id'  , '=',  $id )
                          ->where('inc_employee.emp_no'  , '=',  $emp_no )
                          ->where('inc_employee_header.incentive_date'  , '=',  $incentive_date )
                          ->get();
              //dd($request['formData']['inc_section_id']);
              $arr['line_emp']=$line_emp;
              $arr['header_emp']=$header_emp[0];
              $arr['selected_line']=$request['formData']['inc_section_id'];
              $arr['inc_production_incentive_line_id']=$inc_production_incentive_line_id;



              if($arr == null)
               throw new ModelNotFoundException("Requested section not found", 1);
               else
               return response([ 'data' => $arr ]);
            }
            else{
              return response($this->authorize->error_response(), 401);
            }


      }


      public function save_transfer(Request $request){

        if($this->authorize->hasPermission('CALENDER_LINE_TRANSFER'))//check permission
            {
              $id  = $request->id;
              $incentive_date = $request->incentive_date;
              $user = auth()->payload();
              $user_loc = $user['loc_id'];
              $loc_details = Location::find($user_loc);
              $raise_loc = $loc_details['raise_intCompanyID'];
              $d2d_loc = $loc_details['d2d_loc_id'];

              $from_line = $request['formTranferData']['from_line']['from_line'];
              $to_line = $request['formTranferData']['to_line']['inc_section_id'];
              $emp_no  = $request->emp;
              $inc_production_incentive_line_id = $request['formTranferData']['inc_production_incentive_line_id'];

              $load_line = ProductionIncentiveLine::find($inc_production_incentive_line_id);

              $check_cc = ProductionIncentiveLine::where('incentive_date'  , '=', $incentive_date )
                          ->where('emp_no'  , '=', $emp_no )
                          ->where('work_duration'  , '<>', 0 )
                          ->count();

              if($check_cc+1 > 2){
                          $err = 'maximum line transfer exceeded.';
                          return response([ 'data' => ['status' => '0','message' => $err]]);
                  }
              //dd($check_cc);

              if($from_line == $to_line){

                $check_hold = ProductionIncentiveLine::where('incentive_date'  , '=', $incentive_date )
                            ->where('emp_no'  , '=', $emp_no )->count();
                          //  dd($check_hold);
                if($check_hold > 1){
                  $err = 'Employee already Transferred.';
                  return response([ 'data' => ['status' => '0','message' => $err]]);
                }

                $pro_line=ProductionIncentiveLine::find($inc_production_incentive_line_id);
                $pro_line->work_duration = $request['formTranferData']['work_duration'];
                $pro_line->button_colour = 'btn-warning';
                $pro_line->save();

                return response([ 'data' => [
                  'message' => 'Work Hours Updated successfully',
                  'status'=>1,
                  'emp_detail_id' => $load_line['emp_detail_id'],
                  'inc_production_incentive_line_id' => $inc_production_incentive_line_id
                  ]
                ], Response::HTTP_CREATED );


              }else{
                $check_hold2 = ProductionIncentiveLine::where('incentive_date'  , '=', $incentive_date )
                            ->where('emp_no'  , '=', $emp_no )
                            ->where('to_line_no'  , '=', $request['formTranferData']['to_line']['line_no'] )
                            ->count();
                          //  dd($check_hold);
                if($check_hold2 > 0){
                  $err = 'Employee already Transferred.';
                  return response([ 'data' => ['status' => '0','message' => $err]]);
                }

                $Update_Incentive = null;
                $pro_line_list=ProductionIncentiveLine::find($inc_production_incentive_line_id);

                $pro_detail = new ProductionIncentiveLine();
                $pro_detail->inc_production_incentive_id = $pro_line_list['inc_production_incentive_id'];
                $pro_detail->emp_no = $pro_line_list['emp_no'];
                $pro_detail->emp_detail_id = $pro_line_list['emp_detail_id'];
                $pro_detail->line = $pro_line_list['line']+1;
                $pro_detail->incentive_date = $pro_line_list['incentive_date'];
                $pro_detail->from_line_no = $pro_line_list['from_line_no'];
                $pro_detail->to_line_no = $request['formTranferData']['to_line']['line_no'];
                $pro_detail->work_duration = $request['formTranferData']['work_duration'];
                $pro_detail->shift_duration = $pro_line_list['shift_duration'];
                $pro_detail->incentive_payment =  $pro_line_list['incentive_payment'];
                $pro_detail->status = 'PLANNED';
                $pro_detail->emp_status = 'TEAM MEMBER';
                $pro_detail->button_colour= 'btn-warning';
                $pro_detail->incentive_attendance= 1;
                $pro_detail->save();

                $pro_line_list2=ProductionIncentiveLine::find($pro_detail['inc_production_incentive_line_id']);
                $find_incentive_date = $pro_line_list2['incentive_date'];
                $find_to_line_no = $pro_line_list2['to_line_no'];

                $get_incentive_count = ProductionIncentive::where('incentive_date'  , '=', $find_incentive_date )
                            ->where('line_no'  , '=', $find_to_line_no )
                            ->where('user_loc_id'  , '=', $user_loc )
                            ->count();
                if($get_incentive_count == 0){
                   $Update_Incentive = 0;
                }else{
                    $get_incentive = ProductionIncentive::where('incentive_date'  , '=', $find_incentive_date )
                              ->where('line_no'  , '=', $find_to_line_no )
                              ->where('user_loc_id'  , '=', $user_loc )
                              ->get();

                    //dd($get_incentive[0]['incentive_payment']);

                    $update_new_line=ProductionIncentiveLine::find($pro_detail['inc_production_incentive_line_id']);
                    $update_new_line->incentive_payment = $get_incentive[0]['incentive_payment'];
                    $update_new_line->save();
                }


                // $pro_line=ProductionIncentiveLine::find($inc_production_incentive_line_id);
                // $pro_line->work_duration = $pro_line_list['work_duration'] - $request['formTranferData']['work_duration'];
                // $pro_line->save();

                return response([ 'data' => [
                  'message' => 'Work Hours Updated successfully',
                  'status'=>1,
                  'emp_detail_id' => $load_line['emp_detail_id'],
                  'inc_production_incentive_line_id' => $inc_production_incentive_line_id
                  ]
                ], Response::HTTP_CREATED );


              }
            }
            else{
              return response($this->authorize->error_response(), 401);
            }


        // click karanakota emp id ekai emp num ekai load karaganna...ekata anuwa update eka hadanna.....
      }

      public function save_cadre_header(Request $request){


        if($this->authorize->hasPermission('CALENDER_CADRE_CREATE'))//check permission
            {
              $cadre_header = new CadreHeader();
              $cadre_header->cadre_name = strtoupper($request['cadreData']['cadre_name']);
              $cadre_header->cadre_type = strtoupper($request['cadreData']['cadre_type']);
              $cadre_header->status = 1;
              $cadre_header->save();

              return response([ 'data' => [
                'message' => 'Cadre Header Saved successfully',
                'status'=>1
                ]
              ], Response::HTTP_CREATED );
            }
            else{
              return response($this->authorize->error_response(), 401);
            }



      }

      public function remove_cadre_details(Request $request){
        if($this->authorize->hasPermission('CALENDER_CADRE_DELETE'))//check permission
            {
              //dd($request);
              for($r = 0 ; $r < sizeof($request['lines']) ; $r++)
               {
                 $cadre_Detail=CadreDetail::find($request['lines'][$r]['cadre_detail_id']);
                 $cadre_Detail->status = 0;
                 $cadre_Detail->save();
            }

              return response([ 'data' => [
                'message' => 'Cadre line(s) removed successfully',
                'status'=>1
                ]
              ], Response::HTTP_CREATED );
            }
            else{
              return response($this->authorize->error_response(), 401);
            }




      }

      public function remove_cadre_saved_lines(Request $request){

      if($this->authorize->hasPermission('CALENDER_CADRE_SECTION_DELETE'))//check permission
          {
            $user = auth()->payload();
            $user_loc = $user['loc_id'];
            $cadre_name  = $request['line']['emp_status'];
            $cadre_type = $request['line']['cadre_type'];
            $incentive_date = $request['line']['incentive_date'];
            //$inc_production_incentive_line_id = $request['line']['inc_production_incentive_line_id'];
            //dd($incentive_date);
            ProductionIncentiveLine::where('user_loc_id' , '=', $user_loc )
                             ->where('created_by', '=', $user['user_id'] )
                             ->where('emp_status', '=', $cadre_name  )
                             ->where('incentive_date', '=', $incentive_date  )
                             ->delete();

            $succ = 'Cadre ('.$cadre_name.') removed successfully';
            return response([ 'data' => ['status' => '1','message' => $succ]]);

          }
          else{
            return response($this->authorize->error_response(), 401);
          }

      }

      public function remove_cadre_header(Request $request){

        if($this->authorize->hasPermission('CALENDER_INCENTIVE_CADRE_REMOVE'))//check permission
            {
              //dd($request);
              $check_emp = CadreDetail::where('inc_cadre_detail.cadre_id'  , '=',  $request['lines']['cadre_id'] )
              ->where('inc_cadre_detail.status'  , '<>', 0 )
              ->count();

              //dd($request);
              if($check_emp > 0){
                $err = 'Employee already Exists.';
                return response([ 'data' => ['status' => '0','message' => $err]]);

              }

                $cadre_Detail=CadreHeader::find($request['lines']['cadre_id']);
                $cadre_Detail->status = 0;
                $cadre_Detail->save();

              return response([ 'data' => [
                'message' => 'Cadre header removed successfully',
                'status'=>1
                ]
              ], Response::HTTP_CREATED );

            }
            else{
              return response($this->authorize->error_response(), 401);
            }


      }



      public function load_to_incentive(Request $request){

        if($this->authorize->hasPermission('CALENDER_CADRE_TRANSFER'))//check permission
            {
              $id  = $request->id;
              $incentive_date = $request->incentive_date;
              $user = auth()->payload();
              $user_loc = $user['loc_id'];

              for($s = 0 ; $s < sizeof($request['lines']) ; $s++)
               {
                  $check_ = CadreHeader::join('inc_cadre_detail','inc_cadre_detail.cadre_id','=','inc_cadre_header.cadre_id')
                  ->where('inc_cadre_header.status' , '<>', 0 )
                  ->where('inc_cadre_header.cadre_name' , '=', $request['lines'][$s]['cadre_name'] )
                  ->count();

                  if($check_ == 0)
                  {
                    $err = 'Cadre ('.$request['lines'][$s]['cadre_name'].') is Empty.';
                    return response([ 'data' => ['status' => '0','message' => $err]]);

                  }

                }


              for($s = 0 ; $s < sizeof($request['lines']) ; $s++)
               {
                  $check_ = ProductionIncentiveLine::where('incentive_date'  , '=',  $incentive_date )
                  ->where('inc_production_incentive_id'  , '=', 0 )
                  ->where('emp_status'  , '=',  $request['lines'][$s]['cadre_name'] )
                  ->count();

                  if($check_ > 0)
                  {
                    $err = 'Cadre ('.$request['lines'][$s]['cadre_name'].') already inserted to the current incentive.';
                    return response([ 'data' => ['status' => '0','message' => $err]]);

                  }

                }

              for($q = 0 ; $q < sizeof($request['lines']) ; $q++)
               {

                 $load_emp_list = CadreDetail::join('inc_employee','inc_employee.emp_no','=','inc_cadre_detail.emp_no')
                 ->join('inc_employee_header','inc_employee_header.emp_header_id','=','inc_employee.emp_header_id')
                 ->where('inc_employee_header.incentive_date'  , '=',  $incentive_date )
                 ->where('inc_cadre_detail.cadre_id'  , '=',  $request['lines'][$q]['cadre_id'] )
                 ->where('inc_cadre_detail.user_loc_id'  , '=', $user_loc )
                 ->where('inc_cadre_detail.status'  , '<>', 0 )
                 ->select('inc_cadre_detail.emp_no','inc_cadre_detail.line_no','inc_cadre_detail.shift_duration','inc_employee.emp_detail_id')
                 ->get();

                 //echo $load_emp_list; die();

                 for($r = 0 ; $r < sizeof($load_emp_list) ; $r++)
                  {

                    $pro_detail = new ProductionIncentiveLine();
                    $pro_detail->inc_production_incentive_id = 0;
                    $pro_detail->emp_no = $load_emp_list[$r]['emp_no'];
                    $pro_detail->emp_detail_id = $load_emp_list[$r]['emp_detail_id'];
                    $pro_detail->line = $r+1;
                    $pro_detail->incentive_date = $incentive_date;
                    $pro_detail->from_line_no = $load_emp_list[$r]['line_no'];
                    $pro_detail->to_line_no = $load_emp_list[$r]['line_no'];
                    $pro_detail->work_duration = $load_emp_list[$r]['shift_duration'];
                    $pro_detail->shift_duration = $load_emp_list[$r]['shift_duration'];
                    $pro_detail->incentive_payment =  0;
                    $pro_detail->status = 'PLANNED';
                    $pro_detail->emp_status = $request['lines'][$q]['cadre_name'];
                    $pro_detail->button_colour= 'btn-success';
                    $pro_detail->incentive_attendance= 1;
                    $pro_detail->save();

                  }


               }



               return response([ 'data' => [
                 'message' => 'Cadre Added successfully',
                 'status'=>1
                 ]
               ], Response::HTTP_CREATED );




            }
            else{
              return response($this->authorize->error_response(), 401);
            }

      }


      public function confirm_line_details(Request $request){

      if($this->authorize->hasPermission('CALENDER_CONFIRM'))//check permission
          {
            $id  = $request->id;
            $incentive_date = $request->incentive_date;
            $user = auth()->payload();
            $user_loc = $user['loc_id'];
            $loc_details = Location::find($user_loc);
            $raise_loc = $loc_details['raise_intCompanyID'];
            $d2d_loc = $loc_details['d2d_loc_id'];

            // $line_alert = ProductionIncentive::where('production_incentive_header_id'  , '=',  $id )
            // ->where('incentive_date'  , '=', $incentive_date )
            // ->where('user_loc_id'  , '=', $user_loc )
            // ->where('status'  , '=', 'PLANNED' )
            // ->select(DB::raw("GROUP_CONCAT(line_no) AS count"))
            // ->get();
            //
            // dd($line_alert);

            $check_line_eff_update = ProductionIncentive::where('production_incentive_header_id'  , '=',  $id )
            ->where('incentive_date'  , '=', $incentive_date )
            ->where('user_loc_id'  , '=', $user_loc )
            ->where('efficiency_rate'  , '=', 0 )
            ->where('status'  , '=', 'PLANNED' )
            ->count();

            if($check_line_eff_update > 0){
              $err = 'Please Update line Efficiency, AQL and CNI.';
              return response([ 'data' => ['status' => '0','message' => $err]]);

            }

            $check_emp = ProductionIncentive::where('production_incentive_header_id'  , '=',  $id )
            ->where('incentive_date'  , '=', $incentive_date )
            ->where('user_loc_id'  , '=', $user_loc )
            ->where('status'  , '=', 'PLANNED' )
            ->count();

            //dd($request);
            if($check_emp == 0){
              $err = 'Incentive Line(s) not saved yet.';
              return response([ 'data' => ['status' => '0','message' => $err]]);

            }

            if($request['Dataset'] == []){
              $err = 'Please enter CADRE DETAILS for Confirmation.';
              return response([ 'data' => ['status' => '0','message' => $err]]);
            }

            $load_list_Direct_section = ProductionIncentiveLine::where('inc_production_incentive_line.incentive_date'  , '=', $incentive_date )
            ->where('user_loc_id'  , '=', $user_loc )
            ->where('status'  , '=', 'PLANNED' )
            ->where('inc_production_incentive_id','=',0)
            ->where('to_line_no','<>',null)
            ->select('to_line_no')
            ->groupBy('to_line_no')
            ->get();

            $load_list_Team_section = ProductionIncentiveLine::where('inc_production_incentive_line.incentive_date'  , '=', $incentive_date )
            ->where('user_loc_id'  , '=', $user_loc )
            ->where('status'  , '=', 'PLANNED' )
            ->where('inc_production_incentive_id','<>',0)
            ->select('to_line_no')
            ->groupBy('to_line_no')
            ->get();

            //echo $load_list_Direct_section;die();
            for($q = 0 ; $q < sizeof($load_list_Direct_section) ; $q++)
             {
                $lines = $load_list_Direct_section[$q]['to_line_no'];

                $check_line_count = ProductionIncentive::where('production_incentive_header_id'  , '=',  $id )
                ->where('incentive_date'  , '=', $incentive_date )
                ->where('user_loc_id'  , '=', $user_loc )
                ->where('line_no'  , '=', $lines )
                ->where('status'  , '=', 'PLANNED' )
                ->count();

                if($check_line_count == 0){
                  $err = 'Cadre Line(s) are not saved in the system.';
                  return response([ 'data' => ['status' => '0','message' => $err]]);

                }
             }


             for($y = 0 ; $y < sizeof($load_list_Team_section) ; $y++)
              {
                 $lines2 = $load_list_Team_section[$y]['to_line_no'];

                 $check_line_count2 = ProductionIncentive::where('production_incentive_header_id'  , '=',  $id )
                 ->where('incentive_date'  , '=', $incentive_date )
                 ->where('user_loc_id'  , '=', $user_loc )
                 ->where('line_no'  , '=', $lines2 )
                 ->where('status'  , '=', 'PLANNED' )
                 ->count();

                 if($check_line_count2 == 0){
                   $err2 = 'Team Member Line(s) are not saved in the system.';
                   return response([ 'data' => ['status' => '0','message' => $err2]]);

                 }
              }

            // dd();

            //  NEED UPDATE EQUATION  HERE ---------------------------------------------------------------------------------------

            $get_saved_list = ProductionIncentiveLine::select('emp_detail_id','emp_no','inc_production_incentive_line_id')
            ->where('incentive_date'  , '=', $incentive_date )
            ->where('user_loc_id'  , '=', $user_loc )
            ->get();



            for($g = 0 ; $g < sizeof($get_saved_list) ; $g++)
             {
               $incentive_line_id = $get_saved_list[$g]['inc_production_incentive_line_id'];
               $emp_detail_id = $get_saved_list[$g]['emp_detail_id'];
               $emp_no = $get_saved_list[$g]['emp_no'];

               $get_eq = EmployeeDetails::join('inc_designation_equation','inc_employee.emp_designation','=','inc_designation_equation.inc_designation_equation_id')
               ->where('inc_employee.emp_detail_id'  , '=', $emp_detail_id )
               ->where('inc_employee.emp_no'  , '=', $emp_no )
               ->where('inc_employee.user_loc_id'  , '=', $user_loc )
               ->get();



               $pro=ProductionIncentiveLine::find($incentive_line_id);
               $pro->inc_equation_id = $get_eq[0]['inc_equation_id'];
               $pro->save();

             }




            for($r = 0 ; $r < sizeof($request['Dataset']) ; $r++)
             {

               $inc_production_incentive_line_id = $request['Dataset'][$r]['inc_production_incentive_line_id'];
               $cadre_type = $request['Dataset'][$r]['cadre_type'];
               $to_line_no = $request['Dataset'][$r]['to_line_no'];

               if($cadre_type == "DIRECT"){
                 //dd($request['Dataset'][$r]);

                 $find_incentive = ProductionIncentive::select('incentive_payment','aql','cni')
                 ->where('production_incentive_header_id'  , '=',  $id )
                 ->where('incentive_date'  , '=', $incentive_date )
                 ->where('user_loc_id'  , '=', $user_loc )
                 ->where('line_no'  , '=', $to_line_no )
                 ->where('status'  , '=', 'PLANNED' )
                 ->get();

                 //check Equation and update INCENTIVE PAYMENT  -  EQ 03 - (INC *AQL)  || EQ 04 -  (INC *AQL * CNI)    ----------------
                 $check_EQ = ProductionIncentiveLine::select('inc_equation_id')
                 ->where('inc_production_incentive_line_id'  , '=',  $inc_production_incentive_line_id )
                 ->get();


                 if($check_EQ[0]['inc_equation_id'] == "EQUATION 03"){
                   $pro=ProductionIncentiveLine::find($inc_production_incentive_line_id);
                   $pro->incentive_payment = floatval($find_incentive[0]['incentive_payment'])*floatval($find_incentive[0]['aql']);
                   $pro->save();
                 }

                 if($check_EQ[0]['inc_equation_id'] == "EQUATION 04"){
                   $pro=ProductionIncentiveLine::find($inc_production_incentive_line_id);
                   $pro->incentive_payment = floatval($find_incentive[0]['incentive_payment'])*floatval($find_incentive[0]['aql'])*floatval($find_incentive[0]['cni']);
                   $pro->save();
                 }


               }else if($cadre_type == "INDIRECT"){

                 $check_EQ_I = ProductionIncentiveLine::select('inc_equation_id')
                 ->where('inc_production_incentive_line_id'  , '=',  $inc_production_incentive_line_id )
                 ->get();

                 if($check_EQ_I[0]['inc_equation_id'] == "EQUATION 05"){

                   $load_data = DB::connection('d2d')->select("SELECT d2d_efficincy.location, d2d_efficincy.createdate,
                                IFNULL(ROUND(((Sum(d2d_efficincy.produceminits)/Sum(d2d_efficincy.useminits))*100),0),0) AS line_eff
                                FROM (((d2d_efficincy)))
                                INNER JOIN view_sumeff ON d2d_efficincy.sc = view_sumeff.sc AND d2d_efficincy.lineno = view_sumeff.lineno AND d2d_efficincy.location = view_sumeff.location
                                WHERE d2d_efficincy.createdate = '$incentive_date' AND d2d_efficincy.location = '$d2d_loc'
                                GROUP BY d2d_efficincy.location ");

                   $line_eff = $load_data[0]->line_eff;

                   $ladder_details = DB::select("SELECT IFNULL( e.incentive_payment, 0 ) AS incentive_payment
                                FROM
                                inc_efficiency_indirect_ladder_header AS eh
                                INNER JOIN inc_efficiency_indirect_ladder AS e ON eh.indirect_ladder_id = e.serial
                                WHERE e.indirect_location = '$user_loc' AND e.efficeincy_rate = '$line_eff' AND
                                e.serial = ( SELECT Max( ted.serial ) FROM inc_efficiency_indirect_ladder AS ted)");

                   //dd($ladder_details[0]->incentive_payment);
                   $IN_Incentive_pay = $ladder_details[0]->incentive_payment;

                   $pro=ProductionIncentiveLine::find($inc_production_incentive_line_id);
                   $pro->incentive_payment = $IN_Incentive_pay;
                   $pro->save();
                 }


               }




             }

             DB::table('inc_production_incentive')
                 ->where('production_incentive_header_id', $id)
                 ->where('incentive_date'  , '=', $incentive_date )
                 ->where('user_loc_id'  , '=', $user_loc )
                 ->update(['status' => 'CONFIRMED']);

             $pro_h=ProductionIncentiveHeader::find($id);
             $pro_h->incentive_status = 'READY TO CALCULATE';
             $pro_h->calender_colour = '#d0dc72';
             $pro_h->save();


             return response([ 'data' => [
               'message' => 'Production Incentive Successfully Confirmed ',
               'status'=>1
               ]
             ], Response::HTTP_CREATED );




          }
          else{
            return response($this->authorize->error_response(), 401);
          }

      }




      public function calculate(Request $request){
        if($this->authorize->hasPermission('CALENDER_CALCULATE'))//check permission
            {
              $id  = $request->id;
              $incentive_date = $request->incentive_date;
              $user = auth()->payload();
              $user_loc = $user['loc_id'];

              // LOAD TEAM MEMBER -->  SAVE  ====================================================================================================

              $load_list_Team_Member = ProductionIncentiveLine::where('incentive_date'  , '=', $incentive_date )
              ->where('user_loc_id'  , '=', $user_loc )
              ->where('status'  , '=', 'PLANNED' )
              ->where('emp_status','=','TEAM MEMBER')
              ->get();


              for($r = 0 ; $r < sizeof($load_list_Team_Member) ; $r++)
               {
                 $X = 0; // Direct Incentive Ladder
                 $AQL = 0; // incentive_aql
                 $SPECIAL = 0; // special_variable
                 $ATTENDANCE = 0; // incentive_attendance
                 $CNI = 0; // incentive_cni


                 $inc_production_incentive_line_id = $load_list_Team_Member[$r]['inc_production_incentive_line_id'];
                 $emp_no = $load_list_Team_Member[$r]['emp_no'];
                 $work_duration = $load_list_Team_Member[$r]['work_duration'];
                 $incentive_payment = $load_list_Team_Member[$r]['incentive_payment'];
                 $inc_equation_id = $load_list_Team_Member[$r]['inc_equation_id'];
                 $emp_status = $load_list_Team_Member[$r]['emp_status'];
                 $incentive_attendance = $load_list_Team_Member[$r]['incentive_attendance'];
                 $line_no = $load_list_Team_Member[$r]['to_line_no'];

                 $SPECIAL_DATA = SpecialFactor::select('paid_rate')->get();
                 $AQL_CNI_DATA = ProductionIncentive::select('efficiency_rate','aql','cni','order_type')
                 ->where('incentive_date'  , '=', $incentive_date )
                 ->where('user_loc_id'  , '=', $user_loc )
                 ->where('line_no'  , '=', $line_no )
                 ->where('status'  , '=', 'CONFIRMED' )
                 ->get();



                 if($inc_equation_id == "EQUATION 01"){

                   $X = $incentive_payment;
                   $AQL = $AQL_CNI_DATA[0]['aql'];
                   $SPECIAL = $SPECIAL_DATA[0]['paid_rate'];
                   $ATTENDANCE = $incentive_attendance;

                   $INCENTIVE = floatval($X)*floatval($AQL)*floatval($SPECIAL)*floatval($ATTENDANCE);

                   $pro=ProductionIncentiveLine::find($inc_production_incentive_line_id);
                   $pro->emp_incentive_payment = $INCENTIVE;
                   $pro->save();

                 }else if($inc_equation_id == "EQUATION 02"){

                   $X = $incentive_payment;
                   $AQL = $AQL_CNI_DATA[0]['aql'];
                   $CNI = $AQL_CNI_DATA[0]['cni'];
                   $SPECIAL = $SPECIAL_DATA[0]['paid_rate'];
                   $ATTENDANCE = $incentive_attendance;

                   $INCENTIVE = floatval($X)*floatval($AQL)*floatval($CNI)*floatval($SPECIAL)*floatval($ATTENDANCE);

                   $pro=ProductionIncentiveLine::find($inc_production_incentive_line_id);
                   $pro->emp_incentive_payment = $INCENTIVE;
                   $pro->save();

                 }

               }

               // LOAD DIRECT -->  SAVE  ====================================================================================================


               $load_list_Direct = ProductionIncentiveLine::where('inc_production_incentive_line.incentive_date'  , '=', $incentive_date )
               ->where('user_loc_id'  , '=', $user_loc )
               ->where('status'  , '=', 'PLANNED' )
               ->where('inc_production_incentive_id','=',0)
               ->where('to_line_no','<>',null)
               ->select('inc_production_incentive_line.*' ,DB::raw("count(to_line_no) AS count"),
                DB::raw("sum(incentive_payment) AS total_incentive"))
               ->groupBy('emp_no')
               ->get();

               //dd($load_list_Direct);

               for($j = 0 ; $j < sizeof($load_list_Direct) ; $j++)
                {

                  $AVG_AQL = 0; // avg_incentive_aql
                  $POLICY = 0; // incentivepolicy_multiply
                  $SPECIAL = 0; // special_variable
                  $ATTENDANCE = 0; // incentive_attendance
                  $AVG_CNI = 0; // avg_incentive_cni

                  $inc_production_incentive_line_id = $load_list_Direct[$j]['inc_production_incentive_line_id'];
                  $incentive_attendance = $load_list_Direct[$j]['incentive_attendance'];
                  $inc_equation_id = $load_list_Direct[$j]['inc_equation_id'];
                  $total_incentive = $load_list_Direct[$j]['total_incentive'];
                  $count = $load_list_Direct[$j]['count'];
                  $SPECIAL_DATA = SpecialFactor::select('paid_rate')->get();
                  $POLICY_DATA = IncentivePolicy::select('inc_policy_paid_rate')->get();

                  if($inc_equation_id == "EQUATION 03"){

                    $AVG_AQL = floatval($total_incentive)/floatval($count);
                    $POLICY = $POLICY_DATA[0]['inc_policy_paid_rate'];
                    $SPECIAL = $SPECIAL_DATA[0]['paid_rate'];
                    $ATTENDANCE = $incentive_attendance;

                    $INCENTIVE = floatval($AVG_AQL)*floatval($POLICY)*floatval($SPECIAL)*floatval($ATTENDANCE);

                    $pro=ProductionIncentiveLine::find($inc_production_incentive_line_id);
                    $pro->emp_incentive_payment = $INCENTIVE;
                    $pro->save();

                  }else if($inc_equation_id == "EQUATION 04"){

                    $AVG_CNI = floatval($total_incentive)/floatval($count);
                    $POLICY = $POLICY_DATA[0]['inc_policy_paid_rate'];
                    $SPECIAL = $SPECIAL_DATA[0]['paid_rate'];
                    $ATTENDANCE = $incentive_attendance;
                    $INCENTIVE = floatval($AVG_CNI)*floatval($POLICY)*floatval($SPECIAL)*floatval($ATTENDANCE);

                    $pro=ProductionIncentiveLine::find($inc_production_incentive_line_id);
                    $pro->emp_incentive_payment = $INCENTIVE;
                    $pro->save();

                  }



                }


                // LOAD INDIRECT -->  SAVE  ====================================================================================================

                $load_list_InDirect = ProductionIncentiveLine::where('inc_production_incentive_line.incentive_date'  , '=', $incentive_date )
                ->where('user_loc_id'  , '=', $user_loc )
                ->where('status'  , '=', 'PLANNED' )
                ->where('inc_production_incentive_id','=',0)
                ->where('to_line_no','=',null)
                ->select('inc_production_incentive_line.*')
                ->get();

                //dd(sizeof($load_list_InDirect));

                for($h = 0 ; $h < sizeof($load_list_InDirect) ; $h++)
                 {
                   $Y = 0; // Indirect Incentive Ladder
                   $ATTENDANCE = 0; // incentive_attendance

                   $inc_production_incentive_line_id = $load_list_InDirect[$h]['inc_production_incentive_line_id'];
                   $incentive_attendance = $load_list_InDirect[$h]['incentive_attendance'];
                   $inc_equation_id = $load_list_InDirect[$h]['inc_equation_id'];
                   $incentive_payment = $load_list_InDirect[$h]['incentive_payment'];

                   if($inc_equation_id == "EQUATION 05"){

                     $Y = $incentive_payment;
                     $ATTENDANCE = $incentive_attendance;

                     $INCENTIVE = floatval($Y)*floatval($ATTENDANCE);

                     $pro=ProductionIncentiveLine::find($inc_production_incentive_line_id);
                     $pro->emp_incentive_payment = $INCENTIVE;
                     $pro->save();


                   }



                 }

                 $pro_h=ProductionIncentiveHeader::find($id);
                 $pro_h->incentive_status = 'CALCULATED';
                 $pro_h->calender_colour = '#c4c4c4';
                 $pro_h->save();



                return response([ 'data' => [
                  'message' => 'Production Incentive Successfully Calculated',
                  'status'=>1
                  ]
                ], Response::HTTP_CREATED );

            }
            else{
              return response($this->authorize->error_response(), 401);
            }

      }





      public function save_cadre_detail(Request $request){

        if($this->authorize->hasPermission('CALENDER_CADRE_CREATE'))//check permission
            {
              $user = auth()->payload();
              $user_loc = $user['loc_id'];

              //dd($request);

              $check_emp = CadreDetail::where('inc_cadre_detail.emp_no'  , '=',  $request['cadreData']['cadre_emp_no']['emp_no'] )
              ->where('inc_cadre_detail.user_loc_id'  , '=', $user_loc )
              ->where('inc_cadre_detail.status'  , '<>', 0 )
              ->count();

              //dd($request);
              if($check_emp > 0){
                $err = 'Employee already Exists.';
                return response([ 'data' => ['status' => '0','message' => $err]]);

              }

              $check_direct_indirect = CadreHeader::where('cadre_id'  , '=',  $request['cadreData']['cadre_id'] )
              ->where('status'  , '<>', 0 )
              ->get();

              //dd($check_direct_indirect[0]['cadre_type']);

              if($check_direct_indirect[0]['cadre_type'] == "INDIRECT"){

                  $cadre_Detail = new CadreDetail();
                  $cadre_Detail->cadre_id = $request['cadreData']['cadre_id'];
                  $cadre_Detail->emp_no = $request['cadreData']['cadre_emp_no']['emp_no'];
                  $cadre_Detail->emp_detail_id = $request['cadreData']['cadre_emp_no']['emp_detail_id'];
                  $cadre_Detail->shift_duration = $request['cadreData']['cadre_emp_no']['shift_duration'];
                  $cadre_Detail->emp_name = strtoupper($request['cadreData']['cadre_emp_no']['emp_name']);
                  $cadre_Detail->line_no = null;
                  $cadre_Detail->inc_section_id = null;
                  $cadre_Detail->status = 1;
                  $cadre_Detail->save();


              }else{

                for($r = 0 ; $r < sizeof($request['cadreData']['cadre_line_no']) ; $r++)
                 {

                  $cadre_Detail = new CadreDetail();
                  $cadre_Detail->cadre_id = $request['cadreData']['cadre_id'];
                  $cadre_Detail->emp_no = $request['cadreData']['cadre_emp_no']['emp_no'];
                  $cadre_Detail->emp_detail_id = $request['cadreData']['cadre_emp_no']['emp_detail_id'];
                  $cadre_Detail->shift_duration = $request['cadreData']['cadre_emp_no']['shift_duration'];
                  $cadre_Detail->emp_name = strtoupper($request['cadreData']['cadre_emp_no']['emp_name']);
                  $cadre_Detail->line_no = $request['cadreData']['cadre_line_no'][$r]['line_no'];
                  $cadre_Detail->inc_section_id = $request['cadreData']['cadre_line_no'][$r]['inc_section_id'];
                  $cadre_Detail->status = 1;
                  $cadre_Detail->save();

                  }

              }

              return response([ 'data' => [
                'message' => 'Cadre Employee Saved successfully',
                'status'=>1
                ]
              ], Response::HTTP_CREATED );


            }
            else{
              return response($this->authorize->error_response(), 401);
            }

      }


      public function final_calculation_email(Request $request){
        if($this->authorize->hasPermission('CALENDER_SEND_FOR_APPROVEL'))//check permission
            {
              $user = auth()->payload();
              $user_loc = $user['loc_id'];
              $loc_details = Location::find($user_loc);
              $raise_loc = $loc_details['raise_intCompanyID'];
              $d2d_loc = $loc_details['d2d_loc_id'];
              $current_month = $request['current_month'];

              $check_email = EmailStatus::where('inc_email_month'  , '=',  $current_month )
              ->count();
              if($check_email > 0){
                $err = 'Email already Sent.';
                return response([ 'data' => ['status' => '0','message' => $err]]);

              }

              $load_not_holiday_list = ProductionIncentiveHeader::where(DB::raw("DATE_FORMAT(inc_production_incentive_header.incentive_date,'%Y-%m')")  , '=', $current_month )
              ->where('user_loc_id'  , '=', $user_loc )
              ->where('incentive_status'  , '<>', 'HOLIDAY' )
              ->get();

              $load_calculated_list = ProductionIncentiveHeader::where(DB::raw("DATE_FORMAT(inc_production_incentive_header.incentive_date,'%Y-%m')")  , '=', $current_month )
              ->where('user_loc_id'  , '=', $user_loc )
              ->where('incentive_status'  , '=', 'READY TO SEND' )
              ->get();

              if(sizeof($load_calculated_list) == 0){
                $err = 'Please do the Final Calculation before Send For Approval.';
                return response([ 'data' => ['status' => '0','message' => $err]]);

              }

              if(sizeof($load_not_holiday_list) != sizeof($load_calculated_list)){
                $err = 'Please Calculate Final Production Incentive';
                return response([ 'data' => ['status' => '0','message' => $err]]);

              }else{

                $Email = new EmailStatus();
                $Email->inc_email_month = $current_month;
                $Email->status = 'PENDING';
                $Email->save();

                $approval = new Approval();
                $approval->start('PRODUCTION_INCENTIVE', $Email['email_id'], $Email['created_by']);//start po approval process

                //Sent for Approval

                DB::table('inc_production_incentive_header')
                       ->where(DB::raw("DATE_FORMAT(inc_production_incentive_header.incentive_date,'%Y-%m')")  , '=', $current_month )
                       ->where('incentive_status', 'READY TO SEND')
                       ->where('user_loc_id' , '=', $user_loc )
                       ->update([
                         'incentive_status' => 'SENT FOR APPROVAL',
                         'calender_colour' => '#795548'
                       ]);

                return response([ 'data' => [
                  'message' => 'Production Incentive Successfully Sent for Approval',
                  'status'=>1
                  ]
                ], Response::HTTP_CREATED );


              }





            }
            else{
              return response($this->authorize->error_response(), 401);
            }


      }


      public function final_calculation(Request $request){

        if($this->authorize->hasPermission('CALENDER_FINAL_CALCULATION'))//check permission
            {
              $user = auth()->payload();
              $user_loc = $user['loc_id'];
              $loc_details = Location::find($user_loc);
              $raise_loc = $loc_details['raise_intCompanyID'];
              $d2d_loc = $loc_details['d2d_loc_id'];
              $current_month = $request['current_month'];

              $check_email = EmailStatus::where('inc_email_month'  , '=',  $current_month )
              ->count();
              if($check_email > 0){
                $err = 'Email already Sent.';
                return response([ 'data' => ['status' => '0','message' => $err]]);

              }

              $check_saved = ProductionIncentiveHeader::where(DB::raw("DATE_FORMAT(inc_production_incentive_header.incentive_date,'%Y-%m')")  , '=', $current_month )
              ->where('user_loc_id'  , '=', $user_loc )
              ->where('incentive_status'  , '=', 'READY TO SEND' )
              ->count();
              if($check_saved > 0){
                $err = 'Production Incentive Already Calculated.';
                return response([ 'data' => ['status' => '0','message' => $err]]);

              }

              $load_not_holiday_list = ProductionIncentiveHeader::where(DB::raw("DATE_FORMAT(inc_production_incentive_header.incentive_date,'%Y-%m')")  , '=', $current_month )
              ->where('user_loc_id'  , '=', $user_loc )
              ->where('incentive_status'  , '<>', 'HOLIDAY' )
              //->where('incentive_status'  , '<>', 'CALCULATED' )
              ->where('incentive_status'  , '<>', 'READY TO SEND' )
              ->get();

              $load_calculated_list = ProductionIncentiveHeader::where(DB::raw("DATE_FORMAT(inc_production_incentive_header.incentive_date,'%Y-%m')")  , '=', $current_month )
              ->where('user_loc_id'  , '=', $user_loc )
              ->where('incentive_status'  , '=', 'CALCULATED' )
              ->get();

              //dd(sizeof($load_not_holiday_list));

              if(sizeof($load_calculated_list) == 0){

                $err = 'Please Calculate All Incentive Details.';
                return response([ 'data' => ['status' => '0','message' => $err]]);

              }

              if(sizeof($load_not_holiday_list) != sizeof($load_calculated_list)){
                $err = 'Please Calculate Production Incentive';
                return response([ 'data' => ['status' => '0','message' => $err]]);

              }

              for($j = 0 ; $j < sizeof($load_calculated_list) ; $j++)
               {
                 $INC_DATE_2 = $load_calculated_list[$j]['incentive_date'];
                 $load_from_raise_count = DB::connection('raise')->select("SELECT Count(attendence.dtmDate) AS day_count FROM attendence
                                  INNER JOIN employee ON attendence.strEmpNo = employee.strEmpNo AND attendence.bytCompanyCode = employee.bytCompany
                                  INNER JOIN shift ON employee.strShift = shift.intShiftID AND attendence.bytCompanyCode = shift.intCompanyID
                                  WHERE attendence.dtmDate = '$INC_DATE_2' AND attendence.bytCompanyCode = '$raise_loc' GROUP BY attendence.dtmDate
                 ");

                 if(sizeof($load_from_raise_count) == 0){
                   $err = 'No attendance records updated ('.$incentive_date_format_change.') ';
                   return response([ 'data' => ['status' => '0','message' => $err]]);

                 }


               }


              for($r = 0 ; $r < sizeof($load_calculated_list) ; $r++)
               {

                $INC_DATE = $load_calculated_list[$r]['incentive_date'];
                $load_from_raise = DB::connection('raise')->select("SELECT attendence.strEmpNo,employee.strEPFNo,attendence.dtmDate,attendence.bytCompanyCode,
                                   attendence.strTimeIn,attendence.strTimeOut,attendence.strDay,shift.dtmTimeIn,shift.dtmTimeOut
                                   FROM attendence
                                   INNER JOIN employee ON attendence.strEmpNo = employee.strEmpNo AND attendence.bytCompanyCode = employee.bytCompany
                                   INNER JOIN shift ON employee.strShift = shift.intShiftID AND attendence.bytCompanyCode = shift.intCompanyID
                                   WHERE attendence.dtmDate = '$INC_DATE' AND attendence.bytCompanyCode = '$raise_loc' ");
                if(sizeof($load_from_raise) == 0){
                  $err = 'Fail to update attendance from ('.$INC_DATE.').Connection lost. ';
                  return response([ 'data' => ['status' => '0','message' => $err]]);

                }
                for($k = 0 ; $k < sizeof($load_from_raise) ; $k++)
                       {
                         $in_time = null;
                         $out_time = null;
                         $shift_start = null;
                         $shift_end = null;
                         $incentive_attendance = null;
                         $employee_duration = null;
                         $shift_duration = null;

                         $in_time =  $load_from_raise[$k]->strTimeIn;
                         $out_time =  $load_from_raise[$k]->strTimeOut;

                         $shift_start =  $load_from_raise[$k]->dtmTimeIn;
                         $shift_end =  $load_from_raise[$k]->dtmTimeOut;

                         $employee_duration = ((int)$out_time - (int)$in_time);
                         $shift_duration = ((int)$shift_end - (int)$shift_start);

                         $buffer_policy = BufferPolicy::select('hours')->get();
                         $buffer = ((int)$buffer_policy[0]['hours'] * 2);

                         $shift_buffer = (int)$shift_duration - (int)$buffer;
                         $shift_hald_day = (int)$shift_duration/2;

                         if($employee_duration >= $shift_buffer){$incentive_attendance = 1.0;}
                         if($employee_duration < $shift_buffer AND $employee_duration >= $shift_hald_day){$incentive_attendance = 0.5;}
                         if($employee_duration < $shift_hald_day ){$incentive_attendance = 0.0;}

                         //dd($incentive_attendance);

                         $emp_attendance = new EmployeeAttendance();
                         $emp_attendance->emp_no = $load_from_raise[$k]->strEmpNo;
                         $emp_attendance->epf_no = $load_from_raise[$k]->strEPFNo;
                         $emp_attendance->attendance_date = $load_from_raise[$k]->dtmDate;
                         $emp_attendance->raise_location = $load_from_raise[$k]->bytCompanyCode;
                         $emp_attendance->in_time = $in_time;
                         $emp_attendance->out_time = $out_time;
                         $emp_attendance->strDay =$load_from_raise[$k]->strDay;
                         $emp_attendance->shift_start_time = $shift_start;
                         $emp_attendance->shift_end_time = $shift_end;
                         $emp_attendance->emp_duration = $employee_duration;
                         $emp_attendance->shift_duration = $shift_duration;
                         $emp_attendance->buffer_duration = $buffer;
                         $emp_attendance->incentive_attendance = $incentive_attendance;
                         $emp_attendance->save();

                         $load_list = ProductionIncentiveLine::where('inc_production_incentive_line.incentive_date'  , '=', $INC_DATE )
                         ->where('user_loc_id'  , '=', $user_loc )
                         ->where('emp_no'  , '=', $load_from_raise[$k]->strEmpNo )
                         ->where('status'  , '=', 'PLANNED' )
                         ->select('inc_production_incentive_line.*')
                         ->get();

                         for($m = 0 ; $m < sizeof($load_list) ; $m++)
                         {
                           $inc_production_incentive_line_id = $load_list[$m]['inc_production_incentive_line_id'];
                           $emp_detail_id = $load_list[$m]['emp_detail_id'];
                           $emp_no = $load_list[$m]['emp_no'];
                           $work_duration_2 = $load_list[$m]['work_duration'];
                           $shift_duration_2 = $load_list[$m]['shift_duration'];
                           $emp_incentive_payment = $load_list[$m]['emp_incentive_payment'];
                           $actual_work_hours = (float)$work_duration_2 / (float)$shift_duration_2;

                           $final_incentive_payment = (float)$emp_incentive_payment * (float)$incentive_attendance * (float)$actual_work_hours;

                           //dd($final_incentive_payment);

                           DB::table('inc_production_incentive_line')
                                  ->where('inc_production_incentive_line_id',  $inc_production_incentive_line_id)
                                  ->where('emp_detail_id', $emp_detail_id)
                                  ->where('emp_no' , '=', $emp_no )
                                  ->update([
                                    'final_incentive_attendance' => $incentive_attendance,
                                    'final_incentive_payment' => $final_incentive_payment
                                  ]);

                         }



                         DB::table('inc_production_incentive_header')
                                ->where('incentive_date',  $INC_DATE)
                                ->where('incentive_status', 'CALCULATED')
                                ->where('user_loc_id' , '=', $user_loc )
                                ->update([
                                  'incentive_status' => 'READY TO SEND',
                                  'calender_colour' => '#c4c4c4'
                                ]);




                       }

                 //$strTimeIn =  $load_from_raise[$r]
                 //dd($load_from_raise);
               }





               return response([ 'data' => [
                 'message' => 'Production Incentive Successfully Calculated',
                 'status'=>1
                 ]
               ], Response::HTTP_CREATED );


            }
            else{
              return response($this->authorize->error_response(), 401);
            }


      }

      public function load_cadre_header(Request $request){
        $user = auth()->payload();
        $user_loc = $user['loc_id'];

        //dd($user_loc);
        $cader_header= CadreHeader::where('user_loc_id'  , '=', $user_loc )->where('status'  , '<>', 0 )->get();

        $arr['cader_header'] = $cader_header;
        //$arr['ladder_count'] = sizeof($ladder);

        if($arr == null)
            throw new ModelNotFoundException("Requested section not found", 1);
        else
            return response([ 'data' => $arr ]);

      }


      public function load_cadre_detail(Request $request){
        $user = auth()->payload();
        $user_loc = $user['loc_id'];

        //dd($request['cadreData']['cadre_emp_no']);



        //dd($request['formData']);
        $cader_detail = CadreHeader::join('inc_cadre_detail','inc_cadre_detail.cadre_id','=','inc_cadre_header.cadre_id')
                      ->where('inc_cadre_header.user_loc_id'  , '=', $user_loc )
                      ->where('inc_cadre_header.cadre_id'  , '=', $request['cadreData']['cadre_id'] )
                      ->where('inc_cadre_detail.status'  , '<>', 0 )
                      ->orderBy('inc_cadre_detail.cadre_detail_id', 'desc')
                      ->get();

        $arr['cader_detail'] = $cader_detail;
        //$arr['ladder_count'] = sizeof($ladder);

        if($arr == null)
            throw new ModelNotFoundException("Requested section not found", 1);
        else
            return response([ 'data' => $arr ]);

      }


      public function load_direct_incentive(Request $request){
        $id  = $request->id;
        $incentive_date = $request->incentive_date;
        $user = auth()->payload();
        $user_loc = $user['loc_id'];

        $emp_total  = ProductionIncentiveLine::join('inc_cadre_header','inc_production_incentive_line.emp_status','=','inc_cadre_header.cadre_name')
                      ->where('inc_production_incentive_line.user_loc_id'  , '=', $user_loc )
                      ->where('inc_production_incentive_line.incentive_date'  , '=', $incentive_date )
                      ->where('inc_cadre_header.status'  , '<>', 0 )
                      ->where('inc_production_incentive_line.inc_production_incentive_id'  , '=', 0 )
                      ->where('inc_cadre_header.user_loc_id'  , '=', $user_loc )
                      //->where('inc_production_incentive_line.incentive_payment'  , '<>', 0 )
                      ->groupBy('inc_production_incentive_line.emp_no')
                      ->select('inc_production_incentive_line.emp_no',
                      DB::raw("round(sum(inc_production_incentive_line.incentive_payment)/(count(inc_production_incentive_line.emp_no)),2) AS total_incentive"))
                      ->get();


        $load_direct_inc = ProductionIncentiveLine::join('inc_cadre_header','inc_production_incentive_line.emp_status','=','inc_cadre_header.cadre_name')
                      ->where('inc_production_incentive_line.user_loc_id'  , '=', $user_loc )
                      ->where('inc_production_incentive_line.incentive_date'  , '=', $incentive_date )
                      ->where('inc_cadre_header.status'  , '<>', 0 )
                      ->where('inc_production_incentive_line.inc_production_incentive_id'  , '=', 0 )
                      ->where('inc_cadre_header.user_loc_id'  , '=', $user_loc )
                      ->select('inc_cadre_header.*','inc_production_incentive_line.*','inc_production_incentive_line.work_duration as total')
                      ->get();

          //echo $emp_total;die();

        //dd(sizeof($load_direct_inc));


        $arr['load_direct_inc'] = $load_direct_inc;
        $arr['emp_total'] = $emp_total;
        $arr['count'] = sizeof($load_direct_inc);
        $arr['count2'] = sizeof($emp_total);

        if($arr == null)
            throw new ModelNotFoundException("Requested section not found", 1);
        else
            return response([ 'data' => $arr ]);

      }







}
