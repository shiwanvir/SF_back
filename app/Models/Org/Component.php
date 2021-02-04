<?php

namespace App\Models\Org;

use Illuminate\Database\Eloquent\Model;
use App\BaseValidator;

class Component extends BaseValidator
{
    protected $table='product_component';
    protected $primaryKey='product_component_id';
    public $incrementing = false;
    protected $keyType = 'string';

    const UPDATED_AT='updated_date';
    const CREATED_AT='created_date';

    public function __construct() {
        parent::__construct();
    }
}
