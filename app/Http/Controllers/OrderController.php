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

use App\Exports\WithMultipleSheetsExport;
use Maatwebsite\Excel\Facades\Excel;

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
        try{
            $order = DB::transaction( function () use ($request){
                $now = new \DateTime();
                $_parent = null;
                if(isset($request->_order) && $request->_order){
                    $parent = Order::find($request->_order);
                    if(!$parent){
                        return response()->json(["success" => false, "server_status" => 404, "msg" => "No se encontro el pedido"]);
                    }else{
                        $_parent = $parent->_order ? $parent->_order : $parent->id;
                    }
                }
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
                    '_status' => 1,
                    '_order' => $_parent ? $_parent : null
                ]);
                $this->log(1, $order);
                return $order->fresh(['products' => function($query){
                    $query->with(['prices' => function($query){
                        $query->whereIn('_type', [1,2,3,4])->orderBy('_type');
                    },'variants']);
                }, 'client', 'price_list', 'status', 'created_by', 'workpoint', 'history']);
            });
        }catch(\Exception $e){
            return response()->json(["msg" => "No se ha podido crear el pedido", "server_status" => 500]);
        }
        $order->parent = $order->_order ? Order::with(['status', 'created_by'])->find($order->_order) : [];
        $order->children = Order::with(['status', 'created_by'])->where('_order', $order->id)->get();
        return response()->json(new OrderResource($order));
    }

    public function log($case, Order $order, $_printer = null){
        // Instance or OrderLog to save data
        $events = 0;
        switch($case){
            case 1: //Levantando pedido
                $user = User::find($this->account->_account);
                $order->_status = 1;
                $order->save();
                $events++;
                $log = $this->createLog($order->id, 1, []);
                $user->order_log()->save($log); // Order was created by
            break;
            case 2: //Asignar caja
                $assign_cash_register = $this->getProcess($case);
                $_cash = $this->getCash($order, "Secuencial"/* json_decode($assign_cash_register->details)->mood */);
                $cashRegister = CashRegister::find($_cash);
                $order->_status = 2;
                $order->save();
                $events++;
                $log = $this->createLog($order->id, 2, []);
                $cashRegister->order_log()->save($log);// The system assigned cash register
            case 3: //Recepción
                if(!$_printer){
                    $printer = Printer::where([['_type', 1], ['_workpoint', $this->account->_workpoint]])->first();
                }else{
                    $printer = Printer::find($_printer);
                }
                $cellerPrinter = new MiniPrinterController($printer->ip, 9100, 5);
                $_workpoint_to = $order->_workpoint_from;
                $order->refresh(['created_by', 'products' => function($query) use ($_workpoint_to){
                    $query->with(['locations' => function($query)  use ($_workpoint_to){
                        $query->whereHas('celler', function($query) use ($_workpoint_to){
                            $query->where([['_workpoint', $_workpoint_to], ['_type', 1]]);
                        });
                    }]);
                }, 'client', 'price_list', 'status', 'created_by', 'workpoint', 'history']);
                $cash_ = $order->history->filter(function($log){
                    return $log->pivot->_status == 2;
                })->values()->all()[0];
                /* $cash_ = $a[0]->pivot->responsable; */
                $cellerPrinter->orderReceipt($order, $cash_); /* INVESTIGAR COMO SALTAR A LA SIGUIENTE SENTENCIA DESPUES DE X TIEMPO */
                $validate = $this->getProcess(3); // Verificar si la validación es necesaria
                if($validate[0]['active']){
                    $user = User::find($this->account->_account);
                    // Order was passed next status by
                    $log = $this->createLog($order->id, 3, []);
                    $user->order_log()->save($log);
                    $order->_status = 3;
                    $order->save();
                    $events++;
                    break;
                }
            case 4: //Por surtir
                $to_supply = $this->getProcess(4);
                if($to_supply[0]['active']){
                    $bodegueros = Account::with('user')->whereIn('_rol', [6,7])->whereNotIn('_status', [4,5])->count();
                    $tickets = 100000000;
                    $in_suppling = Order::where([
                        ['_workpoint_from', $this->account->_workpoint],
                        ['_status', $case] // Status Surtiendo
                    ])->count(); // Para saber cuantos pedidos se estan surtiendo
                    if($in_suppling>($bodegueros*$tickets)){
                        // Poner en status 4 (el pedido esta por surtir)
                        $user = User::find($this->account->_account);
                        // Order was passed next status by
                        $log = $this->createLog($order->id, 4, []);
                        $user->order_log()->save($log);
                        $order->_status = 4;
                        $order->save();
                        $events++;
                        break;
                    }
                }
            case 5: //Surtiendo
                $_workpoint_to = $order->_workpoint_from;
                
                $order->load(['created_by', 'products' => function($query) use ($_workpoint_to){
                    $query->with(['locations' => function($query)  use ($_workpoint_to){
                        $query->whereHas('celler', function($query) use ($_workpoint_to){
                            $query->where([['_workpoint', $_workpoint_to], ['_type', 1]]);
                        });
                    }]);
                }, 'client', 'price_list', 'status', 'created_by', 'workpoint', 'history']);
                $cash_ = $order->history->filter(function($log){
                    return $log->pivot->_status == 2;
                })->values()->all()[0];
                $printer = Printer::where([['_type', 2], ['_workpoint', $this->account->_workpoint], ['name', 'LIKE', '%'.$cash_->pivot->responsable->num_cash.'%']])->first();
                if(!$printer){
                    $printer = Printer::where([['_type', 2], ['_workpoint', $this->account->_workpoint]])->first();
                }
                $cellerPrinter = new MiniPrinterController($printer->ip, 9100, 5);
                /* $cash_ = $cash_[0]->pivot->responsable; */
                $cellerPrinter->orderTicket($order, $cash_);
                $user = User::find($this->account->_account);
                // Order was passed next status by
                $log = $this->createLog($order->id, 5, []);
                $user->order_log()->save($log);
                $order->_status = 5;
                $order->save();
                $events++;
                break;
            case 6: //Por validar
                $validate = $this->getProcess(6); //Verificar si la validación es necesaria
                if($validate[0]['active']){
                    $user = User::find($this->account->_account);
                    $log = $this->createLog($order->id, 6, []);
                    $user->order_log()->save($log);
                    $order->_status = 6;
                    $order->save();
                    $events++;
                    break;
                }
            case 7: //Validando mercancía
                $user = User::find($this->account->_account);
                $log = $this->createLog($order->id, 7, []);
                $user->order_log()->save($log);
                $order->_status = 7;
                $order->save();
                $events++;
                break;
            case 8: //En caja
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
            case 9: //Cobrando
                $order->history()->attach($case, ["details" => json_encode([]), '_responsable' => $this->account->_account]);
                break;
            case 10: //Finalizado
                $order->history()->attach($case, ["details" => json_encode([]), '_responsable' => $this->account->_account]);
                break;
            case 100: //Cancelado
                $user = User::find($this->account->_account);
                $log = $this->createLog($order->id, 100, []);
                $user->order_log()->save($log);
                $order->_status = 100;
                $order->save();
                $events++;
                break;
            case 101: //Modificando
                $user = User::find($this->account->_account);
                $log = $this->createLog($order->id, 101, []);
                $user->order_log()->save($log);
                $order->_status = 101;
                $order->save();
                $events++;
                break;
        }
        $order->refresh('history');
        $news_logs = $order->history->filter(function($statu) use($case){
            return $statu->id >= $case;
        })->values()->map(function($event){
            return [
                "id" => $event->id,
                "name" => $event->name,
                "active" => $event->active,
                "allow" => $event->allow,
                "details" => json_decode($event->pivot->details),
                "created_at" => $event->pivot->created_at->format('Y-m-d H:i')
            ];
        })->toArray();
        return array_slice($news_logs, count($news_logs)-$events);
    }

    public function nextStep(Request $request){
        $order = Order::find($request->_order);
        $_workpoint_to = $order->_workpoint_from;
        if($order){
            $order->load(['created_by', 'products' => function($query) use ($_workpoint_to){
                $query->with(['locations' => function($query)  use ($_workpoint_to){
                    $query->whereHas('celler', function($query) use ($_workpoint_to){
                        $query->where([['_workpoint', $_workpoint_to], ['_type', 1]]);
                    });
                }]);
            }, 'client', 'price_list', 'status', 'created_by', 'workpoint', 'history']);
            $_status = $this->getNextStatus($order);
            $_printer = isset($request->_printer) ? $request->_printer : null;
            $_process = array_column(OrderProcess::all()->toArray(), 'id');
            if(in_array($_status, $_process)){
                $result = $this->log($_status, $order, $_printer);
                if($result){
                    return response()->json(['success' => true, 'status' => $result, "server_status" => 200]);
                }
                return response()->json(['success' => false, 'status' => null, 'msg' => "No se ha podido cambiar el status", "server_status" => 500]);
            }
            return response()->json(['success' => false, 'msg' => "Status no válido", "server_status" => 400]);
        }
        return response()->json(['success' => false, 'msg' => "Orden desconocida", "server_status" => 404]);
    }

    public function cancelled(Request $request){
        $order = Order::find($request->_order);
        if($order){
            if($order->_status == 100){
                return response()->json(['success' => false, 'msg' => "El pedido ya esta cancelado", "server_status" => 400]);
            }
            $log = new OrderLog;
            $log->_order = $order->id;
            $log->_status = 100;
            $log->details = json_encode([]);
            $user = User::find($this->account->_account);
            $order->_status = 100;
            $order->save();
            // Order was cancelled by
            $user->order_log()->save($log);
            $order->refresh('history');
            $log = $order->history->filter(function($statu){
                return $statu->id >= 100;
            })->values()->map(function($event){
                return [
                    "id" => $event->id,
                    "name" => $event->name,
                    "active" => $event->active,
                    "allow" => $event->allow,
                    "details" => json_decode($event->pivot->details),
                    "created_at" => $event->pivot->created_at->format('Y-m-d H:i')
                ];
            })->toArray();
            return response()->json(["success" => true, "status" => array_slice($log, count($log)-1,1), "server_status" => 200]);
        }
        return response()->json(['success' => false, 'msg' => "Pedido no encontrado", "server_status" => 404]);
    }

    public function editting(Request $request){
        $order = Order::find($request->_order);
        if($order){
            if($order->_status >= 5 && $order->_status == 101){
                return response()->json(['success' => false, 'msg' => "El pedido ya no puede ser modificada", "server_status" => 400]);
            }
            $log = new OrderLog;
            $log->_order = $order->id;
            $log->_status = 101;
            $log->details = json_encode([]);
            $user = User::find($this->account->_account);
            $order->_status = 101;
            $order->save();
            // Order was cancelled by
            $user->order_log()->save($log);
            $order->refresh('history');
            $log = $order->history->filter(function($statu){
                return $statu->id >= 101;
            })->values()->map(function($event){
                return [
                    "id" => $event->id,
                    "name" => $event->name,
                    "active" => $event->active,
                    "allow" => $event->allow,
                    "details" => json_decode($event->pivot->details),
                    "created_at" => $event->pivot->created_at->format('Y-m-d H:i')
                ];
            });
            return response()->json(["success" => true, "status" => $log, "server_status" => 200]);
        }
        return response()->json(['success' => false, 'msg' => "Orden desconocida", "server_status" => 404]);
    }

    public function addProduct(Request $request){
        try{
            $order = Order::find($request->_order);
            $prices = $order->_price_list ? [$order->_price_list] : [1,2,3,4];
            $product = Product::with(['prices' => function($query) use($prices){
                $query->whereIn('_type', $prices)->orderBy('_type');
            }, 'units', 'stocks' => function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            }])->find($request->_product);
            if($product){
                //if(count($product->stocks)>0 && $product->stocks[0]->pivot->_status != 1){ return response()->json(["msg" => "No puedes agregar ese producto", "success" => false]); }
                $amount = isset($request->amount) ? $request->amount : 1; /* CANTIDAD EN UNIDAD */
                $_supply_by = isset($request->_supply_by) ? $request->_supply_by : 1; /* UNIDAD DE MEDIDA */
                $units = $this->getAmount($product, $amount, $_supply_by); /* CANTIDAD EN PIEZAS */
                if($order->_client==0){
                    $price_list = $order->_price_list;
                }else{
                    $price_list = 1; /* PRICE LIST */
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
                        return response()->json(["msg" => "El producto tiene el precio en 0", "success" => false, "server_status" => 400]);
                    }
                }else{
                    return response()->json(["msg" => "El producto no tiene precios", "success" => false, "server_status" => 400]);
                }
            }else{
                return response()->json(["msg" => "Producto no encontrado", "success" => false, "server_status" => 404]);
            }
        }catch(Exception $e){
            return response()->json(["msg" => "No se ha podido agregar el producto", "success" => false, "server_status" => 500]);
        }
    }

    public function setDeliveryValue(Request $request){
        try{
            $order = Order::find($request->_order);
            if($this->account->_account == $order->_created_by || in_array($this->account->_rol, [1,2,3])){
                $product = $order->products()->where('id', $request->_product)->first();
                if($product){
                    $amount = isset($request->amount) ? $request->amount : 1; /* CANTIDAD EN UNIDAD */
                    $_supply_by = isset($request->_supply_by) ? $request->_supply_by : 1; /* UNIDAD DE MEDIDA */
                    $units = $this->getAmount($product, $amount, $_supply_by); /* CANTIDAD EN PIEZAS */
                    if($order->_client==0){
                        $price_list = $order->_price_list;
                    }else{
                        $price_list = 1; /* PRICE LIST */
                    }
                    $index_price = array_search($price_list, array_column($product->prices->toArray(), 'id'));

                    $order->products()->syncWithoutDetaching([$request->_product => ['toDelivered' => $units]]);
                    return response()->json(["success" => true]);
                    /* if($product->pivot->amount != $request->amount){
                        return response()->json(["Se recalculan datos"]);
                    }else{
                        return response()->json(["Se guarda la cantidad"]);
                    } */
                }else{
                    return response()->json(["Se añade el producto a la cesta", "success" => true, "server_status" => 200]);
                }
            }else{
                return response()->json(["msg" => "No puedes agregar productos", "success" => false, "server_status" => 400]);
            }
        }catch(Exception $e){
            return response()->json(["msg" => "No se ha podido agregar el producto", "success" => false, "server_status" => 500]);
        }
    }

    public function removeProduct(Request $request){
        try{
            $order = Order::find($request->_order);
            $order->products()->detach([$request->_product]);
            return response()->json(["success" => true, "server_status" => 200]);
        }catch(Exception $e){
            return response()->json(["msg" => "No se ha podido eliminar el producto", "server_status" => 500]);
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
        $status_by_rol = $this->getStatusByRol();
        $printers = PrinterType::with(['printers' => function($query){
            $query->where('_workpoint', $this->account->_workpoint);
        }])->orderBy('id')->get();

        $clause = [
            ['_workpoint_from', $this->account->_workpoint]
        ];

        $orders = Order::withCount('products')->with(['status', 'created_by', 'workpoint'])->where($clause)->where([['created_at', '>=', $date_from], ['created_at', '<=', $date_to]])->whereIn('_status', $status_by_rol)->get();

        return response()->json([
            'status' => $status,
            'printers' => $printers,
            'orders' => OrderResource::collection($orders),
            "server_status" => 200
        ]);
        /* $orders = Order::withCount('products')->with(['status', 'created_by', 'workpoint'])->where($clause)->where([['created_at', '>=', $date_from], ['created_at', '<=', $date_to]])->whereIn('_status', $status_by_rol)->paginate(20);
        $result = OrderResource::collection($orders);
        return response($result->response()->getData(true), 200); */
    }

    public function find($id){
        $order = Order::with(['products' => function($query){
            $query->with(['prices' => function($query){
                $query->whereIn('_type', [1,2,3,4])->orderBy('_type');
            },'variants', 'stocks' => function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            }]);
        }, 'client', 'price_list', 'status', 'created_by', 'workpoint', 'history'])->find($id);
        $order->parent = $order->_order ? Order::with(['status', 'created_by'])->find($order->_order) : [];
        $order->children = Order::with(['status', 'created_by'])->where('_order', $order->id)->get();
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
        $process = OrderProcess::with(['config' => function($query){
            $query->where('_workpoint', $this->account->_workpoint);
        }])->find($request->_status);
        if($process){
            if($process->allow){
                $process->config()->updateExistingPivot($this->account->_workpoint, ['active' => !$process->config[0]->pivot->active]);
                return response()->json([
                    "success" => true
                ]);
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
                    $query->where([['_workpoint', $_workpoint_to], ['_type', 1]]);
                });
            }]);
        }, 'client', 'price_list', 'status', 'created_by', 'workpoint', 'history']);

        $cash_ = $order->history->filter(function($log){
            return $log->pivot->_status == 2;
        })->values()->all()[0];

        $in_coming = $order->history->filter(function($log){
            return $log->pivot->_status == 5;
        })->values()->all()[0];
        $printer = Printer::find($request->_printer);
        $cellerPrinter = new MiniPrinterController($printer->ip, 9100, 5);
        $res = $cellerPrinter->orderTicket($order, $cash_, $in_coming);
        if($res){
            $order->printed = $order->printed +1;
            $order->save();
        }
        return response()->json(["success" => $res, "server_status" => 200]);
    }

    public function getCash($order, $mood){
        if($order->_order){
            $order = Order::with('history')->find($order->_order);
            $cash_ = $order->history->filter(function($log){
                return $log->pivot->_status == 2;
            })->values()->all();
            if(count($cash_)>0){
                return $cash_[0]->pivot->responsable->id;
            }
        }
        switch($mood){
            case "Secuencial":
                // 1.- Obtener cajas
                $date_from = new \DateTime();
                $date_from->setTime(0,0,0);
                $date_to = new \DateTime();
                $date_to->setTime(23,59,59);
                $cashRegisters = CashRegister::withCount(['order_log' => function($query) use($date_from, $date_to){
                    $query->where([['created_at', '>=', $date_from], ['created_at', '<=', $date_to]]);
                }])->where([['_workpoint', $this->account->_workpoint], ["_status", 1]])->get()->sortBy('num_cash');
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
            case 8:
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

    public function createLog($_order, $_status, $details){
        $log = new OrderLog;
        $log->_order = $_order;
        $log->_status = $_status;
        $log->details = json_encode($details);
        return $log;
    }

    public function test(Request $request){
        /* $order = Order::with('history')->find($request->_order);
        $responsables = $order->history->map(function($log){
            return $log->pivot->responsable;
        }); */
        $date_from = new \DateTime();
        $date_from->setTime(0,0,0);
        $date_to = new \DateTime();
        $date_to->setTime(23,59,59);
        /*  */
        //$orders = Order::with(['history'])->where([['_workpoint_from', $this->account->_workpoint],['created_at', '>=', $date_from], ['created_at', '<=', $date_to]])->orderBy('num_ticket', 'desc')->first();
        $order = Order::with(['history'])->where([['_workpoint_from', $this->account->_workpoint],['created_at', '>=', "2021-08-28 00:00:00"], ['created_at', '<=', "2021-08-28 23:59:59"], ['_status', '>', 2]])->orderBy('num_ticket', 'desc')->first();
        $cash = $order->history->filter(function($log){
            return $log->pivot->_status == 2;
        })[0]->pivot->responsable->num_ticket;
        /* return response()->json(OrderResource::collection($orders)); */
        return response()->json($cash);
        return response()->json(new OrderResource($orders));
    }

    public function addMassiveProducts(Request $request){
        try{
            $order = Order::find($request->_order);
            $notFound = [];
            $notPrices = [];
            $notPrice = [];

            if($order){
                foreach($request->products as $product_code) {
                    $prices = $order->_price_list ? [$order->_price_list] : [1,2,3,4];
                    $product = Product::with(['prices' => function($query) use($prices){
                        $query->whereIn('_type', $prices)->orderBy('_type');
                    }, 'units', 'stocks' => function($query) use($order){
                        $query->where('_workpoint', $order->_workpoint_from);
                    }])->where("code", $product_code["code"])->first();
                    if($product){
                        $amount = isset($request->amount) ? $request->amount : 1; /* CANTIDAD EN UNIDAD */
                        $_supply_by = isset($request->_supply_by) ? $request->_supply_by : 1; /* UNIDAD DE MEDIDA */
                        $units = $this->getAmount($product, $amount, $_supply_by); /* CANTIDAD EN PIEZAS */
                        if($order->_client==0){
                            $price_list = $order->_price_list;
                        }else{
                            $price_list = 1; /* PRICE LIST */
                        }
                        $index_price = array_search($price_list, array_column($product->prices->toArray(), 'id'));
                        if($index_price === 0 || $index_price>0){
                            $price = $product->prices[$index_price]->pivot->price;
                            if($price > 0){
                                $order->products()->syncWithoutDetaching([$product->id => ['kit' => "", 'amount' => $amount ,'units' => $units, "_supply_by" => $_supply_by, "_price_list" => $price_list, 'comments' => "", 'price' => $price, "total" => ($units * $price)]]);
                            }else{
                                /* return response()->json(["msg" => "El producto tiene el precio en 0", "success" => false]); */
                                $notPrices[] = $product_code["code"];
                            }
                        }else{
                            /* return response()->json(["msg" => "El producto no tiene precios", "success" => false]); */
                            $notPrice[] = $product_code["code"];
                        }
                    }else{
                        $notFound[] = $product_code["code"];
                    }
                }
            }
            return response()->json([
                "notFound" => $notFound,
                "notPrices" => $notPrices,
                "notPrice" => $notPrice
            ]);
        }catch(Exception $e){
            return response()->json(["msg" => "No se ha podido agregar el producto", "success" => false]);
        }
    }

    public function exportExcel(Request $request){
        $orders = [];
        foreach($request->_orders as $_order){
            $order = Order::with(['products'])->find($_order);
            if($order){
                /* $orders[$_order] = $order->products->map(function($product){
                    return [
                        "Código" => $product->name,
                        "Modelo" => $product->code,
                        "Descripción" => $product->description,
                        "Piezas" => $product->pivot->units
                    ];
                })->toArray(); */
                $orders[] = $order->products->map(function($product) use($_order){
                    return [
                        "Folio" => $_order,
                        "Código" => $product->name,
                        "Modelo" => $product->code,
                        "Descripción" => $product->description,
                        "Piezas" => $product->pivot->units
                    ];
                })->toArray();
            }else{
                $notFound[] = $_order;
            }
        }
        $format = [
            'A' => "NUMBER",
            'B' => "TEXT",
            'C' => "TEXT"
        ];
        return response()->json(array_merge_recursive(...$orders));
        $export = new WithMultipleSheetsExport($orders, $format);
        return Excel::download($export, "pedidos_preventa".date("d-m-Y_H:m:s").".xlsx");
    }

    public function getNextStatus(Order $order){
        if($order->_status == 100 || $order->_status == 101){
            $previous_log = $order->history->sortByDesc(function($log){
                return $log->pivot->created_at;
            })->filter(function($log) use($order){
                return $log->id != $order->_status;
            })->values()->all()[0];
            $_status = $previous_log->id;
        }else{
            $_status = $order->_status + 1;
        }
        return $_status;
    }
}
