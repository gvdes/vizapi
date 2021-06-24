<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Order;
use App\OrderProcess;
use App\Printer;
use App\PrinterType;
use App\Account;
use App\Product;
use App\OrderLog;
use App\User;
use App\CashRegister;

use App\Http\Resources\Order as OrderResource;
use App\Http\Resources\OrderStatus as OrderStatusResource;

class OrderController extends Controller{
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
        $order = DB::transaction( function () use ($request){
            $now = new \DateTime();
            $num_ticket = Order::where('_workpoint_from', $this->account->_workpoint)->whereDate('created_at', $now)->count()+1;
            $client = isset($request->_client) ? \App\Client::find($request->_client) : \App\Client::find(0);
            $order = Order::create([
                'num_ticket' => $num_ticket,
                'name' => isset($request->_client) ? $client->name : $request->name,
                '_client' => $client->id,
                '_price_list' => $client->_price_list,
                '_created_by' => $this->account->_account,
                '_workpoint_from' => $this->account->_workpoint,
                'time_life' => '00:30:00',
                '_status' => 1
            ]);
            $this->log(1, $order);
            return $order->fresh(['products' => function($query){
                $query->with(['prices' => function($query){
                    $query->whereIn('_type', [1,2,3,4])->orderBy('_type');
                },'variants']);
            }, 'client', 'price_list', 'status', 'created_by', 'workpoint']);
        });
        try{
        }catch(\Exception $e){
            return response()->json(["msg" => "No se ha podido crear el pedido"]);
        }
        return response()->json(new OrderResource($order));
    }

    public function log($case, Order $order, $_printer = null){
        /* $process = OrderProcess::all(); */
        $status = [];
        // Instance or OrderLog to save data
        $log = new OrderLog;
        $log->_order = $order->id;

        switch($case){
            case 1:
                $log->_status = 1;
                $log->details = json_encode([]);
                $user = User::find($this->account->_account);
                $order->_status = 1;
                $order->save();
                // Order was created by
                $user->order_log()->save($log);
            break;
            case 2:
                $log->_status = 2;
                $log->details = json_encode([]);
                $assign_cash_register = $this->getProcess($case);
                $_cash = $this->getCash($order, "Secuencial"/* json_decode($assign_cash_register->details)->mood */);
                $cashRegister = CashRegister::find($_cash);
                // The system assigned casg register
                $order->_status = 2;
                $order->save();
                $cashRegister->order_log()->save($log);
            case 3:
                $log->_status = 3;
                $log->details = json_encode([]);
                if(!$_printer){
                    $printer = Printer::where('_type', 1)->first();
                }else{
                    $printer = Printer::find($_printer);
                }
                $cellerPrinter = new MiniPrinterController($printer->ip, 9100);
                $cellerPrinter->orderReceipt($order);
                $validate = $this->getProcess(3); // Verificar si la validación es necesaria
                if($validate[0]['active']){
                    $log->details = json_encode([]);
                    $user = User::find($this->account->_account);
                    // Order was passed next status by
                    $user->order_log()->save($log);
                    $order->_status = 3;
                    $order->save();
                    break;
                }
            case 4:
                $log->_status = 4;
                $log->details = json_encode([]);
                $to_supply = $this->getProcess(4);
                if($to_supply[0]['active']){
                    $bodegueros = Account::with('user')->whereIn('_rol', [6,7])->whereNotIn('_status', [4,5])->count();
                    $tickets = 1000;
                    $in_suppling = Order::where([
                        ['_workpoint_from', $this->account->_workpoint],
                        ['_status', $case] // Status Surtiendo
                    ])->count(); // Para saber cuantos pedidos se estan surtiendo
                    if($in_suppling>($bodegueros*$tickets)){
                        // Poner en status 4 (el pedido esta por surtir)
                        $log->details = json_encode([]);
                        $user = User::find($this->account->_account);
                        // Order was passed next status by
                        $user->order_log()->save($log);
                        $order->_status = 4;
                        $order->save();
                        break;
                    }
                }
            case 5:
                if(!$_printer){
                    $printer = Printer::where('_type', 3)->first();
                }else{
                    $printer = Printer::find($_printer);
                }
                $cellerPrinter = new MiniPrinterController($printer->ip, 9100);
                $cellerPrinter->orderTicket($order);
                $log->details = json_encode([]);
                $user = User::find($this->account->_account);
                // Order was passed next status by
                $user->order_log()->save($log);
                $order->_status = 5;
                $order->save();
                /* $order->history()->attach($case, ["details" => json_encode([]), '_responsable' => $this->account->_account]); */
                break;
            case 6:
                $validate = $this->getProcess($case); //Verificar si la validación es necesaria
                if($validate->active){
                    $order->history()->attach($case, ["details" => json_encode([]), '_responsable' => $this->account->_account]);
                    break;
                }
            case 7:
                $order->history()->attach($case, ["details" => json_encode([]), '_responsable' => $this->account->_account]);
                break;
            case 8:
                $end_to_sold = $this->getProcess($case);
                if($end_to_sold->active){
                    $cajeros = 4;
                    $in_cash_register = Order::where([
                        ['_workpoint_from', $this->_account->_workpoint],
                        ['_status', $case] //status Cobrando
                    ])->count(); //Para saber cuantos pedidos se estan surtiendo
                    if($in_cash_register = 0){
                        //poner en status 7 (el pedido esta en caja)
                        break;
                    }
                }
            case 9:
                $order->history()->attach($case, ["details" => json_encode([]), '_responsable' => $this->account->_account]);
                break;
            case 10:
                $order->history()->attach($case, ["details" => json_encode([]), '_responsable' => $this->account->_account]);
                break;
        }
        return $status;
    }

    public function nextStep(Request $request){
        $order = Order::with('history','products')->find($request->_order);
        if($order){
            $_status = $order->_status+1;
            $_printer = isset($request->_printer) ? $request->_printer : null;
            if(($_status>0 && $_status<10) || $_status == 100){
                $result = $this->log($_status, $order, $_printer);
                return response()->json(['success' => true, 'status' => $result]);
                if($result){
                    return response()->json(['success' => true, 'status' => $result]);
                }
                return response()->json(['success' => false, 'status' => null, 'msg' => "No se ha podido cambiar el status"]);
            }
            return response()->json(['success' => false, 'msg' => "Status no válido"]);
        }
        return response()->json(['success' => false, 'msg' => "Orden desconocida"]);
    }

    public function cancelled(Request $request){
        $order = Order::with('history','products')->find($request->_order);
        if($order){
            
            $log = new OrderLog;
            $log->_order = $order->id;
            $log->_status = 100;
            $log->details = json_encode([]);
            $user = User::find($this->account->_account);

            $order->_status = 100;
            $order->save();
            // Order was created by
            $user->order_log()->save($log);
            return response()->json(["success" => true, "status" => []]);
        }
        return response()->json(['success' => false, 'msg' => "Orden desconocida"]);
    }

    public function addProduct(Request $request){
        try{
            $order = Order::find($request->_order);
            if($this->account->_account == $order->_created_by || in_array($this->account->_rol, [1,2,3])){
                $product = Product::with(['prices' => function($query){
                    $query->whereIn('_type', [1,2,3,4])->orderBy('_type');
                }, 'units', 'stocks' => function($query){
                    $query->where('_workpoint', $this->account->_workpoint);
                }])->find($request->_product);
                if($product){
                    $amount = isset($request->amount) ? $request->amount : 1; /* CANTIDAD EN UNIDAD */
                    $_supply_by = isset($request->_supply_by) ? $request->_supply_by : 1; /* UNIDAD DE MEDIDA */
                    $units = $this->getAmount($product, $amount, $_supply_by); /* CANTIDAD EN PIEZAS */
                    if($order->_client==0){
                        $price_list = $order->_price_list;
                    }else{
                        $price_list = 1;
                    }
                    $index_price = array_search($price_list, array_column($product->prices->toArray(), 'id'));
                    if($index_price === 0 || $index_price>0){
                        $price = $product->prices[$index_price]->pivot->price;
                        if($price > 0){
                            $order->products()->syncWithoutDetaching([$request->_product => ['kit' => "", 'amount' => $amount ,'units' => $units, "_supply_by" => $_supply_by, "_price_list" => $price_list, 'comments' => $request->comments, 'price' => $price, "total" => ($units * $price)]]);
                            return response()->json([
                                "id" => $product->id,
                                "code" => $product->code,
                                "name" => $product->name,
                                "description" => $product->description,
                                "pieces" => $product->pieces,
                                "prices" => $product->prices->map(function($price){
                                    return [
                                        "id" => $price->id,
                                        "name" => $price->name,
                                        "price" => $price->pivot->price,
                                    ];
                                }),
                                "ordered" => [
                                    "comments" => $request->comments,
                                    "amount" => $amount,
                                    "units" => $units,
                                    "stock" => 0,
                                    "_supply_by" => $_supply_by,
                                    "_price_list" => $price_list,
                                    "price" => $price,
                                    "total" => $units * $price,
                                    "kit" => "",
                                ],
                                "stocks" => [
                                    [
                                        "alias" => count($product->stocks)>0 ? $product->stocks[0]->alias : "",
                                        "name" => count($product->stocks)>0 ? $product->stocks[0]->name : "",
                                        "stock"=> count($product->stocks)>0 ? $product->stocks[0]->pivot->stock : 0,
                                        "gen" => count($product->stocks)>0 ? $product->stocks[0]->pivot->gen : 0,
                                        "exh" => count($product->stocks)>0 ? $product->stocks[0]->pivot->exh : 0,
                                        "min" => count($product->stocks)>0 ? $product->stocks[0]->pivot->min : 0,
                                        "max"=> count($product->stocks)>0 ? $product->stocks[0]->pivot->min : 0,
                                    ]
                                ]
                            ]);
                        }else{
                            return response()->json(["msg" => "El producto tiene el precio en 0", "success" => false]);
                        }
                    }else{
                        return response()->json(["msg" => "El producto no tiene precios", "success" => false]);
                    }
                }else{
                    return response()->json(["msg" => "Producto no encontrado", "success" => false]);
                }
            }else{
                return response()->json(["msg" => "No puedes agregar productos", "success" => false]);
            }
        }catch(Exception $e){
            return response()->json(["msg" => "No se ha podido agregar el producto", "success" => false]);
        }
    }

    public function removeProduct(Request $request){
        try{
            $order = Order::find($request->_order);
            $order->products()->detach([$request->_product]);
            return response()->json(["success" => true]);
        }catch(Exception $e){
            return response()->json(["msg" => "No se ha podido eliminar el producto"]);
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

    public function calculatePriceList($product, $units){
        if($units >= $product->pieces){
            return 5;
        }elseif($units>=round($product->pieces/2)){
            return 3;
        }elseif($units>=3){
            return 2;
        }else{
            return 1;
        }
    }
    
    public function index(Request $request){
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

        $status = $this->getProcess();
        /* $printers_types = $this->getPrintersTypes(); */
        $status_by_rol = $this->getStatusByRol();
        $printers = PrinterType::with(['printers' => function($query){
            $query->where('_workpoint', $this->account->_workpoint);
        }])->orderBy('id')->get();

        $clause = [
            ['_workpoint_from', $this->account->_workpoint]
        ];

        if($this->account->_rol == 4 || $this->account->_rol == 5 /* || $this->account->_rol == 7 */){
            array_push($clause, ['_created_by', $this->account->_account]);
        }

        $status_with_orders = OrderProcess::with(['config' => function($query){
            $query->where('_workpoint', $this->account->_workpoint);
        },'orders' => function($query) use($clause, $date_from, $date_to){
            $query->withCount('products')->with(['status', 'created_by', 'workpoint'])->where($clause)->where([['orders.created_at', '>=', $date_from], ['orders.created_at', '<=', $date_to]]);
        }])->whereIn('id', $status_by_rol)->orderBy('id')->get();

        /* $orders = Order::withCount('products')->with(['status', 'created_by', 'workpoint'])->where($clause)->where([['created_at', '>=', $date_from], ['created_at', '<=', $date_to]])->whereIn('_status', $status_by_rol)->get()->groupBy('status.name'); */

        return response()->json([
            'status' => OrderStatusResource::collection($status_with_orders),
            'printers' => $printers,
            /* 'orders' => $orders */
            /* 'orders' => OrderResource::collection($orders) */
        ]);
    }

    public function find($id){
        $order = Order::with(['products' => function($query){
            $query->with(['prices' => function($query){
                $query->whereIn('_type', [1,2,3,4])->orderBy('_type');
            },'variants', 'stocks' => function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            }]);
        }, 'client', 'price_list', 'status', 'created_by', 'workpoint', 'history'])->find($id);
        /* $groupBy = $order->products->map(function($product){
            $product->locations->sortBy('path');
            return $product;
        })->groupBy(function($product){
            if(count($product->locations)>0){
                return explode('-',$product->locations[0]->path)[0];
            }else{
                return '';
            }
        })->sortBy(function($product){
            if(count($product->locations)>0){
                return $product->locations[0]->path;
            }
            return '';
        })->sortKeys(); */
        return response()->json(new OrderResource($order));
    }

    public function config(){
        $status = OrderProcess::with(['config' => function($query){
            $query->where('_workpoint', $this->account->_workpoint);
        }])->get()->map(function($status){
            return [
                "id" => $status->id,
                "name" => $status->name,
                "active" => $status->config[0]->active,
                "allow" => $status->allow,
                "details" => $status->config[0]->details
            ];
        });
        return response()->json([
            'status' => $status,
        ]);
    }

    public function getProcess($_status = "all"){
        if($_status == "all"){
            $status = OrderProcess::with(['config' => function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            }])->get()->map(function($status){
                return [
                    "id" => $status->id,
                    "name" => $status->name,
                    "active" => $status->config[0]->pivot->active,
                    "allow" => $status->allow,
                    "details" => json_decode($status->config[0]->pivot->details)
                ];
            });
        }else{
            $status = OrderProcess::with(['config' => function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            }])->where('id', $_status)->get()->map(function($status){
                return [
                    "id" => $status->id,
                    "name" => $status->name,
                    "active" => $status->config[0]->pivot->active,
                    "allow" => $status->allow,
                    "details" => $status->config[0]->pivot->details
                ];
            });
        }
        return $status;
    }

    public function changeConfig(Request $request){
        $process = OrderProccess::with(['config' => function($query){
            $query->where('_workpoint', $this->account->_workpoint);
        }])->find($request->_status);
        if($process){
            if($process->allow){
                $process->config()->updateExistingPivot($this->account->_workpoint, ['active' => !$process->config[0]->active]);
            }else{
                return response()->json([
                    "success" => false,
                    "msg" => "El status no puede ser modificado"
                ]);
            }
        }else{
            return response()->json([
                "success" => false,
                "msg" => "No se encontro el status"
            ]);
        }
    }

    public function migrateToRequesition(Request $request){
        $requisition = App\Requisition::find($request->_requisition);
        if($requisition){
            
        }
        return response()->json(["msg" => "No se encontro el pedido", "success" => false]);
    }

    public function reimpresion(Request $request){
        $order = Order::find($request->_order);
        $_workpoint_to = $order->_workpoint_from;
        $order->load(['created_by', 'products' => function($query) use ($_workpoint_to){
            $query->with(['locations' => function($query)  use ($_workpoint_to){
                $query->whereHas('celler', function($query) use ($_workpoint_to){
                    $query->where('_workpoint', $_workpoint_to);
                });
            }]);
        }, 'history']);
        $printer = Printer::find($request->_printer);
        $cellerPrinter = new MiniPrinterController($printer->ip, 9100);
        $res = $cellerPrinter->orderTicket($order);
        if($res){
            $order->printed = $order->printed +1;
            $order->save();
        }
        return response()->json(["success" => $res]);
    }

    public function getCash($order, $mood){
        switch($mood){
            case "Secuencial":
                // 1.- Obtener cajas
                $cashRegisters = CashRegister::withCount('order_log')->where([['_workpoint', $this->account->_workpoint], ["_status", 1]])->get()->sortBy('num_cash');
                $inCash = array_column($cashRegisters->toArray(), 'order_log_count');
                $_cash = $cashRegisters[array_search(min($inCash), $inCash)]->id;
                return $_cash;
        }

    }

    public function initConfiguration(){
        $status = OrderProcess::get();
        $workpoints = \App\WorkPoint::whereIn('id', range(1,15))->get();
        foreach($workpoints as $workpoint){
            foreach($status as $row){
                $row->config()->attach($workpoint->id, ["active" => $row->active, "details" => json_encode([])]);
            }
        }
        return response()->json($status);
    }

    public function getPrintersTypes(){
        switch($this->account->_rol){
            case 1:
            case 2:
            case 3:
                return [1,2,3,4];
            case 4: //vendedor
                return [1];
            case 5: //Cajero
                return [2];
            case 6: //Administrador de almacenes
            case 7: //Bodeguero
                return [3,4];
            case 9:
                return [2];
        }
    }

    public function getStatusByRol(){
        switch($this->account->_rol){
            case 1:
            case 2:
            case 3:
                return range(1,10);
            case 4: //vendedor
                return range(1,10);
            case 5: //Cajero
                return range(6,9);
            case 6: //Administrador de almacenes
            case 7: //Bodeguero
                return range(3,5);
            case 9:
                return [6,7];
        }
    }
}
