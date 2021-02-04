<?php
namespace App\Libraries;
use Illuminate\Support\Facades\DB;
use App\Models\Org\ConversionFactor;
use App\Models\Org\UOM;
use App\Models\Store\Store;
use App\Models\Store\SubStore;
use App\Models\Store\StoreBin;
class InventoryValidation{


  public static function inspection_status_validation($data){
      foreach ($data as  $value) {
      $status=DB::table('store_inspec_status')->where('status_name','=',$value['inspection_status'])->first();
      if($status==null)
      return false;
    }
    return true;
  }

  public static function conversion_factor_validation($from_uom,$to_uom){
  if($from_uom==$to_uom){
    return true;
  }
  $_uom_unit_code=UOM::where('uom_id','=',$from_uom)->pluck('uom_code');
  $_uom_base_unit_code=UOM::where('uom_id','=',$to_uom)->pluck('uom_code');
  $ConversionFactor=ConversionFactor::select('*')
  ->where('unit_code','=',$_uom_unit_code[0])
  ->where('base_unit','=',$_uom_base_unit_code[0])
  ->first();
  if($ConversionFactor==null){
    return false;
  }
  else if($ConversionFactor!=null){
    return true;
  }
}

public static function store_sub_store_bin_validation($type,$name)
{
if($type=="STORE"){
  $store_name=Store::where('store_name','=',$name)->first();
  if($store_name==null)
  return false;
  else if($store_name!=null)
  return true;
  }
else if($type=="SUB_STORE"){
$sub_store_name=SubStore::where('substore_name','=',$name)->first();
if($sub_store_name==null)
  return false;

 else if($sub_store!=null)
  return true;
}
else if($type=="BIN"){
  $bin_name=StoreBin::where('store_bin_name','=',$name)->first();
  if($bin_name==null)
  return false;
  else if($bin_name!= null)
  return true;

 }

}

}
