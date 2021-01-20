<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\User;
use App\CycleCount;
use App\CycleCountStatus;
use App\CycleCountType;
use App\Account;
use App\Product;
use App\Http\Resources\Inventory as InventoryResource;
use Illuminate\Support\Facades\Auth;

class CycleCountController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        $this->account = Auth::payload()['workpoint'];
    }

    public function create(Request $request){
        try {
            $payload = Auth::payload();
            $counter = DB::transaction(function() use($payload, $request){
                $counter = CycleCount::create([
                    'notes' => isset($request->notes) ? $request->notes : "",
                    '_workpoint' => $payload['workpoint']->_workpoint,
                    '_created_by' => $payload['workpoint']->_account,
                    '_type' => $request->_type,
                    '_status' => 1
                ]);
                $this->log(1, $counter);
                return $counter->fresh('workpoint', 'created_by', 'type', 'status', 'responsables', 'log');
            });
            return response()->json(["success" => true, "inventory" => new InventoryResource($counter)]);
        }catch(\Exception $e){
            return response()->json(["message"=> "No se ha podido crear el contador"]);
        }
    }

    public function addResponsable(Request $request){
        $inventory = CycleCount::find($request->_inventory);
        if($inventory){
            $res = $inventory->responsables()->toggle($request->_responsable);
            return response()->json(["success" => $res]);
        }
        return response()->json(["message" => "Folio de inventario no encontrado"]);
    }

    public function index(Request $request){
        /* $products = Product::with('prices')->whereIn('code', array_column($request->products, "modelo"))->get();
        $res = $products->map(function($product){
            $prices = $product->prices->map(function($price){
                return [$price->alias => $price->pivot->price];
            })->toArray();
            $res = ["code" => $product->code, "descripcion" => $product->description];
            return array_merge($res, $prices);
        });
        return response()->json($res); */
        $account = User::find($this->account->_account);
        $now = new \DateTime();
        if(isset($request->date)){
            $now = $request->date;
        }

        $invetories = CycleCount::with(['workpoint', 'created_by', 'type', 'status', 'responsables', 'log', 'products' => function($query){
            $query->with(['locations' => function($query){
                $query->whereHas('celler', function($query){
                    $query->where('_workpoint', $this->account->_workpoint);
                });
            }]);
        }])->where('_created_by', $this->account->_account)
                                ->orWhere(function($query){
                                    $query->whereHas('responsables', function($query){
                                        $query->where('_account', $this->account->_account);
                                    });
                                })
                                ->whereDate('created_at', $now)
                                ->get();
        return response()->json([
            "type" => CycleCountType::all(),
            "status" => CycleCountStatus::all(),
            "inventory" => InventoryResource::collection($invetories)
        ]);
    }

    public function find($id){
        $inventory = CycleCount::with(['workpoint', 'created_by', 'type', 'status', 'responsables', 'log', 'products' => function($query){
            $query->with(['locations' => function($query){
                $query->whereHas('celler', function($query){
                    $query->where('_workpoint', $this->account->_workpoint);
                });
            }]);
        }])->find($id);
        if($inventory){
            return response()->json(["success" => true, "inventory" => new InventoryResource($inventory)]);
        }
        return response()->json(["success" => false, "message" => "El folio no existe"]);
    }

    public function nextStep(Request $request){
        $inventory = CycleCount::find($request->_inventory);
        if($inventory){
            $status = isset($request->_status) ? $request->_status : $inventory->_status+1;
            if($status>0 && $status<5){
                $result = $this->log($status, $inventory);
                if($result){
                    $inventory->_status= $status;
                    $inventory->save();
                    $inventory->load('workpoint', 'created_by', 'type', 'status', 'responsables', 'log');
                }
                return response()->json(["success" => $result, 'order' => new InventoryResource($inventory)]);
            }
            return response()->json(["success" => false, "message" => "Status no válido"]);
        }else{
            return response()->json(["success" => false, "message" => "Clave de inventario no válido"]);
        }
    }

    public function addProducts(Request $request){
        $_products = $request->_products;
        $inventory = CycleCount::find($request->_inventory);
        $products_add = [];
        if($inventory){
            if($inventory->_type == 1){
                $products = Product::with(['stocks' => function($query) use ($inventory){
                    $query->where('_workpoint', $inventory->_workpoint);
                },'locations' => function($query){
                    $query->whereHas('celler', function($query){
                        $query->where('_workpoint', $this->account->_workpoint);
                    });
                }])->whereIn('id', $_products)->get();
                foreach($products as $product){
                    $inventory->products()->attach($product->id, [
                        'stock' => count($product->stocks)>0 ? $product->stocks[0]->pivot->stock : 0,
                        "details" => json_encode([
                            "editor" => ""
                        ])
                    ]);
                    array_push($products_add, [
                        "id" => $product->id,
                        "code" => $product->code,
                        "name" => $product->name,
                        "description" => $product->description,
                        "dimensions" => $product->dimensions,
                        "pieces" => $product->pieces,
                        "ordered" => [
                            "stocks" => $product->stocks[0]->pivot->stock,
                            "stocks_acc" => null,
                            "details" => [
                                "editor" => ""
                            ]
                        ],
                        "units" => $product->units,
                        'locations' => $product->locations->map(function($location){
                            return [
                                "id" => $location->id,
                                "name" => $location->name,
                                "alias" => $location->alias,
                                "path" => $location->path
                            ];
                        })
                    ]);
                }
            }else{
                $products = Product::with(['stocks' => function($query) use ($inventory){
                    $query->where('_workpoint', $inventory->_workpoint);
                },'locations' => function($query){
                    $query->whereHas('celler', function($query){
                        $query->where('_workpoint', $this->account->_workpoint);
                    });
                }])->whereIn('id', $_products)->get();
                foreach($products as $product){
                    $inventory->products()->attach($product->id, [
                        'stock' => 0,
                        "details" => json_encode([
                            "editor" => ""
                        ])
                    ]);
                    array_push($products_add, [
                        "id" => $product->id,
                        "code" => $product->code,
                        "name" => $product->name,
                        "description" => $product->description,
                        "dimensions" => $product->dimensions,
                        "pieces" => $product->pieces,
                        "ordered" => [
                            "stocks" => null,
                            "stocks_acc" => null,
                            "details" => [
                                "editor" => ""
                            ]
                        ],
                        "units" => $product->units,
                        'locations' => $product->locations->map(function($location){
                            return [
                                "id" => $location->id,
                                "name" => $location->name,
                                "alias" => $location->alias,
                                "path" => $location->path
                            ];
                        })
                    ]);
                }
            }
            $inventory->settings = json_encode($request->settings);
            $inventory->save();
            return response()->json(["success" => true, "products" => $products_add]);
        }
        return response()->json(["success" => false, "message" => "Folio de inventario no encontrado"]);
    }

    public function removeProducts(Request $request){
        $_products = $request->_products;
        $inventory = CycleCount::find($request->_inventory);
        if($inventory){
            $inventory->products()->detach($_products);
            return response()->json(["success" => true]);
        }
        return response()->json(["success" => false, "message" => "Folio de inventario no encontrado"]);
    }

    public function saveValue(Request $request){
        $account = Account::with('user')->find($this->account->id);
        $responsable = $account->user->names.' '.$account->user->surname_pat;
        $inventory = CycleCount::find($request->_inventory);
        $settings = $request->settigs;
        if($inventory){
            $inventory->products()->updateExistingPivot($request->_product, ['stock_acc' => $request->stock, "details" => json_encode(["editor" => $responsable, "settings" => $settings])]);
            return response()->json(["success" => true]);
        }
        return response()->json(["success" => false, "message" => "Folio de inventario no encontrado"]);
    }

    public function log($case, CycleCount $inventory){
        $account = Account::with('user')->find($this->account->id);
        $responsable = $account->user->names.' '.$account->user->surname_pat;
        switch($case){
            case 1:
                $inventory->log()->attach(1, [ 
                    'details' => json_encode([
                        "responsable" => $responsable
                    ]),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                return true;
            break;
            case 2:
                if(count($inventory->products)>0){
                    $inventory->log()->attach(2, [ 
                        'details' => json_encode([
                            "responsable" => $responsable
                        ]),
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    return true;
                }else{
                    return false;
                }
            break;
            case 3:
                $num = $inventory->products()->where('stock_acc', null)->count();
                if($num<=0){
                    $inventory->log()->attach(3, [
                        'details' => json_encode([
                            "responsable" => $responsable
                        ]),
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    return true;
                }else{
                    return false;
                }
            break;
            case 4:
                $inventory->log()->attach(4, [ 
                    'details' => json_encode([
                        "responsable" => $responsable
                    ]),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                return true;
            break;
        }
    }
}
