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
            $name = $request->name;
            $account = $this->account;
            $order = DB::transaction( function () use ($account, $name){
                $now = new \DateTime();
                $num_ticket = Order::where('_workpoint_from', $account->_workpoint)->whereDate('created_at', $now)->count()+1;
                $order = Order::create([
                    'num_ticket' => $num_ticket,
                    'name' => $name ? $name : "Pedido ".$num_ticket,
                    '_created_by' => $account->_account,
                    'workpoint_from' => $account->_workpoint,
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
                $order->history()->attach(3, ["details" => json_encode([
                    "responsable" => $responsable
                ])]);
            break;
        }
    }
}
