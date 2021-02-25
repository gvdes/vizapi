<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Product;
use App\WorkPoint;
use App\CellerSection;
use App\Celler;

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
        $this->account = Auth::payload()['workpoint'];
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
                return response()->json($data);
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

    public function sinMaximos(){
        $ids_categories = array_merge(range(37,57), range(130,184)); //calcular
        $workpoint = WorkPoint::find($this->account->_workpoint);
        $products = Product::with(['category', 'stocks' => function($query){
            $query->where('_workpoint', $this->account->_workpoint);
        }])->where('_status', 1)->whereIn('_category', $ids_categories)->whereHas('stocks', function($query){
            $query->where([
                ['_workpoint', $this->account->_workpoint],
                ['min', '<=', 0],
                ['max', '<=', 0]
            ]);
        })->get();
        $res = [];
        foreach (array_chunk($products->toArray(), 2000) as $key => $part) {
            $codes = array_column($part , 'code');
            $client = curl_init();
            curl_setopt($client, CURLOPT_URL, $workpoint->dominio."/access/public/product/stocks");
            curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($client, CURLOPT_POST, 1);
            curl_setopt($client,CURLOPT_TIMEOUT, 20);
            $data = http_build_query(["products" => $codes]);
            curl_setopt($client, CURLOPT_POSTFIELDS, $data);
            $stocks = json_decode(curl_exec($client), true);
            if($stocks){
                $res = array_merge($res, $stocks);
            }else{
                return response()->json(["msg"=> "No se han podido obtener todos los stocks"]);
            }
        }
        $p = $products->map(function($product, $key) use($res){
            return [
                "code" => $product->code,
                "scode" => $product->name,
                "category" => $product->category->name,
                "description" => $product->description,
                "pieces" => $product->pieces,
                "stock" => $res[$key]['stock'],
                "min" => strval($product->stocks[0]->pivot->max),
                "max" => strval($product->stocks[0]->pivot->max),
            ];
        })->filter(function($product){
            return $product['stock']>0;
        })->values()->all();
        return response()->json($p);
        $export = new ArrayExport($p);
        $date = new \DateTime();
        return Excel::download($export, "sinMaximos.xlsx");
    }

    public function sinUbicaciones(){
        $workpoint = WorkPoint::find($this->account->_workpoint);
        $celler = Celler::with('sections')->where([
            ['_workpoint', $this->account->_workpoint],
            ['_type', 1]
            ])->first();
        $ids_sections = array_column($celler->sections->toArray(), 'id');
        $ids_categories = array_merge(range(37,57), range(130,184)); //calcular
        $products = Product::where('_status', 1)->whereIn('_category', $ids_categories)->whereHas('locations', function($query) use($ids_sections){
            $query->whereIn('_location', $ids_sections);
        },'<=',0)->get();
        
        $res = [];
        foreach (array_chunk($products->toArray(), 2000) as $key => $part) {
            $codes = array_column($part , 'code');
            $client = curl_init();
            curl_setopt($client, CURLOPT_URL, $workpoint->dominio."/access/public/product/stocks");
            curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($client, CURLOPT_POST, 1);
            curl_setopt($client,CURLOPT_TIMEOUT, 20);
            $data = http_build_query(["products" => $codes]);
            curl_setopt($client, CURLOPT_POSTFIELDS, $data);
            $stocks = json_decode(curl_exec($client), true);
            if($stocks){
                $res = array_merge($res, $stocks);
            }else{
                return response()->json(["msg"=> "No se han podido obtener todos los stocks"]);
            }
        }
        $p = $products->map(function($product, $key) use($res){
            return [
                "code" => $product->code,
                "scode" => $product->name,
                "category" => $product->category->name,
                "description" => $product->description,
                "pieces" => $product->pieces,
                "stock" => $res[$key]['stock'],
                "location"=> "",
            ];
        })->filter(function($product){
            return $product['stock']>0;
        })->values()->all();
        return response()->json($p);
        $export = new ArrayExport($p);
        $date = new \DateTime();
        return Excel::download($export, "sinUbicaciones.xlsx");
    }

    public function comparativoGeneralExhibicion(){
        $ids_categories = array_merge(range(37,57), range(130,184)); //calcular
        $products = Product::whereIn('_category', $ids_categories)->get();
        /* $workpoint = WorkPoint::find($this->account->_workpoint);
        foreach (array_chunk($products->toArray(), 2000) as $key => $part) {
            $codes = array_column($part , 'code');
            $client = curl_init();
            curl_setopt($client, CURLOPT_URL, $workpoint->dominio."/access/public/product/allStocks");
            curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($client, CURLOPT_POST, 1);
            curl_setopt($client,CURLOPT_TIMEOUT, 20);
            $data = http_build_query(["products" => $codes]);
            curl_setopt($client, CURLOPT_POSTFIELDS, $data);
            $stocks = json_decode(curl_exec($client), true);
            if($stocks){
                $result = $products->map(function($product, $key) use($stocks){
                    return [
                        'code' => $product->name,
                        'scode' => $product->code,
                        'description' => $product->description,
                        'general' => $stocks[$key]['stock_general'],
                        'exhibición' => $stocks[$key]['stock_exhibicion']
                    ];
                });
                return $result;
            }else{
                return response()->json(["msg"=> "No se han podido obtener todos los stocks"]);
            }
        } */
        $result = $products->map(function($product){
            return [
                "code" => $product->code,
                "scode" => $product->name,
                "category" => $product->category->name,
                "description" => $product->description,
                "pieces" => $product->pieces,
                "stock_general" => rand(-10,200)/* $res[$key]['stock_general'] */,
                "stock_exhibicion" => rand(-10,200)/* $res[$key]['stock_exhibicion'] */,
            ];
        })->filter(function($product){
            return $product["stock_general"]>0 && $product["stock_exhibicion"]<=0;
        });
        $export = new ArrayExport($result);
        $date = new \DateTime();
        return Excel::download($export, "exhibicionVsGeneral.xlsx");
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

    public function chechStocks(Request $request){
        $stores = "all";
        $workpoints = WorkPoint::whereIn('id', $request->_workpoints)->get();
        $codes = array_column($request->products, 'code');
        $products = Product::whereIn('code', $codes)->get();
        $stocks = [];
        foreach($workpoints as $workpoint){
            $client = curl_init();
            curl_setopt($client, CURLOPT_URL, $workpoint->dominio."/access/public/product/stocks");
            curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($client, CURLOPT_POST, 1);
            curl_setopt($client,CURLOPT_TIMEOUT,30);
            $data = http_build_query(["products" => $codes]);
            curl_setopt($client, CURLOPT_POSTFIELDS, $data);
            $stocks[$workpoint->alias] = json_decode(curl_exec($client), true);
        }
        $result = $products->map(function($product, $key) use($stocks, $workpoints){
            $data = [
                'code' => $product['code'],
                'scode' => $product->name,
                'pieces' => $product->pieces,
                'category'=> $product->category->name,
                'descripción' => $product->description
            ];
            foreach($workpoints as $workpoint){
                if($stocks[$workpoint->alias]){
                    $arr_provicional = array_column($stocks[$workpoint->alias],'code');
                    $key_pro = array_search($data['code'], $arr_provicional);
                    $data[$workpoint->alias] = $stocks[$workpoint->alias][$key_pro]['stock'];
                    $min = "min".$workpoint->alias;
                    $max = "max".$workpoint->alias;
                    $data[$min] = $stocks[$workpoint->alias][$key_pro]['min'];
                    $data[$max] = $stocks[$workpoint->alias][$key_pro]['max'];
                }else{
                    $data[$workpoint->alias] = '--';
                }
            }
            return $data;
        })->toArray();

        return $result;
    }

    public function test(Request $request){
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

    public function ventas(Request $request){
        /* $models = array_column($request->products, "modelo");
        $products = Product::with('prices')->whereIn('code', $models)->get();
        return response()->json($products); */
        $products = collect($request->products);
        $products2 = $request->products;
        /* $codes = array_column($products->toArray(), 'code'); */
        $workpoints = WorkPoint::whereIn('id', [1,3,4,5,7,9])->get();
        $stocks = [];
        foreach($workpoints as $workpoint){
            $client = curl_init();
            curl_setopt($client, CURLOPT_URL, $workpoint->dominio."/access/public/ventas");
            curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($client, CURLOPT_POST, 1);
            curl_setopt($client,CURLOPT_TIMEOUT,200);
            $data = http_build_query(["codes" => $products2]);
            curl_setopt($client, CURLOPT_POSTFIELDS, $data);
            $ventas[$workpoint->alias] = json_decode(curl_exec($client), true);
        }
        $result = $products->map(function($product, $key) use($ventas, $workpoints){
            /* $data = [
                'code' => $product->code,
                'scode' => $product->name,
                'pieces' => $product->pieces,
                'category'=> $product->category->name,
                'descripción' => $product->description
            ]; */
            foreach($workpoints as $workpoint){
                if($ventas[$workpoint->alias]){
                    $product[$workpoint->alias] = $ventas[$workpoint->alias][$key]['total'];
                    /* $min = "min".$workpoint->alias;
                    $max = "max".$workpoint->alias;
                    $data[$min] = $stocks[$workpoint->alias][$key]['min'];
                    $data[$max] = $stocks[$workpoint->alias][$key]['max']; */
                }else{
                    $product[$workpoint->alias] = '--';
                }
            }
            return $product;
        })->toArray();

        return $result;
    }
}
