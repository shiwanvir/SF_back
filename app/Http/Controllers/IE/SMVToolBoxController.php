<?php

namespace App\Http\Controllers\IE;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Http\Controllers\Controller;
use App\Models\IE\OperationComponent;
use App\Models\IE\GarmentOperationMaster;
use App\Models\IE\SMVReadingHeader;
use App\Models\IE\SMVReadingDetails;
use App\Models\IE\SMVReadingSummary;
use App\Models\IE\SMVReadingHeaderHistory;
use App\Models\IE\SMVReadingDetailsHistory;
use App\Models\IE\SMVReadingSummaryHistory;
use Exception;
use App\Libraries\CapitalizeAllFields;
use Illuminate\Support\Facades\DB;
use App\Libraries\AppAuthorize;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\OperationBreakdownDownload;
use App\Models\IE\OperationSubComponentDetails;
use App\Models\IE\SilhouetteOperationMappingDetails;
use App\Models\IE\SilhouetteOperationMappingheader;

class SMVToolBoxController extends Controller
{
  var $authorize = null;
    public function __construct()
    {
      //add functions names to 'except' paramert to skip authentication
      $this->middleware('jwt.verify', ['except' => ['index','OperationDataExport']]);
        $this->authorize = new AppAuthorize();
    }

    //get Service Type list
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
      else if($type == 'handsontable')    {
        $search = $request->search;
        return response([
          'data' => $this->handsontable_search($search)
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



        //create a Service Type
        public function store(Request $request)
        {
          $header=$request->header;
          $details=$request->detail;
          $summary=$request->summary;
          if($this->authorize->hasPermission('SMV_TOOL_BOX_PRINT'))//check permission
          {
          $ReadingHeader = new SMVReadingHeader();
          if($ReadingHeader->validate($header))
          {
            if($this->details_list_validation($details)==true){

            $ReadingHeader->fill($header);
            $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($ReadingHeader);
            $ReadingHeader->status = 1;
            $ReadingHeader->version=1;
            $ReadingHeader->save();

            foreach ($details as $value) {
              $smvDetails=new  SMVReadingDetails();
              $smvDetails->fill($value);
              $smvDetails->sub_component_detail_id=$value['detail_id'];
              $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($smvDetails);
              $smvDetails->status=1;
              $smvDetails->smv_reading_id=$ReadingHeader->smv_reading_id;
              $operationSubComponentDetails=OperationSubComponentDetails::find($value['detail_id']);
              $smvDetails->gsd_smv=$operationSubComponentDetails->gsd_smv;
              $smvDetails->operation_code=$operationSubComponentDetails->operation_code;
              $smvDetails->save();
              // code...
            }
            foreach ($summary as $value) {
              $summaryDetails=new SMVReadingSummary();
              $summaryDetails->fill($value);
              $summaryDetails->smv_reading_id=$ReadingHeader->smv_reading_id;
              $summaryDetails->status=1;
              $summaryDetails->save();

            }

            return response([ 'data' => [
              'message' => 'SMV Details Saved successfully',
              'garmentOperation' => $ReadingHeader,
              'id'=>$ReadingHeader->smv_reading_id,
              'status'=>'1'
              ]
            ], Response::HTTP_CREATED );

          }
          else if($this->details_list_validation($details)==false){
              return response([ 'data' => [
              'message' => 'Validation Error',
              'smvReading' => $ReadingHeader,
              'status'=>'0'
              ]
            ], Response::HTTP_CREATED );
          }
        }
          else
          {
              $errors = $ReadingHeader->errors();// failure, get errors
              $errors_str = $ReadingHeader->errors_tostring();
              return response(['errors' => ['validationErrors' => $errors, 'validationErrorsText' => $errors_str]], Response::HTTP_UNPROCESSABLE_ENTITY);
          }

        }
        else{
          return response($this->authorize->error_response(), 401);
        }
        }

        //details data set validation
        public function details_list_validation($dataSet){
          $status=false;
          //dd($dataSet);
          foreach ($dataSet as $value) {
            $smvDetails=new SMVReadingDetails();
            if($smvDetails->validate($value)){

              $status= true;
             }
             else
             {
                 $errors = $smvDetails->errors();// failure, get errors
                 $errors_str = $smvDetails->errors_tostring();
                 return response(['errors' => ['validationErrors' => $errors, 'validationErrorsText' => $errors_str]], Response::HTTP_UNPROCESSABLE_ENTITY);
             }
            }
            //dd("d3saww");
          return $status;
        }

    //get a Service Type
    public function show($id)
    {
      if($this->authorize->hasPermission('SMV_TOOL_BOX_VIEW'))//check permission
      {
      $status=1;
      $smvreadingheader = SMVReadingHeader::join('cust_customer','ie_smv_reading_header.customer_id','=','cust_customer.customer_id')
                                            ->join('product_silhouette','ie_smv_reading_header.product_silhouette_id','=','product_silhouette.product_silhouette_id')->find($id);
      //$operation_component=OperationComponent::find($smvreadingheader->operation_component_id);

    /*  $subComponentsList=SMVReadingDetails::join('ie_operation_sub_component_details','ie_smv_reading_details.sub_component_detail_id','=','ie_operation_sub_component_details.detail_id')
      ->join('ie_operation_sub_component_header','ie_operation_sub_component_details.operation_sub_component_id','=','ie_operation_sub_component_header.operation_sub_component_id')->where('smv_reading_id','=',$id)->where('ie_smv_reading_details.status','=',$status)->select('ie_operation_sub_component_header.*')->groupBy('ie_operation_sub_component_header.operation_sub_component_id')->get();*/
      if($this->authorize->hasPermission('SMV_TOOL_BOX_IE_PERMISSION'))//check permission
      {
      $smvreadingDetails=SMVReadingDetails::join('ie_operation_component','ie_smv_reading_details.operation_component_id','=','ie_operation_component.operation_component_id')
      ->join('ie_operation_sub_component_details','ie_smv_reading_details.sub_component_detail_id','=','ie_operation_sub_component_details.detail_id')
        ->join('ie_machine_type','ie_operation_sub_component_details.machine_type_id','=','ie_machine_type.machine_type_id')
      ->join('ie_operation_sub_component_header','ie_operation_sub_component_details.operation_sub_component_id','=','ie_operation_sub_component_header.operation_sub_component_id')->where('smv_reading_id','=',$id)->where('ie_smv_reading_details.status','=',$status)
      ->select('ie_operation_component.*','ie_operation_sub_component_header.*','ie_operation_sub_component_details.detail_id as sub_co_detail_id', 'ie_operation_sub_component_details.operation_name', 'ie_operation_sub_component_details.machine_type_id', 'ie_operation_sub_component_details.operation_sub_component_id', 'ie_operation_sub_component_details.cost_smv', 'ie_operation_sub_component_details.status','ie_smv_reading_details.detail_id', 'ie_smv_reading_details.smv_reading_id', 'ie_smv_reading_details.sub_component_detail_id', 'ie_smv_reading_details.operation_component_id', 'ie_smv_reading_details.machine_type_id', 'ie_smv_reading_details.operation_name', 'ie_smv_reading_details.cost_smv','ie_smv_reading_details.gsd_smv','ie_smv_reading_details.operation_code','ie_machine_type.machine_type_name','ie_operation_component.operation_component_name as ori_operation_component_name','ie_operation_sub_component_header.operation_sub_component_name as ori_operation_sub_component_name')
      ->get();
       }
     else{
       $smvreadingDetails=SMVReadingDetails::join('ie_operation_component','ie_smv_reading_details.operation_component_id','=','ie_operation_component.operation_component_id')
       ->join('ie_operation_sub_component_details','ie_smv_reading_details.sub_component_detail_id','=','ie_operation_sub_component_details.detail_id')
         ->join('ie_machine_type','ie_operation_sub_component_details.machine_type_id','=','ie_machine_type.machine_type_id')
       ->join('ie_operation_sub_component_header','ie_operation_sub_component_details.operation_sub_component_id','=','ie_operation_sub_component_header.operation_sub_component_id')->where('smv_reading_id','=',$id)->where('ie_smv_reading_details.status','=',$status)
       ->select('ie_operation_component.*','ie_operation_sub_component_header.*','ie_operation_sub_component_details.detail_id as sub_co_detail_id', 'ie_operation_sub_component_details.operation_name', 'ie_operation_sub_component_details.machine_type_id', 'ie_operation_sub_component_details.operation_sub_component_id', 'ie_operation_sub_component_details.cost_smv', 'ie_operation_sub_component_details.status','ie_smv_reading_details.detail_id', 'ie_smv_reading_details.smv_reading_id', 'ie_smv_reading_details.sub_component_detail_id', 'ie_smv_reading_details.operation_component_id', 'ie_smv_reading_details.machine_type_id', 'ie_smv_reading_details.operation_name', 'ie_smv_reading_details.cost_smv','ie_machine_type.machine_type_name','ie_operation_component.operation_component_name as ori_operation_component_name','ie_operation_sub_component_header.operation_sub_component_name as ori_operation_sub_component_name')
       ->get();

     }

      $smvSummary=SMVReadingSummary::join('ie_garment_operation_master','ie_smv_reading_summary.garment_operation_id','=','ie_garment_operation_master.garment_operation_id')
      ->where('smv_reading_id','=',$id)->where('ie_smv_reading_summary.status','=',$status)->get();

      if($smvreadingheader == null)
        throw new ModelNotFoundException("Requested SMV Reading Not Found", 1);
    //}
     else
        return response([ 'data' => ['header'=>$smvreadingheader,
                                   'detail'=>$smvreadingDetails,
                                    'summary'=>$smvSummary
                                  ] ]);
      }
      else{
        return response($this->authorize->error_response(), 401);
      }
    }


    public function update(Request $request, $id){
      if($this->authorize->hasPermission('SMV_TOOL_BOX_EDIT'))//check permission
      {
        $header=$request->header;
        $details=$request->detail;
        $summary=$request->summary;
          $smvReadingHeader = SMVReadingHeader::find($id);
          $smvReadinHeaderHistory=new SMVReadingHeaderHistory();
          $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($smvReadingHeader);
            if ($smvReadingHeader->validate($header)) {
              if($this->details_list_validation($details)==true){

              $smvReadinHeaderHistory= $smvReadingHeader->replicate();
              $smvReadinHeaderHistory->setTable('ie_smv_reading_header_history');
              $smvReadinHeaderHistory->smv_reading_id=$id;
              $smvReadinHeaderHistory->save();
              $smvReadingHeader->version=$smvReadingHeader->version+1;
              //dd($header);
              $smvReadingHeader->total_smv=$header['total_smv'];
              $smvReadingHeader->save();

              $this->insertToHistorytable($id);

              foreach ($details as $value) {
                $_sub_compoent_detail=null;
                $smvDetails=new  SMVReadingDetails();
                $smvDetails->fill($value);
              /*  if(empty($key['sub_co_detail_id'])==true){
                $smvDetails->sub_component_detail_id=$value['detail_id'];
                $_sub_compoent_detail=$value['detail_id'];
                }
               if(empty($key['sub_co_detail_id'])==false){
                  $_sub_compoent_detail=$value['sub_co_detail_id'];
                }*/

                $operationSubComponentDetails=OperationSubComponentDetails::find($value['sub_component_detail_id']);
                $smvDetails->gsd_smv=$operationSubComponentDetails->gsd_smv;
                $smvDetails->operation_code=$operationSubComponentDetails->operation_code;
                $capitalizeAllFields=CapitalizeAllFields::setCapitalAll($smvDetails);
                $smvDetails->status=1;
                $smvDetails->smv_reading_id=$smvReadingHeader->smv_reading_id;
                $smvDetails->save();
              }
              foreach ($summary as $value) {
                $summaryDetails=new SMVReadingSummary();
                $summaryDetails->fill($value);
                $summaryDetails->smv_reading_id=$smvReadingHeader->smv_reading_id;
                $summaryDetails->status=1;
                $summaryDetails->save();
              }

              return response(['data' => [
                      'message' => 'SMV Details Updated Successfully',
                      'smvreadingHeader' => $smvReadingHeader,
                      'id'=>$smvReadingHeader->smv_reading_id,
                      'status'=>'1'
              ]]);
            }
            else if($this->details_list_validation($details)==false){
                return response([ 'data' => [
                'message' => 'Validation Error',
                'garmentOperation' => $smvReadingHeader,
                'status'=>'0'
                ]
              ], Response::HTTP_CREATED );
            }

            }
            else {
             $errors = $smvReadingHeader->errors();// failure, get errors
             $errors_str = $smvReadingHeader->errors_tostring();
             return response(['errors' => ['validationErrors' => $errors, 'validationErrorsText' => $errors_str]], Response::HTTP_UNPROCESSABLE_ENTITY);
           }

      }
      else{
        return response($this->authorize->error_response(), 401);
      }
    }


    public function insertToHistorytable($id){

      $details=SMVReadingDetails::where('smv_reading_id','=',$id)->where('status','=',1)->get();

      foreach ($details as $value) {
       $smvReadingDetailsHistory=new SMVReadingDetailsHistory();
        $smvReadingDetailsOldLine=SMVReadingDetails::find($value['detail_id']);
        $smvReadingDetailsHistory=$smvReadingDetailsOldLine->replicate();
        $smvReadingDetailsHistory->setTable('ie_smv_reading_details_history');
        $smvReadingDetailsHistory->detail_id=$value['detail_id'];
        $smvReadingDetailsHistory->save();
        $smvReadingDetailsOldLine->status=0;
        $smvReadingDetailsOldLine->save();
        }
      $summaryDetails=SMVReadingSummary::where('smv_reading_id','=',$id)->where('status','=',1)->get();
      foreach ($summaryDetails as $value) {
      $summaryOldLine=SMVReadingSummary::find($value['summary_id']);
      $smvReadingSummaryHistory= new SMVReadingSummaryHistory();
      $smvReadingSummaryHistory=$summaryOldLine->replicate();
      $smvReadingSummaryHistory->setTable('ie_smv_reading_summary_history');
      $smvReadingSummaryHistory->summary_id=$value['summary_id'];
      $smvReadingSummaryHistory->save();
      $summaryOldLine->status=0;
      $summaryOldLine->save();
      }

    }



    //deactivate a Service Type
    public function destroy($id)
    {
      if($this->authorize->hasPermission('SMV_TOOL_BOX_DELETE'))//check permission
      {

      $header = SMVReadingHeader::where('smv_reading_id', $id)->update(['status' => 0]);
      $details=SMVReadingDetails::where('smv_reading_id',$id)->update(['status' => 0]);
      $summary=SMVReadingSummary::where('smv_reading_id',$id)->update(['status' => 0]);
      return response([
        'data' => [
          'message' => 'SMV Details Deactivated Successfully.',
          'smvDetails' => $header,
          'status'=>'1'
        ]
      ]);
    }
    else{
      return response($this->authorize->error_response(), 401);
    }
  }

  public function searchDetails(Request $request){
  if($this->authorize->hasPermission('SMV_TOOL_BOX_SEARCH')){
  if($this->authorize->hasPermission('SMV_TOOL_BOX_IE_PERMISSION'))//check permission
  {
    $status=1;

    $detials=OperationComponent::join('ie_operation_sub_component_header','ie_operation_component.operation_component_id','=','ie_operation_sub_component_header.operation_component_id')
                                ->join('ie_operation_sub_component_details','ie_operation_sub_component_header.operation_sub_component_id','=','ie_operation_sub_component_details.operation_sub_component_id')
                                ->join('ie_machine_type','ie_operation_sub_component_details.machine_type_id','=','ie_machine_type.machine_type_id')
                                ->whereIn('ie_operation_sub_component_header.operation_sub_component_id',array_flatten($request->header['operation_sub_component_id']))
                                ->where('ie_operation_sub_component_details.status','=',$status)
                                ->where('ie_operation_component.operation_component_id','=',$request->header['operation_component_id'])
                                ->select('*','ie_operation_sub_component_details.detail_id as sub_component_detail_id','ie_operation_component.operation_component_name as ori_operation_component_name','ie_operation_sub_component_header.operation_sub_component_name as ori_operation_sub_component_name')
                                ->get();
   $summary=GarmentOperationMaster::where('garment_operation_id','!=',"SEWING")
                                    ->where('status','=',1)->get();
                                return response([
                                  'data' => [
                                    'details' => $detials,
                                    'summary'=>$summary,
                                    'status'=>'1'
                                  ]
                                ]);
  }
  else{
    $status=1;

    $detials=OperationComponent::join('ie_operation_sub_component_header','ie_operation_component.operation_component_id','=','ie_operation_sub_component_header.operation_component_id')
                                ->join('ie_operation_sub_component_details','ie_operation_sub_component_header.operation_sub_component_id','=','ie_operation_sub_component_details.operation_sub_component_id')
                                ->join('ie_machine_type','ie_operation_sub_component_details.machine_type_id','=','ie_machine_type.machine_type_id')
                                ->whereIn('ie_operation_sub_component_header.operation_sub_component_id',array_flatten($request->header['operation_sub_component_id']))
                                ->where('ie_operation_sub_component_details.status','=',$status)
                                ->where('ie_operation_component.operation_component_id','=',$request->header['operation_component_id'])
                                ->select('ie_operation_component.*','ie_operation_sub_component_header.*','ie_operation_sub_component_details.detail_id', 'ie_operation_sub_component_details.operation_name', 'ie_operation_sub_component_details.machine_type_id', 'ie_operation_sub_component_details.operation_sub_component_id', 'ie_operation_sub_component_details.cost_smv', 'ie_operation_sub_component_details.status','ie_machine_type.machine_type_name','ie_operation_sub_component_details.detail_id as sub_component_detail_id','ie_operation_component.operation_component_name as ori_operation_component_name','ie_operation_sub_component_header.operation_sub_component_name as ori_operation_sub_component_name')
                                ->get();
   $summary=GarmentOperationMaster::where('garment_operation_id','!=',"SEWING")->get();
                                return response([
                                  'data' => [
                                    'details' => $detials,
                                    'summary'=>$summary,
                                    'status'=>'1'
                                  ]
                                ]);

  }
}
else{
  return response($this->authorize->error_response(), 401);
}
}

public function sillhouette_wise_all(Request $request){
$search=$request->silhouette;
if($this->authorize->hasPermission('SMV_TOOL_BOX_SEARCH')){
if($this->authorize->hasPermission('SMV_TOOL_BOX_IE_PERMISSION'))//check permission
{
  $status=1;
  $approval_status="APPROVED";

  $detials=SilhouetteOperationMappingheader::join('ie_silhouette_operation_mapping_details','ie_silhouette_operation_mapping_header.mapping_header_id','=','ie_silhouette_operation_mapping_details.mapping_header_id')
                              ->join('ie_operation_component','ie_silhouette_operation_mapping_details.operation_component_id','=','ie_operation_component.operation_component_id')
                              ->join('ie_operation_sub_component_header','ie_operation_component.operation_component_id','=','ie_operation_sub_component_header.operation_component_id')
                              ->join('ie_operation_sub_component_details','ie_operation_sub_component_header.operation_sub_component_id','=','ie_operation_sub_component_details.operation_sub_component_id')
                              ->join('ie_machine_type','ie_operation_sub_component_details.machine_type_id','=','ie_machine_type.machine_type_id')
                              ->where('ie_operation_sub_component_details.status','=',$status)
                              ->where('ie_operation_component.status','=',$status)
                              ->where('ie_operation_sub_component_header.status','=',$status)
                              ->where('ie_operation_sub_component_header.approval_status','=',$approval_status)
                              ->where('ie_operation_component.approval_status','=',$approval_status)
                              ->where('ie_silhouette_operation_mapping_header.status','=',$status)
                              ->where('ie_silhouette_operation_mapping_header.product_silhouette_id','=',$search)
                              ->select('ie_operation_sub_component_header.*')
                              ->select('*','ie_operation_sub_component_details.detail_id as sub_component_detail_id','ie_operation_component.operation_component_name as ori_operation_component_name','ie_operation_sub_component_header.operation_sub_component_name as ori_operation_sub_component_name')
                              ->get();


 $summary=GarmentOperationMaster::where('garment_operation_id','!=',"SEWING")
                                  ->where('status','=',1)->get();
                              return response([
                                'data' => [
                                  'details' => $detials,
                                  'summary'=>$summary,
                                  'status'=>'1'
                                ]
                              ]);
}
else{
  $status=1;

  $detials=SilhouetteOperationMappingheader::join('ie_silhouette_operation_mapping_details','ie_silhouette_operation_mapping_header.mapping_header_id','=','ie_silhouette_operation_mapping_details.mapping_header_id')
                              ->join('ie_operation_component','ie_silhouette_operation_mapping_details.operation_component_id','=','ie_operation_component.operation_component_id')
                               ->join('ie_operation_sub_component_header','ie_operation_component.operation_component_id','=','ie_operation_sub_component_header.operation_component_id')
                              ->join('ie_operation_sub_component_details','ie_operation_sub_component_header.operation_sub_component_id','=','ie_operation_sub_component_details.operation_sub_component_id')
                              ->join('ie_machine_type','ie_operation_sub_component_details.machine_type_id','=','ie_machine_type.machine_type_id')
                              ->where('ie_operation_sub_component_details.status','=',$status)
                              ->where('ie_operation_component.status','=',$status)
                              ->where('ie_operation_sub_component_header.status','=',$status)
                              ->where('ie_operation_sub_component_header.approval_status','=',$approval_status)
                              ->where('ie_operation_component.approval_status','=',$approval_status)
                              ->where('ie_silhouette_operation_mapping_header.status','=',$status)
                              ->where('ie_silhouette_operation_mapping_header.product_silhouette_id','=',$search)
                              ->select('ie_operation_component.*','ie_operation_sub_component_header.*','ie_operation_sub_component_details.detail_id', 'ie_operation_sub_component_details.operation_name', 'ie_operation_sub_component_details.machine_type_id', 'ie_operation_sub_component_details.operation_sub_component_id', 'ie_operation_sub_component_details.cost_smv', 'ie_operation_sub_component_details.status','ie_machine_type.machine_type_name','ie_operation_sub_component_details.detail_id as sub_component_detail_id','ie_operation_component.operation_component_name as ori_operation_component_name','ie_operation_sub_component_header.operation_sub_component_name as ori_operation_sub_component_name')
                              ->get();
 $summary=GarmentOperationMaster::where('garment_operation_id','!=',"SEWING")->get();
                              return response([
                                'data' => [
                                  'details' => $detials,
                                  'summary'=>$summary,
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
        return response($this->validate_duplicate_code($request->operation_component_id , $request->operation_component_code));
      }
    }


    //check Service Type code already exists
    private function validate_duplicate_code($id , $code)
    {

      $OperationComponent = OperationComponent::where('operation_component_code','=',$code)->first();
      if($OperationComponent == null){
        return ['status' => 'success'];
      }
      else if($OperationComponent->operation_component_id == $id){
        return ['status' => 'success'];
      }
      else {
        return ['status' => 'error','message' => 'Operation Componet Code Already Exists'];
      }
    }


    //get filtered fields only
    private function list($active = 0 , $fields = null)
    {
      $query = null;
      if($fields == null || $fields == '') {
        $query = OperationComponent::select('*');
      }
      else{
        $fields = explode(',', $fields);
        $query = OperationComponent::select($fields);
        if($active != null && $active != ''){
          $query->where([['status', '=', $active]]);
        }
      }
      return $query->get();
    }

    //search Service Type for autocomplete
    private function autocomplete_search($search)
  	{
  		$operation_lists = OperationComponent::where([['operation_component_code', 'like', '%' . $search . '%'],])
       ->where('status','1')
      ->pluck('operation_component_code')
      ->toArray();
  		return  json_encode($operation_lists);
  	}


    //get searched Service Types for datatable plugin format
    private function datatable_search($data)
    {
      $start = $data['start'];
      $length = $data['length'];
      $draw = $data['draw'];
      $search = $data['search']['value'];
      $order = $data['order'][0];
      $order_column = $data['columns'][$order['column']]['data'];
      $order_type = $order['dir'];
      //dd($search);
      $list = SMVReadingHeader::join('cust_customer','ie_smv_reading_header.customer_id','=','cust_customer.customer_id')
      ->join('product_silhouette','ie_smv_reading_header.product_silhouette_id','=','product_silhouette.product_silhouette_id')
      ->select('ie_smv_reading_header.*','cust_customer.customer_name')
      ->where('product_silhouette.product_silhouette_id'  , 'like', $search.'%' )
      ->Orwhere('cust_customer.customer_name'  , 'like', $search.'%' )
      ->Orwhere('total_smv'  , 'like', $search.'%' )
      ->Orwhere('version'  , 'like', $search.'%' )
      ->orderBy($order_column, $order_type)
      ->offset($start)->limit($length)->get();

      $count = SMVReadingHeader::join('cust_customer','ie_smv_reading_header.customer_id','=','cust_customer.customer_id')
      ->join('product_silhouette','ie_smv_reading_header.product_silhouette_id','=','product_silhouette.product_silhouette_id')
       ->select('ie_smv_reading_header.*','cust_customer.customer_name')
      ->where('product_silhouette.product_silhouette_id'  , 'like', $search.'%' )
      ->Orwhere('cust_customer.customer_name'  , 'like', $search.'%' )
      ->Orwhere('total_smv'  , 'like', $search.'%' )
      ->Orwhere('version'  , 'like', $search.'%' )
      ->count();

      return [
          "draw" => $draw,
          "recordsTotal" => $count,
          "recordsFiltered" => $count,
          "data" => $list
      ];
    }


    private function handsontable_search($search){
      $garment_operation_lists = GarmentOperationMaster::where([['garment_operation_name', 'like', '%' . $search . '%'],])
      ->where('status','1')
      ->get()->pluck('garment_operation_name');
  		return  $garment_operation_lists;
    }

    public function OperationDataExport(Request $request){
      $status=1;
      $id=$request->id;
      $smvreadingheader = SMVReadingHeader::join('ie_smv_reading_details','ie_smv_reading_header.smv_reading_id','=','ie_smv_reading_details.smv_reading_id')
      ->join('ie_operation_component','ie_smv_reading_details.operation_component_id','=','ie_operation_component.operation_component_id')
                                            ->join('cust_customer','ie_smv_reading_header.customer_id','=','cust_customer.customer_id')
                                            ->join('product_silhouette','ie_smv_reading_header.product_silhouette_id','=','product_silhouette.product_silhouette_id')->select('*')->find($id);

    $subComponentsList=DB::SELECT("SELECT
	                                ie_smv_reading_details.*
                                   FROM
	                              ie_smv_reading_details
                                INNER JOIN ie_operation_sub_component_details ON ie_smv_reading_details.sub_component_detail_id = ie_operation_sub_component_details.detail_id
                                INNER JOIN ie_operation_sub_component_header ON ie_operation_sub_component_details.operation_sub_component_id = ie_operation_sub_component_header.operation_sub_component_id
                                WHERE
	                              ie_smv_reading_details.smv_reading_id = $id
                                AND ie_smv_reading_details.status = 1
                              ");

      return view('ie/operation_breakdown', array(
        'header'=>$smvreadingheader,
        'details'=>$subComponentsList
      ));
    }

}
