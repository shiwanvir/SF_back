<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests;
use App\Http\Controllers\Controller;
//use App\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Models\Admin\NotificationAssign;
use App\Models\Admin\UsrProfile;

class NotificationAssignController extends Controller {

    public function __construct() {
        //add functions names to 'except' paramert to skip authentication
        $this->middleware('jwt.verify', ['except' => ['index']]);
    }


    public function index(Request $request)
    {
      $type = $request->type;

      if($type == 'types'){
        return response([
          'data' => $this->get_notification_types()
        ]);
      }
      else if($type == 'assigned_users'){
        $notification_type = $request->notification_type;
        return response([
          'data' => $this->get_assigned_users($notification_type)
        ]);
      }
    }

      //get filtered fields only
    private function list()
    {
      return PermissionCategory::orderBy('description', 'ASC')->get();
    }



    //get searched goods types for datatable plugin format
    private function datatable_search($data)
    {
      /*$start = $data['start'];
      $length = $data['length'];
      $draw = $data['draw'];
      $search = $data['search']['value'];
      $order = $data['order'][0];
      $order_column = $data['columns'][$order['column']]['data'];
      $order_type = $order['dir'];

      $permission_list = Permission::select('*')
      ->where('name'  , 'like', $search.'%' )
      ->orderBy($order_column, $order_type)
      ->offset($start)->limit($length)->get();

      $permission_count = Permission::where('name'  , 'like', $search.'%' )
      ->count();

      return [
          "draw" => $draw,
          "recordsTotal" => $permission_count,
          "recordsFiltered" => $permission_count,
          "data" => $permission_list
      ];*/
    }


    public function store(Request $request) {
      $data = new NotificationAssign();
      if($data->validate($request->all()))
      {
        //chek already exists
        $count = NotificationAssign::where('type', '=', $request->type)->where('user_id', '=', $request->user_id)->count();
        if($count > 0){
          return response([ 'data' => [
            'status' => 'error',
            'message' => 'User already assigned'
            ]
          ]);
        }

        $data->fill($request->all());
        $data->save();

        return response([ 'data' => [
          'status' => 'success',
          'message' => 'User assigned successfully',
          'users' => $this->get_assigned_users($data->type)
          ]
        ], Response::HTTP_CREATED );
      }
      else
      {
        $errors = $data->errors();// failure, get errors
        $errors_str = $data->errors_tostring();
        return response(['errors' => ['validationErrors' => $errors, 'validationErrorsText' => $errors_str]], Response::HTTP_UNPROCESSABLE_ENTITY);
      }
    }


    public function show($id) {

    }


    public function edit($id) {

    }

    public function update(Request $request, $id) {

    }


    public function destroy($id) {
        $assigned_user = NotificationAssign::find($id);
        NotificationAssign::where('id', '=', $id)->delete();

        return response([
          'data' => [
            'message' => 'User was removed successfully.',
            'users' => $this->get_assigned_users($assigned_user->type),
            'status' => 'success'
          ]
        ]);      
    }


    //private functions ........................................................

    private function get_notification_types(){
      $list = DB::table('app_notification_types')->get();
      return $list;
    }


    private function get_assigned_users($type){
      $users = NotificationAssign::join('usr_profile', 'usr_profile.user_id', '=', 'app_notification_assign.user_id')
      ->join('usr_login', 'usr_login.user_id', '=', 'usr_profile.user_id')
      ->select('app_notification_assign.id', 'usr_profile.*', 'usr_login.user_name')
      ->where('app_notification_assign.type', '=', $type)
      ->get();
      return $users;
    }

}
