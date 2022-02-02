<?php

namespace App\Http\Controllers;

use App\Product;
use App\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\ProductCategory;
use App\ProductStatus;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ArrayExport;

use App\Http\Resources\Product as ProductResource;

class ProductController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public $account = null;
    public function __construct(){
        $this->account = Auth::payload()['workpoint'];
    }

    public function restoreProducts(){
        // Restablece el catalgo maestro de productos emparejando el catalogo en CEDIS SP (F_ART) con el de MySQL (products)
        try{
            $start = microtime(true);
            $_cedis = env("CEDIS") ? env("CEDIS") : 1; //Validar de donde se optiene la información
            $CEDIS = \App\WorkPoint::find($_cedis);
            $access = new AccessController($CEDIS->dominio);
            $products = $access->getAllProducts();
            $categories = ProductCategory::where([['id', '>', 403], ['deep', 2]])->get()->groupBy('root');
            $families = ProductCategory::where([['id', '>', 403], ['deep', 1]])->get();
            $array_families = array_column($families->toArray(), 'alias');
            $result = [];
            if($products){
                DB::transaction(function() use ($products, $families, $categories, $array_families){
                    foreach($products as $product){
                        $_provider = $product['_provider'] <= 0 ? 1 : $product['_provider'];
                        $date = $product['created_at'] > "2000-01-01 00:00:00" ? $product['created_at'] : "2020-01-02 00:00:00";
                        $instance = Product::firstOrCreate([
                            'code'=> trim($product['code'])
                        ], [
                            'name' => $product['name'],
                            'barcode' => $product['barcode'],
                            'large' => $product['large'],
                            'description' => trim($product['description']),
                            'label' => trim($product['label']),
                            'reference' => trim($product['reference']),
                            'dimensions' => $product['dimensions'],
                            'pieces' => $product['pieces'],
                            '_category' => $this->getCategoryId(trim($product['_family']), trim($product['_category']), $categories, $families, $array_families),
                            '_status' => $product['_status'],
                            '_provider' => $_provider,
                            '_unit' => $product['_unit'],
                            'created_at' => $date,
                            'updated_at' => new \DateTime(),
                            'cost' => $product['cost']
                        ]);
                        $instance->name = $product['name'];
                        $instance->barcode = $product['barcode'];
                        $instance->large = $product['large'];
                        $instance->cost = $product['cost'];
                        $instance->dimensions = $product['dimensions'];
                        $instance->_category = $this->getCategoryId(trim($product['_family']), trim($product['_category']), $categories, $families, $array_families);
                        $instance->description = trim($product['description']);
                        $instance->label = trim($product['label']);
                        $instance->reference = trim($product['reference']);
                        $instance->pieces = $product['pieces'];
                        $instance->_provider = $_provider;
                        $instance->_status = $product['_status'];
                        $instance->created_at = $date;
                        $instance->updated_at = new \DateTime();
                        $instance->save();
                    }
                });
                return response()->json([
                    "success" => true,
                    "products" => count($products),
                    "result" => $result,
                    "time" => microtime(true) - $start
                ]);
            }
            return response()->json(["message" => "No se obtuvo respuesta del servidor de access"]);
        }catch(Exception $e){
            return response()->json(["message" => "No se ha podido poblar la base de datos"]);
        }
    }

    public function compareCatalog(){
        // Pasar al status eliminado (4) los productos que ya no se encuetran en Factusol en el catalogo de MySQL
        $products = Product::where('_status', '!=', 4)->get(); //Todos los productos excepto los eliminados
        $start = microtime(true);
        $_cedis = env("CEDIS") ? env("CEDIS") : 1; //Validar de donde se obtiene la información
        $CEDIS = \App\WorkPoint::find($_cedis);
        $access = new AccessController($CEDIS->dominio); //Conexión al access de la sucursal
        $products_access = $access->getAllProducts(); // Obtener todos los productos
        $codes = array_column($products_access, 'code');
        $ok = [];
        $notExits = [];
        foreach($products as $product){
            $key = array_search($product->code, $codes);
            if($key === 0 || $key > 0){
                $ok[] = $product->code;
            }else{
                $notExits[] = $product->code;
                $product->_status = 4; //Se 'eliminan' los productos que no existen actualmente en la tabla - products -
                $product->save();
            }
        }
        return response()->json(["ok" => $ok, "notExits" => $notExits]);
    }

    public function getCategoryId($family, $category, $categories, $families, $array_families){// Función para obtener el id de una categoría que viene de access
        $keyFamily = array_search($family, $array_families, true);
        if($keyFamily>0 || $keyFamily === 0){
            $array_categories = array_column($categories[$families[$keyFamily]->id]->toArray(),'alias');
            $keyCategory = array_search($category, $array_categories, true);
            if($keyCategory>0 || $keyCategory === 0){
                return $categories[$families[$keyFamily]->id][$keyCategory]->id;
            }else{
                return $families[$keyFamily]->id;
            }
        }else{
            return 404;
        }
    }

    public function restorePrices(){
        /* Función para empatar los precios del ACCESS de CEDIS con los de MySQL */
        try{
            $start = microtime(true);
            $products = Product::all()->toArray();
            $_cedis = env("CEDIS") ? env("CEDIS") : 1; // Validar de donde se obtiene la información
            $workpoint = \App\WorkPoint::find($_cedis); // Se trae la instación de la sucursal
            $access = new AccessController($workpoint->dominio); // Se hace la conexión con la sucursal
            $prices = $access->getPrices(); // Se obtienen todos los precios del access
            if($products && $prices){
                DB::transaction(function() use ($products, $prices){
                    DB::table('product_prices')->delete(); // Se eliminan todos los precios
                    $codes =  array_column($products, 'code');
                    $prices_insert = collect($prices)->map(function($price) use($products, $codes){
                        $index_product = array_search($price['code'], $codes, true);
                        if($index_product === 0 || $index_product > 0){
                            return [
                                '_product' => $products[$index_product]["id"],
                                'price' => $price['price'],
                                '_type' => $price['_type']
                            ];
                        }
                    })->filter(function($prices){
                        return !is_null($prices);
                    })->values()->all();
                    foreach (array_chunk($prices_insert, 1000) as $insert) {
                        $success = DB::table('product_prices')->insert($insert);
                    }
                });
                return response()->json([
                    "success" => true,
                    "products" => count($products),
                    "time" => microtime(true) - $start
                ]);
            }
            return response()->json(["message" => "No se obtuvo respuesta del servidor de factusol"]);
        }catch(Exception $e){
            return response()->json(["message" => "No se ha podido poblar la base de datos"]);
        }
    }

    public function updateTable(Request $request){
        /* 
            Actualización y replicación de los productos y precios son almacenados en MySQL
            y se envian a todas las sucursales con excepción de puebla, ya que
            en esta sucursal solo se dan de alta los productos que habra en su inventario
            y se manejan otras tarifas.
            Nota: El precio AAA de CEDIS es el Costo de las sucursales, por lo que este no es replicado a las sucursales
         */
        $start = microtime(true);
        $date = isset($request->date) ? $request->date : null;
        $_cedis = env("CEDIS") ? env("CEDIS") : 1; //Se valida quien es la sucursal de CEDIS del cual se tomaran los datos
        $workpoint = \App\WorkPoint::find($_cedis); // Se busca la instancia de CEDIS
        $access = new AccessController($workpoint->dominio); // Se hace la conexión al ACCESS de la sucursal
        $required_products = $request->products ? : false; // Se valida si se actualizaran los productos
        $required_prices = $request->prices ? : false; // Se valida si se actualizaran los precios
        $store_success = []; // Almacena las sucursales que se han actualizado de forma correcta
        $store_fail = []; // Almacen las sucursales que no se han actualizado correctamente
        $products = $access->getUpdatedProducts($date); //Se traen los productos actualizados para almacenar en MySQL
        $raw_data = $access->getRawProducts($date, $required_prices, $required_products); //Se traen los product actualizados para replicar a las sucursales
        if($request->stores == "all"){ //Se envian los cambios a todas las sucursales
            $categories = ProductCategory::where([['id', '>', 403], ['deep', 2]])->get()->groupBy('root');
            $families = ProductCategory::where([['id', '>', 403], ['deep', 1]])->get();
            $array_families = array_column($families->toArray(), 'alias');

            if($products && $request->complete){
                DB::transaction(function() use ($products, $required_prices, $families, $categories, $array_families){
                    foreach($products as $product){
                        $_category = $this->getCategoryId($product['_family'], $product['_category'], $categories, $families, $array_families);
                        $_provider = $product['_provider'] <= 0 ? 1 : $product['_provider'];
                        $instance = Product::firstOrCreate([
                            'code'=> trim($product['code'])
                        ], [
                            'name' => trim($product['name']),
                            'barcode' => $product['barcode'],
                            'description' => trim($product['description']),
                            'label' => trim($product['label']),
                            'reference' => trim($product['reference']),
                            'large' => $product['large'],
                            'dimensions' => $product['dimensions'],
                            'pieces' => $product['pieces'],
                            '_category' => $_category,
                            '_status' => $product['_status'],
                            '_provider' => $_provider,
                            '_unit' => $product['_unit'],
                            'created_at' => new \DateTime(),
                            'updated_at' => new \DateTime(),
                            'cost' => $product['cost']
                        ]);
                        $instance->barcode = $product['barcode'];
                        $instance->large = $product['large'];
                        $instance->name = $product['name'];
                        $instance->cost = $product['cost'];
                        //$instance->_status = $product['_status'];
                        $instance->_category = $_category;
                        $instance->description = $product['description'];
                        $instance->label = $product['label'];
                        $instance->reference = $product['reference'];
                        $instance->pieces = $product['pieces'];
                        $instance->_provider = $_provider;
                        $instance->updated_at = new \DateTime();
                        $instance->save();
                        $prices = [];
                        if($required_prices && count($products)<1000){
                            foreach($product['prices'] as $price){
                                $prices[$price['_type']] = ['price' => $price['price']];
                            }
                            $instance->prices()->sync($prices);
                        }
                    }
                });
            }
            $stores = \App\Workpoint::where([['_type', 2], ['id', '!=', 18], ['active', true]])->get();
        }else{
            $stores = \App\WorkPoint::whereIn('id', $request->stores)->get();
        }
        if($raw_data){
            if($_cedis == 1){
                foreach($stores as $store){
                    $access_store = new AccessController($store->dominio);
                    $result = $access_store->syncProducts($raw_data["prices"], $raw_data["products"]);
                    if($result){
                        $store_success[] = $store->alias;
                    }else{
                        $store_fail[] = $store->alias;
                    }
                }
            }
            return response()->json([
                "success" => true,
                "products" => count($products),
                "time" => microtime(true) - $start,
                "tiendas actualizadas" => $store_success,
                "tiendas que no se pudieron actualizar" => $store_fail
            ]);
        }else{
            return response()->json([
                "success" => false,
                "msg" => "No se tuvo conexión a CEDIS"
            ]);
        }
    }

    public function autocomplete(Request $request){ // Autocomplete antiguo que estaba en la sección de minimos y maximos
        $code = $request->code;
        $esElProducto = Product::with(['prices' => function($query){
            $query->whereIn('_type', [1,2,3,4])->orderBy('_type');
        }, 'units', 'variants', 'status'])
        ->orWhere(function($query) use($code){
            $query->orWhere('name', $code)
            ->orWhere('code', $code);
        })
        ->where("_status", "!=", 4)
        ->first();

        $products = Product::with(['prices' => function($query){
                            $query->whereIn('_type', [1,2,3,4])->orderBy('_type');
                        }, 'units', 'variants', 'status'])
                        ->whereHas('variants', function(Builder $query) use ($code){
                            $query->where('barcode', 'like', '%'.$code.'%');
                        })
                        ->orWhere(function($query) use($code){
                            $query->orWhere('name', $code)
                            ->orWhere('code', $code)
                            ->orWhere('name', 'like','%'.$code.'%')
                            ->orWhere('code', 'like','%'.$code.'%')
                            ->orWhere('description', 'like','%'.$code.'%');
                        })
                        ->where("_status", "!=", 4)
                        ->orderBy('_status', 'asc')
                        ->limit('20')->get();
        if($esElProducto && count($products)==20){
            $products[] = $esElProducto;
        }
        return response()->json(ProductResource::collection($products));
    }

    public function getMassiveProducts(Request $request){
        // Función para obtener los productos y obtener la lista de los que se encontraron y no
        $codes = $request->codes;
        $products = [];
        $notFound = [];
        $uniques = array_unique($codes);
        $repeat = array_values(array_diff_assoc($codes, $uniques));
        foreach($uniques as $code){
            $product = Product::with(['prices' => function($query){
                $query->whereIn('_type', [1,2,3,4])->orderBy('_type');
            }, 'units', 'variants', 'status'])
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
            "products" => ProductResource::collection($products),
            "fails" => [
                "notFound" => $notFound,
                "repeat" => $repeat
            ]
        ]);
    }

    public function getProductByCategory(Request $request){
        /* Función paara traer los productos con sus atributos ya sea por su categoria o no */
        $products = [];
        $filter = null;
        if(!isset($request->_category)){
            $category = ProductCategory::where('root', 0)->orderBy('name')->get();
        }else{
            $category = ProductCategory::with('attributes')->find($request->_category);
            $category->children = ProductCategory::where('root', $request->_category)->orderBy('name')->get();
            $category2 = $category;
            $category2->children = $this->getDescendentsCategory($category2);
            $ascendents = $this->getAscendentsCategory($category2);
            $filter = $this->getFilter($ascendents);
        }
        if(isset($request->products)){
            if(isset($request->_category)){
                $ids = $this->getIdsTree($category2);
                if(isset($request->filter)){
                    $attributes = $request->filter;
                    $products = Product::with('attributes')->where(function($query) use($attributes){
                        foreach($attributes as $attribute){
                            $query->whereHas('attributes',function(Builder $query) use($attribute){
                                $query->where('_attribute', $attribute['_attribute'])->whereIn('value', $attribute['values']);
                            });
                        }
                    })->whereIn('_category', $ids)->get();
                }else{
                    $products = Product::with('attributes')->whereIn('_category', $ids)->get();
                }
            }else{
                $products = Product::limit(100)->get();
            }
        }
        return response()->json([
            "categories" => $category,
            "filter" => $filter,
            "products" => ProductResource::collection($products)
        ]);
    }

    public function categoryTree(Request $request){ /* Función para obtener los decendientes de una categoría o de todas las secciones */
        if(isset($request->_category)){
            $_category = $request->_category;
            $category = ProductCategory::with('attributes')->find($_category);
            $category->children = $this->getDescendentsCategory($category);
        }else{
            $categories = ProductCategory::with('attributes')->where('deep', 0)->get();
            $map = $categories->map(function($category){
                $category->children = $this->getDescendentsCategory($category);
                return $category;
            });
            return response()->json($map);
        }
        return response()->json($category);

        if(isset($request->attributes)){    
            $attributes = $request->attributes;
            $products = Product::with('attributes')->whereHas('attributes', function(Builder $query) use($attributes){
                foreach($atributes as $attribute){
                    $query->where(['_attribute', $attribute->_attributes])->whereIn(['value', $attribute->value]);
                }
            })->whereIn('_category', $ids)->get();
        }
    }

    public function getDescendentsCategory($category){
        $children = ProductCategory::with('attributes')->where('root', $category->id)->orderBy('name')->get();
        if(count($children)>0){
            return $children->map(function($category){
                $category->children = $this->getDescendentsCategory($category);
                return $category;
            });
        }
        return $children;
    }

    public function getDescendentsCategory2($category){
        $children = ProductCategory::where('root', $category->id)->orderBy('name')->get();
        if(count($children)>0){
            return $children->map(function($category){
                $category->children = $this->getDescendentsCategory($category);
                return $category;
            });
        }
        return $children;
    }

    public function getProductsByCategory(Request $request){
        // No recuerdo que realiza, no la eliminare por si acaso
        $category = ProductCategory::find($request->_category);
        $max_stock_cedis = $request->stock;
        if($category){
            $category->children = $this->getDescendentsCategory($category);
            $ids = $this->getIdsTree($category);
            $products = Product::whereHas('stocks', function($query) use($max_stock_cedis){
                $query->where([['_workpoint', 1], ['stock', '>', 0], ['stock', '<=', $max_stock_cedis]])
                ->orWhere([
                    ['_workpoint', '>', 2],
                    ['stock', '>', 0]
                ])->distinct();
            }, '>', 2)->with(['stocks' => function($query){
                $query->where([["stock", '>', 0], ['_workpoint', '!=', 2]]);
            }])->whereIn('_category', $ids)->where('_status', 1)->get();
            $pedido = [];
            foreach($products as $product){
                $stocks = $product->stocks->map(function($stock){
                    return [
                        "_workpoint" => $stock->id,
                        "alias" => $stock->alias,
                        "stock" => $stock->pivot->stock,
                        "gen" => $stock->pivot->gen,
                        "exh" => $stock->pivot->exh,
                        "min" => $stock->pivot->min,
                        "max" => $stock->pivot->max,
                    ];
                });
                $ids_stock_workpoints = array_column($stocks->toArray(), '_workpoint');
                $key = array_search(1, $ids_stock_workpoints);
                if($key === 0 || $key >0){
                    $stock_cedis = $product->stocks[$key]['pivot']['stock'];
                    if($request->up){
                        $destino = $stocks->filter(function($stock){
                            return $stock['_workpoint'] != 1;
                        })->sortByDesc('stock')->values();
                        if($stock_cedis <= $max_stock_cedis){
                            $pedido[$destino[0]['alias']][] = [
                                "id" => $product->id,
                                "code" => $product->code,
                                "name" => $product->name,
                                "description" => $product->description,
                                "piezas" => $stock_cedis,
                                "stock actual" => $destino[0]['stock']
                            ];
                        }
                    }else{
                        $destino = $stocks->filter(function($stock){
                            return $stock['_workpoint'] != 1;
                        })->sortBy('stock')->values();
                        if($stock_cedis <= $max_stock_cedis){
                            $pedido[$destino[0]['alias']][] = [
                                "id" => $product->id,
                                "code" => $product->code,
                                "name" => $product->name,
                                "description" => $product->description,
                                "piezas" => $stock_cedis,
                                "stock actual" => $destino[0]['stock']
                            ];
                        }
                    }
                }
            }
            return response()->json(["result" => $pedido]);
        }
        return response()->json(["msg" => "Categoria no valida"]);
    }

    public function getAscendentsCategory($category){
        if($category->root==0){
            return $category;
        }
        $asc = ProductCategory::with('attributes')->find($category->root);
        $asc->children = [$category];
        return $this->getAscendentsCategory($asc);
    }

    public function getFilter($category){
        $children = collect($category->children);
        $children_attributes = $children->reduce(function($filter, $category){
            $filters_children = $this->getFilter($category);
            return array_merge($filter, $filters_children);
        }, []);
        $filter = $category->attributes->toArray();
        return array_merge($children_attributes, $filter);
    }

    public function getIdsTree($category){
        $children = collect($category->children);
        $children_ids = $children->reduce(function($ids, $category){
            $ids_children = $this->getIdsTree($category);
            return array_merge($ids, $ids_children);
        }, []);
        $id = [$category->id];
        return array_merge($children_ids, $id);
    }


    public function updateStatus(Request $request){ // Función para actualizar el status en la sucursal
        $product = Product::find($request->_product); // Se valida que el producto exista
        if($product){
            $result =  $product->stocks()->updateExistingPivot($this->account->_workpoint, ['_status' => $request->_status]); //Se actualiza el status sin eliminar los datos de la sucursal
            return response()->json(["success" => $result]); // Se retorna si tuvo exito la operación
        }
        return response()->json(["success" => false]);
    }

    public function getStatus(Request $request){ // Función para obtener todos los status de los productos (Catalogo de status)
        $status = ProductStatus::all();
        return response()->json(["status" => $status]);
    }

    public function getProducts(Request $request){ // Función autocomplete 2.0
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

        if(!in_array($this->account->_rol, [1,2,3,8])){
            /* 
                Solo las personas que tengan un rol administrativo podran
                visualizar todo el catalogo de productos.
                Si no tienes un rol administrativo solo veras los productos vigenten
                en el catalogo de factusol CEDIS
             */
            $query = $query->where("_status", "!=", 4);
        }

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

        $query = $query->with(['units', 'status']); // por default se obtienen las unidades y el status general

        if(isset($request->_workpoint_status) && $request->_workpoint_status){ // Se obtiene el stock de la tienda se se aplica el filtro
            $workpoints = $request->_workpoint_status;
            $workpoints[] = $this->account->_workpoint; // Siempre se agrega el status de la sucursal
            $query = $query->with(['stocks' => function($query) use($workpoints){ //Se obtienen los stocks de todas las sucursales que se pasa el arreglo
                $query->whereIn('_workpoint', $workpoints)->distinct();
            }]);
        }else{
            $query = $query->with(['stocks' => function($query){ //Se obtiene el stock de la sucursal
                $query->where('_workpoint', $this->account->_workpoint)->distinct();
            }]);
        }

        if(isset($request->with_locations) && $request->with_locations){ //Se puede agregar todas las ubicaciones de la sucursal
            $query = $query->with(['locations' => function($query){
                $query->whereHas('celler', function($query){
                    $query->where('_workpoint', $this->account->_workpoint);
                });
            }]);
        }
        
        if(isset($request->check_stock) && $request->check_stock){ //Se puede agregar el filtro de busqueda para validar si tienen o no stocks los productos
            if($request->with_stock){
                $query = $query->whereHas('stocks', function(Builder $query){
                    $query->where('_workpoint', $this->account->_workpoint)->where('stock', '>', 0); //Con stock
                });
            }else{
                $query = $query->whereHas('stocks', function(Builder $query){
                    $query->where('_workpoint', $this->account->_workpoint)->where('stock', '<=', 0); //Sin stock
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

        $query = $query->with('variants');

        if(isset($request->paginate) && $request->paginate){
            $products = $query->orderBy('_status', 'asc')->paginate($request->paginate);
        }else{
            $products = $query->orderBy('_status', 'asc')->get();
        }
        return response()->json(ProductResource::collection($products));
    }

    public function getSectionsChildren($id){
        $sections = \App\CellerSection::where('root', $id)->get();
        if(count($sections)>0){
            $res = $sections->map(function($section){
                $children = $this->getSectionsChildren($section->id);
                return $children;
            })->reduce(function($res, $section){
                return array_merge($res, $section);
            }, []);
            array_push($res,$id);
            return $res;
        }else {
            return [$id];
        }
    }

    public function getCategoriesChildren($id){
        $categories = ProductCategory::where('root', $id)->get();
        if(count($categories)>0){
            $res = $categories->map(function($category){
                $children = $this->getCategoriesChildren($category->id);
                return $children;
            })->reduce(function($res, $category){
                return array_merge($res, $category);
            }, []);
            array_push($res,$id);
            return $res;
        }else {
            return [$id];
        }
    }

    public function addProductsLastYears(){
        /* 
            CASO ESPECIAL
            Esta función se hizo para poblar el catalogo maestro de productos con los creados desde 2016, para obtener el historico completo
        */
        // Se obtienen los productos del access local
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, "localhost/access/public/product/all");
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client,CURLOPT_TIMEOUT, 10);
        $products = json_decode(curl_exec($client), true); // Se parsean los datos para poder trabajar con los datos
        $providers = \App\Provider::all(); // Se obtienen todos los proveedores
        $ids_providers = array_column($providers->toArray(), "id"); // Se obtienen todos los id de los proveedores
        curl_close($client); // Se cierra la conexión con el access
        if($products){ // Se lograron obtener los productos
            DB::transaction(function() use ($products, $ids_providers){ // Se inizializa la transacción para garantizar que se almacenaron los producto acteriores y los códigos relacionados
                foreach($products as $product){
                    $key = array_search($product['_provider'], $ids_providers); // Validar el id del proveedor
                    $_provider = ($key === 0 || $key > 0) ? $product['_provider'] : 404; // Si no existe el id se asignara el 404 "No encontrado / válido"
                    $instance = Product::firstOrCreate([ // Se obtiene el primer elemento que coincida con el modelo, sino lo hay se crea y lo retorna
                        'code'=> $product['code']
                    ], [
                        'name' => $product['name'],
                        'barcode' => $product['barcode'],
                        'description' => $product['description'],
                        'dimensions' => $product['dimensions'],
                        'pieces' => $product['pieces'],
                        '_category' => $product['_category'],
                        '_status' => $product['_status'],
                        '_provider' => $_provider,
                        '_unit' => $product['_unit'],
                        'created_at' => $product['created_at'],
                        'updated_at' => new \DateTime(),
                        'cost' => $product['cost']
                    ]);
                    $instance->name = $product['name'];
                    $instance->barcode = $product['barcode'];
                    $instance->cost = $product['cost'];
                    $instance->dimensions = $product['dimensions'];
                    $instance->_category = $product['_category'];
                    $instance->description = $product['description'];
                    $instance->pieces = $product['pieces'];
                    $instance->_provider = $_provider;
                    $instance->_status = $product['_status'];
                    $instance->created_at = $product['created_at'];
                    $instance->updated_at = new \DateTime();
                    $instance->save();

                }
                DB::table('product_variants')->delete(); // Eliminamos todos los códigos relacionados
                // Conexión para obenter los codigos relacionados
                $client = curl_init();
                curl_setopt($client, CURLOPT_URL, "localhost/access/public/product/related");
                curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($client,CURLOPT_TIMEOUT,10);
                $codes = json_decode(curl_exec($client), true); // Se parsean los códigos para trabajarlos
                curl_close($client); // Se cierra la conexión
                $products2 = Product::all(); // Se obtienen todos los productos
                
                $array_codes = array_column($products2->toArray(), 'code'); // Se hace un arreglo con todos los modelos de los productos
                if($codes){ // Se obtuvieron los códigos relacionados
                    foreach($codes as $code){
                        $key = array_search($code["ARTEAN"], $array_codes);  // Se busca modelo con el que esta relacionado en este momento
                        if($key>0 || $key === 0){ // Se válida si el código relacionado esta relacionado con un modelo válido
                            $insert[] = ["_product" => $products2[$key]->id, 'barcode' => $code['EANEAN'], 'stock' => 0]; // Se crea la relación
                        }
                    }
                    DB::table('product_variants')->insert($insert); //Se insertan todos los códigos relacionados
                }
            });
        }
    }

    public function getABC(Request $request){
        /* 
            Función para obtener reporte de ABC por
            Valor de inventario
            Venta ($$$)
            Unidades vendidas
         */
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
        $categories = isset($request->categories) ? $request->categories : ["Navidad"];
        $products = Product::selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS categoryy')
        ->with(['category','sales' => function($query) use($date_from, $date_to){
            $query->where([['created_at', '>=', $date_from], ['created_at', '<=', $date_to]]);
        }, 'stocks', 'prices' => function($query){
            $query->where('_type', 7);
        }])->where([['id', '!=', 7089], ['id', '!=', 5816], ['description', "NOT LIKE", '%CREDITO%'], ['_status', '!=', 4]])
        ->havingRaw('section = ?', $categories)
        ->get()->map(function($product){
            $unidades_vendidas = $product->sales->sum(function($sale){
                return $sale->pivot->amount;
            });
            $costo_total = $unidades_vendidas * $product->cost;
            $venta_total = $product->sales->sum(function($sale){
                return $sale->pivot->total;
            });
            $rentabilidad = 0;
            $stock = $product->stocks->unique('id')->values()->sum(function($stock){
                return $stock->pivot->stock;
            });
            $valor_inventario = $product->cost * $stock;
            $price = count($product->prices) > 0 ? $product->prices[0]->pivot->price : 0;
            if($unidades_vendidas > 0 && $venta_total > 0){
                if($costo_total <= 0 || $costo_total/$venta_total>2){
                    $costo_total = $product->cost * $unidades_vendidas;
                    if($costo_total<=0){
                        $costo_total = $price * $unidades_vendidas;
                    }
                }
                if($venta_total>0){
                    $rentabilidad = ($venta_total - $costo_total) / $venta_total;
                }else{
                    $rentabilidad = $price;
                }
            }else{
                if($price>0){
                    $rentabilidad = ($price - $product->cost) / $price;
                }else{
                    $rentabilidad = $price;
                }
            }
            $prices = $product->prices->reduce(function($res, $price){
                $res[$price->name] = $price->pivot->price;
                return $res;
            }, []);
            return [
                "Modelo" => $product->code,
                "Código" => $product->name,
                "Descripción" => $product->description,
                "Sección" => $product->section,
                "Familia" => $product->family,
                "Categoria" => $product->categoryy,
                "Costo" => $product->cost,
                "Precio AAA" => $price,
                "stock" => $stock,
                "Valor del inventario" => $valor_inventario,
                "Unidades vendidas" => $unidades_vendidas,
                "Venta total" => $venta_total,
                "Costo total" => $costo_total,
                "Rentabilidad" => $rentabilidad,
                "Ganancia bruta" => $venta_total - $costo_total
            ];
        })->sortByDesc('Valor del inventario');
        $venta_total = $products->sum('Venta total');
        $valor_inventario = $products->sum('Valor del inventario');
        $ganancia_total = $products->sum('Ganancia bruta');
        $valor_absoluto_inventario = 0;
        $valor_absoluto_venta = 0;
        $valor_absoluto_ganancia = 0;
        $result = $products->map(function($product) use($valor_inventario, &$valor_absoluto_inventario){
            $valor_relativo = $product['Valor del inventario'] / $valor_inventario;
            $valor_absoluto_inventario = $valor_absoluto_inventario + $valor_relativo;
            if($valor_absoluto_inventario>=0 && $valor_absoluto_inventario<=.80){
                $product["Clasificación valor del inventario"] = "A";
            }else if($valor_absoluto_inventario>.80 && $valor_absoluto_inventario<=.95){
                $product["Clasificación valor del inventario"] = "B";
            }else{
                $product["Clasificación valor del inventario"] = "C";
            }
            return $product;
        })->sortByDesc("Venta total")->map(function($product) use($venta_total, &$valor_absoluto_venta){
            $valor_relativo = $product['Venta total'] / $venta_total;
            $valor_absoluto_venta = $valor_absoluto_venta + $valor_relativo;
            if($valor_absoluto_venta>=0 && $valor_absoluto_venta<=.80){
                $product["Clasificación venta"] = "A";
            }else if($valor_absoluto_venta>.80 && $valor_absoluto_venta<=.95){
                $product["Clasificación venta"] = "B";
            }else{
                $product["Clasificación venta"] = "C";
            }
            return $product;
        })->sortByDesc("Ganancia bruta")->map(function($product) use($ganancia_total, &$valor_absoluto_ganancia){
            $valor_relativo = $product['Ganancia bruta'] / $ganancia_total;
            $valor_absoluto_ganancia = $valor_absoluto_ganancia + $valor_relativo;
            if($valor_absoluto_ganancia>=0 && $valor_absoluto_ganancia<=.80){
                $product["Clasificación ganancia"] = "A";
            }else if($valor_absoluto_ganancia>.80 && $valor_absoluto_ganancia<=.95){
                $product["Clasificación ganancia"] = "B";
            }else{
                $product["Clasificación ganancia"] = "C";
            }
            return $product;
        });
        $export = new ArrayExport($result->toArray());
        $date = new \DateTime();
        return Excel::download($export, "ABCD_PRODUCTOS.xlsx");
    }

    public function getABCStock(Request $request){
        // Función para obtener reporte de ABC por valor de inventario por sucursal
        $workpoints = \App\WorkPoint::where(['active', true])->get();
        $response = [];
        foreach($workpoints as $workpoint){
            $products = Product::selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS categoryy')
            ->with(['provider','category', 'stocks' => function($query) use($workpoint){
                $query->where('_workpoint', $workpoint->id)->distinct();
            }, 'prices' => function($query){
                $query->where('_type', 7);
            }])->where([['id', '!=', 7089], ['id', '!=', 5816], ['description', "NOT LIKE", '%CREDITO%'], ['_status', '!=', 4]])
            ->get()->map(function($product) use($categories, $ids_categories, $workpoint){
                $stock = count($product->stocks)> 0 ? $product->stocks[0]->pivot->stock : 0;
                $valor_inventario = $product->cost * $stock;
                $price = count($product->prices) > 0 ? $product->prices[0]->pivot->price : 0;
                return [
                    "Sucursal" => $workpoint->name,
                    "Modelo" => $product->code,
                    "Código" => $product->name,
                    "Descripción" => $product->description,
                    "Proveedor" => $product->provider->name,
                    "Sección" => $product->section,
                    "Familia" => $product->family,
                    "Categoria" => $product->categoryy,
                    "Costo" => $product->cost,
                    "Precio AAA" => $price,
                    "stock" => $stock,
                    "Valor del inventario" => $valor_inventario
                ];
            })->sortByDesc('Valor del inventario');
    
            $valor_inventario = $products->sum(function($product){
                return $product['Valor del inventario'] > 0 ? $product['Valor del inventario'] : 0;
            });
            $valor_absoluto_inventario = 0;
            $result = $products->map(function($product) use($valor_inventario, &$valor_absoluto_inventario){
                $valor_relativo = $product['Valor del inventario'] / $valor_inventario;
                $valor_absoluto_inventario = $valor_absoluto_inventario + $valor_relativo;
                if($product['Valor del inventario'] <= 0){
                    $product["Clasificación valor del inventario"] = "No aplica";
                }else if($valor_absoluto_inventario>=0 && $valor_absoluto_inventario<=.80){
                    $product["Clasificación valor del inventario"] = "A";
                }else if($valor_absoluto_inventario>.80 && $valor_absoluto_inventario<=.95){
                    $product["Clasificación valor del inventario"] = "B";
                }else{
                    $product["Clasificación valor del inventario"] = "C";
                }
                return $product;
            });
            $response[] = $result->toArray();
        }
        /* $export = new ArrayExport($products->toArray()); */
        $export = new ArrayExport(array_merge(...$response));
        $date = new \DateTime();
        return Excel::download($export, "ABCD_PRODUCTOS_STOCK.xlsx");
    }

    public function updateRelatedCodes(){ // Función para actualizar los códigos relacionados
        $CEDIS = \App\WorkPoint::find(1); //Se busca la sucursal de CEDIS
        $access = new AccessController($CEDIS->dominio); //Se hace la conexión a la base de datos
        $codes = $access->getRelatedCodes(); //Se obtiene todos los códigos de barras
        if($codes){ // Se valida que llegaron los codigos de barras
            DB::transaction(function() use ($products, $codes){
                DB::table('product_variants')->delete(); //Eliminar todos los códigos relacionados
                $products = Product::all(); //Se obtienen todos los productos
                $array_codes = array_column($products->toArray(), 'code'); //Se hace un arreglo de todos los modelos
                foreach($codes as $code){
                    $key = array_search($code["ARTEAN"], $array_codes); //Se busca el modelo con los de la base de datos y si esta se agrega el código relacionado
                    if($key>0 || $key === 0){
                        $insert[] = ["_product" => $products[$key]->id, 'barcode' => $code['EANEAN'], 'stock' => 0]; // Se da el formato para almacenar en la tabla product_variants
                    }
                }
                DB::table('product_variants')->insert($insert); //Se guardan todos los códigos de barras
            });
        }
    }
}
