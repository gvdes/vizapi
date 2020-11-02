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
                return $product->min<= 0 || $product->max<=0;
            });
            return response()->json($result);
        }
        return response()->json([
            "msg" => "No se han podido obtener los maximos"
        ]);
    }

    public function comparativoGeneralExhibicion(){

    }

    public function comparativoCedisGeneral(){

    }

    public function comprasVsVentasProveedor(){

    }

    public function inventarioCedisPantaco(){

    }
}
