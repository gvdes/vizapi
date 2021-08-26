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
        try{
            $start = microtime(true);
            $CEDIS = \App\WorkPoint::find(1);
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
                            'code'=> $product['code']
                        ], [
                            'name' => $product['name'],
                            'barcode' => $product['barcode'],
                            'description' => $product['description'],
                            'dimensions' => $product['dimensions'],
                            'pieces' => $product['pieces'],
                            '_category' => $this->getCategoryId($product['_family'], $product['_category'], $categories, $families, $array_families),
                            '_status' => $product['_status'],
                            '_provider' => $_provider,
                            '_unit' => $product['_unit'],
                            'created_at' => $date,
                            'updated_at' => new \DateTime(),
                            'cost' => $product['cost']
                        ]);
                        $instance->name = $product['name'];
                        $instance->barcode = $product['barcode'];
                        $instance->cost = $product['cost'];
                        $instance->dimensions = $product['dimensions'];
                        $instance->_category = $this->getCategoryId($product['_family'], $product['_category'], $categories, $families, $array_families);
                        $instance->description = $product['description'];
                        $instance->pieces = $product['pieces'];
                        $instance->_provider = $_provider;
                        /* $instance->_status = $product['_status']; */
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

    public function getCategoryId($family, $category, $categories, $families, $array_families/* , $array_categories */){
        $keyFamily = array_search($family, $array_families, true);
        if($keyFamily>0 || $keyFamily === 0){
            $array_categories = array_column($categories[$families[$keyFamily]->id]->toArray(),'alias');
            $keyCategory = array_search($category, $array_categories, true);
            /* return $keyCategory; */
            if($keyCategory>0 || $keyCategory === 0){
                return $categories[$families[$keyFamily]->id][$keyCategory]->id;
            }else{
                return $families[$keyFamily]->id;
            }
        }else{
            return 404;
        }
    }

    public function saveStocks(){
        $products = Product::whereHas('stocks')->with('stocks')->get();
        $stocks = $products->map(function($product){
            return $product->stocks->unique('id')->values()->map(function($stock){
                return $stock->pivot;
            });
        })->toArray();
        $insert = array_merge(...$stocks);
        foreach(array_chunk($insert, 1000) as $toInsert){
            DB::table('stock_history')->insert($toInsert);
        }
        return response()->json(["Filas insertadas" => count($insert)]);
    }

    public function restorePrices(){
        try{
            $start = microtime(true);
            $products = Product::all()->toArray();
            $workpoint = \App\WorkPoint::find(1);
            $access = new AccessController($workpoint->dominio);
            $prices = $access->getPrices(); /* AGREGAR METODO */
            if($products && $prices){
                DB::transaction(function() use ($products, $prices){
                    DB::table('product_prices')->delete();
                    //array prices
                    $codes =  array_column($products, 'code');
                    $prices_insert = $prices->map(function($price) use($products, $codes){
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
        $start = microtime(true);
        $date = isset($request->date) ? $request->date : null;
        $workpoint = \App\WorkPoint::find(1);
        $access = new AccessController($workpoint->dominio);
        $required_products = $request->products ? : false;
        $required_prices = $request->prices ? : false;
        $raw_data = $access->getRawProducts($date, $required_prices, $required_products);
        $store_success = [];
        $store_fail = [];
        if($request->stores == "all"){
            $products = $access->getUpdatedProducts($date);
            $prices_required = $request->prices;
            
            $categories = ProductCategory::where([['id', '>', 403], ['deep', 2]])->get()->groupBy('root');
            $families = ProductCategory::where([['id', '>', 403], ['deep', 1]])->get();
            $array_families = array_column($families->toArray(), 'alias');

            if($products){
                DB::transaction(function() use ($products, $required_prices, $families, $categories, $array_families){
                    foreach($products as $product){
                        $_category = $this->getCategoryId($product['_family'], $product['_category'], $categories, $families, $array_families);
                        $_provider = $product['_provider'] <= 0 ? 1 : $product['_provider'];
                        $instance = Product::firstOrCreate([
                            'code'=> $product['code']
                        ], [
                            'name' => $product['name'],
                            'barcode' => $product['barcode'],
                            'description' => $product['description'],
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
                        $instance->name = $product['name'];
                        $instance->cost = $product['cost'];
                        /* $instance->_status = $product['_status']; */
                        $instance->_category = $_category;
                        $instance->description = $product['description'];
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
            /* if($required_prices && count($products) >= 1000){
                $this->restorePrices();
            } */
            $stores = \App\Workpoint::whereIn('id', [3,4,5,6,7,8,9,10,11,12,13,17])->get();
        }else{
            $stores = \App\WorkPoint::whereIn('alias', $request->stores)->get();
        }
        foreach($stores as $store){
            $access_store = new AccessController($store->dominio);
            $result = $access_store->syncProducts($raw_data["prices"], $raw_data["products"]);
            if($result){
                $store_success[] = $store->alias;
            }else{
                $store_fail[] = $store->alias;
            }
        }
        return response()->json([
            "success" => true,
            "products" => $products,
            "time" => microtime(true) - $start,
            "tiendas actualizadas" => $store_success,
            "tiendas que no se pudieron actualizar" => $store_fail
        ]);
        /* try{
        }catch(\Exception $e){
            return response()->json(["message" => "No se ha podido actualizar la tabla de productos"]);
        } */
    }

    public function addAtributes(Request $request){
        $products = $request->products;
        $total = 0;
        foreach($products as $row){
            $product = Product::where('code', $row['codigo'])->first();
            if($product){
                $description = mb_convert_encoding($row['descripcion'], "UTF-8");
                $product->description = ucfirst(mb_strtolower($description));
                $product->_category = $row['categoria'];
                $product->save();
                $remove = ['categoria', 'descripcion', 'codigo'];
                $arr = collect(array_diff_key($row, array_flip($remove)));
                $attributes = $arr->filter(function($el){
                    return $el !='N-A';
                })->map(function($el, $key){
                    if($el=='OK'){
                        return ['value'=> "Si"];    
                    }
                    $value = mb_convert_encoding($el, "UTF-8");
                    return ['value'=> ucfirst(mb_strtolower($value))];
                })->toArray();
                $product->attributes()->attach($attributes);
                $total++;
            }
        }
        return response()->json($total);
    }

    public function autocomplete(Request $request){
        $code = $request->code;
        $esElProducto = Product::with(['prices' => function($query){
            $query->whereIn('_type', [1,2,3,4])->orderBy('_type');
        }, 'units', 'variants', 'status'])
        ->orWhere('name', $request->code)
        ->orWhere('code', $request->code)->first();

        $products = Product::with(['prices' => function($query){
                            $query->whereIn('_type', [1,2,3,4])->orderBy('_type');
                        }, 'units', 'variants', 'status'])
                        ->whereHas('variants', function(Builder $query) use ($code){
                            $query->where('barcode', 'like', '%'.$code.'%');
                        })
                        ->orWhere('name', $request->code)
                        ->orWhere('code', $request->code)
                        ->orWhere('name', 'like','%'.$code.'%')
                        ->orWhere('code', 'like','%'.$code.'%')
                        ->orWhere('description', 'like','%'.$code.'%')->orderBy('_status', 'asc')
                        ->limit('20')->get();
        if($esElProducto && count($products)==20){
            $products[] = $esElProducto;
        }
        return response()->json(ProductResource::collection($products));
    }

    public function getMassiveProducts(Request $request){
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

    public function categoryTree(Request $request){
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

    public function getCategory(Request $request){
        $products = collect($request->products);
        $res = $products->map(function($p){
            $product = Product::with('category')->where('code',$p['pro_code'])->orWhere('name',$p['pro_code'])->first();
            if($product){
                return [
                    'id' => $product->id,
                    'code' => $product->code,
                    'location'=> $p['pro_location'],
                    'description' => $product->description,
                    'category' => $product->category->name
                ];
            }
            return [
                'code' => $p['pro_code'],
                'location'=> $p['pro_location'],
                'description' => $p['pro_largedesc'],
            ];
        });
        return response()->json($res);
    }

    public function updateStatus(Request $request){
        $product = Product::find($request->_product);
        if($product){
            $product->_status = $request->_status;
            return response()->json(["success" => $product->save()]);
        }
        return response()->json(["success" => false]);
    }

    public function getStatus(Request $request){
        $status = ProductStatus::all();
        return response()->json(["status" => $status]);
    }

    public function getProducts(Request $request){
        $query = Product::query();

        if(isset($request->autocomplete) && $request->autocomplete){
            $codes = explode('ID-', $request->autocomplete);
            if(count($codes)>1){
                $query = $query->where('id', $codes[1]);
            }else{
                if(strlen($request->autocomplete)>1){
                    $query = $query->whereHas('variants', function(Builder $query) use ($request){
                        $query->where('barcode', 'like', '%'.$request->autocomplete.'%');
                    })
                    ->orWhere('name', $request->autocomplete)
                    ->orWhere('barcode', $request->autocomplete)
                    ->orWhere('code', $request->autocomplete)
                    ->orWhere('name', 'like','%'.$request->autocomplete.'%')
                    ->orWhere('code', 'like','%'.$request->autocomplete.'%');
                }
            }
        }

        if(isset($request->products) && $request->products){
            $query = $query->whereHas('variants', function(Builder $query) use ($request){
                $query->whereIn('barcode', $request->products);
            })
            ->orWhereIn('name', $request->products)
            ->orWhereIn('code', $request->product);
        }

        if(isset($request->_category)){
            $_categories = $this->getCategoriesChildren($request->_category);
            $query = $query->whereIn('_category', $_categories);
        }

        if(isset($request->_status)){
            $query = $query->where('_status', $request->_status);
        }
        
        if(isset($request->_location)){
            $_locations = $this->getSectionsChildren($request->_location);
            $query = $query->whereHas('locations', function( Builder $query) use($_locations){
                $query->whereIn('_location', $_locations);
            });
        }

        if(isset($request->_celler) && $request->_celler){
            $locations = \App\CellerSection::where([['_celler', $request->_celler],['deep', 0]])->get();
            $ids = $locations->map(function($location){
                return $this->getSectionsChildren($location->id);
            });
            $_locations = array_merge(...$ids);
            $query = $query->whereHas('locations', function( Builder $query) use($_locations){
                $query->whereIn('_location', $_locations);
            });
        }

        if(isset($request->check_sales)){
            //OBTENER FUNCIÓN DE CHECAR STOCKS
        }

        $query = $query->with(['status', 'stocks' => function($query){
            $query->where('_workpoint', $this->account->_workpoint);
        }]);
        /* if(isset($request->with_stock) && $request->with_stock){
        } */

        if(isset($request->with_locations) && $request->with_locations){
            $query = $query->with(['locations' => function($query){
                $query->whereHas('celler', function($query){
                    $query->where('_workpoint', $this->account->_workpoint);
                });
            }]);
        }
        
        if(isset($request->check_stock) && $request->check_stock){
            if($request->with_stock){
                $query = $query->whereHas('stocks', function(Builder $query){
                    $query->where('_workpoint', $this->account->_workpoint)->where('stock', '>', 0);
                });
            }else{
                $query = $query->whereHas('stocks', function(Builder $query){
                    $query->where('_workpoint', $this->account->_workpoint)->where('stock', '<=', 0);
                });
            }
        }

        if(isset($request->with_prices) && $request->with_prices){
            $query = $query->with(['prices' => function($query){
                $query->whereIn('_type', [1, 2, 3, 4])->orderBy('id');
            }]);
        }

        if(isset($request->limit) && $request->limit){
            $query = $query->limit($request->limit);
        }

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

    public function seguimientoMercancia(){
        //Validar si tendran llegadas con sus respectivas fechas
        //Validar
    }

    public function addProductsLastYears(){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, "localhost/access/public/product/all");
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client,CURLOPT_TIMEOUT,10);
        $products = json_decode(curl_exec($client), true);
        $providers = \App\Provider::all();
        $ids_providers = array_column($providers->toArray(), "id");
        curl_close($client);
        if($products){
            DB::transaction(function() use ($products, $ids_providers){
                foreach($products as $product){
                    $key = array_search($product['_provider'], $ids_providers);
                    $_provider = ($key === 0 || $key > 0) ? $product['_provider'] : 404;
                    $instance = Product::firstOrCreate([
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
                DB::table('product_variants')->delete();

                $client = curl_init();
                curl_setopt($client, CURLOPT_URL, "localhost/access/public/product/related");
                curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($client,CURLOPT_TIMEOUT,10);
                $codes = json_decode(curl_exec($client), true);
                curl_close($client);
                $products2 = Product::all();
                
                $array_codes = array_column($products2->toArray(), 'code');
                if($codes){
                    foreach($codes as $code){
                        $key = array_search($code["ARTEAN"], $array_codes);
                        if($key>0 || $key === 0){
                            $insert[] = ["_product" => $products2[$key]->id, 'barcode' => $code['EANEAN'], 'stock' => 0];
                        }
                    }
                    DB::table('product_variants')->insert($insert);
                }
            });
        }
    }

    public function depure(Request $request){
        $start = microtime(true);
        /* $arr_codes = array_column($request->products, 'code'); */
        /* $products = []; */
        $found = [];
        $notFound = [];
        foreach (array_chunk($request->products/* $arr_codes */, 50) as $codes){
            $fac = new FactusolController();
            $found = array_merge($found, $fac->depure($codes)["found"]);
            $notFound = array_merge($notFound, $fac->depure($codes)["notFound"]);
        }
        return response()->json([
            "success" => true,
            "found" => $found,
            "notFound" => $notFound,
            "time" => microtime(true) - $start
        ]);
    }

    public function getABC(Request $request){
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
        $categories = \App\ProductCategory::where('deep', "<=" ,2)->get();
        $ids_categories = array_column($categories->toArray(), 'id');
        $products = Product::with(['category','sales' => function($query) use($date_from, $date_to){
            $query->where([['created_at', '>=', $date_from], ['created_at', '<=', $date_to]]);
        }, 'stocks', 'prices' => function($query){
            $query->where('_type', 7);
        }])->where([['id', '!=', 7089], ['id', '!=', 5816], ['description', "NOT LIKE", '%CREDITO%'], ['_status', '!=', 4]])->get()->map(function($product) use($categories, $ids_categories){
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
            if($product->category->deep == 0){
                $section = $product->category->name;
                $family = "";
                $category = "";
            }else if($product->category->deep == 1){
                $key = array_search($product->category->root, $ids_categories);
                if($key === 0 || $key > 0){
                    $section = $categories[$key]->name;
                    $family = $product->category->name;
                    $category = "";
                }else{
                    $section = $categories->category->root;
                    $family = $product->category->name;
                    $category = "";
                }
            }else{
                $key = array_search($product->category->root, $ids_categories);
                if($key === 0 || $key > 0){
                    $family = $categories[$key]->name;
                    $key2 = array_search($categories[$key]->root, $ids_categories);
                    if($key2 === 0 || $key2 > 0){
                        $section = $categories[$key2]->name;
                        $category = $product->category->name;
                    }else{
                        $section = $categories[$key]->root;
                        $category = $product->category->name;
                    }
                }else{
                    $section = "";
                    $family = $categories->category->root;
                    $category = $product->category->name;
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
                /* "Proveedor" => $product->provider->name, */
                "Sección" => $section,
                "Familia" => $family,
                "Categoria" => $category,
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

    public function getDiferenceBetweenStores(Request $request){
        $clouster = \App\WorkPoint::find(1);
        $access_clouster = new AccessController($clouster->dominio);
        $products = $access_clouster->getAllProducts(["CODART"])['products'];
        $store = \App\WorkPoint::find($request->_workpoint);
        $access_store = new AccessController($store->dominio);
        $differences = $access_store->getDifferencesBetweenCatalog($products);
        return response()->json($differences);
    }

    public function syncProducts(Request $required){
        $stores = \App\WorkPoint::whereIn('id', [3,4,5,6,7,8,9,10,11,12,13,17])->get();
        return $stores;
        $clouster = \App\WorkPoint::find(1);
        $access_clouster = new AccessController($clouster->dominio);
        if(strtoupper($request->type) == "COMPLETA"){
            $data = $access_clouster->getAllProducts();
        }else{
            $data = $access_clouster->getAllProducts($request->date);
        }
        
        if(strtoupper($request->stores) == "ALL"){
            $stores = \App\WorkPoint::whereIn('id', [3,4,5,6,7,8,9,10,11,12,13,17])->get();
        }else{
            $stores = \App\WorkPoint::whereIn('id', $request->stores)->get();
        }
        $result = [];
        foreach($stores as $store){
            $access_store = new AccessController($store->dominio);
            $result[$store->name] = $access_store->syncProducts($data);
        }
        return response()->json($result);
    }

    public function getABCStock(Request $request){
        $categories = \App\ProductCategory::where('deep', 0)->get();
        $ids_categories = array_column($categories->toArray(), 'id');
        /* $codes = array_column($request->products, 'Modelo');
        $products = Product::with(['stocks' => function($query) use($request){
            $query->where('_workpoint', $request->_workpoint);
        }, 'category', 'prices', 'locations' => function($query) use($request){
            $query->whereHas('celler',function($query) use($request){
                $query->where('_workpoint', $request->_workpoint);
            });
        }])->whereIn('code', $codes)->where('_status', '!=', 4)->get()->map(function($product) use($categories, $ids_categories, $request){
            if($product->category->deep == 0){
                $family = $product->category->name;
                $category = "";
            }else{
                $key = array_search($product->category->root, $ids_categories, true);
                if($product->category === 2){
                    $key = array_search($categories[$key]->root, $ids_categories, true);
                    $family = $categories[$key]->name;
                    $category = $product->category->name;
                }
                $family = $categories[$key]->name;
                $category = $product->category->name;
            }
            $prices = $product->prices->reduce(function($res, $price){
                $res[$price->name] = $price->pivot->price;
                return $res;
            }, []);
        
            $stocks = $product->stocks->unique('id')->values()->reduce(function($res, $stock){
            $res["stock_".$stock->name] = $stock->pivot->stock;
            return $res;
            }, []);
            $total_stocks = array_reduce($stocks, function($total, $store){
                return $store['pivot']['stock'] + $total;
            }, 0);

            $a = [
                "Modelo" => $product->code,
                "Código" => $product->name,
                "Descripción" => $product->description,
                "Piezas por caja" => $product->pieces,
                "Costo" => $product->cost,
                "Familia" => $family,
                "Categoría" => $category,
                "stock" => $total_stocks,
                "Ubicaciones" => implode(',', array_column($product->locations->toArray(), 'path'))
            ];
            $x = array_merge($a, $prices);
            return array_merge($x, $stocks);
        });
        $export = new ArrayExport($products->toArray());
        return Excel::download($export, "ABCD_PRODUCTOS_STOCK.xlsx"); */
        $workpoints = \App\WorkPoint::whereIn('id', range(1,13))->get();
        $response = [];
        foreach($workpoints as $workpoint){
            $products = Product::with(['provider','category', 'stocks' => function($query) use($workpoint){
                $query->where('_workpoint', $workpoint->id);
            }, 'prices' => function($query){
                $query->where('_type', 7);
            }])->where([['id', '!=', 7089], ['id', '!=', 5816], ['description', "NOT LIKE", '%CREDITO%'], ['_status', '!=', 4]])->get()->map(function($product) use($categories, $ids_categories, $workpoint){
                $stock = count($product->stocks)> 0 ? $product->stocks[0]->pivot->stock : 0;
                $valor_inventario = $product->cost * $stock;
                $price = count($product->prices) > 0 ? $product->prices[0]->pivot->price : 0;
                if($product->category->deep == 0){
                    $family = $product->category->name;
                    $category = "";
                }else{
                    $key = array_search($product->category->root, $ids_categories, true);
                    if($product->category === 2){
                        $key = array_search($categories[$key]->root, $ids_categories, true);
                        $family = $categories[$key]->name;
                        $category = $product->category->name;
                    }
                    $family = $categories[$key]->name;
                    $category = $product->category->name;
                }
                return [
                    "Sucursal" => $workpoint->name,
                    "Modelo" => $product->code,
                    "Código" => $product->name,
                    "Descripción" => $product->description,
                    "Proveedor" => $product->provider->name,
                    "Familia" => $family,
                    "Categoria" => $category,
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
}
