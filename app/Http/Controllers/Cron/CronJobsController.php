<?php

namespace App\Http\Controllers\Cron;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Mockery\CountValidator\Exception;

use Illuminate\Support\Facades\Mail;
use App\Mail\MailSenderMailable;
use App\Jobs\MailSendJob;

use DB;

class CronJobsController extends Controller {

    public function index() {

    }

    public function material_and_item_creation_report(){
         $to_users = DB::select("SELECT usr_profile.email, '' AS name FROM app_notification_assign
         INNER JOIN usr_profile ON usr_profile.user_id = app_notification_assign.user_id
         WHERE app_notification_assign.type = 'MATERIALS AND ITEMS'");

         $data = [
           'type' => 'MATERIALS_AND_ITEMS_REPORT',
           'data' => [
             'items_count' => 100
           ],
           'mail_data' => [
             'subject' => 'New Materials & Items Generated',
             'to' => $to_users
           ]
         ];
         $job = new MailSendJob($data);//dispatch mail to the queue
         dispatch($job);
         echo 'Success';
    }


    public function smv_added_to_style_report(){
         $to_users = DB::select("SELECT usr_profile.email, '' AS name FROM app_notification_assign
         INNER JOIN usr_profile ON usr_profile.user_id = app_notification_assign.user_id
         WHERE app_notification_assign.type = 'SMV CREATE'");

         $data = [
           'type' => 'SMV_CREATE_REPORT',
           'data' => [
           ],
           'mail_data' => [
             'subject' => 'SMV Added to Style',
             'to' => $to_users
           ]
         ];

         $job = new MailSendJob($data);//dispatch mail to the queue
         dispatch($job);
         echo 'Success';
    }


    /*public function costing_for_review_report(){
         $to_users = DB::select("SELECT usr_profile.email, '' AS name FROM app_notification_assign
         INNER JOIN usr_profile ON usr_profile.user_id = app_notification_assign.user_id
         WHERE app_notification_assign.type = 'COSTING REVIEW'");

         $data = [
           'type' => 'COSTING_REVIEW_REPORT',
           'data' => [
           ],
           'mail_data' => [
             'subject' => 'Costing for Review',
             'to' => $to_users
           ]
         ];
         $job = new MailSendJob($data);//dispatch mail to the queue
         dispatch($job);
         echo 'Success';
    }*/

}
