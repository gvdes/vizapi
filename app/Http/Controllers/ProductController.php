<?php

namespace App\Http\Controllers;

use App\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

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
            $workpoint = \App\WorkPoint::find($this->account->_workpoint);
            $client = curl_init();
            curl_setopt($client, CURLOPT_URL, $workpoint->dominio."access/public/product");
            /* curl_setopt($client, CURLOPT_URL, "192.168.1.24/access/public/product"); */
            curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
            $products = json_decode(curl_exec($client));
            curl_setopt($client, CURLOPT_URL, "192.168.1.24/access/public/prices");
            $prices = json_decode(curl_exec($client));
            curl_close($client);
            $ids = [];
            if($products && $prices){
                foreach($products as $product){
                    $id[$product['code']] = Product::insertGetId($product);
                }
                //array prices
                $prices_insert = $prices->map(function($price) use ($id){
                    $price->_product = $id[$code];
                    return $price;
                })->filter(function($price){
                    return $price!=null;
                })->toArray();
                foreach (array_chunk($prices_insert, 1000) as $insert) {
                    $success = DB::table('product_prices')->insert($insert);
                }
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

    public function updateTable(Request $request){
        try{
            $start = microtime(true);
            $workpoint = \App\WorkPoint::find($this->account->_workpoint);
            $client = curl_init();
            /* curl_setopt($client, CURLOPT_URL, $workpoint->dominio."access/public/product/updates"); */
            curl_setopt($client, CURLOPT_URL, "192.168.1.24/access/public/product/updates");
            curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
            $products = json_decode(curl_exec($client));
            return response()->json($products);
        }catch(\Exception $e){
            return response()->json(["message" => "No se ha podido actualizar la tabla de productos"]);
        }
    }

}
