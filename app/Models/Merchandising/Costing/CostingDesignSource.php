<?php

namespace App\Models\Merchandising\Costing;

use Illuminate\Database\Eloquent\Model;
use App\Libraries\UniqueIdGenerator;

use App\BaseValidator;

class CostingDesignSource extends BaseValidator {

    protected $table = 'costing_design_source';
    protected $primaryKey = 'design_source_id';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';

    protected $fillable = ['design_source_id', 'design_source_name'];

    protected $rules = array(
        'design_source_name' => 'required'
    );

}
