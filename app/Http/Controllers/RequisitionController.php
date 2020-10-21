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
                $now = new \DateTime();
                $num_ticket = Requisition::where('_workpoint_to', $request->_workpoint_to)
                                            ->whereDate('created_at', $now)
                                            ->count()+1;
                $num_ticket_store = Requisition::where('_workpoint_from', $this->account->_workpoint)
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
                    "_status" => 1
                ]);
                $this->log(1, $requisition);
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
            $requisition = Requisition::find($request->_requisition);
            $product = Product::find($request->_product);
            $amount = isset($request->amount) ? $request->amount : 1;
            $requisition->products()->syncWithoutDetaching([$request->_product => ['units' => $amount, 'comments' => $request->comments]]);
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
        $account = Account::with(['permissions'=> function($query){
            $query->whereIn('id', [29,30])->get();
        }])->find($this->account->id);
        $permissions = $account->permissions->map(function($permission){
            $id = $permission->id - 28;
            return [$id];
        });
        $types = Type::whereIn('id', $permissions)->get();
        $status = Process::all();
        $now = new \DateTime();
        $requisitions = Requisition::with(['type', 'status', 'products' => function($query){
                                        $query->with('prices', 'units', 'variants');
                                    }, 'to', 'from', 'created_by', 'log'])->where('_created_by', $this->account->_account)
                                    /* ->whereIn('_status', [1,2,3,4,5,6,7,8]) */
                                    /* ->whereDate('created_at', $now) */
                                    ->get();
        return response()->json([
            "workpoints" => $workpoints,
            "types" => $types,
            "status" => $status,
            /* "requisitions" => $requisitions */
            "requisitions" => RequisitionResource::collection($requisitions)
        ]);
    }

    public function find($id){
        $requisition = Requisition::with(['type', 'status', 'products' => function($query){
            $query->with('prices', 'units', 'variants');
        }, 'to', 'from', 'created_by', 'log'])->find($id);
        return response()->json(new RequisitionResource($requisition));
    }
}
