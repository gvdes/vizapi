<?php

namespace App\Http\Controllers;

use App\CycleCount;
use Illuminate\Http\Request;
use Carbon\CarbonImmutable;
use App\Http\Resources\Inventory as InventoryResource;
use App\Product;
use App\Requisition;
use App\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use App\ProductCategory;
use App\ProductStatus;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Resources\Product as ProductResource;
use App\Exports\ArrayExport;
use App\Account;
use Carbon\Carbon;
use App\RequisitionProcess as Process;
use App\Celler;
use App\Sales;
use App\CashRegister;
use App\CellerSection;
use App\Workpoint;



class CiclicosController extends Controller{

    public function index(Request $request){
        // sleep(3);
        try {
            $view = $request->query("v");
            $store = $request->query("store");
            $now = CarbonImmutable::now();

            $from = $now->startOf($view)->format("Y-m-d H:i");
            $to = $now->endOf("day")->format("Y-m-d H:i");
            $resume = [];

            $inventories = CycleCount::with([ 'status', 'type', 'log', 'created_by' ])
                ->withCount('products')
                ->where(function($q) use($from,$to){ return $q->where([ ['created_at','>=',$from],['created_at', '<=', $to] ]); })
                ->where("_workpoint",$store)
                ->get();

            return response ()->json([
                "inventories"=>$inventories,
                "params"=>[ $from, $to, $view, $store ],
                "req"=>$request->all()
            ]);
        }  catch (\Error $e) { return response()->json($e,500); }
    }

    public function find(Request $request){
        $folio = $request->route("folio");
        $wkp = $request->query("store");

        $inventory = CycleCount::with([
                        'workpoint',
                        'created_by',
                        'type',
                        'status',
                        'responsables',
                        'log',
                        'products' => function($query) use($wkp){
                                            $query->with(['locations' => function($query) use($wkp){
                                                $query->whereHas('celler', function($query) use($wkp){
                                                    $query->where('_workpoint', $wkp);
                                                });
                                            }]);
                                        }
                    ])
                    ->where([ ["id","=",$folio], ["_workpoint","=",$wkp] ])
                    ->first();

        if($inventory){
            return response()->json([
                "inventory" => new InventoryResource($inventory),
                "params" => [$folio, $wkp]
            ]);
        }else{ return response("Not Found",404); }
    }

    public function getProducts(Request $request){ // Función autocomplete 2.0

        $workpoint = $request->_workpoint;
        // return 'hjos';
        //Se obtienen todo los datos del producto y se le agrega la sección, familia y categoría
        $query = Product::query()->selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS categoryy');
        if(isset($request->autocomplete) && $request->autocomplete){ //Valida si se utilizara la función de autocompletado ?
            $codes = explode('ID-', $request->autocomplete); // Si el codigo tiene ID- al inicio la busqueda sera por el id que se le asigno en el catalog maestro (tabla products)
            if(count($codes)>1){
                $query = $query->where('id', $codes[1]);
            }elseif(isset($request->strict) && $request->strict){ //La coincidencia de la busqueda sera exacta
                /*
                    La busqueda se realiza por:
                    Modelo -> code
                    Código -> name
                    Codigo de barras -> barcode
                    Códigos relacionados -> variants.barcode
                */
                if(strlen($request->autocomplete)>1){
                    $query = $query->whereHas('variants', function(Builder $query) use ($request){
                        $query->where('barcode', $request->autocomplete);
                    })
                    ->orWhere(function($query) use($request){
                        $query->orWhere('name', $request->autocomplete)
                        ->orWhere('barcode', $request->autocomplete)
                        ->orWhere('code', $request->autocomplete);
                    });
                }
            }else{ //La busqueda se realizara por similitud
                /*
                    La busqueda se realiza por:
                    Modelo -> code
                    Código -> name
                    Codigo de barras -> barcode
                    Códigos relacionados -> variants.barcode
                */
                if(strlen($request->autocomplete)>1){
                    $query = $query->whereHas('variants', function(Builder $query) use ($request){
                        $query->where('barcode', 'like', '%'.$request->autocomplete.'%');
                    })
                    ->orWhere(function($query) use($request){
                        $query->orWhere('name', $request->autocomplete)
                        ->orWhere('barcode', $request->autocomplete)
                        ->orWhere('code', $request->autocomplete)
                        ->orWhere('name', 'like','%'.$request->autocomplete.'%')
                        ->orWhere('code', 'like','%'.$request->autocomplete.'%');
                    });
                }
            }
        }
        $query = $query->where("_status", "!=", 4);

        // if(!in_array($this->account->_rol, [1,2,3,8])){
        //     /*
        //         Solo las personas que tengan un rol administrativo podran
        //         visualizar todo el catalogo de productos.
        //         Si no tienes un rol administrativo solo veras los productos vigenten
        //         en el catalogo de factusol CEDIS
        //      */
        //     $query = $query->where("_status", "!=", 4);
        // }

        if(isset($request->products) && $request->products){ //Se puede buscar mas de un codigo a la vez mendiente el parametro products
            $query = $query->whereHas('variants', function(Builder $query) use ($request){
                $query->whereIn('barcode', $request->products);
            })
            ->orWhereIn('name', $request->products)
            ->orWhereIn('code', $request->product);
        }

        if(isset($request->_category)){ //Se puede realizar una busqueda con el filtro de sección, familia, categoría mediente el ID de lo que estamos buscando
            $_categories = $this->getCategoriesChildren($request->_category); // Se obtiene los hijos de esa categoría
            $query = $query->whereIn('_category', $_categories); // Se añade el filtro de la categoría para realizar la busqueda
        }

        if(isset($request->_status)){ // Se puede realizar una busqueda con el filtro de status del producto mediante el ID del status que estamos buscando
            $query = $query->where('_status', $request->_status); // Se añade el filtro de la categoría para realizar la busqueda
        }

        if(isset($request->_location)){ //Se puede realizar una busqueda con filtro de ubicación del producto mediante el ID de la ubicación (sección, pasillo, tarima, etc) que estamos buscando
            $_locations = $this->getSectionsChildren($request->_location); //Se obtienen todos los hijos de la sección de la busqueda para realizar la busqueda completa
            $query = $query->whereHas('locations', function( Builder $query) use($_locations){
                $query->whereIn('_location', $_locations); // Se añade el filtro de la sección para realizar la busqueda
            });
        }

        if(isset($request->_celler) && $request->_celler){ // Se puede realizar una busqueda con filtro de almacen
            $locations = \App\CellerSection::where([['_celler', $request->_celler],['deep', 0]])->get(); // Se obtiene todas las ubicaciones dentro del almacen
            $ids = $locations->map(function($location){
                return $this->getSectionsChildren($location->id);
            });
            $_locations = array_merge(...$ids); // Se genera un arreglo con solo los ids de las ubicaciones
            $query = $query->whereHas('locations', function( Builder $query) use($_locations){
                $query->whereIn('_location', $_locations);
            });
        }

        if(isset($request->check_sales)){
            //OBTENER FUNCIÓN DE CHECAR STOCKS
        }

        $query = $query->with(['units', 'status', 'variants']); // por default se obtienen las unidades y el status general

        if(isset($request->_workpoint_status) && $request->_workpoint_status){ // Se obtiene el stock de la tienda se se aplica el filtro

            if($request->_workpoint_status == "all"){
                $query = $query->with(['stocks']);
            }else{
                $workpoints = $request->_workpoint_status;
                $workpoints[] = 1; // Siempre se agrega el status de la sucursal
                $query = $query->with(['stocks' => function($query) use($workpoints){ //Se obtienen los stocks de todas las sucursales que se pasa el arreglo
                    $query->whereIn('_workpoint', $workpoints)->distinct();
                }]);
            }
        }else{
            $query = $query->with(['stocks' => function($query) use($workpoint){ //Se obtiene el stock de la sucursal
                $query->where('_workpoint', $workpoint)->distinct();
            }]);
        }

        if(isset($request->with_locations) && $request->with_locations){ //Se puede agregar todas las ubicaciones de la sucursal
            $query = $query->with(['locations' => function($query) use ($workpoint) {
                $query->whereHas('celler', function($query) use ($workpoint) {
                    $query->where([['_workpoint', $workpoint],['_type',2]]);
                });
            }]);
        }

        if(isset($request->check_stock) && $request->check_stock){ //Se puede agregar el filtro de busqueda para validar si tienen o no stocks los productos
            if($request->with_stock){
                $query = $query->whereHas('stocks', function(Builder $query) use($workpoint){
                    $query->where('_workpoint', $workpoint)->where('stock', '>', 0); //Con stock
                });
            }else{
                $query = $query->whereHas('stocks', function(Builder $query) use($workpoint){
                    $query->where('_workpoint', $workpoint)->where('stock', '<=', 0); //Sin stock
                });
            }
        }

        if(isset($request->with_prices) && $request->with_prices){ //Se puede agregar los precios de lista del producto
            $query = $query->with(['prices' => function($query){
                $query->whereIn('_type', [1, 2, 3, 4])->orderBy('id'); //Solo se envian los precios de Menudeo, Mayoreo, Docena o Media caja y caja
                //Los demas precios no seran mostrados por regla de negocio
            }]);
        }

        if(isset($request->limit) && $request->limit){ //Se puede agregar un limite de los resultados mostrados
            $query = $query->limit($request->limit);
        }

        // $query = $query->with(['variants']);

        if(isset($request->paginate) && $request->paginate){
            $products = $query->orderBy('_status', 'asc')->paginate($request->paginate);
        }else{
            $products = $query->orderBy('_status', 'asc')->get();
        }
        return response()->json($products);
    }

    public function getMassiveProducts(Request $request){
        // Función para obtener los productos y obtener la lista de los que se encontraron y no
        $codes = $request->codes;
        $workpoint = $request->_workpoint;
        $products = [];
        $notFound = [];
        $uniques = array_unique($codes);
        $repeat = array_values(array_diff_assoc($codes, $uniques));
        foreach($uniques as $code){
            $product = Product::with([
            'prices' => function($query){
                $query->whereIn('_type', [1,2,3,4])->orderBy('_type');
            },
            'units',
            'variants',
            'status',
            'locations' => function($query) use ($workpoint) {
                $query->whereHas('celler', function($query) use ($workpoint) {
                    $query->where([['_workpoint', $workpoint],['_type',2]]);
                });
            }
            ])
            ->whereHas('variants', function(Builder $query) use ($code){
                $query->where('barcode', $code);
            })
            ->orWhere(function($query) use($code){
                $query->where('name', $code);
            })
            ->orWhere(function($query) use($code){
                $query->where('code', $code);
            })
            ->first();
            if($product){
                array_push($products, $product);
            }else{
                array_push($notFound, $code);
            }
        }

        return response()->json([
            "products" => $products,
            "fails" => [
                "notFound" => $notFound,
                "repeat" => $repeat
            ]
        ]);
    }

    public function getProductsCompare(Request $request){
        $sid = $request->route('sid');
        $seccion = $request->sections;
            $products = Product::with([
                'categories.familia.seccion',
                'stocks' => function($query) use ($sid) { //Se obtiene el stock de la sucursal
                    $query->whereIn('_workpoint',[1,2,$sid])->distinct();
                }
                ])
                ->whereHas('categories.familia.seccion', function($query) use ($seccion) { // Aplicamos el filtro en la relación seccion
                    $query->whereIn('name',$seccion);
                })
                ->whereHas('stocks', function($query) { // Solo productos con stock mayor a 0 en el workpoint
                    $query->whereIn('_workpoint', [1, 2])
                          ->where('stock', '>', 0); // Filtra solo aquellos con stock positivo
                })
                ->whereHas('stocks', function($query) use ($sid) { // Solo productos con stock mayor a 0 en el workpoint
                    $query->where('_workpoint',$sid)
                          ->where('stock', '=', 0); // Filtra solo aquellos con stock positivo
                })
                ->where('_status','!=',4)->get();
        return response()->json($products);
    }

    public function secciones(){
        $seccion = ProductCategory::where('deep',0)->where('alias','!=',null)->get();
        return response()->json($seccion);
    }

    public function getCedis(){
        $cedis = Workpoint::where([['_type',1],['active',1]])->get();
        return response()->json($cedis,200);
    }

    public function getSeccion(Request $request){
        $sid = $request->route('sid');
        $type = $request->_type;
        if($type == 1){
            $families = ProductCategory::where([['alias','!=',null],['deep',0]])
            ->get();
            $locations = Celler::where([['_workpoint',$sid]])->get();
            $cellers = $locations->map(function($celler){
                $celler->sections = \App\CellerSection::where([
                    ['_celler', '=',$celler->id],
                    ['deep', '=', 0],
                ])->get();
                return $celler;
            });
            $res = ['locations'=>$cellers,'sections'=>$families];
            return response()->json($res,200);
        }else if($type == 2){
            $families = ProductCategory::with('familia.seccion')->where([['alias','!=',null]])
            ->get();
            $res= ['families'=>$families];
            return response()->json($res,200);
        }else{
            $res = [
                "message"=>'No existe el tipo de resurtido',
            ];
            return response()->json($res,200);
        }
    }

    public function getProductReport(Request $request){
        $sid = $request->route('sid');
        $seccion = $request->data;
        $products = Product::with([
            'categories.familia.seccion',
            'locations'  => function($query) use($sid ) {
                $query->whereHas('celler', function($query)use($sid){
                    $query->where('_workpoint', $sid );
                });
            },
            'stocks' => function($query) use ($sid) { //Se obtiene el stock de la sucursal
                $query->whereIn('_workpoint',[1,2,$sid])->distinct();
            }])
            ->whereHas('categories.familia', function($query) use ($seccion) { // Aplicamos el filtro en la relación seccion
                $query->whereIn('id',$seccion);
            })
            ->whereHas('stocks', function($query) { // Solo productos con stock mayor a 0 en el workpoint
                $query->whereIn('_workpoint', [1, 2])
                        ->where('stock', '>', 0); // Filtra solo aquellos con stock positivo
            })
            ->where('_status','!=',4)->get();
        return response()->json($products);
    }

    public function getProductReportLocations(Request $request){
        // return $request->all();
        $workpoint_to = $request->_workpoint_to;
        $workpoint_from = $request->_workpoint_from;
        $celler = isset($request->celler) ? $request->celler : false;
        $locations = isset($request->locations) ? $request->locations : false;
        if($locations){
            $loc = $this->getAllDescendantLocations($locations);
        }
        $sections = isset($request->section) ? $request->section : false;
        $products = Product::with([
            'categories.familia.seccion',
            'locations'  => function($query) use($workpoint_from, $celler ) {
                $query->whereHas('celler', function($query)use($workpoint_from,$celler){
                    $query->where('_workpoint', $workpoint_from );
                    $query->whereIn('id',$celler);
                });
            },
            'stocks' => function($query) use ($workpoint_from) { //Se obtiene el stock de la sucursal
                $query->whereIn('_workpoint',[1,2, 16,$workpoint_from])->distinct();
            }]);
            if($sections){
                $products->whereHas('categories.familia.seccion', function($query) use ($sections) { // Aplicamos el filtro en la relación seccion
                    $query->whereIn('id',$sections);
                });
            }
            if($celler){
                $products->whereHas('locations',function($query) use ($celler)  {
                    $query->whereHas('celler', function($query)use($celler){
                        $query->whereIn('id',$celler );
                    });
                });
            }
            if($loc){
                $products->whereHas('locations',function($query) use ($loc)  {
                    $query->whereIn('id',$loc);
            });
            }

            $res = $products->whereHas('stocks', function($query) { // Solo productos con stock mayor a 0 en el workpoint
                $query->whereIn('_workpoint', [1, 2, 16])
                        ->where('stock', '>', 0); // Filtra solo aquellos con stock positivo
            })
            ->where('_status','!=',4)->get();
        return response()->json($res);
    }

    public function getAllDescendantLocations($locations, $descendants = []) {
        // Busca los hijos directos
        $children = CellerSection::whereIn('root', $locations)->pluck('id');

        // Si no hay hijos, terminamos
        if ($children->isEmpty()) {
            return $descendants;
        }

        // Agregar hijos encontrados a la lista de descendientes
        $descendants = array_merge($descendants, $children->toArray());

        // Llamada recursiva con los hijos encontrados
        return $this->getAllDescendantLocations($children, $descendants);
    }



    public function create(Request $request){//creacion de pedido
        $_workpoint_from = $request->workpoint_from;//hacia donde
        $_workpoint_to = $request->workpoint_to;//de donde
        $products = $request->products;
        // return $products;
        $data = $this->getToSupplyFromStore($products);

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
            "_created_by" => $request->id_userviz,
            "_workpoint_from" => $_workpoint_from,
            "_workpoint_to" => $_workpoint_to,
            "_type" => $request->type,
            "printed" => 0,
            "time_life" => "00:15:00",
            "_status" => 1
        ]);
        $this->log(1, $requisition);
        if(isset($data['products'])){ $requisition->products()->attach($data['products']); }

        if($request->_type != 1){ $this->refreshStocks($requisition); }

        $requisition->load('type', 'status', 'products.categories.familia.seccion', 'to', 'from', 'created_by', 'log');
            $this->nextStep($requisition->id);
            return response()->json([
                "success" => true,
                "order" => $requisition
            ]);
    }

    public function getToSupplyFromStore($products){ // Función para hacer el pedido de productos de familia

        $tosupply = [];
        foreach ($products as $product) {
                $tosupply[$product['id']] = [ 'units'=>$product['pieces'], "cost"=>$product['cost'], 'amount'=>$product['required'], "_supply_by"=>3, 'comments'=>'', "stock"=>0 ];
        }
        return ["products" => $tosupply];
    }

    public function log($case, Requisition $requisition, $_printer=null, $actors=[]){
        $account = Account::with('user')->find(1);
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
            case 1: // LEVANTAR PEDIDO
                $requisition->log()->attach(1, [ 'details'=>json_encode([ "responsable"=>$responsable ]), 'created_at' => carbon::now()->format('Y-m-d H:i:s'), 'updated_at' => carbon::now()->format('Y-m-d H:i:s') ]);
            break;

            case 2: // POR SURTIR => IMPRESION DE COMPROBANTE EN TIENDA
                $port = 9100;
                $requisition->log()->attach(2, [ 'details'=>json_encode([ "responsable"=>$responsable ]), 'created_at' => carbon::now()->format('Y-m-d H:i:s'), 'updated_at' => carbon::now()->format('Y-m-d H:i:s') ]);// se inserta el log dos al pedido con su responsable
                $requisition->_status=2; // se prepara el cambio de status del pedido (a por surtir (2))
                $requisition->save(); // se guardan los cambios
                $requisition->fresh(['log']); // se refresca el log del pedido
                $_workpoint_to = $requisition->_workpoint_to;
                $requisition->load(['log', 'products' => function($query) use ($_workpoint_to){
                    $query->with(['locations' => function($query)  use ($_workpoint_to){
                        $query->whereHas('celler', function($query) use ($_workpoint_to){
                            $query->where('_workpoint', $_workpoint_to);
                        });
                    }]);
                }]);
                if($requisition->_workpoint_to == 2){
                    $ipprinter = env("PRINTERTEX");
                }else if($requisition->_workpoint_to == 24){
                    $ipprinter = env("PRINTERBOL");
                }else if($requisition->_workpoint_to == 16){
                    $ipprinter = env("PRINTERBRASIL");
                }else{
                    $ipprinter = env("PRINTER_P3") ;
                }

                $miniprinter = new MiniPrinterController($ipprinter, $port);
                $printed_provider = $miniprinter->requisitionTicket($requisition);

                if($printed_provider){
                    $requisition->printed = ($requisition->printed+1);
                    $requisition->save();
                }else {
                    $groupvi = "120363185463796253@g.us";
                    $mess = "El pedido ".$requisition->id." no se logro imprimir, favor de revisarlo";
                    $this->sendWhatsapp($groupvi, $mess);
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
    }

    public function refreshStocks(Requisition $requisition){ // Función para actualizar los stocks de un pedido de resurtido
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

    public function nextStep($id){
        $requisition = Requisition::with(["to", "from", "created_by"])->find($id);
        $server_status = 200;
        if($requisition){
            $_status = $requisition->_status+1;

            $process = Process::all()->toArray();

            if(in_array($_status, array_column($process, "id"))){
                $result = $this->log($_status, $requisition);
                $msg = $result["success"] ? "" : "No se pudo cambiar el status";
                $server_status = $result ["success"] ? 200 : 500;
            }else{
                $msg = "Status no válido";
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

    public function impPreview(Request $request){
        $requisition =  $request->all();
        $miniprinter = new MiniPrinterController($request->ip_address, 9100);
        $printed_provider = $miniprinter->previewRequisition($requisition);
        if($printed_provider){
            return response()->json('Impresion Correcta',200);
        }else{
            return response()->json('Impresion Incorrecta',401);
        }
    }

    public function  reportProductsCategories(Request $request){
        // return $request->all();
        $workpoint_to = $request->_workpoint_to;
        $workpoint_from = $request->_workpoint_from;
        $seccion = isset($request->seccion) ? $request->seccion : false;
        $familia = isset($request->familia) ? $request->familia : false;
        $categoria = isset($request->categoria) ? $request->categoria : false;
        $products = Product::with([
            'categories.familia.seccion',
            'locations'  => function($query) use($workpoint_from ) {
                $query->whereHas('celler', function($query)use($workpoint_from){
                    $query->where('_workpoint', $workpoint_from );
                });
            },
            'stocks' => function($query) use ($workpoint_from) { //Se obtiene el stock de la sucursal
                $query->whereIn('_workpoint',[1,2,16,$workpoint_from])->distinct();
            }
        ]);
        if($seccion){
            $products->whereHas('categories.familia.seccion', function($query) use ($seccion) { // Aplicamos el filtro en la relación seccion
                $query->whereIn('id',$seccion);
            });
        }
        if($familia){
            $products->whereHas('categories.familia', function($query) use ($familia) { // Aplicamos el filtro en la relación seccion
                $query->whereIn('id',$familia);
            });
        }
        if($categoria){
            $products->whereHas('categories', function($query) use ($categoria) { // Aplicamos el filtro en la relación seccion
                $query->whereIn('id',$categoria);
            });
        }
        $products->whereHas('stocks', function($query) { // Solo productos con stock mayor a 0 en el workpoint
                $query->whereIn('_workpoint', [1, 2, 16])
                        ->where('stock', '>', 0); // Filtra solo aquellos con stock positivo
        });
        $result = $products->where('_status','!=',4)->get();
        return response()->json($result);
    }
}
