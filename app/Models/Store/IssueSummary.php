<?php
/**
 * Created by PhpStorm.
 * User: sankap
 * Date: 5/1/2019
 * Time: 11:41 AM
 */

namespace App\Models\Store;
use App\BaseValidator;


class IssueSummary extends BaseValidator
{
    protected $table='store_issue_summary';
    protected $primaryKey='summary_id';
    const UPDATED_AT='updated_date';
    const CREATED_AT='created_date';

    //protected $fillable=['issue_id','so_no', 'cus_po','item_code','material_code', 'color','size', 'grn_no', 'qty', 'uom' ];

    protected $rules=array(
        ////'color_code'=>'required',
        //'color_name'=>'required'
    );

    public function __construct() {
        parent::__construct();
    }

    public function issue(){
        return $this->belongsTo('App\Models\Store\IssueHeader');
    }



}
