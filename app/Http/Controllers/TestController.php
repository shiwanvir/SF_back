<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Mockery\CountValidator\Exception;

use Illuminate\Support\Facades\Mail;
use App\Mail\MailSenderMailable;
use App\Jobs\MailSendJob;

use DB;

class TestController extends Controller {

    public function index() {

    }

    public function auth(Request $request) {
        return json_encode(array(
          'status' => 'success',
          'message' => 'You successfully loged In'
        ));
    }


    public function send_mail(){
      /*$data = [
        'to' => [['email' => 'chamilap@helaclothing.com'],['email' => 'chamilamacp@gmail.com']],
        'mail_data' => [
          'header_title' => 'Test',
          'body' => 'This is a sample email'
        ]
      ];
      $job = new MailSendJob($data);
      dispatch($job);*/
    //  MailSendJob::dispatch();
    //return view('email.email');
    $sql = "SELECT
      app_process_approval_stages.id,
      app_process_approval_stages.stage_order,
      app_process_approval_stage_users.user_id,
      usr_login.user_name,
      app_process_approval_stage_users.approval_date,
      app_process_approval_stage_users.`status`

      FROM app_process_approval_stage_users
      INNER JOIN app_process_approval_stages ON app_process_approval_stages.id = app_process_approval_stage_users.approval_stage_id
      INNER JOIN usr_login ON app_process_approval_stage_users.user_id = usr_login.user_id
      WHERE app_process_approval_stages.approval_id = ? AND app_process_approval_stage_users.`status` != 'PENDING'";
    $data = DB::select($sql, [19]);
    echo json_encode($data[0]->user_name);
    }



    public function test(){
      $po_data = DB::table('merc_po_order_header')
      ->join('usr_login', 'usr_login.user_id', '=', 'merc_po_order_header.created_by')
      ->select('merc_po_order_header.*', 'usr_login.user_name')->where('po_id', 51)->first();

      $po_lines = DB::table('merc_po_order_details')
      ->join('item_master', 'item_master.master_id', '=', 'merc_po_order_details.item_code')
      ->leftjoin('org_color', 'org_color.color_id', 'merc_po_order_details.colour')
      ->leftjoin('org_uom', 'org_uom.uom_id', 'merc_po_order_details.uom')
      ->where('merc_po_order_details.po_header_id', '=', 51)
      ->select('merc_po_order_details.*', 'item_master.master_code', 'item_master.master_description',
      'org_color.color_code', 'org_color.color_name', 'org_uom.uom_code')->get();
//echo json_encode($po_lines);die();
        $to_users = DB::select("SELECT usr_profile.email, '' AS name FROM app_notification_assign
        INNER JOIN usr_profile ON usr_profile.user_id = app_notification_assign.user_id
        WHERE app_notification_assign.type = 'PO CONFIRM'");

        $data = [
          'type' => 'PO_CONFIRM',
          'data' => [
            'po' => $po_data,
            'po_lines' => $po_lines
          ],
          'mail_data' => [
            'subject' => 'Purchase Order Notification',
            'to' => $to_users
          ]
        ];
        $job = new MailSendJob($data);//dispatch mail to the queue
        dispatch($job);

    }


    public function test1(){
      /*$style_data = DB::table('style_creation')
          ->join('usr_profile', 'usr_profile.user_id', '=', 'style_creation.created_by')
          ->join('usr_login', 'usr_login.user_id', '=', 'style_creation.created_by')
          ->select('style_creation.*', 'usr_profile.first_name', 'usr_login.user_name', DB::raw('DATE_ADD("style_creation.created_date", INTERVAL 10 DAY) AS due_date'))
          ->where('style_creation.style_id', '=', 1)
          ->first();
          echo json_encode($style_data);*/
          $html = '';
          Mail::send([], [], function ($message) use ($html) {
  $message->to('chamilap@helaclothing.com')
    ->subject('Test')
    ->from('intsys@helaclothing.com', 'Company name')
    ->setBody($html, 'text/html');
});
    }



}
