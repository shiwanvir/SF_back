<?php

namespace App\Libraries;

use Illuminate\Support\Facades\DB;

class UniqueIdGenerator
{

  public static function generateUniqueId($type , $company_id)
  {
      $unque_id = DB::transaction(function () use ($type , $company_id){

          $id = 0;
          if($company_id == null || $company_id == 0){ //for master data
            DB::table('unique_id_generator')
            ->where([ ['process_type' , '=' , $type]])
            ->increment('unque_id', 1);

            $id = DB::table('unique_id_generator')->where([['process_type' , '=' , $type]])
            ->sharedLock()
            ->value('unque_id');
            //$prefix=DB::table('unique_id_generator')->where([['process_type' , '=' , $type]])->value('prefix_code');
            //$id=$prefix.$id;
              }
          else {
            DB::table('unique_id_generator')
            ->where([ ['process_type' , '=' , $type] , ['company' , '=' , $company_id] ])
            ->increment('unque_id', 1);

            $id = DB::table('unique_id_generator')->where([ ['process_type' , '=' , $type] , ['company' , '=' , $company_id] ])
            ->sharedLock()
            ->value('unque_id');
            //$prefix=DB::table('unique_id_generator')->where([['process_type' , '=' , $type], ['company' , '=' , $company_id] ])->value('prefix_code');
            //$id=$prefix.$id;
          }
          //dd($id);
          return $id;
      });
      //dd($unque_id);
      return $unque_id;
  }

}
