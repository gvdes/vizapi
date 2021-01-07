<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\User;
use App\CycleCount;
use App\CycleCountStatus;
use App\CycleCountType;
use App\Account;
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
                    'notes' => $request->notes/* isset($request->notes) ? $request->notes : "" */,
                    '_workpoint' => $payload['workpoint']->_workpoint,
                    '_created_by' => $payload['workpoint']->_account,
                    '_type' => $request->_type,
                    '_status' => 1
                ]);
                $this->log(1, $counter);
                return $counter->fresh('workpoint', 'created_by', 'type', 'status', 'responsables');
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
        return response()->json(["msg" => "Folio de inventario no encontrado"]);
    }

    public function index(Request $request){
        $account = User::find($this->account->_account);
        $now = new \DateTime();
        if(isset($request->date)){
            $now = $request->date;
        }
        $invetories = CycleCount::where('_created_by', $this->account->_account)
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
            "inventory" => $invetories
        ]);
    }

    public function find($id){
        $inventory = CycleCount::with('workpoint', 'created_by', 'type', 'status', 'responsables')->find($id);
        if($inventory){
            return response()->json(["success" => true, "inventory" => new InventoryResource($inventory)]);
        }
        return response()->json(["success" => false, "msg" => "El folio no existe"]);
    }

    public function nextStep(Request $request){

    }

    public function addProducts(Request $request){
        $_products = $request->_products;
        $inventory = CycleCount::find($request->_inventory);
        if($inventory){
            if($inventory->_type == 1){
                $products = Product::with(['stocks', function($query) use ($inventory){
                    $query->where('_workpoint', $inventory->_workpoint);
                }])->whereIn('id', $_products)->get();
                foreach($products as $product){
                    $inventory->products()->attach($product->id, ['stock' => count($product->stocks)>0 ? $product->stocks[0]->pivot->stock : 0]);
                }
            }else{
                $inventory->products()->attach($_products);
            }
            return response()->json(["success" => true]);
        }
        return response()->json(["success" => false, "message" => "Folio de inventario no encontrado"]);
    }

    public function removeProducts(Request $request){
        $_products = $request->_products;
        $inventory = CycleCount::find($request->_inventory);
        if($inventory){
            $requisition->products()->detach($_products);
            return response()->json(["success" => true]);
        }
        return response()->json(["success" => false, "message" => "Folio de inventario no encontrado"]);
    }

    public function saveValue(Request $request){

    }

    public function saveDetails(Request $request){
        try {
            $counter = CycleCount::find($request->id);
            $counter->details = json_encode($request->details);
            $counter->save();
            return response()->json(["success" => true]);
        }catch(\Exception $e){
            return response()->json(["message" => "No se han podido actualizar los datos"]);
        }
    }

    public function get(Request $request){
        try {
            $counter = CycleCount::with('products', 'type')->find($request->id);
            switch($counter->_status){
                case '1':
                    break;
                case '2':
                    break;
                case '3':
                    break;
            }
        } catch(\Exception $e){
            return response()->json(["message" => "No se ha podido"]);
        }
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
            break;
            case 2:
                $inventory->log()->attach(2, [ 
                    'details' => json_encode([
                        "responsable" => $responsable
                    ]),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            break;
            case 3:
                $inventory->log()->attach(3, [ 
                    'details' => json_encode([
                        "responsable" => $responsable
                    ]),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            break;
            case 4:
                $inventory->log()->attach(4, [ 
                    'details' => json_encode([
                        "responsable" => $responsable
                    ]),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            break;
        }
    }
}
