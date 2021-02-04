<?php

namespace App\Models\Org;

use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class Supplier extends BaseValidator {

    protected $table = 'org_supplier';
    protected $primaryKey = 'supplier_id';
    public $incrementing = false;
    protected $keyType = 'string';

    const UPDATED_AT = 'updated_date';
    const CREATED_AT = 'created_date';

    protected $fillable = ['supplier_code', 'supplier_name', 'supplier_short_name', 'type_of_service', 'business_reg_no',
    'business_reg_date', 'supplier_address1', 'supplier_address2', 'supplier_city', 'supplier_postal_code', 'supplier_state',
     'supplier_county', 'supplier_contact1', 'supplier_contact2','supplier_contact3', 'supplier_email', 'supplier_map_location',
     'supplier_website', 'company_code', 'operation_start_date', 'order_destination', 'currency', 'boi_reg_no', 'boi_reg_date',
      'vat_reg_no', 'svat_no', 'managing_director_name', 'managing_director_email', 'finance_director_name', 'finance_director_email',
        'finance_director_contact', 'additional_comments', 'ship_terms_agreed', 'payemnt_terms', 'payment_mode', 'bank_acc_no', 'bank_name', 'bank_branch', 'bank_code', 'bank_swift', 'bank_iban', 'bank_contact', 'intermediary_bank_name', 'intermediary_bank_address', 'intermediary_bank_contact', 'buyer_posting_group',
        'business_posting_group', 'approved_by', 'system_updated_by', 'supplier_creation_form','supplier_country', 'status'];

    protected $dates = ['operation_start_date'];

    // protected $rules = array(
    //     'supplier_code' => 'required',
    //     'supplier_name' => 'required',
    //     'supplier_short_name' => 'required',
    //     'type_of_service' => 'required',
    //     'supplier_city' => 'required',
    //     'supplier_country' => 'required',
    //     'currency' => 'required'
    //     /*'supplier_contact1' => 'required'*/
    // );

    //Validation Functions
    /**
    *unique:table,column,except,idColumn
    *The field under validation must not exist within the given database table
    **/
    protected function getValidationRules($data) {
      return [
          'supplier_code' => [
            'required',
            'unique:org_supplier,supplier_code,'.$data['supplier_id'].',supplier_id',
          ],
          'supplier_name' => 'required',
          'supplier_short_name' => 'required',
          'type_of_service' => 'required',
          'supplier_city' => 'required',
          'supplier_country' => 'required',
          'currency' => 'required',
          'supplier_country' => 'required',
          'supplier_contact1' => 'required',
      ];
    }

    public function __construct() {
        parent::__construct();
    }

    public function setOperationStartDateAttribute($value) {
      if($value == null)
      {
      $this->attributes['operation_start_date'] = null;
      }else{
      $this->attributes['operation_start_date'] = date('Y-m-d', strtotime($value));
      }

    }

    public function setBusinessRegDateAttribute($value) {
      if($value == null)
      {
      $this->attributes['business_reg_date'] = null;
      }else{
      $this->attributes['business_reg_date'] = date('Y-m-d', strtotime($value));
      }
    }

    public function setBoiRegDateAttribute($value) {
      if($value == null)
      {
      $this->attributes['boi_reg_date'] = null;
      }else{
      $this->attributes['boi_reg_date'] = date('Y-m-d', strtotime($value));
      }
    }




		//default currency of the company
		public function currency()
		{
			 return $this->belongsTo('App\Models\Finance\Currency' , 'currency');
		}

    //country of the company
		public function country()
		{
			 return $this->belongsTo('App\Models\Org\Country' , 'supplier_country');
		}



}
