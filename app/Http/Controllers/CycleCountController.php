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
use PDF;

class CycleCountController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        $this->account = Auth::payload()['workpoint'];
    }

    public function create(Request $request){ // Función para crear un conteo ciclico
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
            $token = isset($request->token) ? $request->token : "";
            return response()->json(["success" => true, "inventory" => new InventoryResource($counter), "token" => $token]);
        }catch(\Exception $e){
            return response()->json(["message"=> "No se ha podido crear el contador"]);
        }
    }

    public function addResponsable(Request $request){ // Función para agregar personas al conteo ciclico que haran el conteo
        $inventory = CycleCount::find($request->_inventory);
        if($inventory){
            $res = $inventory->responsables()->toggle($request->_responsable);
            return response()->json(["success" => $res]);
        }
        return response()->json(["message" => "Folio de inventario no encontrado"]);
    }

    public function index(Request $request){ // Función que nos retorna las conteos ciclicos que hemos realizado en cierto periodo de tiempo, ademas retorna los datos necesarios para seguir creando conteos
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

        $invetories = CycleCount::with(['workpoint', 'created_by', 'type', 'status', 'responsables', 'log'])->withCount('products')
        ->orWhere([["_workpoint", $this->account->_workpoint], ['_created_by', $this->account->_account], ['created_at', '>=', $date_from], ['created_at', '<=', $date_to]])
        ->orWhere(function($query) use($date_to, $date_from){
            $query->whereHas('responsables', function($query){
                $query->where([['_account', $this->account->_account], ['_workpoint', $this->account->_workpoint]]);
            })->where([['created_at', '>=', $date_from], ['created_at', '<=', $date_to]]);
        })
        ->where(function($query) use($date_to, $date_from){
            $query->whereIn("_status", [1,2,3,4])
                ->where([['created_at', '>=', $date_from], ['created_at', '<=', $date_to]]);
        })
        ->get();
        return response()->json([
            "type" => CycleCountType::all(),
            "status" => CycleCountStatus::all(),
            "inventory" => InventoryResource::collection($invetories)
        ]);
    }

    public function find($id){ // Función para agregar un conteo ciclico en especifico con sus repectivos datos para trabajar con el
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

    public function nextStep(Request $request){ // Función para cambiar el status de un conteo ciclico
        $inventory = CycleCount::find($request->_inventory);
        if($inventory){
            $status = isset($request->_status) ? $request->_status : $inventory->_status+1;
            if($status>0 && $status<5){
                $result = $this->log($status, $inventory);
                if($result){
                    $inventory->_status= $status;
                    $inventory->save();
                    $inventory->load(['workpoint', 'created_by', 'type', 'status', 'responsables', 'log', 'products' => function($query){
                        $query->with(['locations' => function($query){
                            $query->whereHas('celler', function($query){
                                $query->where('_workpoint', $this->account->_workpoint);
                            });
                        }]);
                    }]);
                }
                return response()->json(["success" => $result, 'order' => new InventoryResource($inventory)]);
            }
            return response()->json(["success" => false, "message" => "Status no válido"]);
        }else{
            return response()->json(["success" => false, "message" => "Clave de inventario no válido"]);
        }
    }

    public function addProducts(Request $request){ // Función para agregar producto nuevos a un conteo ciclico
        $_products = $request->_products;
        $inventory = CycleCount::find($request->_inventory);
        $products_add = [];
        if($inventory){
            $products = Product::with(['stocks' => function($query) use ($inventory){
                $query->where('_workpoint', $inventory->_workpoint);
            },'locations' => function($query){
                $query->whereHas('celler', function($query){
                    $query->where('_workpoint', $this->account->_workpoint);
                });
            }])->whereIn('id', $_products)->get();
            foreach($products as $product){
                $stock = count($product->stocks)>0 ? $product->stocks[0]->pivot->stock : 0;
                $inventory->products()->attach($product->id, [
                    'stock' => $stock,
                    "stock_acc" => $stock>0 ? null : 0,
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
                        "stocks" => $stock,
                        "stocks_acc" => $stock>0 ? null : 0,
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
            $inventory->settings = json_encode($request->settings);
            $inventory->save();
            return response()->json(["success" => true, "products" => $products_add]);
        }
        return response()->json(["success" => false, "message" => "Folio de inventario no encontrado"]);
    }

    public function removeProducts(Request $request){ // Función para quitar producto nuevos a un conteo ciclico activo
        $_products = $request->_products;
        $inventory = CycleCount::find($request->_inventory);
        if($inventory){
            $inventory->products()->detach($_products);
            return response()->json(["success" => true]);
        }
        return response()->json(["success" => false, "message" => "Folio de inventario no encontrado"]);
    }

    public function saveValue(Request $request){ // Función para poner el valor contado durante el conteo ciclico
        $account = User::find($this->account->_account);
        $inventory = CycleCount::find($request->_inventory);
        $settings = $request->settings;
        if($inventory){
            $inventory->products()->updateExistingPivot($request->_product, ['stock_acc' => $request->stock, "details" => json_encode(["editor" => $account, "settings" => $settings])]);
            return response()->json(["success" => true]);
        }
        return response()->json(["success" => false, "message" => "Folio de inventario no encontrado"]);
    }

    public function saveFinalStock(CycleCount $inventory){ // Función para almacenar el inventario final de todos los articulos cuando los vas a dar por concluir
        foreach($inventory->products as $product){
            $stock_store = $product->stocks->filter(function($stock){
                return $stock->id == $this->account->_workpoint;
            })->values()->all();
            $stock = count($stock_store)>0 ? $stock_store[0]->pivot->stock : 0;
            $inventory->products()->updateExistingPivot($product->id, ["stock_end" => $stock]);
        }
    }

    public function log($case, CycleCount $inventory){ // Función para crear el log de las acción dentro del conteo ciclico
        // Se guarda la personam hora de la actividad y se almacena el nuevo estatus al que pasara
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
                    $this->saveFinalStock($inventory);
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
