<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

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
        if($request->root>0){
            $root = \App\CellerSection::find($request->root);
            $section = \App\CellerSection::create([
                'name' => $request->name,
                'alias' => $request->alias,
                'path' => $root->path.'-'.$request->alias,
                'root' => $root->id,
                'deep' => ($root->deep + 1),
                'details' => $request->details,
                '_celler' => $root->_celler
            ]);
        }else{
            $section = \App\CellerSection::create([
                'name' => $request->name,
                'alias' => $request->alias,
                'path' => $request->alias,
                'root' => 0,
                'deep' => 0,
                'details' => $request->details,
                '_celler' => $request->_celler
            ]);
        }

        return response()->json([
            'success' => true,
            'celler' => $section
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
                $product = $product = \App\Product::with(['locations' => function($query)use($cellers){
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
        $sections = \App\CellerSection::where('_celler', 1)->get();
        $roots = $sections->filter(function($section){
            return $section->root == 0;
        })->map(function($section) use ($sections){
            $section->children = $this->getChildren($sections, $section->id);
            return $section;
        });
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
}
