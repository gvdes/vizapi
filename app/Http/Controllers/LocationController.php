<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\WorkPoint;
use App\Product;
use App\CellerSection;
use App\Exports\ArrayExport;
use Maatwebsite\Excel\Facades\Excel;

class LocationController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public $account = null;
    public function __construct(){
        $this->account = Auth::payload()['workpoint'];
    }

    /**
     * Create celler
     * @param object request
     * @param string request[].name
     * @param string request[]._workpoint
     * @param string request[]._type
     */
    public function createCeller(Request $request){
        $celler = \App\Celler::create([
            'name' => $request->name,
            '_workpoint' => $this->account->_workpoint,
            '_type' => $request->_type
        ]);
        return response()->json([
            'success' => true,
            'celler' => $celler
        ]);
    }

    /**
     * Create section
     * @param object request
     * @param string request[].name
     * @param string request[].alias
     * @param string request[].path
     * @param int request[].root
     * @param int request[].deep
     * @param json request[].details
     * @param int request[].celler
     */
    public function createSection(Request $request){
        $sections = [];
        $increment = isset($request->autoincrement) ? $request->autoincrement : false;
        if($request->root>0){
            $siblings = \App\CellerSection::where('root', $request->root)->count();
            $root = \App\CellerSection::find($request->root);
            $items = isset($request->items) ? $request->items : 1;
            for($i = 0; $i<$items; $i++){
                $index = $siblings+$i+1;
                if($increment){
                    $index = '';
                }
                $section = \App\CellerSection::create([
                    'name' => $request->name.' '.$index,
                    'alias' => $request->alias.''.$index,
                    'path' => $root->path.'-'.$request->alias.''.$index,
                    'root' => $root->id,
                    'deep' => ($root->deep + 1),
                    'details' => json_encode($request->details),
                    '_celler' => $root->_celler
                ]);
                array_push($sections, $section);
            }
        }else{
            $siblings = \App\CellerSection::where([
                ['root', 0],
                ['_celler', $request->_celler]
            ])->count();
            $items = isset($request->items) ? $request->items : 1;
            for($i = 0; $i<$items; $i++){
                $index = $siblings+$i+1;
                if($increment){
                    $index = '';
                }
                $section = \App\CellerSection::create([
                    'name' => $request->name.' '.$index,
                    'alias' => $request->alias.''.$index,
                    'path' => $request->alias.''.$index,
                    'root' => 0,
                    'deep' => 0,
                    'details' => json_encode($request->details),
                    '_celler' => $request->_celler
                ]);
                array_push($sections, $section);
            }
        }

        return response()->json([
            'success' => true,
            'celler' => $sections
        ]);
    }

    public function deleteSection(Request $request){
        $section = CellerSection::find($request->_section);
        if($section){
            if($section->products->count()==0){
                $res = $section->delete();
                return response()->json([
                    'success' => $res
                ]);
            }else{
                return response()->json([
                    'success' => false,
                    'msg' => 'No se ha eliminado la sección debido a que tienen productos ubicados'
                ]);
            }
        }
        return response()->json([
            'success' => false,
            'msg' => 'No se ha encontrado la sección'
        ]);
    }

    public function removeLocations(Request $request){
        $section = CellerSection::find($request->_section);
        $section->sections = \App\CellerSection::where('root', $section->id)->get();
        $sections = $this->getSectionsChildren($section->id);
        $sections2 = CellerSection::whereIn('id', $sections)->get();
        
        foreach($sections2 as $section){
            $section->products()->detach();
        }

        $products = \App\Product::whereHas('locations', function($query) use ($sections){
            return $query->whereIn('_location', $sections);
        })->with(['locations' => function($query) use ($sections){
            return $query->whereIn('_location', $sections);
        }])->get();

        return response()->json(["products" => $products, "sections" => $sections2]);
    }

    /**
     * Get section in celler or children's sections
     * @param object request
     * @param int request[].celler | null
     * @param int request[].section | null
     */
    public function getSections(Request $request){
        $celler = $request->_celler ? $request->_celler : null;
        $section = $request->_section ? \App\CellerSection::find($request->_section) : null;
        $products = [];
        $paginate = $request->paginate ? $request->paginate : 20;
        if($celler && !$section){
            $section = \App\CellerSection::where([
                ['_celler', '=' ,$celler],
                ['deep', '=' ,0],
            ])->get()->map(function($section){
                $section->sections = \App\CellerSection::where('root', $section->id)->get();
                return $section;
            });
            if($request->products){
                $sections = \App\CellerSection::where([
                    ['_celler', '=' ,$celler],
                ])->get()->reduce(function($res, $section){
                    array_push($res, $section->id);
                    return $res;
                },[]);
                $products = \App\Product::whereHas('locations', function($query) use ($sections){
                    return $query->whereIn('_location', $sections);
                })->with(['locations' => function($query) use ($sections){
                    return $query->whereIn('_location', $sections);
                }])->paginate($paginate);
            }
        }else{
            $section->sections = \App\CellerSection::where('root', $section->id)->get();
            if($request->products){
                $sections = $this->getSectionsChildren($section->id);
                $products = \App\Product::whereHas('locations', function($query) use ($sections){
                    return $query->whereIn('_location', $sections);
                })->with(['locations' => function($query) use ($sections){
                    return $query->whereIn('_location', $sections);
                }])->paginate($paginate);
            }
        }
        return response()->json([
            "sections" => $section,
            "products" => $products
        ]);
    }

    /**
     * Get cellers in workpoint
     */
    public function getCellers(){
        $workpoint = $this->account->_workpoint;
        if($workpoint){
            $cellers = \App\Celler::where('_workpoint', $workpoint)->get();
            
            $res = $cellers->map(function($celler){
                $celler->sections = \App\CellerSection::where([
                    ['_celler', '=',$celler->id],
                    ['deep', '=', 0],
                ])->get();
                return $celler;
            });
            return response()->json([
                'cellers' => $res,
            ]);
        }else{
            return response()->json([
                'msg' => 'Usuario no autenticado'
            ]);
        }
    }

    /**
     * Get section in celler or children's sections
     * @param object request
     * @param int request[].code
     */
    public function getProduct(Request $request){
        $code = $request->id;
        $stocks_required = $request->stocks;
        $product = Product::with(['locations' => function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        },'stocks' => function($query) use($stocks_required){
            if($stocks_required){
                $query->where([
                    ['_workpoint', $this->account->_workpoint]
                ])->orWhere('_type', 1);
            }else{
                $query->where([
                    ['_workpoint', $this->account->_workpoint]
                ]);
            }
        },'category', 'status', 'units'])->find($code);
        $stock = $product->stocks->filter(function($stocks){
            return $stocks->id == $this->account->_workpoint;
        })->values()->all();
        $product->stock = $stock[0]->pivot->stock;
        $product->min = $stock[0]->pivot->min;
        $product->max = $stock[0]->pivot->max;
        $product->stocks_stores = $product->stocks->filter(function($stocks){
            return $stocks->id != $this->account->_workpoint;
        })->values()->map(function($stock){
            return ["alias" => $stock->alias, "stocks" => $stock->pivot->stock];
        })->values()->all();
        if($product){
            return response()->json($product);
        }
        return response()->json([
            "msg" => "Producto no encontrado"
        ]);
    }

    /**
     * Set locations to multiples products
     * @param object request
     * @param int request._product
     * @param int request._section
     */
    public function setLocation(Request $request){
        $product = \App\Product::find($request->_product);
        if($product){
            return response()->json([
                'success' => $product->locations()->toggle($request->_section)
            ]);            
        }
        return response()->json([
            'msg' => "Código no válido"
        ]);
    }

    /**
     * Set min and max to products
     * @param object request
     * @param int request.code
     * @param int request.min
     * @param int request.max
     */
    public function setMax(Request $request){
        $client = curl_init();
        $workpoint = \App\WorkPoint::find($this->account->_workpoint);
        $product = Product::where('code', $request->code)->first();
        if($product){
            $product->stocks()->updateExistingPivot($workpoint->id, ['min' => $request->min, 'max' => $request->max]);
            /* curl_setopt($client, CURLOPT_URL, $workpoint->dominio."/access/public/product/setmax?code=$request->code&min=$request->min&max=$request->max");
            curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($client,CURLOPT_TIMEOUT,8);
            $res = json_decode(curl_exec($client), true); */
            return response()->json(["success" => true]);
        }
        return response()->json(["success" => false]);
    }
    
    /* public function getReport(Request $request){
        $report = $request->_type ?  $request->_type : 1;
        switch ($report){
            case 'WithLocation':
                return response()->json($this->ProductsWithoutStock());
            break;
            case 'WithoutLocation':
                return response()->json($this->ProductsWithoutLocation());
        }
        
    } */

    public function ProductsWithoutLocation(){
        $products = \App\Product::has('locations', '=', 0)->select('id','code', 'description')->get()->toArray();
        $stocks = collect(AccessController::getProductWithStock());
        $res = $stocks->map(function($product) use ($products){
            $index = array_search($product['code'], array_column($products, 'code'));
            if($index){
                return [
                    'id' => $products[$index]['id'],
                    'code' => $product['code'],
                    'description' => $products[$index]['description'],
                    'stock' => intval($product['stock'])
                ];
            }else{
                return null;
            }
        })->filter(function($product){
            return !is_null($product);
        })->values()->all();
        return $res;
    }

    public function ProductsWithoutStock(){
        $products = \App\Product::has('locations', '>', 0)->select('id','code', 'description')->get()->toArray();
        $stocks = collect(AccessController::getProductWithoutStock());
        $res = $stocks->map(function($product) use ($products){
            $index = array_search($product['code'], array_column($products, 'code'));
            if($index){
                return [
                    'id' => $products[$index]['id'],
                    'code' => $product['code'],
                    'description' => $products[$index]['description'],
                    'stock' => intval($product['stock'])
                ];
            }else{
                return null;
            }
        })->filter(function($product){
            return !is_null($product);
        })->values()->all();
        return $res;
    }

    public function index(){
        /* $counterProducts = \App\Product::count();
        $workpoint = \App\WorkPoint::find($this->account->_workpoint);
        $sections = \App\Celler::select('id')->where('_workpoint', $workpoint->id)->get()->reduce(function($res, $section){ array_push($res, $section->id); return $res;},[1000]);
        $productsWithoutLocation = \App\Product::with('locations')->whereHas('locations', function (Builder $query) use ($sections){
            $query->whereIn('_celler', $sections);
        }, '<', 1)->select('id','code', 'description')->get();
        $productsWithLocation = \App\Product::whereHas('locations', function (Builder $query) use ($sections){
            $query->whereIn('_celler', $sections);
        }, '>', 0)->select('id','code', 'description')->get();

        $start = microtime(true);
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $workpoint->dominio."/access/public/product/withStock");
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        $withStocks = json_decode(curl_exec($client), true);
        if($withStocks){
            curl_setopt($client, CURLOPT_URL, $workpoint->dominio."/access/public/product/withoutStock");
            $withoutStocks = json_decode(curl_exec($client), true);
            curl_close($client);
            if($withoutStocks){
                $codes_withStock = array_column($withStocks, 'code');
                $withLocationWithStockCounter = $productsWithLocation->filter(function($product) use ($codes_withStock){
                    return array_search($product['code'], $codes_withStock);
                })->count();
                
                $withoutLocationWithStockCounter = $productsWithoutLocation->filter(function($product) use ($codes_withStock){
                    return array_search($product['code'], $codes_withStock);
                })->count();
                $codes_withoutStock = array_column($withoutStocks, 'code');
                $withLocationWithoutStockCounter = $productsWithLocation->filter(function($product) use ($codes_withoutStock){
                    return array_search($product['code'], $codes_withoutStock);
                })->count();
                return response()->json([
                    "withStock" => [
                        "stock" => count($withStocks),
                        "withLocation" => $withLocationWithStockCounter,
                        "withoutLocation" => $withoutLocationWithStockCounter
                    ],
                    "withoutStock" => [
                        "stock" => count($withoutStocks),
                        "withLocation" => $withLocationWithoutStockCounter
                    ],
                    "products" => $counterProducts,
                    "connection" => true
                ]);
            }
        } */
        /* return response()->json([
            "withStock" => [
                "stock" => '--',
                "withLocation" => '--',
                "withoutLocation" => '--'
            ],
            "withoutStock" => [
                "stock" => '--',
                "withLocation" => '--'
            ],
            "products" => $counterProducts,
            "connection" => false
        ]); */

        $counterProducts = Product::count();
        $withStock = Product::whereHas('stocks', function($query){
            $query->where([["stock", ">", 0], ["_workpoint", $this->account->_workpoint]]);
        })->count();
        $withoutStock = Product::whereHas('stocks', function($query){
            $query->where([["stock", "<=", 0], ["_workpoint", $this->account->_workpoint]]);
        })->count();
        $withLocation = Product::whereHas('stocks', function($query){
            $query->where([["stock", ">", 0], ["_workpoint", $this->account->_workpoint]]);
        })->whereHas('locations', function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        },'>',0)->count();
        $withoutLocation = Product::whereHas('stocks', function($query){
            $query->where([["stock", ">", 0], ["_workpoint", $this->account->_workpoint]]);
        })->whereHas('locations', function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        },'<=',0)->count();
        $withLocationWithoutStock = Product::whereHas('stocks', function($query){
            $query->where([["stock", "<=", 0], ["_workpoint", $this->account->_workpoint]]);
        })->whereHas('locations', function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        },'<=',0)->count();
        $generalVsExhibicion = Product::whereHas('stocks', function($query){
            $query->where([["gen", ">", 0], ["exh", "<=", 0], ["_workpoint", $this->account->_workpoint]]);
        })->count();
        $cedis = Product::whereHas('stocks', function($query){
            $query->where([["gen", ">", 0], ["_workpoint", 1]]);
        })->get();

        $general = Product::whereHas('stocks', function($query){
            $query->where([["gen", ">", 0], ["_workpoint", $this->account->_workpoint]]);
        })->get();
        $generalVsCedis = [];
        $arr_general = array_column($general->toArray(), 'code');
        foreach($cedis as $product){
            $key = array_search($product->code, $arr_general);
            if($key === 0 && $key>0){
                //exist
            }else{
                array_push($generalVsCedis, $product);
            }
        }

        $sinMaximos = Product::with(["stocks" => function($query){
            $query->where([["stock", ">", 0], ["min", "<=", 0], ["max", "<=", 0], ["_workpoint", $this->account->_workpoint]]);
        }])->whereHas('stocks', function($query){
            $query->where([["stock", ">", 0], ["min", "<=", 0], ["max", "<=", 0], ["_workpoint", $this->account->_workpoint]]);
        })->count();
        return response()->json([
            "withStock" => [
                "stock" => $withStock,
                "withLocation" => $withLocation,
                "withoutLocation" => $withoutLocation,
                "generalVsExhibicion" => $generalVsExhibicion,
                "sinMaximos" => $sinMaximos
            ],
            "withoutStock" => [
                "stock" => $withoutStock,
                "withLocation" => $withLocationWithoutStock,
                "generalVsCedis" => count($generalVsCedis)
            ],
            "products" => $counterProducts,
            "connection" => false
        ]);
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

    public function getAllSections(Request $request){
        $sections = \App\CellerSection::where('_celler', $request->_celler)->get();
        $roots = $sections->filter(function($section){
            return $section->root == 0;
        })->map(function($section) use ($sections){
            $section->children = $this->getChildren($sections, $section->id);
            return $section;
        })->values()->all();
        return response()->json($roots);
    }

    public function getChildren($sections, $root){
        return $sections->filter(function($section) use($root){
            return $section->root == $root;
        })->map(function($section) use ($sections){
            $section->children = $this->getChildren($sections, $section->id);
            return $section;
        })->values()->all();
    }

    public function getProductByCategory(Request $request){
        $category = \App\ProductCategory::where('root', 0)->get();
        $products = [];
        $filter = null;
        if(isset($request->_category)){
            $category = \App\ProductCategory::find($request->_category);
            $category->children = \App\ProductCategory::where('root', $request->_category)->get();
            $filter = $category->attributes;
        }
        if(isset($request->products)){
            if(isset($request->_category)){
                $ids = [$category->id];
                $ids = $category->children->reduce(function($res, $category){
                    array_push($res, $category->id);
                    return $res;
                }, $ids);
                $products = Product::whereIn('_category', $ids)->get();
            }else{
                $products = Product::limit(100)->get();
            }
        }
        return response()->json([
            "categories" => $category,
            "filter" => $filter,
            "products" => $products,
        ]);
    }

    public function getStocks(Request $request){
        if(count($request->products)>0){
            $products = Product::whereIn('id', $request->products)->get();
            $ids_workpoints = [1, $this->account->_workpoint];
            $workpoints = WorkPoint::whereIn('id', $ids_workpoints)->get()->sortBy('id');
            $stocks = $workpoints->reduce(function($products, $workpoint){
                $client = curl_init();
                curl_setopt($client, CURLOPT_URL, $workpoint->dominio."/access/public/product/stocks");
                curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($client, CURLOPT_POST, 1);
                curl_setopt($client,CURLOPT_TIMEOUT,100);
                $data = http_build_query(["products" => array_column($products->toArray(), "code")]);
                curl_setopt($client, CURLOPT_POSTFIELDS, $data);
                $stocks = json_decode(curl_exec($client), true);
                if($stocks){
                    $codes_array = array_column($stocks, 'code');
                    return $products->map(function($product) use($codes_array, $stocks, $workpoint){
                        $id = array_search($product->code, $codes_array);
                        if(!is_bool($id)){
                            $stock = isset($product->stockStores) ? $product->stockStores : [];
                            array_push($stock, ["workpoint" => $workpoint->alias, "stock" => $stocks[$id]['stock']]);
                            $product->stockStores = $stock;
                        }else{
                            $stock = isset($product->stockStores) ? $product->stockStores : [];
                            array_push($stock, ["workpoint" => $workpoint->alias, "stock" => $stocks[$id]['stock']]);
                            $product->stockStores = $stock;
                        }
                        return $product;
                    });
                }
                return $products;
            }, $products);
            $sorted = $stocks->sortByDesc(function($product){
                return array_reduce($product->stockStores,function($total, $store){
                    return $total + $store['stock'];
                },0);
            })->values()->all();
            return $sorted;
        }
        return response()->json(["message" => "Debe mandar almenos un articulo"]);
    }

    public function setMasiveLocation(Request $request){
        $products = $request->products;
        $added = 0;
        $res = [];
        $location = [];
        foreach($products as $code){
            $product = Product::/* find($code['id']) */whereHas('variants', function(Builder $query) use ($code){
                $query->where('barcode', 'like', '%'.$code['code'].'%');
            })
            ->orWhere('name', 'like','%'.$code['code'].'%')
            ->orWhere('code', 'like','%'.$code['code'].'%')->first();
            $path = $code['path'];
            /* $path = explode('-', $code['path'])[0].'-T'.explode('-', $code['path'])[1];
            $path = $path[0].'-P'.substr($path,1,strlen($path)); */
            if($product){
                $section = CellerSection::whereHas('celler', function($query){
                    $query->where('_workpoint', $this->account->_workpoint);
                })->where('path', $path)->first();
                if($section){
                    $product->locations()->syncWithoutDetaching([$section->id]);
                    $added++;
                }else{
                    array_push($location, ["code" => $code['code'], "location" => $path]);
                }
            }else{
                array_push($res, ["code" => $code['code'], "location" => $path]);
            }
        }
        return response()->json(["success" => $added, "notFound" => $res, "locationNotFound" => $location]);
    }

    public function getStocksFromStores(Request $request){
        $products = Product::whereIn('id', $request->_products)->get();
        $stores = Workpoint::whereIn('id', $request->_stores)->get();
        $codes = array_column($products->toArray(), 'code');
        $stocks = [];
        foreach($stores as $workpoint){
            $client = curl_init();
            curl_setopt($client, CURLOPT_URL, $workpoint->dominio."/access/public/product/stocks");
            curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($client, CURLOPT_POST, 1);
            curl_setopt($client,CURLOPT_TIMEOUT,10);
            $data = http_build_query(["products" => $codes]);
            curl_setopt($client, CURLOPT_POSTFIELDS, $data);
            $stocks[$workpoint->alias] = json_decode(curl_exec($client), true);
        }
        $result = $products->map(function($product, $key) use($stocks, $stores){
            $data = [
                'code' => $product->code,
                'scode' => $product->name,
                'category'=> $product->category->name,
                'descripción' => $product->description
            ];
            foreach($stores as $workpoint){
                if($stocks[$workpoint->alias]){
                    $data[$workpoint->alias] = $stocks[$workpoint->alias][$key]['stock'];
                }else{
                    $data[$workpoint->alias] = '--';
                }
            }
            return $data;
        })->toArray();
        return response()->json($result);
    }

    public function updateStocks(){
        $workpoints = WorkPoint::whereIn('id', [1,13])->get();
        foreach($workpoints as $workpoint){
            $client = curl_init();
            curl_setopt($client, CURLOPT_URL, $workpoint->dominio."/access/public/celler/stock");
            curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
            $stocks = json_decode(curl_exec($client), true);
            if($stocks){
                $products = Product::with(["stocks" => function($query) use($workpoint){
                    $query->where('_workpoint', $workpoint->id);
                }])->where('_status', 1)->get();
                $codes_stocks = array_column($stocks, 'code');
                foreach($products as $product){
                    $key = array_search($product->code, $codes_stocks, true);
                    if($key === 0 || $key > 0){
                        $stock = count($product->stocks)>0 ? $product->stocks[0]->pivot->stock : false;
                        if(gettype($stock) == "boolean"){
                            $product->stocks()->attach($workpoint->id, ['stock' => $stocks[$key]["stock"], 'min' => 0, 'max' => 0]);
                        }elseif($stock != $stocks[$key]["stock"]){
                            $product->stocks()->updateExistingPivot($workpoint->id, ['stock' => $stocks[$key]["stock"]]);
                        }
                    }
                }
            }
        }
        return response()->json(["success" => true]);
    }

    public function getReport(Request $request){
        switch($request->_type){
            case 1:
                $res = $this->conStock();
                $name = "conStock";
                break;
            case 2:
                $res = $this->conStockUbicados();
                $name = "conStockUbicados";
                break;
            case 3:
                $res = $this->conStockSinUbicar();
                $name = "conStockSinUbicar";
                break;
            case 4:
                $res = $this->sinStock();
                $name = "sinStock";
                break;
            case 5:
                $res = $this->sinStockUbicados();
                $name = "sinStockUbicados";
                break;
            case 6:
                $res = $this->sinMaximos();
                $name = "sinMaximos";
                break;
            case 7:
                $res = $this->generalVsExhibicion();
                $name = "generalVsExhibicion";
                break;
            case 8:
                $res = $this->generalVsCedis();
                $name = "generalVsCedis";
                break;
            default:
                $res = ["NOT"=>"4", "_" => "0", "FOUND" =>"4"];
                $name = "noFound";
                break;
        }
        $export = new ArrayExport($res);
        $date = new \DateTime();
        return Excel::download($export, $name.".xlsx");
    }

    public function conStock(){
        $productos = Product::with(['stocks' => function($query){
            $query->where([["stock", ">", "0"], ["_workpoint", $this->account->_workpoint]]);
        }, 'locations' => function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        }])->whereHas('stocks', function($query){
            $query->where([["stock", ">", "0"], ["_workpoint", $this->account->_workpoint]]);
        })->get();
        $res = $productos->map(function($producto){
            $locations = $producto->locations->reduce(function($res, $location){
                return $res.$location->path.",";
            }, '');
            return [
                "codigo" => $producto->name,
                "modelo" => $producto->code,
                "descripcion" => $producto->description,
                "piezas" => $producto->pieces,
                "stock" => $producto->stocks[0]->pivot->stock,
                "locations" => $locations
            ];
        })->toArray();
        return $res;
    }

    public function sinStock(){
        $productos = Product::with(['stocks' => function($query){
            $query->where([["stock", "<=", "0"], ["_workpoint", $this->account->_workpoint]]);
        }, 'locations' => function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        }])->whereHas('stocks', function($query){
            $query->where([["stock", "<=", "0"], ["_workpoint", $this->account->_workpoint]]);
        })->get();
        $res = $productos->map(function($producto){
            $locations = $producto->locations->reduce(function($res, $location){
                return $res.$location->path.",";
            }, '');
            return [
                "codigo" => $producto->name,
                "modelo" => $producto->code,
                "descripcion" => $producto->description,
                "piezas" => $producto->pieces,
                "stock" => $producto->stocks[0]->pivot->stock,
                "locations" => $locations
            ];
        })->toArray();
        return $res;
    }

    public function conStockUbicados(){
        $productos = Product::with(['stocks' => function($query){
            $query->where([["gen", ">", "0"], ["_workpoint", $this->account->_workpoint]]);
        }, 'locations' => function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        }])->whereHas('stocks', function($query){
            $query->where([["gen", ">", "0"], ["_workpoint", $this->account->_workpoint]]);
        })->whereHas('locations', function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        },'>',0)->get();
        $res = $productos->map(function($producto){
            $locations = $producto->locations->reduce(function($res, $location){
                return $res.$location->path.",";
            }, '');
            return [
                "codigo" => $producto->name,
                "modelo" => $producto->code,
                "descripcion" => $producto->description,
                "piezas" => $producto->pieces,
                "stock" => $producto->stocks[0]->pivot->stock,
                "locations" => $locations
            ];
        })->toArray();
        return $res;
    }

    public function conStockSinUbicar(){
        $productos = Product::with(['stocks' => function($query){
            $query->where([["gen", ">", "0"], ["_workpoint", $this->account->_workpoint]]);
        }, 'locations' => function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        }])->whereHas('stocks', function($query){
            $query->where([["gen", ">", "0"], ["_workpoint", $this->account->_workpoint]]);
        })->whereHas('locations', function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        },'<=',0)->get();
        $res = $productos->map(function($producto){
            $locations = $producto->locations->reduce(function($res, $location){
                return $res.$location->path.",";
            }, '');
            return [
                "codigo" => $producto->name,
                "modelo" => $producto->code,
                "descripcion" => $producto->description,
                "piezas" => $producto->pieces,
                "stock" => $producto->stocks[0]->pivot->stock,
                "locations" => $locations
            ];
        })->toArray();
        return $res;
    }

    public function sinStockUbicados(){
        $productos = Product::with(['stocks' => function($query){
            $query->where([["gen", "<=", "0"], ["_workpoint", $this->account->_workpoint]]);
        }, 'locations' => function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        }])->whereHas('stocks', function($query){
            $query->where([["gen", "<=", "0"], ["_workpoint", $this->account->_workpoint]]);
        })->whereHas('locations', function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        },'>',0)->get();
        $res = $productos->map(function($producto){
            $locations = $producto->locations->reduce(function($res, $location){
                return $res.$location->path.",";
            }, '');
            return [
                "codigo" => $producto->name,
                "modelo" => $producto->code,
                "descripcion" => $producto->description,
                "piezas" => $producto->pieces,
                "stock" => $producto->stocks[0]->pivot->stock,
                "locations" => $locations
            ];
        })->toArray();
        return $res;
    }

    public function generalVsExhibicion(){
        $productos = Product::with(['stocks' => function($query){
            $query->where([["gen", ">", "0"], ["exh", "<=", 0], ["_workpoint", $this->account->_workpoint]]);
        }, 'locations' => function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        }])->whereHas('stocks', function($query){
            $query->where([["gen", ">", "0"], ["exh", "<=", 0], ["_workpoint", $this->account->_workpoint]]);
        })->get();
        $res = $productos->map(function($producto){
            $locations = $producto->locations->reduce(function($res, $location){
                return $res.$location->path.",";
            }, '');
            return [
                "codigo" => $producto->name,
                "modelo" => $producto->code,
                "descripcion" => $producto->description,
                "piezas" => $producto->pieces,
                "GENERAL" => $producto->stocks[0]->pivot->gen,
                "EXHIBICION" => $producto->stocks[0]->pivot->exh,
                "locations" => $locations
            ];
        })->toArray();
        return $res;
    }

    public function generalVsCedis(){
        $cedis = Product::whereHas('stocks', function($query){
            $query->where([["gen", ">", 0], ["_workpoint", 1]]);
        })->get();

        $general = Product::whereHas('stocks', function($query){
            $query->where([["gen", ">", 0], ["_workpoint", $this->account->_workpoint]]);
        })->get();
        $generalVsCedis = [];
        $arr_general = array_column($general->toArray(), 'code');
        foreach($cedis as $product){
            $key = array_search($product->code, $arr_general);
            if($key === 0 && $key>0){
                //exist
            }else{
                array_push($generalVsCedis, $product);
            }
        }
        /* $productos = Product::with(["stocks" => function($query){
            $query->where([["gen", ">", "0"], ["_workpoint", 1]])
            ->where([["gen", "<=", "0"], ["_workpoint", $this->account->_workpoint]]);
        }])->whereHas('stocks', function($query){
            $query->where([["gen", ">", "0"], ["_workpoint", 1]])
            ->where([["gen", "<=", "0"], ["_workpoint", $this->account->_workpoint]]);
        })->get(); */

        $res = collect($generalVsCedis)->map(function($producto){
            $locations = $producto->locations->reduce(function($res, $location){
                return $res.$location->path.",";
            }, '');
            return [
                "codigo" => $producto->name,
                "modelo" => $producto->code,
                "descripcion" => $producto->description,
                "piezas" => $producto->pieces,
                "CEDIS" => $producto->stocks[0]->pivot->gen,
                "GENERAL" => $producto->stocks[0]->pivot->exh,
                "locations" => $locations
            ];
        })->toArray();
        return $res;
    }

    public function sinMaximos(){
        $productos = Product::with(["stocks" => function($query){
            $query->where([["stock", ">", 0], ["min", "<=", 0], ["max", "<=", 0], ["_workpoint", $this->account->_workpoint]]);
        }])->whereHas('stocks', function($query){
            $query->where([["stock", ">", 0], ["min", "<=", 0], ["max", "<=", 0], ["_workpoint", $this->account->_workpoint]]);
        })->get();

        $res = $productos->map(function($producto){
            $locations = $producto->locations->reduce(function($res, $location){
                return $res.$location->path.",";
            }, '');
            return [
                "codigo" => $producto->name,
                "modelo" => $producto->code,
                "descripcion" => $producto->description,
                "stock" => $producto->stocks[0]->pivot->stock,
                "min" => $producto->stocks[0]->pivot->min,
                "max" => $producto->stocks[0]->pivot->max
            ];
        })->toArray();
        return $res;
    }

    public function updateStocks2(){
        $workpoints = WorkPoint::whereIn('id', range(1,15))->get();
        $success = 0;
        $_success = [];
        foreach($workpoints as $workpoint){
            $client = curl_init();
            curl_setopt($client, CURLOPT_URL, "192.168.1.224:1618/access/public/celler/stock");
            curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($client, CURLOPT_POST, 1);
            curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
            $data = http_build_query(["_workpoint" => $workpoint->id]);
            curl_setopt($client, CURLOPT_POSTFIELDS, $data);
            $stocks = json_decode(curl_exec($client), true);
            if($stocks){
                $success++;
                array_push($_success, $workpoint->alias);
                $products = Product::with(["stocks" => function($query) use($workpoint){
                    $query->where('_workpoint', $workpoint->id);
                }])->where('_status', 1)->get();
                $codes_stocks = array_column($stocks, 'code');
                foreach($products as $product){
                    $key = array_search($product->code, $codes_stocks, true);
                    if($key === 0 || $key > 0){
                        $stock = count($product->stocks)>0 ? $product->stocks[0]->pivot->stock : false;
                        if(gettype($stock) == "boolean"){
                            $product->stocks()->attach($workpoint->id, ['stock' => $stocks[$key]["stock"], 'min' => 0, 'max' => 0, 'gen' => $stocks[$key]["gen"], 'exh' => $stocks[$key]["exh"]]);
                        }elseif($stock != $stocks[$key]["stock"]){
                            $product->stocks()->updateExistingPivot($workpoint->id, ['stock' => $stocks[$key]["stock"], 'gen' => $stocks[$key]["gen"], 'exh' => $stocks[$key]["exh"]]);
                        }
                    }
                }
            }
        }
        return response()->json(["completados" => $success, "tiendas" => $_success]);
    }
}
