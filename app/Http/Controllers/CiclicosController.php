<?php

namespace App\Http\Controllers;

use App\CycleCount;
use Illuminate\Http\Request;
use Carbon\CarbonImmutable;
use App\Http\Resources\Inventory as InventoryResource;

class CiclicosController extends Controller{

    public function index(Request $request){
        // sleep(3);
        try {
            $view = $request->query("v");
            $store = $request->query("store");
            $now = CarbonImmutable::now();

            $from = $now->startOf($view)->format("Y-m-d H:i");
            $to = $now->endOf("day")->format("Y-m-d H:i");
            $resume = [];

            $inventories = CycleCount::with([ 'status', 'type', 'log', 'created_by' ])
                ->withCount('products')
                ->where(function($q) use($from,$to){ return $q->where([ ['created_at','>=',$from],['created_at', '<=', $to] ]); })
                ->where("_workpoint",$store)
                ->get();

            return response ()->json([
                "inventories"=>$inventories,
                "params"=>[ $from, $to, $view, $store ],
                "req"=>$request->all()
            ]);
        }  catch (\Error $e) { return response()->json($e,500); }
    }

    public function find(Request $request){
        $folio = $request->route("folio");
        $wkp = $request->query("store");

        $inventory = CycleCount::with([
                        'workpoint',
                        'created_by',
                        'type',
                        'status',
                        'responsables',
                        'log',
                        'products' => function($query) use($wkp){
                                            $query->with(['locations' => function($query) use($wkp){
                                                $query->whereHas('celler', function($query) use($wkp){
                                                    $query->where('_workpoint', $wkp);
                                                });
                                            }]);
                                        }
                    ])
                    ->where([ ["id","=",$folio], ["_workpoint","=",$wkp] ])
                    ->first();

        if($inventory){
            return response()->json([
                "inventory" => new InventoryResource($inventory),
                "params" => [$folio, $wkp]
            ]);
        }else{ return response("Not Found",404); }
    }
}
