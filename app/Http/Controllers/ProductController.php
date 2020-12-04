<?php

namespace App\Http\Controllers;

use App\Product;
use App\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\ProductCategory;
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

    public function seeder(){
        try{
            $start = microtime(true);
            $client = curl_init();
            curl_setopt($client, CURLOPT_URL, "192.168.1.224:1618/access/public/product");
            curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
            $products = json_decode(curl_exec($client), true);
            curl_setopt($client, CURLOPT_URL, "192.168.1.224:1618/access/public/prices");
            $prices = collect(json_decode(curl_exec($client), true));
            curl_close($client);
            if($products && $prices){
                DB::transaction(function() use ($products, $prices){
                    //array prices
                    foreach (array_chunk($products, 1000) as $insert) {
                        $success = DB::table('products')->insert($insert);
                    }
                    $codes =  array_column($products, 'code');
                    $prices_insert = $prices->map(function($price) use($products, $codes){
                        $index_product = array_search($price['code'], $codes);
                        if($index_product == 0 || $index_product > 0){
                            return [
                                '_product' => $products[$index_product]['id'],
                                'price' => $price['price'],
                                '_type' => $price['_type']
                            ];
                        }
                    })->toArray();
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

    public function getMaximum(){
        $start = microtime(true);
        //$workpoint = \App\WorkPoint::find($this->account->_workpoint);
        $workpoint = \App\WorkPoint::find(11);
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $workpoint->dominio."/access/public/product/max");
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        $maximum = json_decode(curl_exec($client), true);
        
        foreach($maximum as $row){
            $product = Product::where('code', $row['code'])->first();
            if($product){
                $product->stocks()->attach($workpoint->id, ['min' => $row['min'], 'max' => $row['max'], 'stock' => $row['stock']]);
            }
        }
        return response()->json(["success" => true, "time" => microtime(true) - $start]);
    }

    public function updateTable(Request $request){
        $start = microtime(true);
        $client = curl_init();
        $date = isset($request->date) ? "?date=".$request->date : "";
        curl_setopt($client, CURLOPT_URL, "192.168.1.224:1618/access/public/product/updates".$date);
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        $products = json_decode(curl_exec($client), true);
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
                        '_status' => 1,
                        '_provider' => $product['_provider'],
                        '_unit' => $product['_unit'],
                        'created_at' => new \DateTime(),
                        'updated_at' => new \DateTime()
                    ]);
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
        $products = Product::with(['prices' => function($query){
                            $query->whereIn('_type', [1,2,3,4,5])->orderBy('_type');
                        }, 'units', 'variants'])
                        ->whereHas('variants', function(Builder $query) use ($code){
                            $query->where('barcode', 'like', '%'.$code.'%');
                        })
                        ->orWhere('name', 'like','%'.$code.'%')
                        ->orWhere('code', 'like','%'.$code.'%')
                        ->orWhere('description', 'like','%'.$code.'%')
                        ->limit('20')->get();
        return response()->json($products);
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
        $_category = $request->_category;
        $category = ProductCategory::with('attributes')->find($_category);
        $category->children = $this->getDescendentsCategory($category);
        $ascendents = $this->getAscendentsCategory($category);
        $filter = $this->getFilter($ascendents);
        return response()->json([
            "filter" => $filter,
            "category" => $ascendents
        ]);

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
}
