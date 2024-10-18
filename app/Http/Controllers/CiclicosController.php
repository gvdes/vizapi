<?php

namespace App\Http\Controllers;

use App\CycleCount;
use Illuminate\Http\Request;
use Carbon\CarbonImmutable;
use App\Http\Resources\Inventory as InventoryResource;
use App\Product;
use App\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use App\ProductCategory;
use App\ProductStatus;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Resources\Product as ProductResource;
use App\Exports\ArrayExport;

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
}
