<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Requisition;
use App\RequisitionType as Type;
use App\RequisitionProcess as Process;
use App\Product;
use App\WorkPoint;
use App\Account;
use App\Http\Resources\Requisition as RequisitionResource;

class RequisitionController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public $account = null;
    public function __construct(){
        $this->account = Auth::payload()['workpoint'];
    }

    public function create(Request $request){
        try{
            $requisition = DB::transaction(function() use ($request){
                $_workpoint_from = $this->account->_workpoint;
                $_workpoint_to = $request->_workpoint_to;
                switch ($request->_type){
                    case 2:
                        $data = $this->getToSupplyFromStore($this->account->_workpoint, $_workpoint_to);
                    break;
                    case 3:
                        $_workpoint_from = (isset($request->store) && $request->store) ? $request->store : $this->account->_workpoint;
                        $cadena = explode('-', $request->folio);
                        $folio = count($cadena)>1 ? $cadena[1] : '0';
                        $caja = count($cadena)>0 ? $cadena[0] : '0';
                        $data = $this->getVentaFromStore($folio, $_workpoint_from, $caja, $_workpoint_to);
                        /* $request->notes = $request->notes; */
                    break;
                    case 4:
                        $data = $this->getPedidoFromStore($request->folio, $_workpoint_from, $_workpoint_to);
                        $request->notes = $request->notes ? $request->notes." ".$data['notes'] : $data['notes'];
                    break;
                }
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
                    "_created_by" => $this->account->_account,
                    "_workpoint_from" => $_workpoint_from,
                    "_workpoint_to" => $_workpoint_to,
                    "_type" => $request->_type,
                    "printed" => 0,
                    "time_life" => "00:15:00",
                    "_status" => 1
                ]);
                $this->log(1, $requisition);
                if(isset($data['products'])){
                    $requisition->products()->attach($data['products']);
                }
                if($request->_type != 1){
                    $this->refreshStocks($requisition);
                }
                return $requisition->fresh('type', 'status', 'products', 'to', 'from', 'created_by', 'log');
            });
            return response()->json([
                "success" => true,
                "order" => new RequisitionResource($requisition)
            ]);
        }catch(Exception $e){
            return response()->json(["message" => "No se ha podido crear el pedido"]);
        }
    }

    public function addProduct(Request $request){
        try{
            $amount = isset($request->amount) ? $request->amount : 1; /* CANTIDAD EN UNIDAD */
            if($amount<=0){
                return response()->json(["msg" => "No se puede agregar esta unidad", "success" => false, "server_status" => 400]);
            }
            $requisition = Requisition::find($request->_requisition);
            if($this->account->_account == $requisition->_created_by || in_array($this->account->_rol, [1,2,3])){
                $to = $requisition->_workpoint_to;
                $product = Product::
                selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS category')
                ->with(['units', 'stocks' => function($query) use ($to){
                    $query->whereIn('_workpoint', [$to, $this->account->_workpoint]);
                }, 'prices' => function($query){
                    $query->where('_type', 7);
                }])->find($request->_product);

                $cost = count($product->prices)> 0 ? $product->prices[0]->pivot->price : 0;
                $_supply_by = isset($request->_supply_by) ? $request->_supply_by : $product->_unit;
                $units = $this->getAmount($product, $amount, $_supply_by);
                $_workpoint_stock = $product->stocks->map(function($stock){
                    return $stock->id;
                })->toArray();
                $key_stock = array_search($to, $_workpoint_stock); //Tienda a la que se le pide la mercancia
                $key_stock_from = array_search($this->account->_workpoint, $_workpoint_stock); //Tienda que pide la mercancia
                $stock = ($key_stock > 0 || $key_stock === 0) ? $product->stocks[$key_stock]->pivot->stock : 0;
                $total = $cost * $units;

                $requisition->products()->syncWithoutDetaching([
                    $request->_product => [
                        'amount' => $amount,
                        '_supply_by' => $_supply_by,
                        'units' => $units,
                        'cost' => $cost,
                        'total' => $total,
                        'comments' => isset($request->comments) ? $request->comments : "",
                        'stock' => $stock
                    ]
                ]);
                return response()->json([
                    "id" => $product->id,
                    "code" => $product->code,
                    "name" => $product->name,
                    "description" => $product->description,
                    "dimensions" => $product->dimensions,
                    "section" => $product->section,
                    "family" => $product->family,
                    "category" => $product->category,
                    "pieces" => $product->pieces,
                    "units" => $product->units,
                    "ordered" => [
                        "amount" => $amount,
                        "_supply_by" => $_supply_by,
                        "units" => $units,
                        "cost" => $cost,
                        "total" => $total,
                        "comments" => isset($request->comments) ? $request->comments : "",
                        "stock" => $stock
                    ],
                    "stocks" => [
                        [
                            "alias" => ($key_stock_from > 0 || $key_stock_from === 0) ? $product->stocks[$key_stock_from]->alias : "",
                            "name" => ($key_stock_from > 0 || $key_stock_from === 0) ? $product->stocks[$key_stock_from]->name : "",
                            "stock"=> ($key_stock_from > 0 || $key_stock_from === 0) ? $product->stocks[$key_stock_from]->pivot->stock : 0,
                            "gen" => ($key_stock_from > 0 || $key_stock_from === 0) ? $product->stocks[$key_stock_from]->pivot->gen : 0,
                            "exh" => ($key_stock_from > 0 || $key_stock_from === 0) ? $product->stocks[$key_stock_from]->pivot->exh : 0,
                            "min" => ($key_stock_from > 0 || $key_stock_from === 0) ? $product->stocks[$key_stock_from]->pivot->min : 0,
                            "max"=> ($key_stock_from > 0 || $key_stock_from === 0) ? $product->stocks[$key_stock_from]->pivot->min : 0,
                        ]
                    ]
                ]);
            }else{
                return response()->json(["msg" => "No puedes agregar productos", "success" => false]);
            }
        }catch(Exception $e){
            return response()->json(["msg" => "No se ha podido agregar el producto", "success" => false]);
        }
    }

    public function addMassiveProduct(Request $request){
        $requisition = Requisition::find($request->_requisition);
        $products = isset($request->products) ? $request->products : [];
        $notFound = [];
        $soldOut = [];
        $added = [];
        if($requisition){
            $to = $requisition->_workpoint_to;
            foreach($products as $row){
                $code = $row['code'];
                $product = Product::
                selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS category')
                ->whereHas('variants', function($query) use ($code){
                    $query->where('barcode', $code);
                })->with(['stocks' => function($query) use ($to){
                    $query->whereIn('_workpoint', [$to, $this->account->_workpoint]);
                }])->first();
                if(!$product){
                    $product = Product::
                    selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS category')
                    ->where([['code', $code], ['_status', '!=', 4]])->orWhere([['name', $code], ['_status', '!=', 4]])->with(['stocks' => function($query) use ($to){
                        $query->whereIn('_workpoint', [$to, $this->account->_workpoint]);
                    }])->first();
                }
                if($product){
                    $cost = count($product->prices)> 0 ? $product->prices[0]->pivot->price : false;
                    $amount = isset($row["amount"]) ? $row["amount"] : 1;
                    $_supply_by = isset($request->_supply_by) ? $request->_supply_by : $product->_unit;
                    $units = $this->getAmount($product, $amount, $_supply_by);
                    $_workpoint_stock = $product->stocks->map(function($stock){
                        return $stock->id;
                    })->toArray();
                    $key_stock = array_search($to, $_workpoint_stock); //Tienda que solicita la mercancia
                    $key_stock_from = array_search($this->account->_workpoint, $_workpoint_stock); //Tienda a la que le piden la mercancia
                    $stock = ($key_stock > 0 || $key_stock === 0) ? $product->stocks[$key_stock]->pivot->stock : 0;
                    $total = $cost * $units;
                    $requisition->products()->syncWithoutDetaching([
                        $product->id => [
                            'amount' => $amount,
                            '_supply_by' => $_supply_by,
                            'units' => $units,
                            'cost' => $cost,
                            'total' => $total,
                            'comments' => isset($row["comments"]) ? $row["comments"] : "",
                            'stock' => $stock
                        ]
                    ]);
                    $added [] = [
                        "id" => $product->id,
                        "code" => $product->code,
                        "name" => $product->name,
                        "description" => $product->description,
                        "dimensions" => $product->dimensions,
                        "section" => $product->section,
                        "family" => $product->family,
                        "category" => $product->category,
                        "pieces" => $product->pieces,
                        "units" => $product->units,
                        "ordered" => [
                            "amount" => $amount,
                            "_supply_by" => $_supply_by,
                            "units" => $units,
                            "cost" => $cost,
                            "total" => $total,
                            "comments" => isset($row["comments"]) ? $row["comments"] : "",
                            "stock" => $stock
                        ],
                        "stocks" => [
                            [
                                "alias" => ($key_stock_from > 0 || $key_stock_from === 0) ? $product->stocks[$key_stock_from]->alias : "",
                                "name" => ($key_stock_from > 0 || $key_stock_from === 0) ? $product->stocks[$key_stock_from]->name : "",
                                "stock"=> ($key_stock_from > 0 || $key_stock_from === 0) ? $product->stocks[$key_stock_from]->pivot->stock : 0,
                                "gen" => ($key_stock_from > 0 || $key_stock_from === 0) ? $product->stocks[$key_stock_from]->pivot->gen : 0,
                                "exh" => ($key_stock_from > 0 || $key_stock_from === 0) ? $product->stocks[$key_stock_from]->pivot->exh : 0,
                                "min" => ($key_stock_from > 0 || $key_stock_from === 0) ? $product->stocks[$key_stock_from]->pivot->min : 0,
                                "max"=> ($key_stock_from > 0 || $key_stock_from === 0) ? $product->stocks[$key_stock_from]->pivot->min : 0,
                            ]
                        ]
                    ];
                }else{
                    $notFound[] = $row["code"];
                }
            }

        }
        return response()->json(["added" => $added, "notFound" => $notFound]);
    }

    public function removeProduct(Request $request){
        try{
            $requisition = Requisition::find($request->_requisition);
            if($this->account->_account == $requisition->_created_by || in_array($this->account->_rol, [1,2,3])){
                $requisition->products()->detach([$request->_product]);
                return response()->json(["success" => true]);
            }else{
                return response()->json(["msg" => "No puedes eliminar productos"]);
            }
        }catch(Exception $e){
            return response()->json(["msg" => "No se ha podido eliminar el producto"]);
        }
    }

    public function log($case, Requisition $requisition, $_printer = null, $actors = []){
        $account = Account::with('user')->find($this->account->id);
        $responsable = $account->user->names.' '.$account->user->surname_pat;
        $previous = null;
        if($case != 1){
            $logs = $requisition->log->toArray();
            $end = end($logs);
            $previous = $end['pivot']['_status'];
        }
        if($previous){
            $requisition->log()->syncWithoutDetaching([$previous => [ 'updated_at' => new \DateTime()]]);
        }
        switch($case){
            case 1: /* LEVANTAR PEDIDO*/
                $requisition->log()->attach(1, [ 'details' => json_encode([
                    "responsable" => $responsable
                ])]);
                return true;
            break;
            case 2: /* POR SURTIR */ //IMPRESION DE COMPROBANTE EN TIENDA
                $requisition->log()->attach(2, [ 'details' => json_encode([
                    "responsable" => $responsable
                    ])]);
                $requisition->_status = 2;
                $requisition->save();
                $requisition->fresh(['log']);
                $printer = $_printer ? \App\Printer::find($_printer) : \App\Printer::where([['_type', 2], ['_workpoint', $this->account->_workpoint]])->first();
                $miniprinter = new MiniPrinterController($printer->ip, 9100);
                $msg = $miniprinter->requisitionReceipt($requisition) ? "" : "No se pudo imprimir el comprobante"; //Se ejecuta la impresión
            break;
            case 3: /* SURTIENDO */
                $requisition->log()->attach(3, [ 'details' => json_encode([
                    "responsable" => $responsable,
                    "actors" => $actors
                ])]);
                $requisition->_status = 3;
                $requisition->save();
                $_workpoint_to = $requisition->_workpoint_to;
                $requisition->load(['log', 'products' => function($query) use ($_workpoint_to){
                    $query->with(['locations' => function($query)  use ($_workpoint_to){
                        $query->whereHas('celler', function($query) use ($_workpoint_to){
                            $query->where('_workpoint', $_workpoint_to);
                        });
                    }]);
                }]);
                $printer = $_printer ? \App\Printer::find($_printer) : \App\Printer::where([['_type', 2], ['_workpoint', $requisition->_workpoint_to]])->first();
                $miniprinter = new MiniPrinterController($printer->ip, 9100);
                if($miniprinter->requisitionTicket($requisition)){
                    $requisition->printed = $requisition->printed + 1;
                    $requisition->save();
                }
            break;
            case 4: /* POR VALIDAR EMBARQUE */
                $requisition->log()->attach(4, [ 'details' => json_encode([
                    "responsable" => $responsable
                ])]);
                $requisition->_status = 4;
                $requisition->save();
            break;
            case 5: /* VALIDANDO EMBARQUE */
                $requisition->log()->attach(5, [ 'details' => json_encode([
                    "responsable" => $responsable,
                    "actors" => $actors
                ])]);
                $requisition->_status = 5;
                $requisition->save();
            break;
            case 6: /* POR ENVIAR */
                $requisition->log()->attach(6, [ 'details' => json_encode([
                    "responsable" => $responsable
                ])]);
                $requisition->_status = 6;
                $requisition->save();
            break;
            case 7: /* EN CAMINO */ //SELECCIONAR VEHICULOS
                $requisition->log()->attach(7, [ 'details' => json_encode([
                    "responsable" => $responsable,
                    "actors" => $actors
                ])]);
                $requisition->_status = 7;
                $requisition->save();
            break;
            case 8: /* POR VALIDAR RECEPCIÓN */
                $requisition->log()->attach(8, [ 'details' => json_encode([
                    "responsable" => $responsable
                ])]);
                $requisition->_status = 8;
                $requisition->save();
            break;
            case 9: /* VALIDANDO RECEPCIÓN */
                $requisition->log()->attach(9, [ 'details' => json_encode([
                    "responsable" => $responsable,
                    "actors" => $actors
                ])]);
                $requisition->_status = 9;
                $requisition->save();
                $_workpoint_from = $requisition->_workpoint_from;
                $requisition->load(['log', 'products' => function($query) use ($_workpoint_from){
                    $query->with(['locations' => function($query)  use ($_workpoint_from){
                        $query->whereHas('celler', function($query) use ($_workpoint_from){
                            $query->where('_workpoint', $_workpoint_from);
                        });
                    }]);
                }]);
                $printer = $printer ? \App\Printer::find($_printer) : \App\Printer::where([['_type', 2], ['_workpoint', $requisition->_workpoint_from]])->first();
                $storePrinter = new MiniPrinterController($printer['domain'], $printer['port']);
                $storePrinter->requisitionTicket($requisition);
            break;
            case 10:
                $requisition->log()->attach(10, [ 'details' => json_encode([
                    "responsable" => $responsable
                ])]);
                $requisition->_status = 10;
                $requisition->save();
            break;
            case 100:
                $requisition->log()->attach(100, [ 'details' => json_encode([
                    "responsable" => $responsable
                ])]);
                $requisition->_status = 100;
                $requisition->save();
            break;
            case 101:
                $requisition->log()->attach(101, [ 'details' => json_encode([])]);
                $requisition->_status = 101;
                $requisition->save();
            break;
        }
        $requisition->refresh('log');
        return [
            "success" => true,
            "printed" => $requisition->printed,
            "status" => $requisition->status,
            "log" => $requisition->log->filter(function($event) use($case){
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
            })
        ];
    }

    public function index(Request $request){
        $workpoints = WorkPoint::where('_type', 1)->get();
        $account = Account::with(['permissions'])->find($this->account->id);
        $permissions = array_column($account->permissions->toArray(), 'id');
        $_types = [];
        if(in_array(29,$permissions)){
            array_push($_types, 1);
        }
        if(in_array(30,$permissions)){
            array_push($_types, 2);
        }
        if(in_array(38,$permissions)){
            array_push($_types, 3);
        }
        if(in_array(39,$permissions)){
            array_push($_types, 4);
        }
        $types = Type::whereIn('id', $_types)->get();
        $status = Process::all();
        $clause = [
            ['_workpoint_from', $this->account->_workpoint]
        ];
        if($this->account->_rol == 4 ||  $this->account->_rol == 5 || $this->account->_rol == 7){
            array_push($clause, ['_created_by', $this->account->_account]);
        }
        if(isset($request->date_from) && isset($request->date_to)){
            $date_from = new \DateTime($request->date_from);
            $date_to = new \DateTime($request->date_to);
            if($request->date_from == $request->date_to){
                $date_from->setTime(0,0,0);
                $date_to->setTime(23,59,59);
            }
        }else{
            $date_from = new \DateTime();
            $date_from->setTime(0,0,0);
            $date_to = new \DateTime();
            $date_to->setTime(23,59,59);
        }
        $requisitions = Requisition::with(['type', 'status', 'to', 'from', 'created_by', 'log'])
                                    ->where($clause)
                                    ->whereIn('_status', [1,2,3,4,5,6,7,8,9,10])
                                    ->withCount(["products"])
                                    ->where([['created_at', '>=', $date_from], ['created_at', '<=', $date_to]])
                                    ->get();
        return response()->json([
            "workpoints" => WorkPoint::all()/* $workpoints */,
            "types" => $types,
            "status" => $status,
            /* "units" => \App\ProductUnit::all(), */
            "requisitions" => RequisitionResource::collection($requisitions)
        ]);
    }

    public function dashboard(Request $request){
        if(isset($request->date_from) && isset($request->date_to)){
            $date_from = new \DateTime($request->date_from);
            $date_to = new \DateTime($request->date_to);
            if($request->date_from == $request->date_to){
                $date_from->setTime(0,0,0);
                $date_to->setTime(23,59,59);
            }
        }else{
            $date_from = new \DateTime();
            $date_from->setTime(0,0,0);
            $date_to = new \DateTime();
            $date_to->setTime(23,59,59);
        }
        $date= new \DateTime();
        $requisitions = Requisition::with(['type', 'status', 'to', 'from', 'created_by', 'log', 'products' => function($query){
                                        $query->with(['prices' => function($query){
                                            $query->whereIn('_type', [1,2,3,4,5])->orderBy('_type');
                                        }, 'units', 'variants']);
                                    }])
                                    ->where('_workpoint_to', $this->account->_workpoint)
                                    ->withCount(["products"])
                                    ->whereIn('_status', [1,2,3,4,5,6,7,8,9,10])
                                    ->where([['created_at', '>=', $date_from], ['created_at', '<=', $date_to]])
                                    ->get();
                                    
        return response()->json([
            "workpoints" => WorkPoint::all(),
            "types" => Type::all(),
            "status" => Process::all(),
            "requisitions" => RequisitionResource::collection($requisitions)
        ]);
    }

    public function find($id){
        $requisition = Requisition::with(['type', 'status', 'products' => function($query){
            $query
            ->selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS category')
            ->with(['units', 'variants', 'prices' => function($query){
                return $query->where('_type', 1);
            }, 'stocks' => function($query){
                return $query->where('_workpoint', $this->account->_workpoint);
            }]);
        }, 'to', 'from', 'created_by', 'log'])
        ->withCount(["products"])->find($id);
        return response()->json(new RequisitionResource($requisition));
    }

    public function nextStep(Request $request){
        $requisition = Requisition::find($request->id);
        $server_status = 200;
        if($requisition){
            $_status = isset($request->_status) ? $request->_status : $requisition->_status+1;
            $_printer = isset($request->_printer) ? $request->_printer : null;
            $_actors = isset($request->_actors) ? $request->_actors : [];
            $process = Process::all()->toArray();
            if(in_array($_status, array_column($process, "id"))){
                $result = $this->log($_status, $requisition, $_printer, $_actors);
                $msg = $result["success"] ? "" : "No se pudo cambiar el status";
                $server_status = $result["success"] ? 200 : 500;
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
                "printed" =>  isset($result) ? $result["printed"] : null,
            ]
        ]);
    }

    public function reimpresion(Request $request){
        $requisition = Requisition::find($request->_requisition);
        $workpoint_to_print = Workpoint::find($this->account->_workpoint);
        $requisition->load(['created_by' ,'log', 'products' => function($query){
            $query->with(['locations' => function($query){
                $query->whereHas('celler', function($query){
                    $query->where('_workpoint', $this->account->_workpoint);
                });
            }]);
        }]);
        $printer = isset($request->_printer) ? \App\Printer::find($request->_printer) : \App\Printer::where([['_type', 2], ['_workpoint', $this->account->_workpoint]])->first();
        $cellerPrinter = new MiniPrinterController($printer->ip, 9100);
        $res = $cellerPrinter->requisitionTicket($requisition);
        $requisition->printed = $requisition->printed +1;
        $requisition->save();
        return response()->json(["success" => $res]);
    }

    public function demoImpresion(Request $request){
        $printer = \App\Printer::find($request->_printer);
        $cellerPrinter = new MiniPrinterController($printer->ip, 9100);
        $res = $cellerPrinter->demo();
        return response()->json(["success" => $res]);
    }

    public function search(Request $request){
        $folio = '';
        $where = [];
        $note = '';
        $created_by = '';
        $created_at = '';
        $from = '';
        $to = '';
        $status = '';
        $between_created = '';

        $requesitions = Requisition::where($where);

        return response()->json();
    }

    public function getVentaFromStore($folio, $workpoint_id, $caja, $to){
        $workpoint = WorkPoint::find($workpoint_id);
        $access = new AccessController($workpoint->dominio);
        $venta = $access->getSaleStore($folio, $caja);
        if($venta){
            if(isset($venta['msg'])){
                return ["msg" => $venta['msg']];
            }
            $toSupply = [];
            foreach($venta['products'] as $row){
                $product = Product::with(['stocks' => function($query) use ($to){
                    $query->where('_workpoint', $to);
                }])->where('code', $row['code'])->first();
                if($product){
                    $required = $row['req'];
                    if($product->_unit == 3){
                        $pieces = $product->pieces == 0 ? 1 : $product->pieces;
                        $toSupply[$product->id] = ['units' => $required, "cost" => $product->cost, 'amount' => round($required/$pieces, 2),  "_supply_by" => 3, 'comments' => '', "stock" => count($product->stocks) > 0 ? $product->stocks[0]->pivot->stock : 0];
                    }else{
                        $toSupply[$product->id] = ['units' => $required, "cost" => $product->cost, 'amount' => $required,  "_supply_by" => 1 , 'comments' => '', "stock" => count($product->stocks) > 0 ? $product->stocks[0]->pivot->stock : 0];
                    }
                }
            }
            return ["notes" => "Pedido venta tienda #".$folio, "products" => $toSupply];
        }
        return ["msg" => "No se tenido conexión con la tienda"];
    }

    public function getToSupplyFromStore($workpoint_id, $workpoint_to){
        $workpoint = WorkPoint::find($workpoint_id);
        $_categories = $this->categoriesByStore($workpoint_id);
        $products = Product::selectRaw('products.*, getSection(products._category) AS section')
        ->with(['stocks' => function($query) use($workpoint_id){
            $query->where([
                ['_workpoint', $workpoint_id],
                ['min', '>', 0],
                ['max', '>', 0],
            ])/* ->orWhere([
                ['_workpoint', $workpoint_to],
                ['stock', '>', 0]
            ]) */;
        }])->whereHas('stocks', function($query) use($workpoint_id, $workpoint_to, $_categories){
            $query->where([
                ['_workpoint', $workpoint_id],
                ['min', '>', 0],
                ['max', '>', 0]
            ])->orWhere([
                ['_workpoint', $workpoint_to],
                ['stock', '>', 0]/* ,
                ['_status', '=', 1] */
            ]);
        }, '>', 1)->where('_status', '=', 1)->havingRaw('section = ?', [$_categories[0]]);
        if(count($_categories)>1){
            $products = $products->orHavingRaw('section = ?', [$_categories[1]]);
        }
        if(count($_categories)>2){
            $products = $products->orHavingRaw('section = ?', [$_categories[2]]);
        }
        if(count($_categories)>3){
            $products = $products->orHavingRaw('section = ?', [$_categories[3]]);
        }
        $products = $products->get();
        
        /**OBTENEMOS STOCKS */
        $toSupply = [];
        foreach($products as $key => $product){
            $stock = $product->stocks[0]->pivot->gen;
            $min = $product->stocks[0]->pivot->min;
            $max = $product->stocks[0]->pivot->max;
            if($max>$stock){
                $required = $max - $stock;
            }else{
                $required = 0;
            }
            if($required > 0){
                if(($product->_unit == 1 && $required>6) || $product->_unit!=1){
                    if($product->_unit == 3){
                        $pieces = $product->pieces == 0 ? 1 : $product->pieces;
                        $boxes = floor($required/$pieces);
                        if($boxes >= 1){
                            $toSupply[$product->id] = ['units' => $required, "cost" => $product->cost, 'amount' => $boxes,  "_supply_by" => 3 , 'comments' => '', "stock" => 0];
                        }
                    }else{
                        $toSupply[$product->id] = ['units' => $required, "cost" => $product->cost, 'amount' => $required,  "_supply_by" => 1 , 'comments' => '', "stock" => 0];
                    }
                }
            }
        }
        return ["products" => $toSupply];
    }

    public function getPedidoFromStore($folio, $to){
        $order = \App\Order::find($folio);
        if($order){
            $toSupply = [];
            $products = $order->products()->with(["stocks" => function($query) use($to){
                $query->where("_workpoint", $to);
            }, 'prices' => function($query){
                $query->where('_type', 7);
            }])->get();
            foreach($products as $product){
                $cost = count($product->prices)> 0 ? $product->prices[0]->pivot->price : 0;
                $toSupply[$product->id] = [
                    'amount' => $product->pivot->amount,
                    '_supply_by' => $product->pivot->_supply_by, 
                    'units' => $product->pivot->units,
                    'cost' => $cost,
                    'total' => $cost * $product->pivot->units,
                    'comments' => $product->pivot->comments,
                    "stock" => count($product->stocks) > 0 ? $product->stocks[0]->pivot->stock : 0
                ];
            }
            return ["notes" => " Pedido preventa #".$folio.", ".$order->name, "products" => $toSupply];
        }
        return ["msg" => "No se encontro el pedido"];
    }

    public function refreshStocks(Requisition $requisition){
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

    public function categoriesByStore($_workpoint){
        /**
         * MOC => 405 - 447
         * PAP => 515 - 552
         * CAL => 588 - 628
         * HOG => 752 - 779
         * ELE => 629 - 669
         * JUG => 448 - 514
         * PAR => 553 - 587 AND 780 - 786
         * */
        /* switch($_workpoint){
            case 1:
            case 4:
            case 5:
            case 7:
            case 13:
            case 9:
                $arr = range(405, 447);
                $arr[] = 791;
                return $arr;
                break;
            case 6:
            case 10:
            case 12:
                $_categories = [range(515,552), range(588, 628), range(752, 779), range(629, 669)];
                return array_merge_recursive(...$_categories);
                break;
            case 11:
                return range(448, 514);
                break;
            case 8:
                $_categories = [range(515,552), range(588, 628), range(448, 514)];
                return array_merge_recursive(...$_categories);
                break;
            case 3:
                $_categories = [range(515,552), range(588, 628), range(752, 779), range(629, 669), range(448, 514), range(553, 587), range(780, 786)];
                return array_merge_recursive(...$_categories);
                break;
        } */

        switch($_workpoint){
            case 1:
                return [];
                break;
            case 4:
                return ["Navidad", "Mochila", "Juguete"];
                break;
            case 5:
                return ["Mochila", "Juguete"];
                break;
            case 7:
                return ["Navidad"];
                break;
            case 13:
                return ["Navidad"];
                break;
            case 9:
                return ["Navidad"];
                break;
            case 6:
                return ["Calculadora", "Electronico", "Hogar"];
            case 10:
                return ["Navidad"];
                break;
            case 12:
                return ["Navidad"];
                break;
            case 11:
                return ["Juguete"];
                break;
            case 8:
                return ["Calculadora", "Juguete", "Papeleria"];
                break;
            case 3:
                return ["Navidad", "Paraguas", "Juguete"];
                break;
        }
    }

    public function getAmount($product, $amount, $_supply_by){
        switch ($_supply_by){
            case 1:
                return $amount;
            break;
            case 2:
                return $amount * 12;
            break;
            case 3:
                return ($amount * $product->pieces);
            break;
            case 4:
                return round($amount * ($product->pieces/2));
            break;
        }
    }

    public function setDeliveryValue(Request $request){
        try{
            $requisition = Requisition::find($request->_requisition);
            $product = $order->products()->where('id', $request->_product)->first();
            if($product){
                $amount = isset($request->amount) ? $request->amount : 1; /* CANTIDAD EN UNIDAD */
                $_supply_by = isset($request->_supply_by) ? $request->_supply_by : 1; /* UNIDAD DE MEDIDA */
                $units = $this->getAmount($product, $amount, $_supply_by); /* CANTIDAD EN PIEZAS */
                $requisition->products()->syncWithoutDetaching([$request->_product => ['toDelivered' => $units]]);
                return response()->json(["success" => true, "server_status" => 200]);
            }else{
                return response()->json(["msg" => "El producto no existe", "success" => true, "server_status" => 404]);
            }
        }catch(Exception $e){
            return response()->json(["msg" => "No se ha podido agregar el producto", "success" => false, "server_status" => 500]);
        }
    }

    public function setReceiveValue(Request $request){
        try{
            $requisition = Requisition::find($request->_requisition);
            $product = $order->products()->where('id', $request->_product)->first();
            if($product){
                $amount = isset($request->amount) ? $request->amount : 1; /* CANTIDAD EN UNIDAD */
                $_supply_by = isset($request->_supply_by) ? $request->_supply_by : 1; /* UNIDAD DE MEDIDA */
                $units = $this->getAmount($product, $amount, $_supply_by); /* CANTIDAD EN PIEZAS */
                $requisition->products()->syncWithoutDetaching([$request->_product => ['toReceived' => $units]]);
                return response()->json(["success" => true, "server_status" => 200]);
            }else{
                return response()->json(["msg" => "El producto no existe", "success" => true, "server_status" => 404]);
            }
        }catch(Exception $e){
            return response()->json(["msg" => "No se ha podido agregar el producto", "success" => false, "server_status" => 500]);
        }
    }
}
