<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Requisition;
use Carbon\CarbonImmutable;

class LRestockController extends Controller{

    public function index(Request $request){
        try {
            $view = $request->query("v");

            $now = CarbonImmutable::now();

            $from = $now->startOf($view)->format("Y-m-d H:i");
            $to = $now->endOf("day")->format("Y-m-d H:i");

            $orders = Requisition::with(['type', 'status', 'to', 'from', 'created_by', 'log'])
                ->withCount(["products"])
                ->whereBetween("created_at",[$from,$to])
                ->get();

            return response()->json([
                "view"=>$view,
                "orders"=>$orders,
                "from"=>$from,
                "to"=>$to,
            ]);
        } catch (\Error $e) { return response()->json($e,500); }
    }

    public function order(Request $request){
        $id = $request->route("oid");

        try {
            $order = Requisition::with([
                        'type',
                        'status',
                        'log',
                        'from',
                        'products' => function($query){
                            $query->selectRaw('
                                        products.*,
                                        getSection(products._category) AS section,
                                        getFamily(products._category) AS family,
                                        getCategory(products._category) AS category
                                    ')->with([
                                        'units',
                                        'variants',
                                        'stocks' => function($q){ return $q->whereIn('_workpoint', [1,2]); },
                                        'locations' => fn($qq) => $qq->whereHas('celler', function($qqq){ $qqq->where('_workpoint', 1); }),
                                    ]);
                            }
                    ])->findOrFail($id);

            return response()->json($order);
        } catch (\Error $e) { return response()->json($e,500); }
    }

    public function orderLocs(Request $request){
        $id = $request->route("oid");

        $order = Requisition::with([
            'status',
            'log',
            'products' => fn($q) => $q
                ->selectRaw('
                    products.*,
                    getSection(products._category) AS section,
                    getFamily(products._category) AS family,
                    getCategory(products._category) AS category
                ')->with([
                    'locations' => fn($qq) => $qq->whereHas('celler', function($qqq){ $qqq->where('_workpoint', 1); }),
                    'stocks' => fn($q) => $q->whereIn('_workpoint', [1,2]),
                    'units',
                    'variants'
                ])
        ])->find($id);

        return response()->json($order);
    }

    public function changestate(Request $request){
        try {
            $oid = $request->id;
            $moveTo = $request->state;
            $requisition = Requisition::with(["to", "from", "log", "status", "created_by"])->find($oid);
            $cstate = $requisition->_status;
            $now = CarbonImmutable::now();
            $prevstate = null;

            /**
             * mover de "POR SURTIR (2)" a "SURTIENDO (3)" ||
             * mover de "SURTIENDO (3)" a "Por Enviar (6)"
             *
            */
            if(($cstate==2&&$moveTo==3) || ($cstate==3&&$moveTo==6)){

                $logs = $requisition->log->toArray();
                $end = end($logs);
                $prevstate = $end['pivot']['_status'];

                $prevstate ? $requisition->log()->syncWithoutDetaching([$prevstate => [ 'updated_at' => $now->format("Y-m-m H:m:s")]]) : null;

                $requisition->log()->attach($moveTo, [ 'details'=>json_encode([ "responsable"=>"VizApp" ]) ]);
                $requisition->_status=$moveTo; // se actualiza el status del pedido
                $requisition->save(); // se guardan los cambios
                $requisition->fresh(['log']); // se refresca el log del pedido

                return response()->json($requisition);
            }else{ return response()->json("El status $cstate no puede cambiar a $moveTo",400); }
        } catch (\Error $e) { return response()->json($e,500); }
    }

    public function setdelivery(Request $request){
        $oid = $request->order;
        $product = $request->product;
        $delivery = $request->delivery;
        $ipack = $request->ipack;
        $checkout = $request->checkout;

        $requisition = Requisition::findOrFail($oid);
        $cstate = $requisition->_status;

        $updateCols = $checkout ? [ "toDelivered"=>$delivery, "ipack"=>$ipack, "checkout"=>1 ] : [ "toDelivered"=>$delivery, "ipack"=>$ipack ];

        if($cstate==3){
            $setted = DB::table('product_required')
                    ->where([ ["_requisition",$oid],["_product",$product] ])
                    ->update($updateCols);

            return response()->json([
                "order" => $requisition,
                "product" => $product,
                "setted" => $setted
            ]);
        }else{ return response("El status actual de esta orden no permite modificaciones (orderState: $cstate)",400); }
    }

    public function setreceived(Request $request){
        $oid = $request->order;
        $product = $request->product;
        $received = $request->received;
        $ipack = $request->ipack;
        $checkout = $request->checkout;

        $requisition = Requisition::findOrFail($oid);
        $cstate = $requisition->_status;

        $updateCols = [ "toReceived"=>$received ];

        if($cstate==9){
            $setted = DB::table('product_required')
                    ->where([ ["_requisition",$oid],["_product",$product] ])
                    ->update($updateCols);

            return response()->json([
                "order" => $requisition,
                "product" => $product,
                "setted" => $setted
            ]);
        }else{ return response("El status actual de esta orden no permite modificaciones (orderState: $cstate)",400); }
    }

    public function newinvoice(Request $request){
        try {
            $oid = $request->route("oid");
            $resp = $this->accessGenInvoice($oid);

            if($resp["error"]){
                return response()->json($resp["error"],500);
            }else{
                $requisition = Requisition::with(["to", "from", "log", "status", "created_by"])->find($oid);
                $now = CarbonImmutable::now();
                $prevstate = null;

                $logs = $requisition->log->toArray();
                $end = end($logs);
                $prevstate = $end['pivot']['_status'];
                $prevstate ? $requisition->log()->syncWithoutDetaching([$prevstate => [ 'updated_at' => $now->format("Y-m-m H:m:s")]]) : null;
                $requisition->log()->attach(7, [ 'details'=>json_encode([ "responsable"=>"VizApp" ]) ]);
                $requisition->_status=7; // se actualiza el status del pedido
                $requisition->entry_key = md5($requisition->id);
                $requisition->save(); // se guardan los cambios
                $requisition->fresh(['log']); // se refresca el log del pedido

                return response()->json(["invoice"=>$resp['done'], "requisition"=>$requisition]);
            }
        } catch (\Error $e) { return response()->json($e, 500); }
    }

    public function newentry(Request $request){
        try {
            $oid = $request->route("oid");
            $requisition = Requisition::with(["from"])->find($oid);
            $cstate = $requisition->_status;

            if($cstate==9){
                $ip = $requisition->from["dominio"];
                $resp = $this->accessGenEntry($oid, $ip);

                if($resp["error"]){
                    return response()->json($resp["error"],500);
                }else{
                    $now = CarbonImmutable::now();
                    $prevstate = null;

                    $logs = $requisition->log->toArray();
                    $end = end($logs);
                    $prevstate = $end['pivot']['_status'];
                    $prevstate ? $requisition->log()->syncWithoutDetaching([$prevstate => [ 'updated_at' => $now->format("Y-m-m H:m:s")]]) : null;
                    $requisition->log()->attach(10, [ 'details'=>json_encode([ "responsable"=>"VizApp" ]) ]);
                    $requisition->_status=10; // se actualiza el status del pedido
                    $requisition->save(); // se guardan los cambios
                    $requisition->fresh(['log']); // se refresca el log del pedido

                    return response()->json(["invoice"=>$resp['done'], "requisition"=>$requisition]);
                }
                return response()->json([ "order" => $resp ]);
            }else{ return response("El status actual de esta orden no permite generar entrada (orderState: $cstate)",400); }
        } catch (\Error $e) { return response()->json($e, 500); }
    }



    private function accessGenInvoice($oid){
        $data = json_encode([ "id"=>$oid ]);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, env("URL_INVOICE")."/access/public/Received/Received");
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));

        $exec = json_decode(curl_exec($curl));
        $info = curl_getinfo($curl);

        return curl_errno($curl) ? [ "error"=>curl_error($curl) ] : [ "error"=>false, "done"=>$exec, "info"=>$info ];

        curl_close($curl);
    }

    private function accessGenEntry($oid,$ip){
        $data = json_encode([ "id"=>$oid ]);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "http://$ip/access/public/Required/Required");
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));

        $exec = json_decode(curl_exec($curl));
        $info = curl_getinfo($curl);

        return curl_errno($curl) ? [ "error"=>curl_error($curl) ] : [ "error"=>false, "done"=>$exec, "info"=>$info ];

        curl_close($curl);
    }

    public function printKey(Request $request){
        $oid = $request->oid;

        $req = Requisition::with(["from"])->findOrFail($oid);

        $key = $req->entry_key;
        $id = $req->id;
        $store = $req->from["alias"];

        $printer = new MiniPrinterController("192.168.10.176", 9100);
        $printed = $printer->printKey($id, $store, $key);

        return response()->json($printed);
    }

    public function checkin(Request $request){
        try {
            $oid = $request->oid;
            $key = $request->key;

            $order = Requisition::with([
                        'type',
                        'status',
                        'log',
                        'from',
                        'products' => function($query){
                            $query->selectRaw('
                                        products.*,
                                        getSection(products._category) AS section,
                                        getFamily(products._category) AS family,
                                        getCategory(products._category) AS category
                                    ')->with([
                                        'units',
                                        'variants',
                                        // 'stocks' => function($q){ return $q->whereIn('_workpoint', [1,2]); },
                                        // 'locations' => fn($qq) => $qq->whereHas('celler', function($qqq){ $qqq->where('_workpoint', 1); }),
                                    ]);
                            }
                    ])->where([ ["id",$oid],["entry_key", $key] ])->first();

            if($order){
                return response()->json([ "order"=>$order ]);
            }else{ return response("Sin coincidencias para el folio o llave invalida!",404); }
        } catch (\Error $e) { return response()->json($e,500); }
    }

    public function checkininit(Request $request){
        try {
            $oid = $request->oid;
            $key = $request->key;
            $req = Requisition::with(["log"])->where([ ["id",$oid],["entry_key", $key] ])->first();

            if($req){
                $cstate = $req->_status;

                if($cstate==7){
                    $now = CarbonImmutable::now();
                    $prevstate = null;
                    $logs = $req->log->toArray();
                    $end = end($logs);
                    $prevstate = $end['pivot']['_status'];
                    $prevstate ? $req->log()->syncWithoutDetaching([$prevstate => [ 'updated_at' => $now->format("Y-m-m H:m:s")]]) : null;
                    $req->log()->attach(9, [ 'details'=>json_encode([ "responsable"=>"VizApp" ]) ]);
                    $req->_status=9; // se actualiza el status del pedido
                    $req->save(); // se guardan los cambios
                    $req->fresh(['log']); // se refresca el log del pedido

                    return response()->json([ "req"=>$req ]);
                }else{ return response("El status actual del pedido ($cstate), no permite iniciar el conteo",400); }
            }else{ return response("Sin coincidencias para el folio o llave invalida!",404); }
        } catch (\Error $e) { return response()->json($e,500); }
    }
}
