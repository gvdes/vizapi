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
        if($celler && !$section){
            $sections = \App\CellerSection::where([
                ['_celler', '=' ,$celler],
                ['deep', '=' ,0],
            ])->get();
            $res = $sections->map(function($section){
                $section->sections = \App\CellerSection::where('root', $section->id)->get();
                return $section;
            });
        }else{
            $section->sections = \App\CellerSection::where('root', $section->id)->get();
            $res = $section;
        }
        return response()->json($res);
    }

    /**
     * Get cellers in workpoint
     */
    public function getCellers(){
        $workpoint = env('ID_ENV');
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
                'msg' => 'No ha indicado ningun punto de trabajo'
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
            $product->stock = AccessController::getStock($product->code);
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
}
