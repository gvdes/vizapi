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
        try{
            $order = DB::transaction( function () use ($request){
                $now = new \DateTime();
                $num_ticket = Order::where('_workpoint_from', $this->account->_workpoint)->whereDate('created_at', $now)->count()+1;
                $order = Order::create([
                    'num_ticket' => $num_ticket,
                    'name' => isset($request->name) ? $request->name : "Pedido ".$num_ticket,
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
                }]);
            });
            return response()->json(new OrderResource($order));
        }catch(\Exception $e){
            return response()->json(["msg" => "No se ha podido crear el pedido"]);
        }
    }

    public function log($case, Order $order){
        $account = Account::with('user')->find($this->account->id);
        $process = OrderProcess::all();
        $responsable = $account->user->names.' '.$account->user->surname_pat.' '.$account->user->surname_mat;
        switch($case){
            case 1:
                $order->history()->attach(1, ["details" => json_encode([
                    "responsable" => $responsable
                ])]);
            break;
            case 2:
                $order->history()->attach(2, ["details" => json_encode([
                    "responsable" => $responsable
                ])]);
                $validate = OrderProcess::find(3); //Verificar si la validación es necesaria
                if($validate->active){
                    $order->history()->attach(3, ["details" => json_encode([
                        "responsable" => $responsable
                    ])]);
                }else{
                    $end_to_supply = OrderProcess::find(7);
                    if($end_to_supply->active){
                        $bodegueros = 4;
                        $tickets = 3;
                        $in_suppling = Order::where([
                            ['_workpoint_from', $this->_account->_workpoint],
                            ['_status', 6]
                        ])->count();
                        if($in_suppling>=($bodegueros*$tickets)){
                            //poner en status 4 (el pedido ha llegado en bodega)
                        }else{
                            //poner en status 5 (el pedido se esta surtiendo)
                        }
                    }else{
                        //DETERMINAR CAJA
                    }
                }
            break;
            case 3:
                
            break;
        }
    }

    public function nextStep(Request $request){
        $order = Order::find($request->_order);
        if($order){
            $status = isset($request->_status) ? $request->_status : ($order->_status+1);
            if($status>0 && $status<12){
                $result = $this->log($status, $requisition);
                if($result){
                    return response()->json(['success' => false, 'status' => $result, "data" => $result]);
                }
                return response()->json(['success' => $result, 'order' => new RequisitionResource($requisition)]);
            }
            return response()->json(['success' => false, 'msg' => "Status no válido"]);
        }
        return response()->json(['success' => false, 'msg' => "Orden desconocida"]);
    }

    public function addProduct(Request $request){
        try{
            $order = Order::find($request->_order);
            if($this->account->_account == $order->_created_by){
                $product = Product::with(['prices' => function($query){
                    $query->whereIn('_type', [1,2,3,4])->orderBy('_type');
                }, 'units'])->find($request->_product);
                $amount = isset($request->amount) ? $request->amount : 1;
                $_supply_by = isset($request->_supply_by) ? $request->_supply_by : 1;
                $units = $this->getAmount($product, $amount, $_supply_by);
                $price_list = $this->calculatePriceList($product, $units);
                $index_price = array_search($price_list, array_column($product->prices->toArray(), 'id'));
                $price = $product->prices[$index_price]->pivot->price;
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
                return response()->json(["msg" => "No puedes agregar productos"]);
            }
        }catch(Exception $e){
            return response()->json(["msg" => "No se ha podido agregar el producto"]);
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
                return round($amount * ($product->pieces/2));
            break;
            case 4:
                return ($amount * $product->pieces);
            break;
        }
    }

    public function calculatePriceList($product, $units){
        if($units>=$product->pieces){
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
        $now =  new \DateTime();
        if(isset($request->date)){
            $now = $request->date;
        }
        $status = OrderProcess::all();
        $printers = Printer::where('_workpoint', $this->account->_workpoint)->get();
        $clause = [
            ['_workpoint_from', $this->account->_workpoint]
        ];

        if($this->account->_rol == 4 || $this->account->_rol == 5 || $this->account->_rol == 7){
            array_push($clause, ['_created_by', $this->account->_account]);
        }
        $orders = Order::with(['products' => function($query){
            $query->with(['prices' => function($query){
                $query->whereIn('_type', [1,2,3,4,5])->orderBy('_type');
            }, 'units', 'variants']);
        }, 'status', 'created_by', 'workpoint', "history"])->where($clause)->whereDate('created_at', $now)->get();

        return response()->json([
            'status' => $status,
            'printers' => $printers,
            'orders' => OrderResource::collection($orders)
        ]);
    }

    public function find($id){
        $order = Order::with(['products' => function($query){
            $query->with(['prices' => function($query){
                $query->whereIn('_type', [1,2,3,4])->orderBy('_type');
            },'variants']);
        }])->find($id);

        return response()->json(new OrderResource($order));
    }

    public function config(){
        $status = OrderProcess::all();
        return response()->json([
            'status' => $status,
        ]);
    }

    public function changeConfig(Request $request){
        $status = OrderProcess::find($request->_status);
        if($status->allow){
            $status->active = !$status->active;
            return response()->json(["success" => $status->save()]);
        }
        return response()->json(["msg" => "No se permite desactivar el status", "success" => false]);
    }

    public function migrateToRequesition(){

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
        $cellerPrinter = new MiniPrinterController("192.168.1.10", 9100);
        /* $res = $cellerPrinter->ticket($order); */
        $res = $cellerPrinter->orderTicket($order);
        if($res){
            $order->printed = $order->printed +1;
            $order->save();
        }
        return response()->json(["success" => $res]);
    }
}
