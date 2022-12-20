<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Requisition;
use App\WorkPoint;
use App\Product;
use Carbon\CarbonImmutable;

class LVerificatorController extends Controller{

    public function index(){
        $stores = WorkPoint::all();

        return response()->json([
            "stores"=>$stores
        ]);
    }
}
