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
                if($requisition->_type == 2){
                    $workpoint = WorkPoint::find($requisition->_workpoint_from);
                    $categories = array_merge(range(37,57), range(130,184));
                    $products = Product::with(['stocks' => function($query){
                        $query->where([
                            ['_workpoint', $this->account->_workpoint],
                            ['min', '>', 0],
                            ['max', '>', 0]
                        ]);
                    }])->whereHas('stocks', function($query){
                        $query->where([
                            ['_workpoint', $this->account->_workpoint],
                            ['min', '>', 0],
                            ['max', '>', 0]
                        ]);
                    }, '>', 0)/* ->whereIn('_category', $categories) */->get();
                    $client = curl_init();
                    $workpoint_to = WorkPoint::find($requisition->_workpoint_to);
                    curl_setopt($client, CURLOPT_URL, $workpoint_to->dominio."/access/public/product/stocks");
                    curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
                    curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($client, CURLOPT_POST, 1);
                    curl_setopt($client,CURLOPT_TIMEOUT,100);
                    $data = http_build_query(["products" => array_column($products->toArray(), "code")]);
                    curl_setopt($client, CURLOPT_POSTFIELDS, $data);
                    $stocks = json_decode(curl_exec($client), true);
                    if($stocks){
                        $toSupply = [];
                        foreach($products as $key => $product){
                            $stock = intval($stocks[$key]['stock'])>0 ? intval($stocks[$key]['stock']) : 0;
                            $max = intval($product->stocks[0]->pivot->max);
                            if($max>$stock){
                                $required = $max - $stock;
                            }else{
                                $required = 0;
                            }

                            if($product->_unit == 3){
                                $required = floor($required/$product->pieces);
                            }
                            if($required > 0){
                                $requisition->products()->syncWithoutDetaching([ $product->id => ['units' => $required, 'comments' => '', 'stock' => 0]]);
                            }
                            /* $stock = intval($stocks[$key]['stock'])>0 ? intval($stocks[$key]['stock']) : 0;
                            $required = intval($product->stocks[0]->max) - intval($stocks[$key]['stock']);
                            if($product->_unit == 3){
                                $required = floor($required/$product->pieces);
                            }
                            if($required > 0){
                                $requisition->products()->syncWithoutDetaching([ $product->id => ['units' => $required, 'comments' => '', 'stock' => 0]]);
                            } */
                        }
                    }
                    /* $requisition->_status = 2;
                    $requisition->save();
                    $this->log(2, $requisition); */
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
            $requisition = Requisition::find($request->_requisition);
            if($this->account->_account == $requisition->_created_by){
                $product = Product::with(['prices' => function($query){
                    $query->whereIn('_type', [1,2,3,4,5])->orderBy('_type');
                }, 'units'])->find($request->_product);
                $amount = isset($request->amount) ? $request->amount : 1;
                /* if($product->units->id == 3){
                    $amount = $amount * $product->pieces;
                } */
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
                        "comments" => $request->comments,
                        "stock" => 0
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
                return response()->json(["msg" => "No puedes eliminar productos"]);
            }
        }catch(Exception $e){
            return response()->json(["msg" => "No se ha podido eliminar el producto"]);
        }
    }

    public function log($case, Requisition $requisition){
        $account = Account::with('user')->find($this->account->id);
        $responsable = $account->user->names.' '.$account->user->surname_pat.' '.$account->user->surname_mat;
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
            case 1:
                $requisition->log()->attach(1, [ 'details' => json_encode([
                    "responsable" => $responsable
                ])]);
            break;
            case 2:
                $client = curl_init();
                curl_setopt($client, CURLOPT_URL, $requisition->to->dominio."/access/public/product/stocks");
                curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($client, CURLOPT_POST, 1);
                curl_setopt($client,CURLOPT_TIMEOUT,90);
                $data = http_build_query(["products" => array_column($requisition->products->toArray(), 'code')]);
                curl_setopt($client, CURLOPT_POSTFIELDS, $data);
                $stocks = json_decode(curl_exec($client), true);
                if($stocks){
                    foreach($requisition->products as $key => $product){
                        $requisition->products()->syncWithoutDetaching([
                            $product->id => [
                                'units' => $product->pivot->units,
                                'comments' => $product->pivot->comments,
                                'stock' => $stocks[$key]['stock']
                            ]
                        ]);
                    }
                    $_workpoint_to = $requisition->_workpoint_to;
                    $requisition->load(['log', 'products' => function($query) use ($_workpoint_to){
                        $query->with(['locations' => function($query)  use ($_workpoint_to){
                            $query->whereHas('celler', function($query) use ($_workpoint_to){
                                $query->where('_workpoint', $_workpoint_to);
                            });
                        }]);
                    }]);
                    $cellerPrinter = new MiniPrinterController('192.168.1.36'/* $printer->ip */);
                    if($cellerPrinter->requisitionTicket($requisition)){
                        $storePrinter = new MiniPrinterController('192.168.1.36'/* $printer->ip */);
                        $storePrinter->requisitionReceipt($requisition);
                        $requisition->printed = $requisition->printed +1;
                        $requisition->save();
                        $requisition->log()->attach(2, [ 'details' => json_encode([
                            "responsable" => $responsable
                        ])]);
                    }else{
                        return false;
                    }
                    return true;
                }
                return false;
            break;
            case 3:
                $requisition->log()->attach(3, [ 'details' => json_encode([
                    "responsable" => $responsable
                ])]);
                return true;
            break;
            case 4:
                $requisition->log()->attach(4, [ 'details' => json_encode([
                    "responsable" => $responsable
                ])]);
                return true;
            break;
            case 5:
                $requisition->log()->attach(5, [ 'details' => json_encode([
                    "responsable" => $responsable
                ])]);
                return true;
            break;
            case 6:
                $_workpoint_from = $requisition->_workpoint_from;
                $requisition->load(['log', 'products' => function($query) use ($_workpoint_from){
                    $query->with(['locations' => function($query)  use ($_workpoint_from){
                        $query->whereHas('celler', function($query) use ($_workpoint_from){
                            $query->where('_workpoint', $_workpoint_from);
                        });
                    }]);
                }]);
                $storePrinter = new MiniPrinterController('192.168.1.36'/* $printer->ip */);
                $storePrinter->requisitionTicket($requisition);
                $requisition->log()->attach(6, [ 'details' => json_encode([
                    "responsable" => $responsable
                ])]);
                return true;
            break;
            case 7:
                $requisition->log()->attach(7, [ 'details' => json_encode([
                    "responsable" => $responsable
                ])]);
                return true;
            break;
            case 8:
                $requisition->log()->attach(8, [ 'details' => json_encode([
                    "responsable" => $responsable
                ])]);
                return true;
            break;
            case 9:
                $requisition->log()->attach(9, [ 'details' => json_encode([
                    "responsable" => $responsable
                ])]);
                return true;
            break;
            case 10:
                $requisition->log()->attach(10, [ 'details' => json_encode([
                    "responsable" => $responsable
                ])]);
                return true;
            break;
            case 11:
                $requisition->log()->attach(11, [ 'details' => json_encode([])]);
            break;
        }
    }

    public function index(Request $request){
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
        $now = new \DateTime();
        if(isset($request->date)){
            $now = $request->date;
        }
        $requisitions = Requisition::with(['type', 'status', 'products' => function($query){
                                        $query->with(['prices' => function($query){
                                            $query->whereIn('_type', [1,2,3,4,5])->orderBy('_type');
                                        }, 'units', 'variants']);
                                    }, 'to', 'from', 'created_by', 'log'])
                                    ->where($clause)
                                    ->whereIn('_status', [1,2,3,4,5,6,7,8,9,10])
                                    ->whereDate('created_at', $now)
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
        $today = new \DateTime();
        $requisitions = Requisition::with(['type', 'status', 'products' => function($query){
                                        $query->with(['prices' => function($query){
                                            $query->whereIn('_type', [1,2,3,4,5])->orderBy('_type');
                                        }, 'units', 'variants']);
                                    }, 'to', 'from', 'created_by', 'log'])
                                    ->where('_workpoint_to', $this->account->_workpoint)
                                    ->whereIn('_status', [1,2,3,4,5,6,7,8,9,10])
                                    ->whereDate('created_at', $today)
                                    ->get();
        return response()->json(RequisitionResource::collection($requisitions));
    }

    public function find($id){
        $requisition = Requisition::with(['type', 'status', 'products' => function($query){
            $query->with(['prices' => function($query){
                $query->whereIn('_type', [1,2,3,4,5])->orderBy('_type');
            }, 'units', 'variants']);
        }, 'to', 'from', 'created_by', 'log'])->find($id);
        return response()->json(new RequisitionResource($requisition));
    }

    public function nextStep(Request $request){
        $requisition = Requisition::find($request->id);
        $status = isset($request->_status) ? $request->_status : ($requisition->_status+1);
        if($status>0 && $status<12){
            $result = $this->log($status, $requisition);
            if($result){
                $requisition->_status= $status;
                $requisition->save();
                $requisition->load(['type', 'status', 'products', 'to', 'from', 'created_by', 'log']);
            }
            return response()->json(["success" => $result, 'order' => new RequisitionResource($requisition)]);
        }
        return response()->json(["success" => false, "msg" => "Status no válido"]);
    }

    public function reimpresion(Request $request){
        $requisition = Requisition::find($request->_requisition);
        $_workpoint_to = $requisition->_workpoint_to;
        $requisition->fresh(['log', 'products' => function($query) use ($_workpoint_to){
            $query->with(['locations' => function($query)  use ($_workpoint_to){
                $query->whereHas('celler', function($query) use ($_workpoint_to){
                    $query->where('_workpoint', $_workpoint_to);
                });
            }]);
        }]);
        $cellerPrinter = new MiniPrinterController('192.168.1.36'/* $printer->ip */);
        $res = $cellerPrinter->requisitionTicket($requisition);
        return response()->json(["success" => $res]);
        $requisition->printed = $requisition->printed +1;
        return response()->json(["success" => $requisition->save()]);
    }

    public function test(){
        $categories = array_merge(range(37,57), range(130,184));
        $products = Product::with(['stocks' => function($query){
            $query->where([
                ['_workpoint', $this->account->_workpoint],
                ['min', '>', 0],
                ['max', '>', 0]
            ]);
        }])->whereHas('stocks', function($query){
            $query->where([
                ['_workpoint', $this->account->_workpoint],
                ['min', '>', 0],
                ['max', '>', 0]
            ]);
        }, '>', 0)->whereIn('_category', $categories)->get();
        $workpoint = WorkPoint::find($this->account->_workpoint);
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $workpoint->dominio."/access/public/product/stocks");
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client, CURLOPT_POST, 1);
        curl_setopt($client,CURLOPT_TIMEOUT,100);
        $data = http_build_query(["products" => array_column($products->toArray(), "code")]);
        curl_setopt($client, CURLOPT_POSTFIELDS, $data);
        $stocks = json_decode(curl_exec($client), true);
        /* return response()->json($stocks); */
        if($stocks){
            $toSupply = [];
            $notSupply = [];
            foreach($products as $key => $product){
                $stock = intval($stocks[$key]['stock'])>0 ? intval($stocks[$key]['stock']) : 0;
                $max = intval($product->stocks[0]->pivot->max);
                if($max>$stock){
                    $required = $max - $stock;
                }else{
                    $required = 0;
                }

                if($product->_unit == 3){
                    $required = floor($required/$product->pieces);
                }
                if($required > 0){
                    array_push($toSupply, [$product->code => ['units' => $required, 'comments' => '', 'stock' => 0]]);
                    //$requisition->products()->syncWithoutDetaching([ $product->id => ['units' => $required, 'comments' => '', 'stock' => 0]]);
                }else{
                    array_push($notSupply, [$product->code => ['units' => $required, 'comments' => '', 'stock' => 0]]);
                }
            }
            return response()->json(["supply"=> $toSupply, "notSupply"=> $notSupply]);
        }
    }

    public function search(Request $request){
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
}
