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
            $client = curl_init();
            curl_setopt($client, CURLOPT_URL, "192.168.1.224:1618/access/public/product");
            curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
            $products = json_decode(curl_exec($client), true);
            curl_setopt($client, CURLOPT_URL, "192.168.1.224:1618/access/public/prices");
            $prices = collect(json_decode(curl_exec($client), true));
            curl_close($client);
            if($products && $prices){
                DB::transaction(function() use ($products, $prices){
                    //array prices
                    foreach (array_chunk($products, 1000) as $insert) {
                        $success = DB::table('products')->insert($insert);
                    }
                    $codes =  array_column($products, 'code');
                    $prices_insert = $prices->map(function($price) use($products, $codes){
                        $index_product = array_search($price['code'], $codes);
                        if($index_product == 0 || $index_product > 0){
                            return [
                                '_product' => $products[$index_product]['id'],
                                'price' => $price['price'],
                                '_type' => $price['_type']
                            ];
                        }
                    })->toArray();
                    foreach (array_chunk($prices_insert, 1000) as $insert) {
                        $success = DB::table('product_prices')->insert($insert);
                    }
                });
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
            $client = curl_init();
            curl_setopt($client, CURLOPT_URL, "192.168.1.224:1618/access/public/product/updates");
            curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
            $products = json_decode(curl_exec($client), true);
            return response()->json([
                "success" => true,
                "products" => count($products),
                "time" => microtime(true) - $start
            ]);
            DB::transaction(function() use ($products){
                foreach($products as $product){
                    $instance = Product::updateOrCreate([
                        'code'=> $product['code']
                    ], [
                        'name' => $product['name'],
                        'description' => $product['description'],
                        'pieces' => $product['pieces'],
                        '_category' => $product['_category'],
                        '_status' => $product['_status'],
                        '_provider' => $product['_provider'],
                        '_unit' => $product['_unit']
                    ]);
                    foreach($product['prices'] as $price){
                        $instance->prices()->updateOrCreate(['_type' => $price['_type'], 'price' => $price['price']]);
                    }
                }
                return response()->json([
                    "success" => true,
                    "products" => count($products),
                    "time" => microtime(true) - $start
                ]);
            });
        }catch(\Exception $e){
            return response()->json(["message" => "No se ha podido actualizar la tabla de productos"]);
        }
    }

}
