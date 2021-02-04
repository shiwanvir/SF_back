<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class NotificationAssign extends BaseValidator
{
    protected $table='app_notification_assign';
    protected $primaryKey='id';
    const UPDATED_AT='updated_date';
    const CREATED_AT='created_date';

    protected $fillable = ['type','user_id'];

    //Validation Functions
    /**
    *unique:table,column,except,idColumn
    *The field under validation must not exist within the given database table
    **/
    protected function getValidationRules($data) {
      return [
          'type' => ['required'],
          'user_id' => ['required']
      ];
    }

    public function __construct()
    {
        parent::__construct();
    }
}
