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
            $fac = new FactusolController();
            $products = $fac->todosProductos();
            if($products){
                DB::transaction(function() use ($products){
                    foreach($products as $product){
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
                            '_provider' => $product['_provider'],
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
                        $instance->_provider = $product['_provider'];
                        $instance->_status = $product['_status'];
                        $instance->created_at = $product['created_at'];
                        $instance->updated_at = new \DateTime();
                        $instance->save();
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
            $fac = new FactusolController();
            $prices = $fac->getPrices();
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
        $products = $access->getUpdatedProducts($date);
        try{
            DB::transaction(function() use ($products){
                foreach($products as $product){
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
                        '_provider' => $product['_provider'],
                        '_unit' => $product['_unit'],
                        'created_at' => new \DateTime(),
                        'updated_at' => new \DateTime(),
                        'cost' => $product['cost']
                    ]);
                    $instance->barcode = $product['barcode'];
                    $instance->name = $product['name'];
                    $instance->cost = $product['cost'];
                    $instance->_status = $product['_status'];
                    $instance->_category = $product['_category'];
                    $instance->description = $product['description'];
                    $instance->pieces = $product['pieces'];
                    $instance->_provider = $product['_provider'];
                    $instance->updated_at = new \DateTime();
                    $instance->save();
                    $prices = [];
                    foreach($product['prices'] as $price){
                        $prices[$price['_type']] = ['price' => $price['price']];
                    }
                    $instance->prices()->sync($prices);
                }
            });
            return response()->json([
                "success" => true,
                "products" => count($products),
                "time" => microtime(true) - $start
            ]);
        }catch(\Exception $e){
            return response()->json(["message" => "No se ha podido actualizar la tabla de productos"]);
        }
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
            $query = $query->whereHas('variants', function(Builder $query) use ($request){
                $query->where('barcode', 'like', '%'.$request->autocomplete.'%');
            })
            ->orWhere('name', $request->autocomplete)
            ->orWhere('code', $request->autocomplete)
            ->orWhere('name', 'like','%'.$request->autocomplete.'%')
            ->orWhere('code', 'like','%'.$request->autocomplete.'%');
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

        /* if(isset($request->_celler)){
            $locations = \App\CellerSection::where([['_celler', $request->_celler],['deep', 0]])->get();
            $ids = $locations->map(function($location){
                return $this->getSectionsChildren($location->id);
            });
            $_locations = array_merge(...$ids);
            $query = $query->whereHas('locations', function( Builder $query) use($_locations){
                $query->whereIn('_location', $_locations);
            });
        } */

        if(isset($request->check_sales)){
            //OBTENER FUNCIÓN DE CHECAR STOCKS
        }

        if(isset($request->with_stock) && $request->with_stock){
            $query->with(['stocks' => function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            }]);
        }

        if(isset($request->with_locations) && $request->with_locations){
            $query->with(['locations' => function($query){
                $query->whereHas('celler', function($query){
                    $query->where('_workpoint', $this->account->_workpoint);
                });
            }]);
        }
        
        if(isset($request->check_stock)){
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
            $query->with(['prices' => function($query){
                $query->whereIn('_type', [1, 2, 3, 4]);
            }]);
        }

        if(isset($request->paginate)){
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
        $categories = \App\ProductCategory::where('deep', 0)->get();
        $ids_categories = array_column($categories->toArray(), 'id');
        $products = Product::/* whereHas('sales', function($query) use($date_from, $date_to){
            $query->where([['created_at', '>=', $date_from], ['created_at', '<=', $date_to]]);
        })-> */with(['provider','category','sales' => function($query) use($date_from, $date_to){
            $query->where([['created_at', '>=', $date_from], ['created_at', '<=', $date_to]]);
        }, 'stocks', 'prices' => function($query){
            $query->where('_type', 7);
        }])->where([['_provider', 74], ['id', '!=', 7089], ['id', '!=', 5816], ['description', "NOT LIKE", '%CREDITO%']])->get()->map(function($product) use($categories, $ids_categories){
            $unidades_vendidas = $product->sales->sum(function($sale){
                return $sale->pivot->amount;
            });
            $costo_total = $unidades_vendidas * $product->cost;
            $venta_total = $product->sales->sum(function($sale){
                return $sale->pivot->total;
            });
            $rentabilidad = 0;
            $stock = $product->stocks->sum(function($stock){
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
                "Modelo" => $product->code,
                "Código" => $product->name,
                "Descripción" => $product->description,
                "Proveedor" => $product->provider->name,
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

    public function getDiferenceBetweenStores(){
        $clouster = \App\WorkPoint::find(1);
        $access_clouster = new AccessController($clouster->dominio);
        $products = $access_clouster->getAllProducts(["CODART"]);
        $store = \App\WorkPoint::find(8);
        $access_store = new AccessController($store->dominio);
        $differences = $access_store->getDifferencesBetweenCatalog($products);
        return response()->json($differences);
    }

    public function syncProducts(Request $required){
        $clouster = \App\WorkPoint::find(1);
        $access_clouster = new AccessController($clouster->dominio);
        if(strtoupper($request->type) == "COMPLETA"){
            $data = $access_clouster->getAllProducts();
        }else{
            $data = $access_clouster->getAllProducts($request->date);
        }
        
        if(strtoupper($request->stores) == "ALL"){
            $stores = \App\WorkPoint::whereIn('id', [3,4,5,6,7,8,9,10,11,12,13])->get();
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
}
