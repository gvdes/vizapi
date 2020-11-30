<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Order;
use App\OrderProcess;

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

    public function created(Request $request){
        try{
            $order = DB::transaction( function () use ($request){
                $now = new \DateTime();
                $num_ticket = Order::where('_workpoint_from', $this->account->_workpoint)->whereDate('created_at', $now)->count()+1;
                $order = Order::create([
                    'num_ticket' => $num_ticket,
                    'name' => isset($request->name) ? $request->name : "Pedido ".$num_ticket,
                    '_created_by' => $this->account->_account,
                    'workpoint_from' => $this->account->_workpoint,
                    'time_life' => '00:30:00',
                    '_status' => 1
                ]);
                $this->log(1, $order);
                $this->log(2, $order);
                return $order;
            });
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
            break;
            case 3:
                $process = OrderProcess::find(4); //Verificar si la validación es necesaria
                if($process->active){
                    $order->history()->attach(3, ["details" => json_encode([
                        "responsable" => $responsable
                    ])]);
                }else{

                }

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
            $order = Requisition::find($request->_order);
            if($this->account->_account == $order->_created_by){
                $product = Product::with(['prices' => function($query){
                    $query->whereIn('_type', [1,2,3,4,5])->orderBy('_type');
                }, 'units'])->find($request->_product);
                $amount = isset($request->amount) ? $request->amount : 1;
                $_supply_by = isset($request->_supply_by) ? $request->_supply_by : 1;
                $requisition->products()->syncWithoutDetaching([$request->_product => ['units' => $amount, 'comments' => $request->comments, 'price' => ""]]);
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
                        "amount" => $amount,
                        "unit" => $amount,
                        "comments" => $request->comments,
                        "price" => 0
                    ],
                ]);
            }else{
                return response()->json(["msg" => "No puedes agregar productos"]);
            }
        }catch(Exception $e){
            return response()->json(["msg" => "No se ha podido agregar el producto"]);
        }
    }
}
