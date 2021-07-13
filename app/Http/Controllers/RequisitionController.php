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
                $_workpoint_from = $this->account->_workpoint;
                $_workpoint_to = $request->_workpoint_to;
                switch ($request->_type){
                    case 2:
                        $data = $this->getToSupplyFromStore($this->account->_workpoint, $_workpoint_to);
                    break;
                    case 3:
                        $_workpoint_from = isset($request->store) ? $request->store : $this->account->_workpoint;
                        $cadena = explode('-', $request->folio);
                        $folio = count($cadena)>1 ? $cadena[1] : '0';
                        $caja = count($cadena)>0 ? $cadena[0] : '0';
                        $data = $this->getVentaFromStore($folio, $_workpoint_from, $caja, $_workpoint_to);
                        $request->notes = $request->notes ? $request->notes : $data['notes'];
                    break;
                    case 4:
                        $_workpoint_from = isset($request->store) ? $request->store : $this->account->_workpoint;
                        $data = $this->getPedidoFromStore($request->folio, $_workpoint_from, $_workpoint_to);
                        $request->notes = $request->notes ? $request->notes : $data['notes'];
                    break;
                }
                if(isset($data['msg'])){
                    return response()->json([
                        "success" => false,
                        "msg" => $data['msg']
                    ]);
                }
                $now = new \DateTime();
                $num_ticket = Requisition::where('_workpoint_to', $_workpoint_to)
                                            ->whereDate('created_at', $now)
                                            ->count()+1;
                $num_ticket_store = Requisition::where('_workpoint_from', $_workpoint_from)
                                                ->whereDate('created_at', $now)
                                                ->count()+1;
                $requisition =  Requisition::create([
                    "notes" => $request->notes,
                    "num_ticket" => $num_ticket,
                    "num_ticket_store" => $num_ticket_store,
                    "_created_by" => $this->account->_account,
                    "_workpoint_from" => $_workpoint_from,
                    "_workpoint_to" => $_workpoint_to,
                    "_type" => $request->_type,
                    "printed" => 0,
                    "time_life" => "00:15:00",
                    "_status" => 1
                ]);
                $this->log(1, $requisition);
                if(isset($data['products'])){
                    $requisition->products()->attach($data['products']);
                }
                if($request->_type != 1){
                    $this->refreshStocks($requisition);
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
                $to = $requisition->_workpoint_to;
                $product = Product::with(['units', 'stocks' => function($query) use ($to){
                    $query->where('_workpoint', $to);
                }])->find($request->_product);
                $amount = isset($request->amount) ? $request->amount : 1;
                $stock = count($product->stocks) > 0 ? $product->stocks[0]->pivot->stock : 0;
                $requisition->products()->syncWithoutDetaching([$request->_product => ['units' => $amount, 'comments' => $request->comments, 'stock' => $stock]]);
                return response()->json([
                    "id" => $product->id,
                    "code" => $product->code,
                    "name" => $product->name,
                    "description" => $product->description,
                    "dimensions" => $product->dimensions,
                    "pieces" => $product->pieces,
                    "ordered" => [
                        "amount" => $amount,
                        "comments" => $request->comments,
                        "stock" => $stock
                    ],
                    "units" => $product->units
                ]);
            }else{
                return response()->json(["msg" => "No puedes agregar productos"]);
            }
        }catch(Exception $e){
            return response()->json(["msg" => "No se ha podido agregar el producto"]);
        }
    }

    public function addMassiveProduct(Request $request){
        $requisition = Requisition::find($request->_requisition);
        $added = 0;
        $fail = [];
        if($requisition){
            $products = $request->products;
            $to = $requisition->_workpoint_to;
            foreach($products as $row){
                $code = $row['code'];
                $product = Product::with(['stocks' => function($query) use ($to){
                    $query->where('_workpoint', $to);
                }])->where('code', $code)->where('_status', '!=', 4)->first();
                if($product){
                    if(isset($row['piezas'])){
                        $required = $row['piezas'];
                        if($product->_unit == 3){
                            $pieces = $product->pieces == 0 ? 1 : $product->pieces;
                            $required = round($required/$pieces, 2);
                        }
                    }else{
                        $required = $row['cajas'];
                    }
                    $added++;
                    $requisition->products()->syncWithoutDetaching([$product->id => ['units' => $required, "comments" => "", "stock" => count($product->stocks) > 0 ? $product->stocks[0]->pivot->stock : 0]]);
                }else{
                    array_push($fail, $row['code']);
                }
            }

        }
        return response()->json(["added" => $added, "fail" => $fail]);
    }

    public function removeProduct(Request $request){
        try{
            $requisition = Requisition::find($request->_requisition);
            if($this->account->_account == $requisition->_created_by){
                /* $product = Product::with('prices', 'units')->find($request->_product);
                $amount = isset($request->amount) ? $request->amount : 1; */
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
        $responsable = $account->user->names.' '.$account->user->surname_pat;
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
                // RECARGAR STOCKS DE LA REQUISISION
                //RECARGAR LA REQUISIÓN
                $_workpoint_to = $requisition->_workpoint_to;
                $requisition->load(['log', 'products' => function($query) use ($_workpoint_to){
                    $query->with(['locations' => function($query)  use ($_workpoint_to){
                        $query->whereHas('celler', function($query) use ($_workpoint_to){
                            $query->where('_workpoint', $_workpoint_to);
                        });
                    }]);
                }]);
                
                //IMPRESION EN LA BODEGA
                $workpoint_to_print = Workpoint::find($requisition->_workpoint_to);
                $printer = $this->getPrinter($workpoint_to_print, $requisition->_workpoint_from);
                $cellerPrinter = new MiniPrinterController($printer['domain'], $printer['port']);
                if($cellerPrinter->requisitionTicket($requisition)){
                    $requisition->printed = $requisition->printed +1;
                    $requisition->save();
                }
                //IMPRESION DE COMPROBANTE EN TIENDA
                $workpoint_to_print = Workpoint::find($requisition->_workpoint_from);
                $printer = $this->getPrinter($workpoint_to_print, $requisition->_workpoint_from);
                $storePrinter = new MiniPrinterController($printer['domain'], $printer['port']);
                $storePrinter->requisitionReceipt($requisition);
                $requisition->log()->attach(2, [ 'details' => json_encode([
                    "responsable" => $responsable
                ])]);
                return true;
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
                /* $_workpoint_from = $requisition->_workpoint_from;
                $requisition->load(['log', 'products' => function($query) use ($_workpoint_from){
                    $query->with(['locations' => function($query)  use ($_workpoint_from){
                        $query->whereHas('celler', function($query) use ($_workpoint_from){
                            $query->where('_workpoint', $_workpoint_from);
                        });
                    }]);
                }]);
                $workpoint_to_print = Workpoint::find($requisition->_workpoint_from);
                $printer = $this->getPrinter($workpoint_to_print, $requisition->_workpoint_from);
                $printer->requisitionTicket($requisition); */
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
                $_workpoint_from = $requisition->_workpoint_from;
                $requisition->load(['log', 'products' => function($query) use ($_workpoint_from){
                    $query->with(['locations' => function($query)  use ($_workpoint_from){
                        $query->whereHas('celler', function($query) use ($_workpoint_from){
                            $query->where('_workpoint', $_workpoint_from);
                        });
                    }]);
                }]);
                $workpoint_to_print = Workpoint::find($requisition->_workpoint_from);
                $printer = $this->getPrinter($workpoint_to_print, $requisition->_workpoint_from);
                $storePrinter = new MiniPrinterController($printer['domain'], $printer['port']);
                $storePrinter->requisitionTicket($requisition);
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
        $account = Account::with(['permissions'])->find($this->account->id);
        $permissions = array_column($account->permissions->toArray(), 'id');
        $_types = [];
        if(in_array(29,$permissions)){
            array_push($_types, 1);
        }
        if(in_array(30,$permissions)){
            array_push($_types, 2);
        }
        if(in_array(38,$permissions)){
            array_push($_types, 3);
        }
        if(in_array(39,$permissions)){
            array_push($_types, 4);
        }
        $types = Type::whereIn('id', $_types)->get();
        $status = Process::all();
        $clause = [
            ['_workpoint_from', $this->account->_workpoint]
        ];
        if($this->account->_rol == 4 ||  $this->account->_rol == 5 || $this->account->_rol == 7){
            array_push($clause, ['_created_by', $this->account->_account]);
        }
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
        $requisitions = Requisition::with(['type', 'status', 'products' => function($query){
                                        $query->with(['prices' => function($query){
                                            $query->whereIn('_type', [1,2,3,4,5])->orderBy('_type');
                                        }, 'units', 'variants']);
                                    }, 'to', 'from', 'created_by', 'log'])
                                    ->where($clause)
                                    ->whereIn('_status', [1,2,3,4,5,6,7,8,9,10])
                                    ->where([['created_at', '>=', $date_from], ['created_at', '<=', $date_to]])
                                    ->get();
        return response()->json([
            "workpoints" => $workpoints,
            "types" => $types,
            "status" => $status,
            "requisitions" => RequisitionResource::collection($requisitions)
        ]);
    }

    public function dashboard(){
        if(isset($request->date)){
            $date = $request->date;
        }
        $date= new \DateTime();
        $requisitions = Requisition::with(['type', 'status', 'products' => function($query){
                                        $query->with(['prices' => function($query){
                                            $query->whereIn('_type', [1,2,3,4,5])->orderBy('_type');
                                        }, 'units', 'variants']);
                                    }, 'to', 'from', 'created_by', 'log'])
                                    ->where('_workpoint_to', $this->account->_workpoint)
                                    ->whereIn('_status', [1,2,3,4,5,6,7,8,9,10])
                                    ->whereDate('created_at', $date)
                                    ->get();
        return response()->json(RequisitionResource::collection($requisitions));
    }

    public function find($id){
        $requisition = Requisition::with(['type', 'status', 'products' => function($query){
            $query->with(['units', 'variants']);
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
        $workpoint_to_print = Workpoint::find($this->account->_workpoint);
        $_workpoint_to = $workpoint_to_print->id;
        $requisition->load(['created_by' ,'log', 'products' => function($query) use ($_workpoint_to){
            $query->with(['locations' => function($query)  use ($_workpoint_to){
                $query->whereHas('celler', function($query) use ($_workpoint_to){
                    $query->where('_workpoint', $_workpoint_to);
                });
            }]);
        }]);
        $printer = $this->getPrinter($workpoint_to_print, $requisition->_workpoint_from);
        $cellerPrinter = new MiniPrinterController($printer['domain'], $printer['port']);
        $res = $cellerPrinter->requisitionTicket($requisition);
        $requisition->printed = $requisition->printed +1;
        $requisition->save();
        return response()->json(["success" => $res]);
    }

    public function demoImpresion(Request $request){
        $printer = \App\Printer::find($request->_printer);
        $cellerPrinter = new MiniPrinterController($printer->ip, 9100);
        $res = $cellerPrinter->demo();
        return response()->json(["success" => $res]);
    }

    public function search(Request $request){
        $folio = '';
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

    public function getPrinter($who, $for){
        $dominio = explode(':', $who->dominio)[0];
        switch($who->id){
            case 1:
                if($for == 8 || $for == 11 || $for == 13 || $for == 14 || $for == 3 || $for == 4 || $for == 7){
                    return ["domain" => env("PRINTER_ABAJO"), "port" => 9100];
                }else{
                    return ["domain" => env("PRINTER_ARRIBA"), "port" => 9100];
                }
                break;
            case 2:
                return ["domain" => "187.202.55.154", "port" => 4065];
                break;
            case 3:
                return ["domain" => "192.168.10.181", "port" => 9100];
                break;
            case 4:
                return ["domain" => $dominio, "port" => 4065];
                break;
            case 5:
                return ["domain" => $dominio, "port" => 4065];
                break;
            case 6:
                return ["domain" => $dominio, "port" => 4065];
                break;
            case 7:
                return ["domain" => $dominio, "port" => 4065];
                break;
            case 8:
                return ["domain" => $dominio, "port" => 4066];
                break;
            case 9:
                return ["domain" => $dominio, "port" => 4065];
                break;
            case 10:
                return ["domain" => $dominio, "port" => 4065];
                break;
            case 11:
                return ["domain" => $dominio, "port" => 4065];
                break;
            case 12:
                return ["domain" => $dominio, "port" => 4066];
                break;
            case 13:
                return ["domain" => $dominio, "port" => 4065];
                break;
            case 14:
                return ["domain" => $dominio, "port" => 9100];
                break;
            case 15:
                return ["domain" => env("PRINTER_ABAJO"), "port" => 9100];
                break;
        }
    }

    public function getVentaFromStore($folio, $workpoint_id, $caja, $to){
        $workpoint = WorkPoint::find($workpoint_id);
        $access = new AccessController($workpoint->dominio);
        $venta = $access->getSaleStore($folio, $caja);
        if($venta){
            if(isset($venta['msg'])){
                return ["msg" => $venta['msg']];
            }
            $toSupply = [];
            foreach($venta['products'] as $row){
                $product = Product::with(['stocks' => function($query) use ($to){
                    $query->where('_workpoint', $to);
                }])->where('code', $row['code'])->first();
                if($product){
                    $required = $row['req'];
                    if($product->_unit == 3){
                        $pieces = $product->pieces == 0 ? 1 : $product->pieces;
                        $required = round($required/$pieces, 2);
                    }
                    if($required > 0){
                        $toSupply[$product->id] = ['units' => $required, 'comments' => '', "stock" => count($product->stocks) > 0 ? $product->stocks[0]->pivot->stock : 0];
                    }
                }
            }
            return ["notes" => "Pedido preventa tienda #".$folio, "products" => $toSupply];
        }
        return ["msg" => "No se tenido conexión con la tienda"];
    }

    public function getToSupplyFromStore($workpoint_id, $workpoint_to){
        $workpoint = WorkPoint::find($workpoint_id);
        $_categories = $this->categoriesByStore($workpoint_id);
        $products = Product::with(['stocks' => function($query) use($workpoint_id){
            $query->where([
                ['_workpoint', $workpoint_id],
                ['min', '>', 0],
                ['max', '>', 0],
            ])/* ->orWhere([
                ['_workpoint', $workpoint_to],
                ['stock', '>', 0]
            ]) */;
        }])->whereHas('stocks', function($query) use($workpoint_id, $workpoint_to, $_categories){
            $query->where([
                ['_workpoint', $workpoint_id],
                ['min', '>', 0],
                ['max', '>', 0]
            ])->orWhere([
                ['_workpoint', $workpoint_to],
                ['stock', '>', 0]
            ]);
        }, '>', 1)->where('_status', '=', 1)->whereIn('_category', $_categories)->get();
        
        /**OBTENEMOS STOCKS */
        $toSupply = [];
        foreach($products as $key => $product){
            $stock = $product->stocks[0]->pivot->gen;
            $min = $product->stocks[0]->pivot->min;
            $max = $product->stocks[0]->pivot->max;
            if($max>$stock){
                $required = $max - $stock;
            }else{
                $required = 0;
            }

            if($product->_unit == 3){
                $pieces = $product->pieces == 0 ? 1 : $product->pieces;
                $required = floor($required/$pieces);
            }
            if($required > 0){
                if(($product->_unit == 1 && $required>6) || $product->_unit!=1){
                    $toSupply[$product->id] = ['units' => $required, 'comments' => '', 'stock' => 0];
                }
            }
        }
        return ["products" => $toSupply];
    }

    public function getPedidoFromStore($folio, $workpoint_id, $to){
        $workpoint = WorkPoint::find($workpoint_id);
        $access = new AccessController($workpoint->dominio);
        $venta = $access->getOrderStore($folio);
        if($venta){
            if(isset($venta['msg'])){
                return ["msg" => $venta['msg']];
            }
            $toSupply = [];
            foreach($venta['products'] as $row){
                $product = Product::with(['stocks' => function($query) use ($to){
                    $query->where('_workpoint', $to);
                }])->where('code', $row['code'])->first();
                $required = $row['req'];
                if(($row['units'] == 1 || $row['units'] == 2) && $product->_unit == 3){
                    $pieces = $product->pieces == 0 ? 1 : $product->pieces;
                    $required = round($required/$pieces, 2);
                }elseif($row['units'] == 3 && $product->_unit == 3){
                    $required = .5;
                }
                if($required > 0){
                    $toSupply[$product->id] = ['units' => $required, 'comments' => '', "stock" => count($product->stocks) > 0 ? $product->stocks[0]->pivot->stock : 0];
                }
            }
            return ["notes" => " Pedido preventa # ".$folio.$venta["notes"], "products" => $toSupply];
        }
        return ["msg" => "No se tenido conexión con la tienda"];
    }

    public function refreshStocks(Requisition $requisition){
        $_workpoint_to = $requisition->_workpoint_to;
        $requisition->load(['log', 'products' => function($query) use ($_workpoint_to){
            $query->with(['stocks' => function($query) use($_workpoint_to){
                $query->where('_workpoint', $_workpoint_to);
            }]);
        }]);
        foreach($requisition->products as $product){
            $requisition->products()->syncWithoutDetaching([
                $product->id => [
                    'units' => $product->pivot->units,
                    'comments' => $product->pivot->comments,
                    'stock' => count($product->stocks) > 0 ? $product->stocks[0]->pivot->stock : 0
                ]
            ]);
        }
        return true;
    }

    public function categoriesByStore($_workpoint){
        /**
         * MOC => 405 - 447
         * PAP => 515 - 552
         * CAL => 588 - 628
         * HOG => 752 - 779
         * ELE => 629 - 669
         * JUG => 448 - 514
         * PAR => 553 - 587 AND 780 - 786
         * */
        switch($_workpoint){
            case 1:
            case 4:
            case 5:
            case 7:
            case 13:
            case 9:
                return range(405, 447);
                break;
            case 6:
            case 10:
            case 12:
                $_categories = [range(515,552), range(588, 628), range(752, 779), range(629, 669)];
                return array_merge_recursive(...$_categories);
                break;
            case 11:
                return range(448, 514);
                break;
            case 8:
                $_categories = [range(515,552), range(588, 628), range(448, 514)];
                return array_merge_recursive(...$_categories);
                break;
            case 3:
                $_categories = [range(515,552), range(588, 628), range(752, 779), range(629, 669), range(448, 514), range(553, 587), range(780, 786)];
                return array_merge_recursive(...$_categories);
                break;
        }
    }
}
