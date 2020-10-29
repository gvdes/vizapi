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
            if($this->account->_account == $requisition->_created_by){
                $product = Product::with('prices', 'units')->find($request->_product);
                $amount = isset($request->amount) ? $request->amount : 1;
                $requisition->products()->syncWithoutDetaching([$request->_product => ['units' => $amount, 'comments' => $request->comments]]);
                return response()->json([
                    "id" => $product->id,
                    "code" => $product->code,
                    "name" => $product->name,
                    "description" => $product->description,
                    "dimensions" => $product->dimensions,
                    "prices" => $product->prices->map(function($price){
                        return [
                            "id" => $price->id,
                            "name" => $price->name,
                            "price" => $price->pivot->price,
                        ];
                    }),
                    "pieces" => $product->pieces.' '.$product->units->alias,
                    "ordered" => [
                        "amount" => $amount,
                        "comments" => $request->comments
                    ]
                ]);
            }else{
                return response()->json(["msg" => "No puedes agregar productos"]);
            }
        }catch(Exception $e){
            return response()->json(["msg" => "No se ha podido agregar el producto"]);
        }
    }

    public function removeProduct(Request $request){
        try{
            $requisition = Requisition::find($request->_requisition);
            if($this->account->_account == $requisition->_created_by){
                $product = Product::with('prices', 'units')->find($request->_product);
                $amount = isset($request->amount) ? $request->amount : 1;
                $requisition->products()->detach([$request->_product]);
                return response()->json(["success" => true]);
            }else{
                return response()->json(["msg" => "No puedes agregar productos"]);
            }
        }catch(Exception $e){
            return response()->json(["msg" => "No se ha podido agregar el producto"]);
        }
    }

    public function log($case, Requisition $requisition){
        $account = Account::with('user')->find($this->account->_account);
        $responsable = $account->user->names.' '.$account->user->surname_pat.' '.$account->user->surname_mat;
        switch($case){
            case 1:
                $requisition->log()->attach(1, [ 'details' => json_encode([])]);
            break;
            case 2:
                $requisition->log()->attach(2, [ 'details' => json_encode([])]);
                $_workpoint_from = $requisition->_workpoint_from;
                $requisition->fresh(['log', 'products' => function($query) use ($_workpoint_from){
                    $query->with(['locations' => function($query)  use ($_workpoint_from){
                        $query->whereHas('celler', function($query) use ($_workpoint_from){
                            $query->where('_workpoint', $_workpoint_from);
                        });

                    }]);
                }]);
                $storePrinter = new MiniPrinterController('192.168.1.36'/* $printer->ip */);
                $storePrinter->requisitionReceipt($requisition);
                $cellerPrinter = new MiniPrinterController('192.168.1.36'/* $printer->ip */);
                $cellerPrinter->requisitionTicket($requisition);
                /* try {
                    $printer = Printer::where('_workpoint', $requisition->workpoint_to)
                    ->where(function($query) use($requisition){
                        $query->where('preferences->workpoints', 'all')->orWhereJsonContains('preferences->workpoints', $requisition->workpoint_from);
                    })->first();
                    if($printer){
                    }
                } catch (\Throwable $th) {
                    //throw $th;
                } */
            break;
            case 3:
                $requisition->log()->attach(3, [ 'details' => json_encode([
                    "responsable" => $responsable
                ])]);
            break;
            case 4:
                $requisition->log()->attach(4, [ 'details' => json_encode([
                    "responsable" => $responsable
                ])]);
            break;
            case 5:
                $requisition->log()->attach(5, [ 'details' => json_encode([
                    "responsable" => $responsable
                ])]);
            break;
            case 6:
                $requisition->log()->attach(6, [ 'details' => json_encode([
                    "responsable" => $responsable
                ])]);
            break;
            case 7:
                $requisition->log()->attach(7, [ 'details' => json_encode([
                    "responsable" => $responsable
                ])]);
            break;
            case 8:
                $requisition->log()->attach(8, [ 'details' => json_encode([
                    "responsable" => $responsable
                ])]);
            break;
            case 9:
                $requisition->log()->attach(9, [ 'details' => json_encode([
                    "responsable" => $responsable
                ])]);
            break;
            case 10:
                $requisition->log()->attach(10, [ 'details' => json_encode([
                    "responsable" => $responsable
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
        $clause = [
            ['_workpoint_from', $this->account->_workpoint]
        ];
        if($this->account->_rol == 4 ||  $this->account->_rol == 5 || $this->account->_rol == 7){
            array_push($clause, ['_created_by', $this->account->_account]);
        }
        $requisitions = Requisition::with(['type', 'status', 'products' => function($query){
                                        $query->with('prices', 'units', 'variants');
                                    }, 'to', 'from', 'created_by', 'log'])
                                    ->where($clause)
                                    ->whereIn('_status', [1,2,3,4,5,6,7,8])
                                    /* ->orWhere(function($query){
                                        $now = new \DateTime();
                                        $query->whereDate('created_at', $now);
                                    }) */
                                    ->get();
        return response()->json([
            "workpoints" => $workpoints,
            "types" => $types,
            "status" => $status,
            "requisitions" => RequisitionResource::collection($requisitions)
        ]);
    }

    public function dashboard(){
        $requisitions = Requisition::with(['type', 'status', 'products' => function($query){
                                        $query->with('prices', 'units', 'variants');
                                    }, 'to', 'from', 'created_by', 'log'])
                                    ->where('_workpoint_to', $this->account->_workpoint)
                                    ->whereIn('_status', [1,2,3,4,5,6,7,8])
                                    ->get();
        return response()->json(RequisitionResource::collection($requisitions));
    }

    public function find($id){
        $requisition = Requisition::with(['type', 'status', 'products' => function($query){
            $query->with('prices', 'units', 'variants');
        }, 'to', 'from', 'created_by', 'log'])->find($id);
        return response()->json(new RequisitionResource($requisition));
    }

    public function nextStep(Request $request){

        $requisition = Requisition::find($request->id);
        $_workpoint_from = $requisition->_workpoint_from;
        /* $requisition->load(['log', 'products' => function($query) use ($_workpoint_from){
            $query->with(['locations' => function($query)  use ($_workpoint_from){
                $query->whereHas('celler', function($query) use ($_workpoint_from){
                    $query->where('_workpoint', $_workpoint_from);
                });

            }]);
        }]);
        return response()->json($requisition); */
        $status = isset($request->_status) ? $request->_status : ($requisition->_status+1);
        if($status>0 && $status<12){
            /* return response()->json($this->log($status, $requisition)); */
            $requisition->_status = $status;
            $requisition->save();
            return response()->json(["success" => true]);
        }
        return response()->json(["success" => false, "message" => "Status no válido"]);
    }
}
