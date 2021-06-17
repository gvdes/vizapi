<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Order;
use App\OrderProcess;
use App\Printer;
use App\Account;
use App\Product;
use App\OrderLog;
use App\User;
use App\CashRegister;

use App\Http\Resources\Order as OrderResource;

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

    public function log($case, Order $order){
        $process = OrderProcess::all();
        $status = [];
        // Instance or OrderLog to save data
        $log = new OrderLog;
        $log->_order = $order->id;
        $log->_status = $case;

        switch($case){
            case 1:
                $log->details = json_encode([]);
                $user = User::find($this->account->_account);
                // Order was created by
                $user->order_log()->save($log);
            break;
            case 2:
                $assign_cash_register = $this->getProcess($case);
                $_cash = $this->getCash($order, json_decode($assign_cash_register->details)->mood);
                $cashRegister = CashRegister::find($_cash);
                // The system assigned casg register
                $cashRegister->order_log()->save($log);
            case 3:
                $validate = $this->getProcess($case); // Verificar si la validación es necesaria
                if($validate->active){
                    $log->details = json_encode([]);
                    $user = User::find($this->account->_account);
                    // Order was passed next status by
                    $user->order_log()->save($log);
                    break;
                }
            case 4:
                $to_supply = $this->getProcess($case);
                if($to_supply->active){
                    $bodegueros = Account::with('user')->whereIn('_rol', [6,7])->whereNotIn('_status', [4,5])->count();
                    $tickets = 3;
                    $in_suppling = Order::where([
                        ['_workpoint_from', $this->_account->_workpoint],
                        ['_status', $case] // Status Surtiendo
                    ])->count(); // Para saber cuantos pedidos se estan surtiendo
                    if($in_suppling>($bodegueros*$tickets)){
                        // Poner en status 4 (el pedido esta por surtir)
                        $log->details = json_encode([]);
                        $user = User::find($this->account->_account);
                        // Order was passed next status by
                        $user->order_log()->save($log);
                        break;
                    }
                }
            case 5:
                $order->history()->attach($case, ["details" => json_encode([]), '_responsable' => $this->account->_account]);
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
        $order = Order::with('log')->find($request->_order);
        if($order){
            $_status = $order->_status+1;
            if(($_status>0 && $_status<10) || $_status == 100){
                $result = $this->log($_status, $order);
                if($result){
                    return response()->json(['success' => true, 'status' => $result]);
                }
                return response()->json(['success' => false, 'status' => null, 'msg' => "No se ha podido cambiar el status"]);
            }
            return response()->json(['success' => false, 'msg' => "Status no válido"]);
        }
        return response()->json(['success' => false, 'msg' => "Orden desconocida"]);
    }

    public function addProduct(Request $request){
        try{
            $order = Order::find($request->_order);
            if($this->account->_account == $order->_created_by || in_array($this->account->_rol, [1,2,3])){
                $product = Product::with(['prices' => function($query){
                    $query->whereIn('_type', [1,2,3,4])->orderBy('_type');
                }, 'units'])->find($request->_product);
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
                                    "_supply_by" => $_supply_by,
                                    "units" => $units,
                                    "_price_list" => $price_list,
                                    "price" => $price,
                                    "total" => $units * $price,
                                    "kit" => "",
                                ],
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
        /* $status = OrderProcess::get();
        $workpoints = \App\WorkPoint::whereIn('id', [1,15])->get();
        foreach($workpoints as $workpoint){
            foreach($status as $row){
                $row->config()->attach($workpoint->id, ["active" => $row->active, "details" => json_encode([])]);
            }
        }
        return response()->json($status); */
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

        $printers = Printer::with('type')->where('_workpoint', $this->account->_workpoint)->get();

        $clause = [
            ['_workpoint_from', $this->account->_workpoint]
        ];

        if($this->account->_rol == 4 || $this->account->_rol == 5 || $this->account->_rol == 7){
            array_push($clause, ['_created_by', $this->account->_account]);
        }
        
        /* $orders = Order::withCount('products')->with(['status', 'created_by', 'workpoint'])->where($clause)->where([['created_at', '>=', $date_from], ['created_at', '<=', $date_to]])->get(); */

        return response()->json([
            'status' => $status,
            'printers' => $printers/* ,
            'orders' => $orders *//* OrderResource::collection($orders) */
        ]);
    }

    public function find($id){
        $order = Order::with(['products' => function($query){
            $query->with(['prices' => function($query){
                $query->whereIn('_type', [1,2,3,4])->orderBy('_type');
            },'variants']);
        }, 'client', 'price_list', 'status', 'created_by', 'workpoint', 'history'])->find($id);
        $groupBy = /* $product */$order->products->map(function($product){
            $product->locations->sortBy('path');
            return $product;
        })->groupBy(function($product){
            if(count($product->locations)>0){
                return explode('-',$product->locations[0]->path)[0];
            }else{
                return '';
            }
        })/* ->sortBy(function($product){
            if(count($product->locations)>0){
                return $product->locations[0]->path;
            }
            return '';
        }) */->sortKeys();
        return $groupBy;
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
        $cellerPrinter = new MiniPrinterController("192.168.1.96", 9100);
        /* $res = $cellerPrinter->orderReceipt($order); */
        $res = $cellerPrinter->orderTicket($order);
        if($res){
            $order->printed = $order->printed +1;
            /* $order->save(); */
        }
        return response()->json(["success" => $res]);
    }

    public function getCash(/* $order, $mood */){
        return $bodegueros;
        switch($mood){
            case "Secuencial":
                // 1.- Obtener cajas
                $cashRegisters = CashRegister::withCount('order_log')->where([['_workpoint', $this->account->_workpoint], ["_status", 1]])->get()->sortBy('num_cash');
                $inCash = array_column($cashRegisters->toArray(), 'order_log_count');
                $_cash = $cashRegisters[array_search(min($inCash), $inCash)]->id;
                return response()->json($_cash);
        }

    }
}
