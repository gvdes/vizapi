<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\WorkPoint;
use App\Product;

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
        if($request->root>0){
            $siblings = \App\CellerSection::where('root', $request->root)->count();
            $root = \App\CellerSection::find($request->root);
            $items = isset($request->items) ? $request->items : 1;
            for($i = 0; $i<$items; $i++){
                $index = $siblings+$i+1;
                $section = \App\CellerSection::create([
                    'name' => $request->name.''.$index,
                    'alias' => $request->alias.' '.$index,
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
                })->with('locations')->paginate($paginate);
            }
        }else{
            $section->sections = \App\CellerSection::where('root', $section->id)->get();
            if($request->products){
                $sections = $this->getSectionsChildren($section->id);
                $products = \App\Product::whereHas('locations', function($query) use ($sections){
                    return $query->whereIn('_location', $sections);
                })->with('locations')->paginate($paginate);
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
        $code = $request->code;
        $workpoint = \App\Workpoint::find($this->account->_workpoint);
        $cellers = \App\Celler::select('id')->where('_workpoint', $workpoint->id)->get()->reduce(function($res, $section){ array_push($res, $section->id); return $res;},[1000]);
        $product = \App\Product::with(['locations' => function($query)use($cellers){
            $query->whereIn('_celler', $cellers);
        }])->with('category', 'status', 'units')->where('code', $code)->orWhere('name', $code)->first();
        if(!$product){
            $product = \App\ProductVariant::where('barcode', $code)->first();
            if($product){
                $product = \App\Product::with(['locations' => function($query)use($cellers){
                    $query->whereIn('_celler', $cellers);
                }])->with('category', 'status', 'units')->find($product->product->id);
            }
        }
        if($product){
            $client = curl_init();
            curl_setopt($client, CURLOPT_URL, $workpoint->dominio."/access/public/product/max/".$product->code);
            curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($client,CURLOPT_TIMEOUT,8);
            $access = json_decode(curl_exec($client), true);
            if($access){
                $product->stock = intval($access['ACTSTO']);
                $product->min = intval($access['MINSTO']);
                $product->max = intval($access['MAXSTO']);
            }else{
                $product->stock = '--';
                $product->min = '--';
                $product->max = '--';
            }
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
        curl_setopt($client, CURLOPT_URL, $workpoint->dominio."/access/public/product/setmax?code=$request->code&min=$request->min&max=$request->max");
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client,CURLOPT_TIMEOUT,8);
        $res = json_decode(curl_exec($client), true);
        return response()->json(["success" => $res]);
    }
    
    public function getReport(Request $request){
        $report = $request->report ?  $request->report : 'WithLocation';
        switch ($report){
            case 'WithLocation':
                return response()->json($this->ProductsWithoutStock());
            break;
            case 'WithoutLocation':
                return response()->json($this->ProductsWithoutLocation());
        }
        
    }

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
        $counterProducts = \App\Product::count();
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
                $withLocationWithStockCounter = $productsWithLocation->filter(function($product) use ($withStocks){
                    return array_search($product['code'], array_column($withStocks, 'code'));
                })->count();
                
                $withoutLocationWithStockCounter = $productsWithoutLocation->filter(function($product) use ($withStocks){
                    return array_search($product['code'], array_column($withStocks, 'code'));
                })->count();
        
                $withLocationWithoutStockCounter = $productsWithLocation->filter(function($product) use ($withoutStocks){
                    return array_search($product['code'], array_column($withoutStocks, 'code'));
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
        }
        return response()->json([
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

    public function existencias(Request $request){
        $category = \App\ProductCategory::where('root', 0)->get();
        $products = [];
        if(isset($request->_category)){
            $category = \App\ProductCategory::find($request->_category);
            $category->children = \App\ProductCategory::where('root', $request->_category)->get();
        }
        $stocks = [];
        if(isset($request->stock)){
            if(isset($request->_category)){
                $ids = [$category->id];
                $ids = $category->children->reduce(function($res, $category){
                    array_push($res, $category->id);
                    return $res;
                }, $ids);
                $products = Product::whereIn('_category', $ids)->get();
                $stocks = $this->getStocks($products);
            }else{
                $stocks = $this->getStocks();
            }
        }
        return response()->json([
            "categories" => $category,
            "filter" => [
                ["name" => "Luces", "details" => ["type" => "select", "options" => [100,200,300,500,100]]],
                ["name" => "Metros", "details" => ["type" => "select", "options" => [2,3,5,7,10,12,15]]],
                ["name" => "Color", "details" => ["type" => "select", "options" => ["Azul", "Blanca", "Rojo", "Verde"]]]
            ],
            "products" => $stocks,
        ]);
    }

    public function getStocks($products = []){
        $ids_workpoints = [1, 2, $this->account->_workpoint];
        $workpoints = WorkPoint::whereIn('id', $ids_workpoints)->get()->sortBy('id');
        $stocks = $workpoints->reduce(function($products, $workpoint){
            $client = curl_init();
            curl_setopt($client, CURLOPT_URL, $workpoint->dominio."/access/public/product/stocks");
            curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($client, CURLOPT_POST, 1);
            curl_setopt($client,CURLOPT_TIMEOUT,100);
            if(count($products) == 0){
                $stocks = json_decode(curl_exec($client), true);
                if($stocks){
                    $stocks = collect($stocks);
                    return $stocks->map(function($product) use($workpoint){
                        $product_search = Product::where('code', $product['code'])->first();
                        if($product_search){
                            if($this->account->_workpoint == $workpoint->id){
                                $product_search->stock = $product['stock'];
                            }else{
                                $product_search->stocks = [["workpoint" => $workpoint->alias, "stock" => $product['stock']]];
                            }
                            return $product_search;
                        }
                        return null;
                    })->filter(function($product){
                        return !is_null($product);
                    });
                }
                return [];
            }else{
                $data = http_build_query(["products" => array_column($products->toArray(), "code")]);
                curl_setopt($client, CURLOPT_POSTFIELDS, $data);
                $stocks = json_decode(curl_exec($client), true);
                if($stocks){
                    $codes_array = array_column($stocks, 'code');
                    return $products->map(function($product) use($codes_array, $stocks, $workpoint){
                        $id = array_search($product->code, $codes_array);
                        if(!is_bool($id)){
                            if($this->account->_workpoint == $workpoint->id){
                                $product->stock = $stocks[$id]['stock'];
                            }else{
                                $stock = isset($product->stocks) ? $product->stocks : [];
                                array_push($stock, ["workpoint" => $workpoint->alias, "stock" => $stocks[$id]['stock']]);
                                $product->stocks = $stock;
                            }
                        }else{
                            if($this->account->_workpoint == $workpoint->id){
                                $product->stock = "--";
                            }else{
                                $stock = isset($product->stocks) ? $product->stocks : [];
                                array_push($stock, ["workpoint" => $workpoint->alias, "stock" => $stocks[$id]['stock']]);
                                $product->stocks = $stock;
                            }
                        }
                        return $product;
                    });
                }
                return $products;
            }
        }, $products)->sortByDesc('stock')->values()->all();
        return $stocks;
    }
}
