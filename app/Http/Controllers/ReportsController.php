<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class ReportsController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public $account = null;
    public function __construct(){
        /* $this->account = Auth::payload()['workpoint']; */
    }

    public function sinMaximos(Request $request){
        $ids_categories = []; //calcular
        $products = Product::whereIn('_category', $ids_categories)->get();
        $workpoint = WorkPoint::find($this->account->_workpoint);
        $codes = array_column($products->toArray(), 'code');
        $stocks = false;
        if($stocks){
            $result = $products->map(function($product) use($stocks){
                $product->stock = $stocks[$key]['stock'];
                $product->min = $stocks[$key]['min'];
                $product->max = $stocks[$key]['max'];
                return $product;
            })->filter(function($product){
                return $product->min<= 0 || $product->max<=0;
            });
            return response()->json($result);
        }
        return response()->json([
            "msg" => "No se han podido obtener los maximos"
        ]);
    }

    public function sinUbicaciones(Request $request){
        $sections = CellerSection::where('_workpoint', $this->account->_workpoint)->get();
        $ids_sections = array_column($sections->toArray(), 'id');
        if($request->_category){
            $ids_categories = []; //calcular
            
            $products = Product::whereIn('_category', $ids_categories)->whereHas('locations', function(Builder $query) use($ids_sections){
                $query->whereIn('_location', $ids_sections);
            },'<=',0)->get();
        }
        $products = Product::whereHas('locations', function(Builder $query) use($ids_sections){
            $query->whereIn('_location', $ids_sections);
        },'<=',0)->get();
        $codes = array_column($products->toArray(), 'code');
        $stocks = false;
        if($stocks){
            $result = $products->map(function($product) use($stocks){
                $product->stock = $stocks[$key]['stock'];
                $product->min = $stocks[$key]['min'];
                $product->max = $stocks[$key]['max'];
                return $product;
            })->filter(function($product){
                return $product->stock > 0;
            });
            return response()->json($result);
        }
        return response()->json([
            "msg" => "No se han podido obtener los maximos"
        ]);
    }

    public function comparativoGeneralExhibicion(){
        $ids_categories = []; //calcular
        $products = Product::whereIn('_category', $ids_categories)->get();
        $workpoint = WorkPoint::find($this->account->_workpoint);
        $codes = array_column($products->toArray(), 'code');
        $stocks = false;
        if($stocks){
            $result = $products->map(function($product, $key) use($stocks){
                return [
                    'code' => $product->name,
                    'scode' => $product->code,
                    'description' => $product->description,
                    'general' => $stocks_cedis[$key]['stock_general'],
                    'exhibición' => $stocks_store[$key]['stock_exhibicion']
                ];
            });
            return $result;
        }
        return response()->json(['msg' => 'No se ha obtenido conexión a las tiendas']);
    }

    public function comparativoCedisGeneral(){
        $ids_categories = []; //calcular
        $products = Product::whereIn('_category', $ids_categories)->get();
        $workpoint = WorkPoint::find($this->account->_workpoint);
        $codes = array_column($products->toArray(), 'code');
        $stocks_cedis = false;
        $stocks_store = false;
        if($stocks_cedis && $stocks_store){
            $result = $products->map(function($product, $key) use($stocks_cedis, $stocks_store, $workpoint){
                return [
                    'code' => $product->name,
                    'scode' => $product->code,
                    'description' => $product->description,
                    'CEDISSP' => $stocks_cedis[$key]['stock'],
                    $workpoint->alias => $stocks_store[$key]['stock']
                ];
            });
            return $result;
        }
        return response()->json(['msg' => 'No se ha obtenido conexión a las tiendas']);
    }

    public function comprasVsVentasProveedor(Request $request){
        $workpoints = WorkPoint::whereIn($request->stores);
        if($request->products){
            $products = Product::whereIn($request->products);
            $codes = array_column($product->toArray(), 'code');
        }else if($request->codes){
            $codes = $request->codes;
        }
    }

    public function inventarioCedisPantaco(){

    }
}
