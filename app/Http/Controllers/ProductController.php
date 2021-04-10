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
        /* $this->account = Auth::payload()['workpoint']; */
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

    public function restorePrices(){
        try{
            $start = microtime(true);
            $products = Product::all()->toArray();
            /* $fac = new FactusolController();
            $prices = $fac->getPrices(); */
            $client = curl_init();
            curl_setopt($client, CURLOPT_URL, "localhost/access/public/prices");
            curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($client,CURLOPT_TIMEOUT,10);
            $prices = collect(json_decode(curl_exec($client), true));
            curl_close($client);
            /* return response()->json($prices); */
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
        $fac = new FactusolController();
        $date = isset($request->date) ? $request->date : null;
        $products = $fac->productosActualizados($date);
        try{
            DB::transaction(function() use ($products){
                foreach($products as $product){
                    $instance = Product::firstOrCreate([
                        'code'=> $product['code']
                    ], [
                        'name' => $product['name'],
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
        /* $products = array_slice($request->products, 1700); */
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
                        ->orWhere('description', 'like','%'.$code.'%')
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
        /* return response()->json($request); */
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
            /* $ascendents = $this->getAscendentsCategory($category);
            $filter = $this->getFilter($ascendents); */
        }else{
            $categories = ProductCategory::with('attributes')->where('deep', 0)->get();
            $map = $categories->map(function($category){
                $category->children = $this->getDescendentsCategory($category);
                /* $ascendents = $this->getAscendentsCategory($category);
                $filter = $this->getFilter($ascendents); */
                return $category;
            });
            return response()->json($map);
        }
        return response()->json($category);
        /* return response()->json([
            "filter" => $filter,
            "category" => $ascendents
        ]); */

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
        /* return response()->json($products); */
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

    public function getProductsWithCodes(Request $request){
        $products = Product::with(['variants', 'category'])->has('variants', '>', 0)->get();
        $categories = \App\ProductCategory::all();
        $arr_categories = array_column($categories->toArray(), "id");
        $map = $products->map(function($product) use($categories, $arr_categories){
            if($product->category->deep == 0){
                $familia = $product->category->name;
            }else{
                $key = array_search($product->category->root, $arr_categories, true);
                $familia = $categories[$key]->name;
            }
            return [
                "Modelo" => $product->code,
                "Código" => $product->name,
                "Descripción" => $product->description,
                "Familia" => $familia,
                "Categoría" => $product->category->name,
                "Variantes" => $product->variants->reduce(function($res, $variant){
                    array_push($res, $variant->barcode);
                    return $res; 
                }, []),
            ];
        });
        return response()->json(["products" => $map]);
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

        if(isset($request->_category)){
            /* $_categories = [];
            foreach($request->_category as $_category){
                $categories = array_merge($categories, $this->getCategoriesChildren($category));
            } */
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
            $query->with(['stocks' => function($query){
                $query->whereIn('_type', [1, 2, 3, 4]);
            }]);
        }

        if(isset($request->paginate)){
            $products = $query->paginate($request->paginate);
        }else{
            $products = $query->get();
        }
        /* return response()->json($products); */
        return response()->json(ProductResource::collection($products));
    }

    public function getPrices(Request $request){
        $products = Product::with('prices')->whereIn('code', array_column($request->products, "modelo"))->get();
        $res = $products->map(function($product){
            $prices = $product->prices->map(function($price){
                return [$price->alias => $price->pivot->price];
            })->toArray();
            $res = ["code" => $product->code, "descripcion" => $product->description];
            return array_merge($res, $prices);
        });
        return response()->json($res);
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

    public function getMochilaPrices(){
        $now = Product::whereIn('_category', range(1,16))->where('updated_at', ">", "2021-03-10 00:00:00")->where('updated_at', "<", "2021-03-10 23:59:59")/* ->paginate(500) */->get();
        $prices = $this->getPrices_access(array_column($now->toArray(), 'code'));
        $arr_products = array_column($prices, "code");
        /* return response()->json(["now" => $arr_products]); */
        $result = $now->filter(function($product) use($prices, $arr_products){
            $key = array_search($product->code, $arr_products);
            if($key === 0 || $key > 0){
                $now = array_column($product->prices->toArray(), "price");
                $yesterday = array_column($prices[$key]["prices"], "price");
                return count(array_diff($now, $yesterday))>0;
            }
            return false;
        });
        return response()->json([/* "now" => count($now), "access" => $prices,  */"result" => $result]);
    }

    public function getPrices_access($codes){
        $access = "C:\\Users\Carlo\\Desktop\\VPAO1O32021.accdb";
        $query = "SELECT TARLTA as _type, PRELTA as price FROM F_LTA WHERE ARTLTA = ?";
        $db = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
        try{
            $res = [];
            foreach($codes as $code){
                $exec = $db->prepare($query);
                $exec->execute([$code]);
                $rows = $exec->fetchAll(\PDO::FETCH_ASSOC);
                $res [] = ["code" => $code, "prices" => $rows];
            }
            return $res;
        }catch(\PDOException $e){
            die($e->getMessage());
        }
    }

    public function addProductsLastYears(){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, "localhost/access/public/product");
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client,CURLOPT_TIMEOUT,10);
        $products = json_decode(curl_exec($client), true);
        curl_close($client);
        if($products){
            DB::transaction(function() use ($products){
                foreach($products as $product){
                    Product::firstOrCreate([
                        'code'=> $product['code']
                    ], [
                        'name' => $product['name'],
                        'description' => $product['description'],
                        'dimensions' => $product['dimensions'],
                        'pieces' => $product['pieces'],
                        '_category' => $product['_category'],
                        '_status' => 4,
                        '_provider' => $product['_provider'],
                        '_unit' => $product['_unit'],
                        'created_at' => $product['created_at'],
                        'updated_at' => new \DateTime(),
                        'cost' => $product['cost']
                    ]);
                }
            });
        }
    }

    public function getPriceAAA(){
        $categories = \App\ProductCategory::where('deep', 0)->get();
        $ids_categories = array_column($categories->toArray(), 'id');
        $products = Product::whereHas('prices',)->with(['prices' => function($query){
            $query->where('_type', 7);
        }])->get()->map(function($product) use($categories, $ids_categories){
            if($product->category->deep == 0){
                $familia = $product->category->name;
                $category = "";
            }else{
                $key = array_search($product->category->root, $ids_categories, true);
                if($product->category === 2){
                    $key = array_search($categories[$key]->root, $ids_categories, true);
                    $familia = $categories[$key]->name;
                    $category = $product->category->name;
                }
                $familia = $categories[$key]->name;
                $category = $product->category->name;
            }
            return [
                "Codigo" => $product->code,
                "Modelo" => $product->name,
                "Descripción" => $product->description,
                "Unidad de medida" => $product->pieces,
                "Familia" => $familia,
                "Categoría" => $category,
                "Costo" => $product->cost,
                "Precio AAA" => count($product->prices)>0 ? $product->prices[0]->pivot->price : 0
            ];
        });
        /* return $products; */
        $export = new ArrayExport($products->toArray());
        $date = new \DateTime();
        return Excel::download($export, "2018_precios.xlsx");
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
}
