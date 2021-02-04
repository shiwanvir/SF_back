<?php

namespace App\Http\Controllers\Reports;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use PhpParser\Builder\Function_;

class CustomerPODetailsReportController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->type;
        if ($type == 'auto_divisions') {
            $search = $request->search;
            return response($this->getDivision($search));
        } elseif ($type == 'auto_users') {
            $search = $request->search;
            return response($this->getUsers($search));
        } elseif ($type == 'auto_currency') {
            $search = $request->search;
            return response($this->getCurrency($search));
        }
    }

    //search customer for autocomplete
    private function getDivision($search)
    {
        $divisions = DB::table('cust_division')
            ->select('division_id', 'division_description')
            ->where([['division_description', 'like', '%' . $search . '%'],])
            ->get();
        return $divisions;
    }

    //search users for autocomplete
    private function getUsers($search)
    {
        # code...
        $users = DB::table('usr_profile')
            ->select('user_id', 'first_name')
            ->where([['first_name', 'like', '%' . $search . '%'],])
            ->get();
        return $users;
    }

    public function getCurrency($search)
    {
        # code...
        $currency = DB::table('fin_currency')
            ->select('currency_id', 'currency_code')
            ->where([['currency_code', 'like', '%' . $search . '%'],])
            ->get();
        return $currency;
    }
}
