<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
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
            '_workpoint' => $request->_workpoint,
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
        $section = \App\CellerSection::create([
            'name' => $request->name,
            'alias' => $request->alias,
            'path' => $request->path,
            'root' => $request->root,
            'deep' => $request->deep,
            'details' => $request->details,
            '_celler' => $request->_celler
        ]);

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
        $payload = Auth::payload();
        $workpoint = $payload['workpoiny']->_workpoint;
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
        $product = \App\Product::with('locations', 'category', 'status', 'units')->where('code', $code)->orWhere('name', $code)->first();
        if($product){
            //$product->stock = AccessController::getStock($product->code);
            $access = AccessController::getMinMax($product->code);
            $product->stock = intval($access['ACTSTO']);
            $product->min = intval($access['MINSTO']);
            $product->max = intval($access['MAXSTO']);
            return response()->json($product);
        }else{
            $product = \App\ProductVariant::where('barcode', $code)->first()->product;
            $product = $product->fresh('locations', 'category', 'status', 'units');
            $access = AccessController::getMinMax($product->code);
            $product->stock = intval($access['ACTSTO']);
            $product->min = intval($access['MINSTO']);
            $product->max = intval($access['MAXSTO']);
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
        $res = AccessController::setMinMax($request->code, $request->min, $request->max); 
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
        $productsWithoutLocation = \App\Product::has('locations', '=', 0)->select('id','code', 'description')->get();
        $productsWithLocation = \App\Product::has('locations', '>', 0)->select('id','code', 'description')->get();

        $withStocks = AccessController::getProductWithStock();
        $withoutStocks = AccessController::getProductWithoutStock();

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
            "products" => $counterProducts
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

}
