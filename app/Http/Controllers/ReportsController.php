<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Product;
use App\WorkPoint;

use App\Exports\ArrayExport;
use Maatwebsite\Excel\Facades\Excel;

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

    public function getReports(Request $request){
        switch($request->report){
            case "sinMaximos":
                $data = $this->sinMaximos();
                $namefile = 'sinMáximos_';
            break;
            case "sinUbicaciones":
                $data = $this->sinUbicaciones();
                $namefile = 'sinUbicaciones_';
            break;
            case "comparativoGeneralExhibicion":
                $data = $this->sinUbicaciones();
                $namefile = 'comparativo';
            break;
            case "comparativoCedisGeneral":
            break;
            case "comprasVsVentaProveedor":
            break;
            case "inventarioCedisPantaco":
            break;
            case "checkStocks":
                $data = $this->chechStocks($stores, $codes);
                $namefile = 'stocks_';
            break;
        }
        if($request->excel){
            $export = new StocksExport($data);
            $date = new \DateTime();
            return Excel::download($export, $namefile.$date.'.xlsx');
        }else{
            return response()->json($data);
        }
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

    public function chechStocks($stores, $codes){
        switch($stores){
            case "navidad": 
                $workpoints = WorkPoint::whereIn('id', [1,2,3,4,5,7,9])->get();
            break;
            case "juguete": 
                $workpoints = WorkPoint::whereIn('id', [1,2,6,8])->get();
            break;
            case "all":
                $workpoints = WorkPoint::all();
        }
        $products = Product::whereIn('code', $codes)->get();
        $codes = array_column($products->toArray(), 'code');
        $stocks = [];
        foreach($workpoints as $workpoint){
            $client = curl_init();
            curl_setopt($client, CURLOPT_URL, $workpoint->dominio."/access/public/product/stocks");
            curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($client, CURLOPT_POST, 1);
            curl_setopt($client,CURLOPT_TIMEOUT,90);
            $data = http_build_query(["products" => $codes]);
            curl_setopt($client, CURLOPT_POSTFIELDS, $data);
            $stocks[$workpoint->alias] = json_decode(curl_exec($client), true);
        }
        $result = $products->map(function($product, $key) use($stocks, $workpoints){
            $data = [
                'code' => $product->code,
                'scode' => $product->name,
                'descripción' => $product->description
            ];
            foreach($workpoints as $workpoint){
                if($stocks[$workpoint->alias]){
                    $data[$workpoint->alias] = $stocks[$workpoint->alias][$key]['stock'];
                }else{
                    $data[$workpoint->alias] = '--';
                }
            }
            return $data;
        })->toArray();

        return $result;
    }

    public function test(){
        $export = new StocksExport([
            [1,2,3],
            [4,5,6]
        ]);
        return Excel::download($export, 'invoices.xlsx');
    }
}
