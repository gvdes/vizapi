<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Requisition;
use App\RequisitionPartition;
use App\WorkPoint;
use App\Product;
use Carbon\CarbonImmutable;
use App\Http\Resources\Requisition as RequisitionResource;
use App\RequisitionProcess as Process;
use App\Http\Resources\ProductRequired as ProductResource;
use App\Account;
use Carbon\Carbon;


class LRestockController extends Controller{

    public function index(Request $request){
        try {
            $view = $request->query("v");
            $dash = $request->query("d");
            $now = CarbonImmutable::now();

            $from = $now->startOf($view)->format("Y-m-d H:i");
            $to = $now->endOf("day")->format("Y-m-d H:i");
            $resume = [];

            $query = Requisition::with(['type', 'status', 'to', 'from', 'created_by', 'log', 'partition.status', 'partition.log'])
                ->withCount(["products"])
                ->whereBetween("created_at",[$from,$to]);

            switch ($dash) {
                case 'P3': case 'p3': $query->whereIn("_workpoint_from", [1,2,3,4,5,6,7,9,10,12,14,17]); break;
                case 'P2': case 'p2': $query->whereIn("_workpoint_from", [8,11,19]); break;
                case 'SAP': case 'sap': $query->whereIn("_workpoint_to", [1]); break;
                case 'BOL': case 'bol': $query->whereIn("_workpoint_to", [13]); break;
                case 'TEX': case 'tex': $query->whereIn("_workpoint_to", [2]); break;
                default: break;
            }

            $orders = $query->get();

            $pdss = DB::select(
                    "SELECT
                        COUNT(PS._product) as total
                    FROM product_stock PS
                    WHERE
                        PS._status=1 AND
                        PS._workpoint=1 AND
                        PS.stock=0 AND (SELECT sum(stock) FROM product_stock WHERE _workpoint=2 and _product=PS._product)=0; ");

            $pndcs = Product::whereHas("stocks", function($qb){
                    $qb->whereIn("_workpoint",[1,2,13])
                        ->whereNotIn("_status",[1,4])
                        ->where("stock",">",0);
                    })->count();
            $resume[] = [ "key"=>"pdss", "name"=>"Productos disponibles sin stock", "total"=>$pdss[0]->total ];
            $resume[] = [ "key"=>"pndcs", "name"=>"Productos no disponibles con stock", "total"=>$pndcs ];

            $printers = WorkPoint::with("printers")->whereIn("id",[1,2,13])->get();

            return response()->json([
                "view"=>$view,
                "orders"=>$orders,
                "from"=>$from,
                "to"=>$to,
                "resume"=>$resume,
                "printers"=>$printers,
                "dash"=>$dash
            ]);
        } catch (\Error $e) { return response()->json($e,500); }
    }

    public function orderFresh(Request $request){
        $id = $request->route("oid");
        $order = Requisition::with(['type', 'status', 'to', 'from', 'created_by', 'log','partition.status','partition.log',
        'partition.products'])
            ->withCount(["products"])
            ->find($id);

        return response()->json(["oid"=>$id,"order"=>$order]);
    }

    public function order(Request $request){
        $id = $request->route("oid");

        try {
            $order = Requisition::with([
                        'type',
                        'status',
                        'log',
                        'from',
                        'to',
                        'partition.status',
                        'partition.log',
                        'partition.products',
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
            $requisition = Requisition::with(["to", "from", "log", "status", "created_by","partition.status","partition.log","type"])->find($oid);
            $cstate = $requisition->_status;
            $now = CarbonImmutable::now();
            $prevstate = null;

            /**
             * mover de "POR SURTIR (2)" a "SURTIENDO (3)" ||
             * mover de "SURTIENDO (3)" a "Por Enviar (6)"
             *
            */
            if(($cstate==2&&$moveTo==3) || ($cstate==3&&$moveTo==4) || ($cstate==4&&$moveTo==5) || ($cstate==5&&$moveTo==6) || ($cstate==6&&$moveTo==7) || ($cstate==7&&$moveTo==8) || ($cstate==8&&$moveTo==9) || ($cstate==9&&$moveTo==10)  || ($cstate==2&&$moveTo==100)){

                $logs = $requisition->log->toArray();
                $end = end($logs);
                $prevstate = $end['pivot']['_status'];

                $prevstate ? $requisition->log()->syncWithoutDetaching([$prevstate => [ 'updated_at' => $now->format("Y-m-m H:m:s")]]) : null;

                $requisition->log()->attach($moveTo, [ 'details'=>json_encode([ "responsable"=>"VizApp" ]) ]);
                $requisition->_status=$moveTo; // se actualiza el status del pedido
                $requisition->save(); // se guardan los cambios
                $requisition->load(['log','status']); // se refresca el log del pedido

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

        if($cstate ==3 || $cstate ==4 || $cstate ==5 || $cstate ==9 ){
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

        $requisition = RequisitionPartition::findOrFail($oid);
        $cstate = $requisition->_status;

        $updateCols = [ "toReceived"=>$received ];

        if($cstate==9){
            $setted = DB::table('product_required')
                    ->where([ ["_partition",$oid],["_product",$product] ])
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
            $supply = $request->route("supply");
            $resp = $this->accessGenInvoice($oid,$supply);
            if($resp["error"]){
                return response()->json($resp["error"],500);
            }else{
                if($resp["httpcode"]==201){
                    $requisition = RequisitionPartition::where([['_requisition',$oid],['_suplier_id',$supply]])->first();
                    // $now = CarbonImmutable::now();
                    // $prevstate = null;

                    // $logs = $requisition->log->toArray();
                    // $end = end($logs);
                    // $prevstate = $end['pivot']['_status'];
                    // $prevstate ? $requisition->log()->syncWithoutDetaching([$prevstate => [ 'updated_at' => $now->format("Y-m-m H:m:s")]]) : null;
                    // $requisition->log()->attach(7, [ 'details'=>json_encode([ "responsable"=>"VizApp" ]) ]);
                    $requisition->_status=6; // se actualiza el status del pedido
                    $requisition->entry_key = md5($requisition->id);
                    $requisition->save(); // se guardan los cambios
                    $requisition->fresh(); // se refresca el log del pedido
                    return response()->json(["invoice"=>$resp['done'], "requisition"=>$requisition]);
                }else{ return response()->json($resp["done"],$resp["httpcode"]); }
            }
        } catch (\Error $e) { return response()->json($e->getMessage(), 500); }
    }

    public function newTransfer(Request $request){
        try {
            $oid = $request->route("oid");
            $supply = $request->route("supply");
            $resp = $this->accessGenTransfer($oid,$supply);
            if($resp["error"]){
                return response()->json($resp["error"],500);
            }else{
                if($resp["httpcode"]==201){
                    $requisition = RequisitionPartition::where([['_requisition',$oid],['_suplier_id',$supply]])->first();
                    // $now = CarbonImmutable::now();
                    // $prevstate = null;

                    // $logs = $requisition->log->toArray();
                    // $end = end($logs);
                    // $prevstate = $end['pivot']['_status'];
                    // $prevstate ? $requisition->log()->syncWithoutDetaching([$prevstate => [ 'updated_at' => $now->format("Y-m-m H:m:s")]]) : null;
                    // $requisition->log()->attach(7, [ 'details'=>json_encode([ "responsable"=>"VizApp" ]) ]);
                    $requisition->_status=6; // se actualiza el status del pedido
                    $requisition->entry_key = md5($requisition->id);
                    $requisition->save(); // se guardan los cambios
                    $requisition->fresh(); // se refresca el log del pedido
                    return response()->json(["transfer"=>$resp['done'], "requisition"=>$requisition]);
                }else{ return response()->json($resp["done"],$resp["httpcode"]); }
            }
        } catch (\Error $e) { return response()->json($e->getMessage(), 500); }
    }

    private function accessGenTransfer($oid,$supply){
        $data = json_encode([ "id"=>$oid, "supply"=>$supply ]);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, env("URL_INVOICE")."/storetools/public/api/Transfer/Transfer");
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

        return curl_errno($curl) ? [ "error"=>curl_error($curl) ] : [ "error"=>false, "done"=>$exec, "httpcode"=>$info["http_code"] ];

        curl_close($curl);
    }

    public function newTransferRec(Request $request){
        try {
            $oid = $request->route("oid");
            $supply = $request->route("supply");
            $resp = $this->accessGenTransferRec($oid,$supply);
            if($resp["error"]){
                return response()->json($resp["error"],500);
            }else{
                if($resp["httpcode"]==201){
                    $requisition = RequisitionPartition::where([['_requisition',$oid],['_suplier_id',$supply]])->first();
                    // $now = CarbonImmutable::now();
                    // $prevstate = null;

                    // $logs = $requisition->log->toArray();
                    // $end = end($logs);
                    // $prevstate = $end['pivot']['_status'];
                    // $prevstate ? $requisition->log()->syncWithoutDetaching([$prevstate => [ 'updated_at' => $now->format("Y-m-m H:m:s")]]) : null;
                    // $requisition->log()->attach(7, [ 'details'=>json_encode([ "responsable"=>"VizApp" ]) ]);
                    $requisition->_status=10; // se actualiza el status del pedido
                    // $requisition->entry_key = md5($requisition->id);
                    $requisition->save(); // se guardan los cambios
                    $requisition->fresh(); // se refresca el log del pedido
                    return response()->json(["transfer"=>$resp['done'], "requisition"=>$requisition]);
                }else{ return response()->json($resp["done"],$resp["httpcode"]); }
            }
        } catch (\Error $e) { return response()->json($e->getMessage(), 500); }
    }

    private function accessGenTransferRec($oid,$supply){
        $data = json_encode([ "id"=>$oid, "supply"=>$supply ]);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, env("URL_INVOICE")."/storetools/public/api/Transfer/TransferRec");
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

        return curl_errno($curl) ? [ "error"=>curl_error($curl) ] : [ "error"=>false, "done"=>$exec, "httpcode"=>$info["http_code"] ];

        curl_close($curl);
    }


    public function newentry(Request $request){
        try {
            $oid = $request->route("oid");
            $requisition = RequisitionPartition::with(["requisition.from"])->find($oid);
            $requi = $requisition->requisition['id'];
            $suply = $requisition->_suplier_id;
            $cstate = $requisition->_status;

            if($cstate==6){
                $ip = $requisition->requisition['from']["dominio"];

                $resp = $this->accessGenEntry($requi, $ip, $suply);
                // $resp = $this->accessGenEntry($requi, '192.168.10.112:1619', $suply);

                if($resp["error"]){
                    return response()->json($resp["error"],500);
                }else{
                    if($resp["httpcode"]==201){

                        // $now = CarbonImmutable::now();
                        // $prevstate = null;

                        // $logs = $requisition->log->toArray();
                        // $end = end($logs);
                        // $prevstate = $end['pivot']['_status'];
                        // $prevstate ? $requisition->log()->syncWithoutDetaching([$prevstate => [ 'updated_at' => $now->format("Y-m-m H:m:s")]]) : null;
                        // $requisition->log()->attach(10, [ 'details'=>json_encode([ "responsable"=>"VizApp" ]) ]);
                        $requisition->_status=6; // se actualiza el status del pedido
                        $requisition->save(); //  guardan los cambios
                        // $requisition->fresh(['log']); // se refresca el log del pedido

                        return response()->json(["invoice"=>$resp['done'], "requisition"=>$requisition]);
                    }else{ return response()->json($resp["done"],$resp["httpcode"]); }
                }
            }else{ return response("El status actual de esta orden no permite generar entrada (orderState: $cstate)",400); }
        } catch (\Error $e) { return response()->json($e->getMessage(), 500); }
    }

    private function accessGenInvoice($oid,$supply){
        $data = json_encode([ "id"=>$oid, "supply"=>$supply ]);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, env("URL_INVOICE")."/storetools/public/api/Received/Received");
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

        return curl_errno($curl) ? [ "error"=>curl_error($curl) ] : [ "error"=>false, "done"=>$exec, "httpcode"=>$info["http_code"] ];

        curl_close($curl);
    }

    private function accessGenEntry($oid,$ip, $suply){
        $data = json_encode([ "id"=>$oid, "suply"=>$suply ]);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "http://$ip/storetools/public/api/Required/Required");
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

        return curl_errno($curl) ? [ "error"=>curl_error($curl) ] : [ "error"=>false, "done"=>$exec, "httpcode"=>$info["http_code"] ];

        curl_close($curl);
    }

    public function printKey(Request $request){
        $ip = $request->ip;
        $port = $request->port;
        $oid = $request->order;

        $req = Requisition::with(["from"])->findOrFail($oid);

        $key = $req->entry_key;
        $id = $req->id;
        $store = $req->from["alias"];

        $printer = new MiniPrinterController($ip, $port);
        $printed = $printer->printKey($id, $store, $key);

        return response()->json($printed);
    }

    public function checkin(Request $request){
        try {
            $oid = $request->oid;
            $key = $request->key;

            $order = RequisitionPartition::with([
                        // 'type',
                        'status',
                        // 'log',
                        'requisition.from',
                        'requisition.to',
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
                                        // 'locations' => fn($qq) => $qq->whereHas('celler', function($qqq){ $qqq->where('_workpoint', 1); }),
                                    ]);
                            }
                    ])->where([ ["_requisition",$oid],["entry_key", $key]])->first();

            if($order){
                return response()->json([ "order"=>$order ]);
            }else{ return response("Sin coincidencias para el folio o llave invalida!",404); }
        } catch (\Error $e) { return response()->json($e,500); }
    }

    public function checkininit(Request $request){
        try {
            $oid = $request->oid;
            $key = $request->key;
            $req = RequisitionPartition::where([ ["_requisition",$oid],["entry_key", $key] ])->first();

            if($req){
                $cstate = $req->_status;

                // if($cstate==8){
                //     $now = CarbonImmutable::now();
                //     $prevstate = null;
                //     $logs = $req->log->toArray();
                //     $end = end($logs);
                //     $prevstate = $end['pivot']['_status'];
                //     $prevstate ? $req->log()->syncWithoutDetaching([$prevstate => [ 'updated_at' => $now->format("Y-m-m H:m:s")]]) : null;
                //     $req->log()->attach(9, [ 'details'=>json_encode([ "responsable"=>"VizApp" ]) ]);
                //     $req->_status=9; // se actualiza el status del pedido
                //     $req->save(); // se guardan los cambios
                //     $req->fresh(['log']); // se refresca el log del pedido

                    return response()->json([ "req"=>$req ]);
                // }else{ return response("El status ($cstate) actual del pedido, no permite iniciar el conteo",400); }
            }else{ return response("Sin coincidencias para el folio o llave invalida!",404); }
        } catch (\Error $e) { return response()->json($e,500); }
    }

    public function report(Request $request){
        $rep = $request->route("rep");

        switch ($rep) {
            case'pdss':$rows=DB::select(
                    "SELECT
                        P.id AS ID,
                        P.code AS CODIGO,
                        P.description AS DESCRIPCION,
                        GETSECTION(PC.id) AS SECCION,
                        GETFAMILY(PC.id) AS FAMILIA,
                        GETCATEGORY(PC.id) AS CATEGORIA,
                        S.name AS ESTADO
                    FROM products P
                        INNER JOIN product_stock PS ON PS._product = P.id
                        INNER JOIN product_status S ON S.id = PS._status
                        INNER JOIN product_categories PC ON PC.id = P._category
                    WHERE PS._status = 1 AND PS._workpoint = 1 AND (SELECT sum(stock) FROM product_stock WHERE _workpoint = 2 and _product = P.id) = 0  AND (SELECT sum(stock) FROM product_stock WHERE _workpoint = 1 and _product = P.id) = 0;");
                    $name = "Productos disponibles sin stock";
                    $key = "pdss";
                break;

            case'pndcs':$rows=DB::select(
                    "SELECT
                        P.id AS ID,
                        P.code AS CODIGO,
                        P.description AS DESCRIPCION,
                        GETSECTION(PC.id) AS SECCION,
                        GETFAMILY(PC.id) AS FAMILIA,
                        GETCATEGORY(PC.id) AS CATEGORIA,
                        S.name AS ESTADO,
                        PS.stock AS CEDIS,
                        (SELECT sum(stock) FROM product_stock WHERE _workpoint = 2 and _product = P.id) as PANTACO
                    FROM products P
                        INNER JOIN product_stock PS ON PS._product = P.id
                        INNER JOIN product_status S ON S.id = PS._status
                        INNER JOIN product_categories PC ON PC.id = P._category
                    WHERE PS._workpoint = 1 AND PS._status NOT IN (1,4) AND PS.stock > 0;");
                    $name = "Productos no disponibles con stock";
                    $key = "pndcs";
                break;

            default: break;
        }

        return response([ "rows"=>$rows, "name"=>$name, "key"=>$key ]);
    }

    public function massaction(Request $request){
        $action = $request->action;

        switch ($action) {
            case 'pndcs':
                $query = 'UPDATE product_stock PS SET PS._status=1 WHERE  PS._workpoint IN (1,2) AND PS._status NOT IN (1,4) AND PS.stock>0;';
                break;

            case 'pdss':
                $query = 'UPDATE product_stock CED
                            INNER JOIN product_stock PAN ON CED._product = PAN._product
                            SET CED._status=3
                            WHERE CED._status=1 AND CED._workpoint=1 AND CED.stock=0 AND PAN.stock=0 AND PAN._workpoint=2;';
                break;
        }

        $q = DB::update($query);

        return response()->json([ "msg"=>"Making $action", "query"=>$query, "exec"=>$q ]);
    }

    public function printforsupply(Request $request){
        $ip = $request->ip;
        $port = $request->port;
        $order = $request->order;
        $requisition = Requisition::find($order);

        $_workpoint_to = $requisition->_workpoint_to;
        $requisition->load(['log', 'products' => function($query) use ($_workpoint_to){
            $query->with(['locations' => function($query)  use ($_workpoint_to){
                $query->whereHas('celler', function($query) use ($_workpoint_to){
                    $query->where('_workpoint', $_workpoint_to);
                });
            }]);
        }]);

        $printer = new MiniPrinterController($ip, $port, 5);
        $printed = $printer->requisitionTicket($requisition);

        if($printed){
            $requisition->printed = $requisition->printed +1;
            $requisition->save();
        }

        return response()->json($printed);
    }

    public function pritnforPartition(Request $request){
        $ip = $request->ip;
        $port = $request->port;
        $requisition = RequisitionPartition::find($request->_partition);
        $workpoint_to_print = Workpoint::find(1);
        $requisition->load(['requisition.from','requisition.created_by','requisition.to', 'log', 'products' => function($query){
            $query->with(['locations' => function($query){
                $query->whereHas('celler', function($query){
                    $query->where('_workpoint', 1);
                });
            }]);
        }]);

        $cellerPrinter = new MiniPrinterController($ip, $port);
        $cellerPrinter;
        $res = $cellerPrinter->PartitionTicket($requisition);
        return response()->json(["success" => $res, "printer" => $ip]);
    }

    public function create(Request $request){
        // try{
            // return $request;
            $requisition = DB::transaction(function() use ($request){
                $_workpoint_from = $request->_workpoint_from;

                $_workpoint_to = $request->_workpoint_to;
               $request->_type;

                        $data = $this->getToSupplyFromStore($_workpoint_from, $_workpoint_to);

                if(isset($data['msg'])){
                    return response()->json([
                        "success" => false,
                        "msg" => $data['msg']
                    ]);
                }

                $now = new \DateTime();
                $num_ticket = Requisition::where('_workpoint_to', $_workpoint_to)
                                            ->whereDate('created_at', $now)
                                            ->count()+1;
                $num_ticket_store = Requisition::where('_workpoint_from', $_workpoint_from)
                                                ->whereDate('created_at', $now)
                                                ->count()+1;
                $requisition =  Requisition::create([
                    "notes" => $request->notes,
                    "num_ticket" => $num_ticket,
                    "num_ticket_store" => $num_ticket_store,
                    "_created_by" => 1,
                    "_workpoint_from" => $_workpoint_from,
                    "_workpoint_to" => $_workpoint_to,
                    "_type" => $request->_type,
                    "printed" => 0,
                    "time_life" => "00:15:00",
                    "_status" => 1
                ]);

                $this->log(1, $requisition);

                if(isset($data['products'])){ $requisition->products()->attach($data['products']); }

                if($request->_type != 1){ $this->refreshStocks($requisition); }

                return $requisition->fresh('type', 'status', 'products', 'to', 'from', 'created_by', 'log');
            });
            $this->nextStep($requisition->id);
            return response()->json([
                "success" => true,
                "order" => new RequisitionResource($requisition)
            ]);
        // }catch(\Exception $e){
        //     return response()->json(["message" => "No se ha podido crear el pedido", "Error"=>$e]);
        // }
    }

    public function getToSupplyFromStore($workpoint_id, $workpoint_to){ // Función para hacer el pedido de minimos y máximos de la sucursal

        // $workpoint = WorkPoint::find($workpoint_id); // Obtenemos la sucursal a la que se le realizara el pedido
        $cats = $this->categoriesByStore($workpoint_id); // Obtener todas las categorias que puede pedir la sucursal
        // Todos los productos antes de ser solicitados se válida que haya en CEDIS y la sucursal los necesite en verdad, verificando que la existencia actual sea menor al máximo en primer instancia

        $wkf = $workpoint_id;
        $wkt = $workpoint_to;

        $pquery = "SELECT
                P.id AS id,
                P.code AS code,
                P._unit AS unitsupply,
                P.pieces AS ipack,
                P.cost AS cost,
                    (SELECT stock FROM product_stock WHERE _workpoint=$wkf AND _product = P.id AND _status != 4 AND min > 0 AND max > 0) AS stock,
                    (SELECT min FROM product_stock WHERE _workpoint=$wkf AND _product = P.id) AS min,
                    (SELECT max FROM product_stock WHERE _workpoint=$wkf AND _product = P.id) AS max,
                    SUM(IF(PS._workpoint=$wkf, PS.stock, 0)) AS CEDIS,
                    (SELECT SUM(stock) FROM product_stock WHERE _workpoint = 2 AND _product = P.id) AS PANTACO
                FROM
                    products P
                        INNER JOIN product_categories PC ON PC.id = P._category
                        INNER JOIN product_stock PS ON PS._product = P.id
                WHERE
                    GETSECTION(PC.id) in ($cats)
                        AND P._status != 4
                        AND (IF(PS._workpoint = $wkt, PS._status, 0)) = 1
                        AND ((SELECT stock FROM product_stock WHERE _workpoint=$wkf AND _product=P.id AND _status!=4 AND min>0 AND max>0)) IS NOT NULL
                        AND (IF((SELECT stock FROM product_stock WHERE _workpoint=$wkf AND _product=P.id AND _status!=4 AND min>0 AND max>0) <= (SELECT min FROM product_stock WHERE _workpoint=$wkf AND _product=P.id), (SELECT  max FROM product_stock WHERE _workpoint=$wkf AND _product = P.id) - (SELECT  stock FROM product_stock WHERE _workpoint=$wkf AND _product = P.id AND _status != 4 AND min > 0 AND max > 0), 0)) > 0
                GROUP BY P.code";

        $rows = DB::select($pquery);
        $tosupply = [];

        foreach ($rows as $product) {
            $stock = $product->stock;
            $min = $product->min;
            $max = $product->max;

            // $required = ($stock<=$min) ? ($max-$stock) : 0;

            // if($required){
                if( $product->unitsupply==3 ){
                    $required = ($stock<=$min) ? ($max-$stock) : 0;
                    $ipack = $product->ipack == 0 ? 1 : $product->ipack;
                    $boxes = floor($required/$ipack);

                    ($boxes>=1) ? $tosupply[$product->id] = [ 'units'=>$required, "cost"=>$product->cost, 'amount'=>$boxes, "_supply_by"=>3, 'comments'=>'', "stock"=>0 ] : null;
                }else if( $product->unitsupply==1){
                    $required = ($max-$stock);
                    if($required >=6 ){
                        ($stock<=$min) ? $tosupply[$product->id] = [ 'units'=>$required, "cost"=>$product->cost, 'amount'=>$required,  "_supply_by"=>1 , 'comments'=>'', "stock"=>0] : null ;
                    }
                }
            // }
        }

        return ["products" => $tosupply];
    }

    public function categoriesByStore($_workpoint){

        /* IMPORTANTE */
        /* En este lugar se establecen las secciones que puede solicitar una sucursal */
        switch($_workpoint){
            case 1: return '"Detalles", "Peluche", "Hogar","Calculadora","Mochila","Papeleria","Juguete"'; break;
            case 3: return '"Paraguas"'; break;
            case 4: return '"Mochila"'; break;
            case 5: return '"Mochila"'; break;
            case 6: return '"Calculadora", "Electronico", "Hogar"'; break;
            case 7: return '"Mochila"'; break;
            case 8: return '"Calculadora", "Juguete", "Papeleria"'; break;
            case 9: return '"Mochila"'; break;
            case 10: return '"Calculadora", "Electronico", "Hogar"'; break;
            case 11: return '"Juguete"'; break;
            case 12: return '"Mochila"'; break;
            case 13: return '"Mochila"'; break;
            case 18: return '"Mochila", "Electronico", "Hogar"'; break;
            case 19: return '"Juguete"'; break;
            case 22: return '"Mochila"'; break;
            case 23: return '"Mochila"'; break;
            case 24: return '"Juguete"'; break;
        }
    }


    public function log($case, Requisition $requisition, $_printer=null, $actors=[]){
        $account = Account::with('user')->find(1);
        $responsable = $account->user->names.' '.$account->user->surname_pat;
        $previous = null;

        // $requisition->load(["from","to"]);
        // $requisition->fresh();

        // $telAdminFrom = $requisition->from["tel_admin"];
        // $telAdminTo = $requisition->from["tel_admin"];

        if($case != 1){
            $logs = $requisition->log->toArray();
            $end = end($logs);
            $previous = $end['pivot']['_status'];
        }

        if($previous){
            $requisition->log()->syncWithoutDetaching([$previous => [ 'updated_at' => new \DateTime()]]);
        }

        switch($case){
            case 1: // LEVANTAR PEDIDO
                $requisition->log()->attach(1, [ 'details'=>json_encode([ "responsable"=>$responsable ]), 'created_at' => carbon::now()->format('Y-m-d H:i:s'), 'updated_at' => carbon::now()->format('Y-m-d H:i:s') ]);
            break;

            case 2: // POR SURTIR => IMPRESION DE COMPROBANTE EN TIENDA
                // $port = $requisition->_workpoint_to==2 ? 4065:9100;
                $port = 9100;

                $requisition->log()->attach(2, [ 'details'=>json_encode([ "responsable"=>$responsable ]), 'created_at' => carbon::now()->format('Y-m-d H:i:s'), 'updated_at' => carbon::now()->format('Y-m-d H:i:s') ]);// se inserta el log dos al pedido con su responsable
                $requisition->_status=2; // se prepara el cambio de status del pedido (a por surtir (2))
                $requisition->save(); // se guardan los cambios
                $requisition->fresh(['log']); // se refresca el log del pedido

                // $whats1 = $this->sendWhatsapp($telAdminFrom, "CEDIS ha recibido tu pedido - FOLIO: #$requisition->id 🤟🏽");
                // $whats2 = $this->sendWhatsapp($telAdminFrom, "Nuevo pedido de ".$requisition->from['name'].": #$requisition->id, esta listo para iniciar surtido!!");

                // traemos el cuerpo del pedido con las ubicaciones del workpoint destino
                $_workpoint_to = $requisition->_workpoint_to;
                $requisition->load(['log', 'products' => function($query) use ($_workpoint_to){
                    $query->with(['locations' => function($query)  use ($_workpoint_to){
                        $query->whereHas('celler', function($query) use ($_workpoint_to){
                            $query->where('_workpoint', $_workpoint_to);
                        });
                    }]);
                }]);

                // definir IMPRESION AUTOMATICA DEL PEDIDO
                // $ipprinter = \App\Printer::where([['_type', 2], ['_workpoint', $_workpoint_to]])->first();
                // $miniprinter = new MiniPrinterController($ipprinter->ip, $port);

                // if($requisition->_workpoint_from == 1){
                //     $par = $requisition->_workpoint_to;
                //     $ipprinter = $par == 2 ? env("PRINTERTEX") :  env("PRINTER_P3");
                // }else{
                //     $stores_p3 = [ 3, 4, 5, 7, 9, 13, 18 , 22 ];
                //     $ipprinter = in_array($requisition->_workpoint_from, $stores_p3) ? env("PRINTER_P3") : env("PRINTER_P2");
                // }


                if($requisition->_workpoint_to == 2){
                    // $ipprinter = env("PRINTERTEX");
                    // $groupvi = "120363185463796253@g.us";
                    // $mess = "Has recibido el pedido ".$requisition->id;
                    // $this->sendWhatsapp($groupvi, $mess);
                }else if($requisition->_workpoint_to == 24){
                    $ipprinter = env("PRINTERBOL");
                }else{
                    // $stores_p3 = [ 1, 3, 4, 5, 7, 9, 13, 18 , 22 ];
                    // $ipprinter = in_array($requisition->_workpoint_from, $stores_p3) ? env("PRINTER_P3") : env("PRINTER_P2");
                    $ipprinter = env("PRINTER_P3") ;
                }

                $miniprinter = new MiniPrinterController($ipprinter, $port);
                $printed_provider = $miniprinter->requisitionTicket($requisition);

                if($printed_provider){
                    $requisition->printed = ($requisition->printed+1);
                    $requisition->save();
                }else {
                    $groupvi = "120363185463796253@g.us";
                    $mess = "El pedido ".$requisition->id." no se logro imprimir, favor de revisarlo";
                    $this->sendWhatsapp($groupvi, $mess);
                }

            $requisition->refresh('log');

            $log = $requisition->log->filter(function($event) use($case){
                return $event->id >= $case;
            })->values()->map(function($event){
                return [
                    "id" => $event->id,
                    "name" => $event->name,
                    "active" => $event->active,
                    "allow" => $event->allow,
                    "details" => json_decode($event->pivot->details),
                    "created_at" => $event->pivot->created_at->format('Y-m-d H:i'),
                    "updated_at" => $event->pivot->updated_at->format('Y-m-d H:i')
                ];
            });

            return [
                "success" => (count($log)>0),
                "printed" => $requisition->printed,
                "status" => $requisition->status,
                "log" => $log
            ];
        }
    }
    public function refreshStocks(Requisition $requisition){ // Función para actualizar los stocks de un pedido de resurtido
        $_workpoint_to = $requisition->_workpoint_to;
        $requisition->load(['log', 'products' => function($query) use ($_workpoint_to){
            $query->with(['stocks' => function($query) use($_workpoint_to){
                $query->where('_workpoint', $_workpoint_to);
            }]);
        }]);
        foreach($requisition->products as $product){
            $requisition->products()->syncWithoutDetaching([
                $product->id => [
                    'units' => $product->pivot->units,
                    'comments' => $product->pivot->comments,
                    'stock' => count($product->stocks) > 0 ? $product->stocks[0]->pivot->stock : 0
                ]
            ]);
        }
        return true;
    }

    public function nextStep($id){
        $requisition = Requisition::with(["to", "from", "created_by"])->find($id);
        $server_status = 200;
        if($requisition){
            $_status = $requisition->_status+1;

            $process = Process::all()->toArray();

            if(in_array($_status, array_column($process, "id"))){
                $result = $this->log($_status, $requisition);
                $msg = $result["success"] ? "" : "No se pudo cambiar el status";
                $server_status = $result ["success"] ? 200 : 500;
            }else{
                $msg = "Status no válido";
                $server_status = 400;
            }
        }else{
            $msg = "Pedido no encontrado";
            $server_status = 404;
        }

        return response()->json([
            "success" => isset($result) ? $result["success"] : false,
            "serve_status" => $server_status,
            "msg" => $msg,
            "updates" =>[
                "status" => isset($result) ? $result["status"] : null,
                "log" => isset($result) ? $result["log"] : null,
                "printed" =>  isset($result) ? $result["printed"] : null
            ]
        ]);
    }
    public function sendWhatsapp($tel, $msg){
        $token = env('WATO');
        $curl = curl_init();//inicia el curl para el envio de el mensaje via whats app
        curl_setopt_array($curl, array(
          CURLOPT_URL => "https://api.ultramsg.com/instance9800/messages/chat",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_SSL_VERIFYHOST => 0,
          CURLOPT_SSL_VERIFYPEER => 0,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => "token=$token&to=$tel&body=$msg&priority=1&referenceId=",//se redacta el mensaje que se va a enviar con los modelos y las piezas y el numero de salida
          CURLOPT_HTTPHEADER => array("content-type: application/x-www-form-urlencoded"),));
        $response = curl_exec($curl);
        $err = curl_error($curl);

        return $response;
        curl_close($curl);
    }

    public function suc(){
        $sucursales = Workpoint::where([['_type',2],['active',1]])->get();
        return response()->json($sucursales,200);
    }

    // public function create($store){
    //     // try{
    //         // return $request;
    //         $requisition = DB::transaction(function() use ($store){
    //             $_workpoint_from = $store;

    //             $_workpoint_to = 1;


    //              $data = $this->getToSupplyFromStore($_workpoint_from, $_workpoint_to);

    //             if(isset($data['msg'])){
    //                 return response()->json([
    //                     "success" => false,
    //                     "msg" => $data['msg']
    //                 ]);
    //             }

    //             $now = new \DateTime();
    //             $num_ticket = Requisition::where('_workpoint_to', $_workpoint_to)
    //                                         ->whereDate('created_at', $now)
    //                                         ->count()+1;
    //             $num_ticket_store = Requisition::where('_workpoint_from', $_workpoint_from)
    //                                             ->whereDate('created_at', $now)
    //                                             ->count()+1;
    //             $requisition =  Requisition::create([
    //                 "notes" => 'Pedido '.$num_ticket,
    //                 "num_ticket" => $num_ticket,
    //                 "num_ticket_store" => $num_ticket_store,
    //                 "_created_by" => 1,
    //                 "_workpoint_from" => $_workpoint_from,
    //                 "_workpoint_to" => $_workpoint_to,
    //                 "_type" => 2,
    //                 "printed" => 0,
    //                 "time_life" => "00:15:00",
    //                 "_status" => 1
    //             ]);

    //             $this->log(1, $requisition);

    //             if(isset($data['products'])){ $requisition->products()->attach($data['products']); }

    //             if(2 != 1){ $this->refreshStocks($requisition); }

    //             return $requisition->fresh('type', 'status', 'products', 'to', 'from', 'created_by', 'log');
    //         });
    //         $this->nextStep($requisition->id);
    //         return response()->json([
    //             "success" => true,
    //             "order" => new RequisitionResource($requisition)
    //         ]);
    //     // }catch(\Exception $e){
    //     //     return response()->json(["message" => "No se ha podido crear el pedido", "Error"=>$e]);
    //     // }
    // }

    // public function automate(){
    //     $stores = Workpoint::where([['_type',2],['active',1]])->whereIn('id',[1])->get();
    //     foreach($stores as $store){
    //         $this->create($store);
    //     }

    // }
}
