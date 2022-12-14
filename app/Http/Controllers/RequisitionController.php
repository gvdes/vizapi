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
use App\Http\Resources\ProductRequired as ProductResource;

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
                        // return response()->json();
                        $data = $this->getToSupplyFromStore($this->account->_workpoint, $_workpoint_to);
                    break;
                    case 3:
                        $_workpoint_from = (isset($request->store) && $request->store) ? $request->store : $this->account->_workpoint;
                        $cadena = explode('-', $request->folio);
                        $folio = count($cadena)>1 ? $cadena[1] : '0';
                        $caja = count($cadena)>0 ? $cadena[0] : '0';
                        $data = $this->getVentaFromStore($folio, $_workpoint_from, $caja, $_workpoint_to);
                        $request->notes = $request->notes ? $request->notes." ".$data['notes'] : $data['notes'];
                    break;
                    case 4:
                        $data = $this->getPedidoFromStore($request->folio, $_workpoint_from, $_workpoint_to);
                        $request->notes = $request->notes ? $request->notes." ".$data['notes'] : $data['notes'];
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

                if(isset($data['products'])){ $requisition->products()->attach($data['products']); }

                if($request->_type != 1){ $this->refreshStocks($requisition); }

                return $requisition->fresh('type', 'status', 'products', 'to', 'from', 'created_by', 'log');
            });
            return response()->json([
                "success" => true,
                "order" => new RequisitionResource($requisition)
            ]);
        }catch(\Exception $e){
            return response()->json(["message" => "No se ha podido crear el pedido", "Error"=>$e]);
        }
    }

    public function addProduct(Request $request){
        try{
            $amount = isset($request->amount) ? $request->amount : 1; /* CANTIDAD EN UNIDAD */
            if($amount<=0){
                return response()->json(["msg" => "No se puede agregar esta unidad", "success" => false, "server_status" => 400]);
            }
            $requisition = Requisition::find($request->_requisition);
            if($this->account->_account == $requisition->_created_by || in_array($this->account->_rol, [1,2,3,6])){
                $to = $requisition->_workpoint_to;
                $product = Product::
                with(['stocks' => function($query) use ($to){
                    $query->where('_workpoint', $to);
                }, 'prices' => function($query){
                    $query->where('_type', 7);
                }])->find($request->_product);

                $cost = count($product->prices)> 0 ? $product->prices[0]->pivot->price : 0;
                $_supply_by = isset($request->_supply_by) ? $request->_supply_by : $product->_unit;
                $units = $this->getAmount($product, $amount, $_supply_by);
                $stock = count($product->stocks)>0 ? $product->stocks[0]->pivot->stock : 0;
                $total = $cost * $units;

                $requisition->products()->syncWithoutDetaching([
                    $request->_product => [
                        'amount' => $amount,
                        '_supply_by' => $_supply_by,
                        'units' => $units,
                        'cost' => $cost,
                        'total' => $total,
                        'comments' => isset($request->comments) ? $request->comments : "",
                        'stock' => $stock
                    ]
                ]);
                $productAdded = $requisition->products()->selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS category')
                ->with(['units', 'stocks' => function($query) use ($to){
                    $query->where('_workpoint', $this->account->_workpoint);
                }, 'prices' => function($query){
                    $query->where('_type', 7);
                }])->where("id", $request->_product)->first();
                return response()->json(new ProductResource($productAdded));
            }else{
                return response()->json(["msg" => "No puedes agregar productos", "success" => false]);
            }
        }catch(\Exception $e){
            return response()->json(["msg" => "No se ha podido agregar el producto", "success" => false]);
        }
    }

    public function addMassiveProduct(Request $request){
        $requisition = Requisition::find($request->_requisition);
        $products = isset($request->products) ? $request->products : [];
        $notFound = [];
        $soldOut = [];
        $added = [];
        if($requisition){
            $to = $requisition->_workpoint_to;
            foreach($products as $row){
                $code = $row['code'];
                $product = Product::
                selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS category')
                ->whereHas('variants', function($query) use ($code){
                    $query->where('barcode', $code);
                })->with(['stocks' => function($query) use ($to){
                    $query->whereIn('_workpoint', [$to, $this->account->_workpoint]);
                }])->first();
                if(!$product){
                    $product = Product::
                    selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS category')
                    ->where([['code', $code], ['_status', '!=', 4]])->orWhere([['name', $code], ['_status', '!=', 4]])->with(['stocks' => function($query) use ($to){
                        $query->whereIn('_workpoint', [$to, $this->account->_workpoint]);
                    }])->first();
                }
                if($product){
                    $cost = count($product->prices)> 0 ? $product->prices[0]->pivot->price : false;
                    $amount = isset($row["amount"]) ? $row["amount"] : 1;
                    $_supply_by = isset($request->_supply_by) ? $request->_supply_by : $product->_unit;
                    $units = $this->getAmount($product, $amount, $_supply_by);
                    $_workpoint_stock = $product->stocks->map(function($stock){
                        return $stock->id;
                    })->toArray();
                    $key_stock = array_search($to, $_workpoint_stock); //Tienda que solicita la mercancia
                    $key_stock_from = array_search($this->account->_workpoint, $_workpoint_stock); //Tienda a la que le piden la mercancia
                    $stock = ($key_stock > 0 || $key_stock === 0) ? $product->stocks[$key_stock]->pivot->stock : 0;
                    $total = $cost * $units;
                    $requisition->products()->syncWithoutDetaching([
                        $product->id => [
                            'amount' => $amount,
                            '_supply_by' => $_supply_by,
                            'units' => $units,
                            'cost' => $cost,
                            'total' => $total,
                            'comments' => isset($row["comments"]) ? $row["comments"] : "",
                            'stock' => $stock
                        ]
                    ]);
                    $added [] = [
                        "id" => $product->id,
                        "code" => $product->code,
                        "name" => $product->name,
                        "cost" => $product->cost,
                        'barcode' => $product->barcode,
                        'label' => $product->label,
                        "description" => $product->description,
                        "dimensions" => $product->dimensions,
                        "section" => $product->section,
                        "family" => $product->family,
                        "category" => $product->category,
                        "pieces" => $product->pieces,
                        "units" => $product->units,
                        "ordered" => [
                            "amount" => $amount,
                            "_supply_by" => $_supply_by,
                            "units" => $units,
                            "cost" => $cost,
                            "total" => $total,
                            "comments" => isset($row["comments"]) ? $row["comments"] : "",
                            "stock" => $stock,
                            "toDelivered" => null,
                            "toReceived" => null
                        ],
                        "stocks" => [
                            [
                                "alias" => ($key_stock_from > 0 || $key_stock_from === 0) ? $product->stocks[$key_stock_from]->alias : "",
                                "name" => ($key_stock_from > 0 || $key_stock_from === 0) ? $product->stocks[$key_stock_from]->name : "",
                                "stock"=> ($key_stock_from > 0 || $key_stock_from === 0) ? $product->stocks[$key_stock_from]->pivot->stock : 0,
                                "gen" => ($key_stock_from > 0 || $key_stock_from === 0) ? $product->stocks[$key_stock_from]->pivot->gen : 0,
                                "exh" => ($key_stock_from > 0 || $key_stock_from === 0) ? $product->stocks[$key_stock_from]->pivot->exh : 0,
                                "min" => ($key_stock_from > 0 || $key_stock_from === 0) ? $product->stocks[$key_stock_from]->pivot->min : 0,
                                "max"=> ($key_stock_from > 0 || $key_stock_from === 0) ? $product->stocks[$key_stock_from]->pivot->min : 0,
                            ]
                        ]
                    ];
                }else{
                    $notFound[] = $row["code"];
                }
            }

        }
        return response()->json(["added" => $added, "notFound" => $notFound]);
    }

    public function sendWhatsapp($tel, $msg){
        $curl = curl_init();//inicia el curl para el envio de el mensaje via whats app
        curl_setopt_array($curl, array(
          CURLOPT_URL => "https://api.ultramsg.com/instance9800/messages/chat",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_SSL_VERIFYHOST => 0,
          CURLOPT_SSL_VERIFYPEER => 0,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => "token=6r5vqntlz18k61iu&to=+52$tel&body=$msg&priority=1&referenceId=",//se redacta el mensaje que se va a enviar con los modelos y las piezas y el numero de salida
          CURLOPT_HTTPHEADER => array("content-type: application/x-www-form-urlencoded"),));
        $response = curl_exec($curl);
        $err = curl_error($curl);

        return $response;
        curl_close($curl);
    }

    public function removeProduct(Request $request){
        try{
            $requisition = Requisition::find($request->_requisition);
            if($this->account->_account == $requisition->_created_by || in_array($this->account->_rol, [1,2,3,6])){
                $requisition->products()->detach([$request->_product]);
                return response()->json(["success" => true]);
            }else{
                return response()->json(["msg" => "No puedes eliminar productos"]);
            }
        }catch(\Exception $e){
            return response()->json(["msg" => "No se ha podido eliminar el producto"]);
        }
    }

    public function log($case, Requisition $requisition, $_printer=null, $actors=[]){
        $account = Account::with('user')->find($this->account->id);
        $responsable = $account->user->names.' '.$account->user->surname_pat;
        $previous = null;

        // $requisition->load(["from","to"]);
        // $requisition->fresh();

        // $telAdminFrom = $requisition->from["tel_admin"];
        // $telAdminTo = $requisition->from["tel_admin"];

        if($case != 1){
            $logs = $requisition->log->toArray();
            $end = end($logs);
            $previous = $end['pivot']['_status'];
        }

        if($previous){
            $requisition->log()->syncWithoutDetaching([$previous => [ 'updated_at' => new \DateTime()]]);
        }

        switch($case){
            case 1: // LEVANTAR PEDIDO
                $requisition->log()->attach(1, [ 'details'=>json_encode([ "responsable"=>$responsable ]) ]);
            break;

            case 2: // POR SURTIR => IMPRESION DE COMPROBANTE EN TIENDA
                // $port = $requisition->_workpoint_to==2 ? 4065:9100;
                $port = 9100;

                $requisition->log()->attach(2, [ 'details'=>json_encode([ "responsable"=>$responsable ]) ]);// se inserta el log dos al pedido con su responsable
                $requisition->_status=2; // se prepara el cambio de status del pedido (a por surtir (2))
                $requisition->save(); // se guardan los cambios
                $requisition->fresh(['log']); // se refresca el log del pedido

                // $whats1 = $this->sendWhatsapp($telAdminFrom, "CEDIS ha recibido tu pedido - FOLIO: #$requisition->id ????????");
                // $whats2 = $this->sendWhatsapp($telAdminFrom, "Nuevo pedido de ".$requisition->from['name'].": #$requisition->id, esta listo para iniciar surtido!!");

                // traemos el cuerpo del pedido con las ubicaciones del workpoint destino
                $_workpoint_to = $requisition->_workpoint_to;
                $requisition->load(['log', 'products' => function($query) use ($_workpoint_to){
                    $query->with(['locations' => function($query)  use ($_workpoint_to){
                        $query->whereHas('celler', function($query) use ($_workpoint_to){
                            $query->where('_workpoint', $_workpoint_to);
                        });
                    }]);
                }]);

                // definir IMPRESION AUTOMATICA DEL PEDIDO
                // $stores_p2 = [8,11,19];
                // $ipprinter = in_array($requisition->_workpoint_from, $stores_p2) ? env("PRINTER_P2") : env("PRINTER_P3");

                $ipprinter = \App\Printer::where([['_type', 2], ['_workpoint', $_workpoint_to]])->first();

                $miniprinter = new MiniPrinterController($ipprinter->ip, $port);
                $printed_provider = $miniprinter->requisitionTicket($requisition);

                if($printed_provider){
                    $requisition->printed = ($requisition->printed+1);
                    $requisition->save();
                }

                // NOTIFICAR VIA IMPRESION / WHATSAPP A LA TIENDA QUE SOLICITO EL PEDIDO
                $printer = $_printer ? \App\Printer::find($_printer) : \App\Printer::where([['_type', 2], ['_workpoint', $this->account->_workpoint]])->first();
                $miniprinter = new MiniPrinterController($printer->ip, $port);
                $printed_store = $miniprinter->requisitionReceipt($requisition); //Se ejecuta la impresi??n
            break;

            case 3: // SURTIENDO
                $requisition->log()->attach(3, [ 'details'=>json_encode([ "responsable"=>$responsable, "actors"=>$actors ]) ]);

                $requisition->_status = 3;
                $requisition->save();

                // $_workpoint_to = $requisition->_workpoint_to;
                    // $requisition->load(['log', 'products' => function($query) use ($_workpoint_to){
                    //     $query->with(['locations' => function($query)  use ($_workpoint_to){
                    //         $query->whereHas('celler', function($query) use ($_workpoint_to){
                    //             $query->where('_workpoint', $_workpoint_to);
                    //         });
                    //     }]);
                    // }]);
                    // $printer = $_printer ? \App\Printer::find($_printer) : $this->getPrinterDefault($requisition->_workpoint_from, $requisition->_workpoint_to);
                    // $miniprinter = new MiniPrinterController($printer->ip, 9100);
                    // if($miniprinter->requisitionTicket($requisition)){
                    //     $requisition->printed = $requisition->printed + 1;
                    //     $requisition->save();
                // }
            break;
        //     case 4: // POR VALIDAR EMBARQUE
        //         //     $requisition->log()->attach(4, [ 'details' => json_encode([
        //         //         "responsable" => $responsable
        //         //     ])]);
        //         //     $requisition->_status = 4;
        //         //     $requisition->save();
        //     // break;
        //     case 5: // VALIDANDO EMBARQUE
        //         $requisition->log()->attach(5, [ 'details' => json_encode([
        //             "responsable" => $responsable,
        //             "actors" => $actors
        //         ])]);
        //         $requisition->_status = 5;
        //         $requisition->save();
        //     break;
        //     case 6: // POR ENVIAR
        //         $requisition->load(['products']);
        //         if($requisition->_workpoint_from === 1 && $requisition->_workpoint_to === 2){
        //             $printer = $_printer ? \App\Printer::find($_printer) : \App\Printer::where([['_type', 2], ['_workpoint', $requisition->to->id]])->first();
        //             $miniprinter = new MiniPrinterController($printer->ip, 9100);
        //             $miniprinter->requisition_transfer($requisition);
        //             $requisition->log()->attach(6, [
        //                 'details' => json_encode([
        //                     "responsable" => $responsable,
        //                     "actors" => $actors,
        //                     "order" => [
        //                         "status" => 200,
        //                         "serie" => "N/A",
        //                         "ticket" => "N/A"
        //                     ],
        //                     "document" => "Traspaso"
        //                 ])
        //             ]);
        //             $requisition->_status = 6;
        //             $requisition->save();
        //         }else{
        //             $access = new AccessController($requisition->to->dominio);
        //             $response = $access->createClientRequisition(new RequisitionResource($requisition));
        //             if($response && $response["status"] == 200){
        //                 $printer = $_printer ? \App\Printer::find($_printer) : \App\Printer::where([['_type', 2], ['_workpoint', $requisition->to->id]])->first();
        //                 $miniprinter = new MiniPrinterController($printer->ip, 9100);
        //                 $miniprinter->validationTicketRequisition($response, $requisition);
        //                 $requisition->log()->attach(6, [
        //                     'details' => json_encode([
        //                         "responsable" => $responsable,
        //                         "actors" => $actors,
        //                         "order" => $response,
        //                         "document" => "Pedido a cliente"
        //                     ])
        //                 ]);
        //                 $requisition->_status = 6;
        //                 $requisition->save();
        //             }
        //         }
        //     break;
            case 7: // EN CAMINO => SELECCIONAR VEHICULOS
                $requisition->log()->attach(7, [ 'details' => json_encode([
                    "responsable" => $responsable,
                    "actors" => $actors
                ])]);
                $requisition->_status = 7;
                $requisition->save();
            break;
        //     case 8: // POR VALIDAR RECEPCI??N
        //         $requisition->log()->attach(8, [ 'details' => json_encode([
        //             "responsable" => $responsable
        //         ])]);
        //         $requisition->_status = 8;
        //         $requisition->save();
        //     break;
        //     case 9: // VALIDANDO RECEPCI??N
        //         $requisition->log()->attach(9, [ 'details' => json_encode([
        //             "responsable" => $responsable,
        //             "actors" => $actors
        //         ])]);
        //         $requisition->_status = 9;
        //         $requisition->save();
        //         $_workpoint_from = $requisition->_workpoint_from;
        //         $requisition->load(['log', 'products' => function($query) use ($_workpoint_from){
        //             $query->with(['locations' => function($query)  use ($_workpoint_from){
        //                 $query->whereHas('celler', function($query) use ($_workpoint_from){
        //                     $query->where('_workpoint', $_workpoint_from);
        //                 });
        //             }]);
        //         }]);
        //         $printer = $printer ? \App\Printer::find($_printer) : \App\Printer::where([['_type', 2], ['_workpoint', $requisition->_workpoint_from]])->first();
        //         $storePrinter = new MiniPrinterController($printer['domain'], $printer['port']);
        //         $storePrinter->requisitionTicket($requisition);
        //     break;
        //     case 10:
        //         $requisition->log()->attach(10, [ 'details' => json_encode([
        //             "responsable" => $responsable
        //         ])]);
        //         $requisition->_status = 10;
        //         $requisition->save();
        //     break;
        //     case 100:
        //         $requisition->log()->attach(100, [ 'details' => json_encode([
        //             "responsable" => $responsable
        //         ])]);
        //         $requisition->_status = 100;
        //         $requisition->save();
        //     break;
        //     case 101:
        //         $requisition->log()->attach(101, [ 'details' => json_encode([])]);
        //         $requisition->_status = 101;
        //         $requisition->save();
        //     break;
        }

        $requisition->refresh('log');

        $log = $requisition->log->filter(function($event) use($case){
            return $event->id >= $case;
        })->values()->map(function($event){
            return [
                "id" => $event->id,
                "name" => $event->name,
                "active" => $event->active,
                "allow" => $event->allow,
                "details" => json_decode($event->pivot->details),
                "created_at" => $event->pivot->created_at->format('Y-m-d H:i'),
                "updated_at" => $event->pivot->updated_at->format('Y-m-d H:i')
            ];
        });

        return [
            "success" => (count($log)>0),
            "printed" => $requisition->printed,
            "status" => $requisition->status,
            "log" => $log
        ];
    }

    public function index(Request $request){ // Funci??n para traer todos los pedidos que ha levantado la sucursal
        $workpoints = WorkPoint::where('_type', 1)->get(); // Obtener la lista de sucursales de tipo CEDIS
        $account = Account::with(['permissions'])->find($this->account->id); // Revisar permisos de la sucursal
        $permissions = array_column($account->permissions->toArray(), 'id'); //IDs de los permisos
        $_types = [1,2,3,4]; // <array> para almacenar los tipo de pedidos que puede levantar el usuario
        // if(in_array(29,$permissions)){
        //     array_push($_types, 1);
        // }
        // if(in_array(30,$permissions)){
        //     array_push($_types, 2);
        // }
        // if(in_array(38,$permissions)){
        //     array_push($_types, 3);
        // }
        // if(in_array(39,$permissions)){
        //     array_push($_types, 4);
        // }
        $types = Type::whereIn('id', $_types)->get(); // Se obtiene los tipos de pedidos que se pueden levantar
        $status = Process::all(); // Se obtienen todos los status de pedidos
        $clause = [
            ['_workpoint_from', $this->account->_workpoint]
        ];
        if($this->account->_rol == 4 ||  $this->account->_rol == 5 || $this->account->_rol == 7){
            // Se v??lida el rol, si no eres administrador solo podras ver los pedidos que haz levantado
            array_push($clause, ['_created_by', $this->account->_account]);
        }
        // Se determina el periodo de busqueda para los pedidos
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
        $requisitions = Requisition::with(['type', 'status', 'to', 'from', 'created_by', 'log'])
                                    ->where($clause)
                                    ->whereIn('_status', [1,2,3,4,5,6,7,8,9,10])
                                    ->withCount(["products"])
                                    ->where([['created_at', '>=', $date_from], ['created_at', '<=', $date_to]])
                                    ->get(); // Se traen todos los pedidos que cumplen el filtro
        return response()->json([
            "workpoints" => WorkPoint::with('type')->whereIn('_type', [1,2])->get(), // Lista de todas las sucursales
            "types" => $types,
            "status" => $status,
            "requisitions" => RequisitionResource::collection($requisitions)
        ]);
    }

    public function dashboard(Request $request){ // Funci??n para trer todos los pedidos que le han solicitado a la sucursal
        // Se determina el periodo de busqueda para los pedidos
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
        $requisitions = Requisition::with(['type', 'status', 'to', 'from', 'created_by', 'log', 'products' => function($query){
                                        $query->with(['prices' => function($query){
                                            $query->whereIn('_type', [1,2,3,4,5])->orderBy('_type');
                                        }, 'units', 'variants']);
                                    }])
                                    ->where('_workpoint_to', $this->account->_workpoint)
                                    ->withCount(["products"])
                                    ->whereIn('_status', [1,2,3,4,5,6,7,8,9,10])
                                    ->where([['created_at', '>=', $date_from], ['created_at', '<=', $date_to]])
                                    ->get(); // Se traen todos los pedidos que cumplen el filtro

        return response()->json([
            "workpoints" => WorkPoint::all(), // Lista de todas las sucursales
            "types" => Type::all(),
            "status" => Process::all(),
            "requisitions" => RequisitionResource::collection($requisitions)
        ]);
    }

    public function find($id){ // Funci??n para buscar un pedido en especifico
        // Se repeta la estructure establecida con Geo
        $requisition = Requisition::with(['type', 'status', 'products' => function($query){
            $query
            ->selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS category')
            ->with(['units', 'variants', 'prices' => function($query){
                return $query->where('_type', 1);
            }, 'stocks' => function($query){
                return $query->where('_workpoint', $this->account->_workpoint);
            }]);
        }, 'to', 'from', 'created_by', 'log'])
        ->withCount(["products"])->find($id);
        return response()->json(new RequisitionResource($requisition));
    }

    public function nextStep(Request $request){
        $requisition = Requisition::with(["to", "from", "created_by"])->find($request->id);
        $server_status = 200;
        if($requisition){
            $_status = isset($request->_status) ? $request->_status:$requisition->_status+1;
            $_printer = isset($request->_printer) ? $request->_printer:null;
            $_actors = isset($request->_actors) ? $request->_actors:[];

            $process = Process::all()->toArray();

            if(in_array($_status, array_column($process, "id"))){
                $result = $this->log($_status, $requisition, $_printer, $_actors);
                $msg = $result["success"] ? "" : "No se pudo cambiar el status";
                $server_status = $result ["success"] ? 200 : 500;
            }else{
                $msg = "Status no v??lido";
                $server_status = 400;
            }
        }else{
            $msg = "Pedido no encontrado";
            $server_status = 404;
        }

        return response()->json([
            "success" => isset($result) ? $result["success"] : false,
            "serve_status" => $server_status,
            "msg" => $msg,
            "updates" =>[
                "status" => isset($result) ? $result["status"] : null,
                "log" => isset($result) ? $result["log"] : null,
                "printed" =>  isset($result) ? $result["printed"] : null
            ]
        ]);
    }

    public function updateStocks(Request $request){
        $requisition = Requisition::with(['products'])->find($request->id);
        if($requisition){
            $this->refreshStocks($requisition);
            $requisition->load(["products" => function($query){
                $query
                ->selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS category')
                ->with(['units', 'variants', 'prices' => function($query){
                    return $query->where('_type', 1);
                }, 'stocks' => function($query){
                    return $query->where('_workpoint', $this->account->_workpoint);
                }]);
            }]);
            return response()->json(["products" => ProductResource::collection($requisition->products), "success" => true, "msg" => "ok", "server_status" => 200]);
        }else{
            return response()->json(["success" => false, "msg" => "Pedido no encontrado", "server_status" => 404]);
        }
    }

    public function reimpresion(Request $request){
        $requisition = Requisition::find($request->_requisition);
        $workpoint_to_print = Workpoint::find($this->account->_workpoint);
        $requisition->load(['created_by' ,'log', 'products' => function($query){
            $query->with(['locations' => function($query){
                $query->whereHas('celler', function($query){
                    $query->where('_workpoint', $this->account->_workpoint);
                });
            }]);
        }]);
        $printer = isset($request->_printer) ? \App\Printer::find($request->_printer) : $this->getPrinterDefault($requisition->_workpoint_from, $this->account->_workpoint);
        $port = 9100;
        // if($this->account->_workpoint == 2){ $port = 4065; }else{ $port = 9100; }
        $cellerPrinter = new MiniPrinterController($printer->ip, $port);
        $res = $cellerPrinter->requisitionTicket($requisition);
        if($requisition->_workpoint_to == $this->account->_workpoint){
            $requisition->printed = $requisition->printed +1;
            $requisition->save();
        }
        return response()->json(["success" => $res, "printer" => $printer, [$requisition->_workpoint_from, $this->account->_workpoint]]);
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

    public function getVentaFromStore($folio, $workpoint_id, $caja, $to){ // Funci??n que nos ayuda a obtener las ventas de las sucursales
        // Se necesita saber el folio, la sucursal, la caja y a que sucursal pasaremos el pedido
        $workpoint = WorkPoint::find($workpoint_id); // Buscamos la sucursal de la que obtendremos la venta
        $access = new AccessController($workpoint->dominio); // Conexi??n al ACCESS de la sucursal
        $venta = $access->getSaleStore($folio, $caja); // Obtenemos las ventas de la sucursal
        if($venta){ // Validamos si encontramos la venta, de ser el caso adaptamos al formato para insertar los productos
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
                        $toSupply[$product->id] = ['units' => $required, "cost" => $product->cost, 'amount' => round($required/$pieces, 2),  "_supply_by" => 3, 'comments' => '', "stock" => count($product->stocks) > 0 ? $product->stocks[0]->pivot->stock : 0];
                    }else{
                        $toSupply[$product->id] = ['units' => $required, "cost" => $product->cost, 'amount' => $required,  "_supply_by" => 1 , 'comments' => '', "stock" => count($product->stocks) > 0 ? $product->stocks[0]->pivot->stock : 0];
                    }
                }
            }
            // Retornamos los siguientes datos para poder trabajar con el pedido
            return ["notes" => "Pedido venta tienda #".$folio, "products" => $toSupply];
        }
        return ["msg" => "No se tenido conexi??n con la tienda"];
    }

    public function getToSupplyFromStore($workpoint_id, $workpoint_to){ // Funci??n para hacer el pedido de minimos y m??ximos de la sucursal
        $workpoint = WorkPoint::find($workpoint_id); // Obtenemos la sucursal a la que se le realizara el pedido
        $cats = $this->categoriesByStore($workpoint_id); // Obtener todas las categorias que puede pedir la sucursal
        // Todos los productos antes de ser solicitados se v??lida que haya en CEDIS y la sucursal los necesite en verdad, verificando que la existencia actual sea menor al m??ximo en primer instancia

        $wkf = $workpoint_id;
        $wkt = $workpoint_to;

        $pquery = "SELECT
                P.id AS id,
                P.code AS code,
                P._unit AS unitsupply,
                P.pieces AS ipack,
                P.cost AS cost,
                    (SELECT stock FROM product_stock WHERE _workpoint=$wkf AND _product = P.id AND _status != 4 AND min > 0 AND max > 0) AS stock,
                    (SELECT  min FROM product_stock WHERE _workpoint=$wkf AND _product = P.id) AS min,
                    (SELECT max FROM product_stock WHERE _workpoint=$wkf AND _product = P.id) AS max,
                    SUM(IF(PS._workpoint=$wkf, PS.stock, 0)) AS CEDIS,
                    (SELECT SUM(stock) FROM product_stock WHERE _workpoint = 2 AND _product = P.id) AS PANTACO
                FROM
                    products P
                        INNER JOIN
                    product_categories PC ON PC.id = P._category
                        INNER JOIN
                    product_stock PS ON PS._product = P.id
                WHERE
                    GETSECTION(PC.id) in ($cats)
                        AND P._status != 4
                        AND (IF(PS._workpoint = 1, PS._status, 0)) = 1
                        AND ((SELECT stock FROM product_stock WHERE _workpoint=$wkf AND _product = P.id AND _status != 4 AND min > 0 AND max > 0)) IS NOT NULL
                        AND (IF((SELECT  stock FROM product_stock WHERE _workpoint=$wkf AND _product = P.id AND _status != 4 AND min > 0 AND max > 0) <= (SELECT  min FROM product_stock WHERE  _workpoint=$wkf AND _product = P.id), (SELECT  max FROM product_stock WHERE _workpoint=$wkf AND _product = P.id) - (SELECT  stock FROM product_stock WHERE _workpoint=$wkf AND _product = P.id AND _status != 4 AND min > 0 AND max > 0), 0)) > 0
                GROUP BY P.code";

        $rows = DB::select($pquery);
        $tosupply = [];

        foreach ($rows as $product) {
            $stock = $product->stock;
            $min = $product->min;
            $max = $product->max;

            $required = ($stock<=$min) ? ($max-$stock) : 0;

            if($required){
                if( $product->unitsupply==3 ){
                    $ipack = $product->ipack == 0 ? 1 : $product->ipack;
                    $boxes = floor($required/$ipack);

                    if($boxes>=1){
                        $tosupply[$product->id] = [ 'units'=>$required, "cost"=>$product->cost, 'amount'=>$boxes, "_supply_by"=>3, 'comments'=>'', "stock"=>0 ];
                    }
                }else if( $product->unitsupply==1 && $required>6 ){
                    $tosupply[$product->id] = [ 'units'=>$required, "cost"=>$product->cost, 'amount'=>$required,  "_supply_by"=>1 , 'comments'=>'', "stock"=>0];
                }
            }
        }

        return ["products" => $tosupply];
    }

    public function getPedidoFromStore($folio, $to){ // Funci??n para exportar los productos que un pedido de preventa
        $order = \App\Order::find($folio); // Se busca el folio del pedido
        if($order){ // Se encontro el pedido
            $toSupply = []; // <array> para producto del pedido de preventa
            $products = $order->products()->with(["stocks" => function($query) use($to){
                $query->where("_workpoint", $to);
            }, 'prices' => function($query){
                $query->where('_type', 7);
            }])->get(); // Se obtienen los pedidos
            foreach($products as $product){ // Se le da formato a los producto para poder ser insertados
                $cost = count($product->prices)> 0 ? $product->prices[0]->pivot->price : 0; // Se obtiene el costo del producto
                $toSupply[$product->id] = [
                    'amount' => $product->pivot->amount,
                    '_supply_by' => $product->pivot->_supply_by,
                    'units' => $product->pivot->units,
                    'cost' => $cost,
                    'total' => $cost * $product->pivot->units,
                    'comments' => $product->pivot->comments,
                    "stock" => count($product->stocks) > 0 ? $product->stocks[0]->pivot->stock : 0
                ];
            }
            return ["notes" => " Pedido preventa #".$folio.", ".$order->name, "products" => $toSupply];
        }
        return ["msg" => "No se encontro el pedido"];
    }

    public function refreshStocks(Requisition $requisition){ // Funci??n para actualizar los stocks de un pedido de resurtido
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

        /* IMPORTANTE */
        /* En este lugar se establecen las secciones que puede solicitar una sucursal */
        switch($_workpoint){
            case 1: return '"Navidad", "Juguete"'; break;
            case 3: return '"Navidad"'; break;
            case 4: return '"Navidad"'; break;
            case 5: return '"Navidad"'; break;
            case 7: return '"Navidad"'; break;
            case 8: return '"Calculadora", "Juguete", "Papeleria"'; break;
            case 9: return '"Navidad"'; break;
            case 6: return '"Calculadora", "Electronico", "Hogar"'; break;
            case 11: return '"Juguete"'; break;
            case 10: return '"Navidad"'; break;
            case 12: return '"Calculadora", "Electronico", "Hogar"'; break;
            case 13: return '"Navidad"'; break;
            case 19: return '"Navidad"'; break;
        }
    }

    public function getAmount($product, $amount, $_supply_by, $pieces = false){ // Funci??n para comvertir las unidades a piezas
        $pieces = $pieces ? $pieces : $product->pieces; // Obtener las piezas por caja
        switch ($_supply_by){
            case 1: // Conversi??n de piezas
                return $amount;
            break;
            case 2: // Conversi??n de docenas
                return $amount * 12;
            break;
            case 3: // Conversi??n de cajas
                return ($amount * $pieces);
            break;
            case 4: // Conversi??n de medias cajas
                return round($amount * ($pieces/2));
            break;
        }
    }

    public function setDeliveryValue(Request $request){
        try{
            $requisition = Requisition::find($request->_requisition);
            if(/* $requisition->_status == 5 */ 1 == 1){
                $product = $requisition->products()->where('id', $request->_product)->first();
                if($product){
                    $amount = isset($request->amount) ? $request->amount : 1; /* CANTIDAD EN UNIDAD */
                    $_supply_by = isset($request->_supply_by) ? $request->_supply_by : 1; /* UNIDAD DE MEDIDA */
                    $pieces = isset($request->pieces) ? $request->pieces : $product->pieces;
                    $units = $this->getAmount($product, $amount, $_supply_by, $pieces); /* CANTIDAD EN PIEZAS */
                    $total = $product->pivot->cost * $units;
                    $requisition->products()->syncWithoutDetaching([
                        $request->_product => [
                            'amount' => $amount,
                            '_supply_by' => $_supply_by,
                            'toDelivered' => $units,
                            "total" => $total
                        ]
                    ]);

                    $productUpdated = $requisition->products()->selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS category')
                        ->with(['units', 'stocks' => function($query){
                            $query->where('_workpoint', $this->account->_workpoint);
                        }, 'prices' => function($query){
                            $query->where('_type', 7);
                        }])->where("id", $request->_product)->first();

                        return response()->json([
                            "success" => true,
                            "server_status" => 200,
                            "msg" => "ok",
                            "data" => new ProductResource($productUpdated)
                        ]);
                }else{
                    $product = Product::
                        with(['stocks' => function($query) use($requisition){
                            $query->where('_workpoint', $requisition->_workpoint_from)->distinct();
                        }, 'prices' => function($query){
                            $query->where('_type', 7);
                        }])->find($request->_product);
                    if($product){
                        $pieces = isset($request->pieces) ? $request->pieces : $product->pieces;
                        $cost = count($product->prices)> 0 ? $product->prices[0]->pivot->price : 0;
                        $amount = isset($request->amount) ? $request->amount : 1; /* CANTIDAD EN UNIDAD */
                        $_supply_by = isset($request->_supply_by) ? $request->_supply_by : 1; /* UNIDAD DE MEDIDA */
                        $units = $this->getAmount($product, $amount, $_supply_by, $pieces); /* CANTIDAD EN PIEZAS */
                        $stock = count($product->stocks)>0 ? $product->stocks[0]->pivot->stock : 0;
                        $total = $cost * $units;
                        $comments = isset($request->comments) ? $request->comments : "";

                        $requisition->products()->syncWithoutDetaching([
                            $request->_product => [
                                'units' => 0,
                                'amount' => $amount,
                                'cost' => $cost,
                                'comments' => $comments,
                                '_supply_by' => $_supply_by,
                                'toDelivered' => $units,
                                'total' => $total,
                                'stock' => $stock
                            ]
                        ]);

                        $productUpdated = $requisition->products()->selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS category')
                            ->with(['units', 'stocks' => function($query){
                                $query->where('_workpoint', $this->account->_workpoint);
                            }, 'prices' => function($query){
                                $query->where('_type', 7);
                            }])->where("id", $request->_product)->first();

                            return response()->json([
                                "success" => true,
                                "server_status" => 200,
                                "msg" => "ok",
                                "data" => new ProductResource($productUpdated)
                            ]);
                    }else{
                        return response()->json(["msg" => "El producto no se encuentra", "server_status" => 404, "success" => false]);
                    }
                }
            }else{
                return response()->json(["msg" => "No se pueden agregar valores de validaci??n de salida en este momento", "server_status" => 404, "success" => false]);
            }
        }catch(\Exception $e){
            return response()->json(["msg" => "No se ha podido agregar el producto", "success" => false, "server_status" => 500]);
        }
    }

    public function setReceiveValue(Request $request){ // Funci??n para seetear el valor de mercancia recibida en el pedido (La sucursal debe poner este valor)
        try{
            $requisition = Requisition::find($request->_requisition);
            $product = $requisition->products()->where('id', $request->_product)->first();
            if($product){
                $amount = isset($request->amount) ? $request->amount : 1; /* CANTIDAD EN UNIDAD */
                $_supply_by = isset($request->_supply_by) ? $request->_supply_by : 1; /* UNIDAD DE MEDIDA */
                $units = $this->getAmount($product, $amount, $_supply_by); /* CANTIDAD EN PIEZAS */
                $requisition->products()->syncWithoutDetaching([$request->_product => ['toReceived' => $units]]);
                return response()->json(["success" => true, "server_status" => 200]);
            }else{
                return response()->json(["msg" => "El producto no existe", "success" => true, "server_status" => 404]);
            }
        }catch(\Exception $e){
            return response()->json(["msg" => "No se ha podido agregar el producto", "success" => false, "server_status" => 500]);
        }
    }

    public function getPrinterDefault($_workpoint_from, $_workpoint){ // Funci??n para determinar que impresora imprimira los tickets de resultido en CEDIS
        // Si se imprime abajo descomentar esta parte
        /* if($_workpoint == 1 && in_array($_workpoint_from, [3,4,5,6,8,11,12,13,19])){
            return \App\Printer::where([['_type', 2], ['_workpoint', $_workpoint], ["name", "LIKE", "%2%"]])->first();
        }else{
            return \App\Printer::where([['_type', 2], ['_workpoint', $_workpoint], ["name", "LIKE", "%1%"]])->first();
        } */

        // con esta funci??n solo saldran las impresiones en la parte de arriba
        return \App\Printer::where([['_type', 2], ['_workpoint', $_workpoint], ["name", "LIKE", "%1%"]])->first();
    }
}
