<?php

namespace App\Http\Controllers\Reports;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

use App\Models\App\ProcessApproval;
use App\Models\App\ProcessApprovalStage;
use App\Models\App\ProcessApprovalStageUser;
use App\Libraries\Approval;

class ApprovalsController extends Controller
{
    public function index(Request $request)
    {
      $type = $request->type;
      if($type == 'datatable') {
        $data = $request->all();
        return response($this->datatable_search($data));
      }
    }

    private function datatable_search($data)
    {
      // if($this->authorize->hasPermission('COUNTRY_VIEW'))//check permission
      // {
        $start = $data['start'];
        $length = $data['length'];
        $draw = $data['draw'];
        $search = $data['search']['value'];
        $order = $data['order'][0];
        $order_column = $data['columns'][$order['column']]['data'];
        $order_type = $order['dir'];
        $user_id = auth()->user()->user_id;

        $country_list = ProcessApprovalStageUser::select('app_process_approval_stages.id','app_process_approval_stages.stage_order',
        'app_process_approval_stage_users.user_id', 'usr_login.user_name', 'app_process_approval_stage_users.approval_date',
        'app_process_approval_stage_users.status', 'app_process_approval.process', 'app_process_approval.document_id')
        ->join('app_process_approval_stages', 'app_process_approval_stages.id', '=', 'app_process_approval_stage_users.approval_stage_id')
        ->join('app_process_approval', 'app_process_approval.id', '=', 'app_process_approval_stages.approval_id')
        ->join('usr_login', 'app_process_approval_stage_users.user_id', '=', 'usr_login.user_id')
        ->where('app_process_approval_stage_users.status', '=', 'PENDING' )
        ->where('app_process_approval_stages.status', '=', 'PENDING' )
        ->where('app_process_approval.status', '=', 'PENDING' )
        ->where('app_process_approval_stage_users.user_id', $user_id)
        ->where(function($q) use($search) {
        $q->where('app_process_approval.document_id'  , 'like', $search.'%' )
        ->Orwhere('app_process_approval.process'  , 'like', $search.'%' );
          })
        ->orderBy($order_column, $order_type)
        ->offset($start)->limit($length)->get();

        $country_count = ProcessApprovalStageUser::join('app_process_approval_stages', 'app_process_approval_stages.id', '=', 'app_process_approval_stage_users.approval_stage_id')
        ->join('app_process_approval', 'app_process_approval.id', '=', 'app_process_approval_stages.approval_id')
        ->join('usr_login', 'app_process_approval_stage_users.user_id', '=', 'usr_login.user_id')
        ->where('app_process_approval_stage_users.status', '=', 'PENDING' )
        ->where('app_process_approval_stages.status', '=', 'PENDING' )
        ->where('app_process_approval.status', '=', 'PENDING' )
        ->where('app_process_approval_stage_users.user_id', $user_id)
        ->Orwhere('app_process_approval.document_id'  , 'like', $search.'%' )
        ->Orwhere('app_process_approval.process'  , 'like', $search.'%' )
        ->count();

        return [
            "draw" => $draw,
            "recordsTotal" => $country_count,
            "recordsFiltered" => $country_count,
            "data" => $country_list
        ];
      // }
      // else{
      //   return response($this->authorize->error_response(), 401);
      // }
    }


    public function store(Request $request){
      $approval_id = $request->approval_id;
      $user_id = auth()->user()->user_id;
      $status = $request->status;
      $approval_remark = '';
      //changeed
      $approval_stage_user = ProcessApprovalStageUser::where('approval_stage_id','=',$approval_id)->first();
      //dd($approval_stage_user);
      if($approval_stage_user == null || $user_id == null){
        return response([
          'data' => [
            'status' => 'error',
            'message' => 'Incorrect details'
          ]
        ]);
      }
      //echo json_encode($approval_id);die();
      //chek email replied user is same as approval user
      if($user_id != null && $user_id == $approval_stage_user->user_id) {
        $approval = new Approval();

        $response = $approval->approve($approval_id, $status, $approval_remark, $user_id);
        if($response == true){
          return response([
            'data' => [
              'status' => 'success',
              'message' => 'Request approved successfully'
            ]
          ]);
        }
        else {
          return response([
            'data' => [
              'status' => 'error',
              'message' => 'Error occured while approving document'
            ]
          ]);
        }
      }
    }

}
