<?php

namespace App\Http\Controllers\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreBinAllocation;
use App\Models\Finance\Item\Category;
use Illuminate\Support\Facades\DB;

class BinConfigController extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request) {
        $type = $request->type;

        if ($type == 'getBinData') {
            $binId = $request->input('bin_id');
            return response($this->getBinData($binId));
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {

        //if ($binConfig->validate($request->all())) {

        // $items = $request->all()['items'];
        // $binConfig = null;
        // foreach ($items as $item) {
        //
        //     $existsData = StoreBinAllocation::getExistRecords($request->input('bin_id'), $request->input('category_name'), $item['subCategoryId']);
        //    // print_r(isset($allocatedBin[0]));
        //     if (isset($allocatedBin[0])) {
        //         foreach ($existsData as $binConfig) {
        //
        //             $binConfig->fill($request->all());
        //             $binConfig->status = ($item['itemCheckedbox'] == true) ? 1 : 0;
        //             $binConfig->max_capacity = $item['capacity'];
        //             $binConfig->height = $item['height'];
        //             $binConfig->length = $item['length'];
        //             $binConfig->width = $item['width'];
        //             $binConfig->save();
        //         }
        //     } else {
        //         if ($item['itemCheckedbox'] == true) {
        //             $binConfig = new StoreBinAllocation();
        //
        //             $binConfig->store_bin_id = $request->input('bin_id');
        //             $binConfig->item_category_id = $request->input('category_name');
        //             $binConfig->fill($request->all());
        //             $binConfig->status = 1;
        //             $binConfig->max_capacity = $item['capacity'];
        //             $binConfig->height = $item['height'];
        //             $binConfig->length = $item['length'];
        //             $binConfig->item_subcategory_id = $item['subCategoryId'];
        //             $binConfig->width = $item['width'];
        //             $binConfig->save();
        //         }
        //     }
        // }
        //
        //
        // return response(['data' => [
        //         'message' => ' Bin Config Saved successfully',
        //         'storeBin' => $binConfig
        //     ]
        //         ], Response::HTTP_CREATED);

    }

    // private function getBinData($binId) {
    //     $allocatedArray = array();
    //     $allocatedBin = StoreBinAllocation::getAllocatedItemByBin($binId);
    //     $configured = false;
    //
    //     if (isset($allocatedBin[0])) {
    //         $configured = true;
    //         $categoryId = $allocatedBin[0]->item_category_id;
    //         $itemList = Category::getItemListByCategory($categoryId);
    //
    //         foreach ($itemList as $item) {
    //             $allocatedArray[$item->subcategory_id]['allocation_id'] = 0;
    //             $allocatedArray[$item->subcategory_id]['item_name'] = $item->subcategory_name;
    //             $allocatedArray[$item->subcategory_id]['item_category_name'] = $item->category_name;
    //             $allocatedArray[$item->subcategory_id]['max_capacity'] = 0;
    //             $allocatedArray[$item->subcategory_id]['width'] = 0;
    //             $allocatedArray[$item->subcategory_id]['height'] = 0;
    //             $allocatedArray[$item->subcategory_id]['length'] = 0;
    //             $allocatedArray[$item->subcategory_id]['item_category_id'] = $item->category_id;
    //             $allocatedArray[$item->subcategory_id]['item_subcategory_id'] = $item->subcategory_id;
    //         }
    //
    //         foreach ($allocatedBin as $aBin) {
    //             $allocatedArray[$aBin->item_subcategory_id]['allocation_id'] = $aBin->allocation_id;
    //             $allocatedArray[$aBin->item_subcategory_id]['max_capacity'] = $aBin->max_capacity;
    //             $allocatedArray[$aBin->item_subcategory_id]['width'] = $aBin->width;
    //             $allocatedArray[$aBin->item_subcategory_id]['height'] = $aBin->height;
    //             $allocatedArray[$aBin->item_subcategory_id]['length'] = $aBin->length;
    //             $allocatedArray[$aBin->item_subcategory_id]['item_category_id'] = $aBin->item_category_id;
    //             $allocatedArray[$aBin->item_subcategory_id]['item_subcategory_id'] = $aBin->item_subcategory_id;
    //         }
    //     }
    //
    //     return [
    //         'data' => [
    //             'configured' => $configured,
    //             'allocatedArray' => array_values($allocatedArray)
    //         ]
    //     ];
    //
    //
    // }

    public function save_details(Request $request){
      $lines = $request->lines;
      $bin_id = $request->bin_id;
      $zone_name = $request->zone_name;
      $rack_name = $request->rack_name;

      //dd($lines);
      for($r = 0 ; $r < sizeof($lines) ; $r++)
      {

        $check = StoreBinAllocation::where('subcategory_id'  , '=', $lines[$r]['subcategory_id'] )
        ->where('store_bin_id'  , '=', $bin_id )
        ->where('status'  , '=', 1 )
        ->count();
        if($check > 0)
          {
            $line_id = $r+1;
            $err = 'Line '.$line_id.' Already used.';
            return response([ 'data' => ['status' => 'error','message' => $err]]);
          }

        if(isset($lines[$r]['uom_code']) == '')
          {
            $line_id = $r+1;
            $err = 'Inventory UOM Line '.$line_id.' Cannot Be Empty.';
            return response([ 'data' => ['status' => 'error','message' => $err]]);

          }

          if(isset($lines[$r]['min']) == '')
          {
            $line_id = $r+1;
            $err = 'Min Qty Line '.$line_id.' Cannot Be Empty.';
            return response([ 'data' => ['status' => 'error','message' => $err]]);

          }

          if(isset($lines[$r]['max']) == '')
          {
            $line_id = $r+1;
            $err = 'Max Qty Line '.$line_id.' Cannot Be Empty.';
            return response([ 'data' => ['status' => 'error','message' => $err]]);

          }


      }

      if($lines != null && sizeof($lines) >= 1){

        for($x = 0 ; $x < sizeof($lines) ; $x++){

          $save_setails = new StoreBinAllocation();
          $save_setails->store_bin_id = $bin_id;
          $save_setails->category_id = $lines[$x]['category_id'];
          $save_setails->subcategory_id = $lines[$x]['subcategory_id'];
          $save_setails->inventory_uom = $lines[$x]['uom_code'];
          $save_setails->max_qty = $lines[$x]['max'];
          $save_setails->min_qty = $lines[$x]['min'];
          $save_setails->status = '1';
          $save_setails->save();


        }
        DB::table('org_store_bin')
            ->where('store_bin_id', $bin_id)
            ->update(['zone_name' => strtoupper($zone_name) , 'rack_name' => strtoupper($rack_name)]);

        return response([
          'data' => [
            'status' => 'success',
            'message' => 'Saved Successfully.',
          ]
        ] , 200);





      }







    }

    public function load_details(Request $request){

      $bin_id = $request->bin_id;
      $load_list = StoreBinAllocation::select('*')
        ->join('item_category', 'item_category.category_id', '=', 'org_store_bin_allocation.category_id')
        ->join('item_subcategory', 'item_subcategory.subcategory_id', '=', 'org_store_bin_allocation.subcategory_id')
        ->where('org_store_bin_allocation.status'  , '=', 1 )
        ->where('store_bin_id'  , '=', $bin_id )
        ->get();

      $load_header = DB::table('org_store_bin')
          ->select('*')
          ->where('store_bin_id', $bin_id)
          ->get();

      return response([ 'data' => [ 'load_list' => $load_list,'load_header' => $load_header,'count' => sizeof($load_list)]], Response::HTTP_CREATED );
    }

    public function delete_details(Request $request){

        $line_id = $request->line_id;

        DB::table('org_store_bin_allocation')
            ->where('allocation_id', $line_id)
            ->update(['status' => 0]);

        return response([ 'data' => ['status' => 'succes','message' => 'Line Deactivated Successfully']]);

    }







}
