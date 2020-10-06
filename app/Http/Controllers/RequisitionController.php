<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Requisition\Requisition;
use App\Product;

class ExampleController extends Controller{
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
                $now = new \DateTime();
                $num_ticket = Requisitation::where('_workpoint_to', $request->_workpoint_to)
                                            ->whereDate('created_at', $now)
                                            ->count()+1;
                $num_ticket_store = Requisitation::where('_workpoint_from', $this->account->_workpoint)
                                                ->whereDate('created_at', $now)
                                                ->count()+1;
                $requisition =  Requisition::create([
                    "notes" => $request->notes,
                    "num_ticket" => $num_ticket,
                    "num_ticket_store" => $num_ticket_store,
                    "_created_by" => $this->account->_account,
                    "_workpoint_from" => $this->account->_workpoint,
                    "_workpoint_to" => $request->_workpoint_to,
                    "_type" => $request->_type,
                    "printed" => 0,
                    "time_life" => "00:15:00",
                ]);
                return $requisition;
            });
            return response()->json([
                "success" => true,
                "order" => $requisition
            ]);
        }catch(Exception $e){
            return response()->json(["message" => "No se ha podido crear el pedido"]);
        }
    }

    public function addProduct(Request $request){
        try{
            $requisition = Requisition::with('workpoint_to')->find($request->id);
            $amount = $request->amount;
            $product = Product::find($request->_product);
            /**CONSULTA DE STOCK EN ACCESS */
            $client = curl_init();
            curl_setopt($client, CURLOPT_URL, $requisition->to->dominio."/access/public/product/max/".$product->code);
            curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($client,CURLOPT_TIMEOUT,8);
            $available = json_decode(curl_exec($client), true);
            $product->stock = $available ? $available['ACTSTO'] : '--';
            return response()->json($product);
        }catch(Exception $e){
            return response()->json(["message" => "No se ha podido agregar el producto"]);
        }
    }

    public function log($case, Requisition $requisition){
        switch($case){
            case 1:
                $requisition->log()->attach(1, [ 'details' => json_encode([])]);
            break;
            case 2:
                $requisition->log()->attach(2, [ 'details' => json_encode([])]);
            break;
            case 3:
                $requisition->log()->attach(3, [ 'details' => json_encode([
                    "started_by" => "",
                    "responsables" => []
                ])]);
            break;
            case 4:
                $requisition->log()->attach(4, [ 'details' => json_encode([
                    "finished_by" => ""
                ])]);
            break;
            case 5:
                $requisition->log()->attach(5, [ 'details' => json_encode([
                    "picked_out_by" => ""
                ])]);
            break;
            case 6:
                $requisition->log()->attach(6, [ 'details' => json_encode([
                    "received_by" => ""
                ])]);
            break;
            case 7:
                $requisition->log()->attach(7, [ 'details' => json_encode([
                    "started_by" => ""
                ])]);
            break;
            case 8:
                $requisition->log()->attach(8, [ 'details' => json_encode([
                    "finished_by" => ""
                ])]);
            break;
            case 9:
                $requisition->log()->attach(9, [ 'details' => json_encode([
                    "finished_by" => ""
                ])]);
            break;
            case 10:
                $requisition->log()->attach(10, [ 'details' => json_encode([
                    "cancelled_by" => ""
                ])]);
            break;
            case 11:
                $requisition->log()->attach(11, [ 'details' => json_encode([])]);
            break;
        }
    }

    public function index(){
        $workpoints = WorkPoint::where('_type', 1)->get();
        $account = Account::with('permissions')->find($this->account->id);
        $types = Type::all();
        $requisitions = Requisition::where(['created_by', $this->account->_account])
                                    ->whereIn('_status', [1,2,3,4,5,6,7,8])
                                    ->get();
        return response()->json();
    }
}
