<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Concept;

class AccountingController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public $account = null;
    public function __construct(){

    }

    public function updateConcepts(Request $request){
        $clouster = \App\WorkPoint::find(1);
        $access_clouster = new AccessController($clouster->dominio);
        $concepts = $access_clouster->getConcepts();
        if($concepts){
            DB::transaction(function() use ($concepts){
                foreach($concepts as $concept){
                    $instance = Concept::firstOrCreate([
                        'id' => $concept['id']
                    ], [
                        'name' => $concept['name'],
                        'alias' => $concept['alias']
                    ]);
                    $instance->name = $concept['name'];
                    $instance->alias = $concept['alias'];
                    $instance->save();
                }
            });
            return response()->json(["msg" => "Successful"]);
        }
        return response()->json(["message" => "No se obtuvo respuesta del servidor de factusol"]);
    }

    public function seederGastos(Request $request){
        $clouster = \App\WorkPoint::find(1);
        $access_clouster = new AccessController($clouster->dominio);
        $gastos = $access_clouster->getAllGastos();
        if($gastos){
            DB::transaction(function() use ($gastos){
                DB::transaction(function() use ($gastos){
                    foreach (array_chunk($gastos, 1000) as $insert) {
                        $success = DB::table('gastos')->insert($insert);
                    }
                });
            });
            return response()->json(["msg" => "Successful"]);
        }
        return response()->json(["message" => "No se obtuvo respuesta del servidor de factusol"]);
    }

    public function getAllOrders(){
        $start = microtime(true);
        $CEDIS = \App\WorkPoint::find(1);
        $access = new AccessController($CEDIS->dominio);
        $orders = $access->getAllProviderOrders();

        $products = \App\Product::all()->toArray();
        $variants = \App\ProductVariant::all()->toArray();
        $codes = array_column($products, 'code');
        $ids_products = array_column($products, 'id');
        $related_codes = array_column($variants, 'barcode');

        DB::transaction(function() use($orders, $codes, $products, $related_codes, $variants, $ids_products){
            foreach($orders as $order){
                $instance = \App\ProviderOrder::create([
                    "serie" => $order["serie"],
                    "code" => $order["code"],
                    "ref" => $order["ref"],
                    "_provider" => $order["_provider"],
                    "_status" => $order["_status"],
                    "description" => $order["description"],
                    "total" => $order["total"],
                    "received_at" => $order["received_at"],
                    "created_at" => $order["created_at"]
                    
                ]);
                $insert = [];
                foreach($order['body'] as $row){
                    $index = array_search($row["_product"], $codes);
                    if($index === 0 || $index > 0){
                        if(array_key_exists($products[$index]['id'], $insert)){
                            $amount = $row['amount'] + $insert[$products[$index]['id']]['amount'];
                            $total = $row['total'] + $insert[$products[$index]['id']]['total'];
                            $price = $total / $amount;
                            $insert[$products[$index]['id']] = [
                                "amount" => $amount,
                                "price" => $price,
                                "total" => $total
                            ];
                            }else{
                                $insert[$products[$index]['id']] = [
                                    "amount" => $row['amount'],
                                    "price" => $row['price'],
                                    "total" => $row['total']
                                ];
                            }
                    }else{
                        $index = array_search($row['_product'], $related_codes);
                        if($index === 0 || $index > 0){
                            $key = array_search($variants[$index]['_product'], $ids_products);
                            if(array_key_exists($variants[$index]['_product'], $insert)){
                                $insert[$variants[$index]['_product']] = [
                                    "amount" => $amount,
                                    "price" => $price,
                                    "total" => $total
                                ];
                            }else{
                                $insert[$variants[$index]['_product']] = [
                                    "amount" => $row['amount'],
                                    "price" => $row['price'],
                                    "total" => $row['total']
                                ];
                            }
                        }
                    }
                }
                $instance->products()->attach($insert);
            }
        });
    }
}
