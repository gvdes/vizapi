<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\OrderSupply;
use App\Product;
use Illuminate\Support\Facades\DB;

class SalidasController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
    }

    /**
     * Create team for support group
     * @param object request
     * @param string request[].name
     * @param string request[].icon - null
     * * @param array request[].members - null
     */
    public function seederSalidas(){
        $access = new AccessController("localhost");
        $salidas = $access->getSalidas();
        if($salidas){
            $products = Product::all()->toArray();
            $variants = \App\ProductVariant::all()->toArray();
            $codes = array_column($products, 'code');
            $ids_products = array_column($products, 'id');
            $related_codes = array_column($variants, 'barcode');
            DB::transaction(function() use($salidas, $codes, $products, $related_codes, $variants, $ids_products){
                foreach($salidas as $salida){
                    $instance = OrderSupply::create([
                        "serie" => $salida['serie'],
                        "num_ticket" => $salida['num_ticket'],
                        "name" => $salida["name"],
                        "_workpoint_from" => $salida["_workpoint_from"],
                        "_workpoint_to" => $salida["_workpoint_to"],
                        "created_at" => $salida["created_at"],
                        "_requisition" => $salida["_requisition"],
                        "ref" => intval($salida["ref"]),
                        "total" => $salida["total"]
                    ]);
                    $insert = [];
                    foreach($salida['body'] as $row){
                        $index = array_search($row["_product"], $codes);
                        if($index === 0 || $index > 0){
                            $costo = ($row['costo'] == 0 || $row['costo'] > $products[$index]['cost']) ? $products[$index]['cost'] : $row['costo'];
                            if(array_key_exists($products[$index]['id'], $insert)){
                                $amount = $row['amount'] + $insert[$products[$index]['id']]['amount'];
                                $total = $row['total'] + $insert[$products[$index]['id']]['total'];
                                $price = $total / $amount;
                                $insert[$products[$index]['id']] = [
                                    "amount" => $amount,
                                    "price" => $price,
                                    "total" => $total,
                                    "costo" => $costo
                                ];
                                }else{
                                    $insert[$products[$index]['id']] = [
                                        "amount" => $row['amount'],
                                        "price" => $row['price'],
                                        "total" => $row['total'],
                                        "costo" => $costo
                                    ];
                                }
                        }else{
                            $index = array_search($row['_product'], $related_codes);
                            if($index === 0 || $index > 0){
                                $key = array_search($variants[$index]['_product'], $ids_products);
                                $costo = ($row['costo'] == 0 || $row['costo'] > $products[$key]['cost']) ? $products[$key]['cost'] : $row['costo'];
                                if(array_key_exists($variants[$index]['_product'], $insert)){
                                    $insert[$variants[$index]['_product']] = [
                                        "amount" => $amount,
                                        "price" => $price,
                                        "total" => $total,
                                        "costo" => $costo
                                    ];
                                }else{
                                    $insert[$variants[$index]['_product']] = [
                                        "amount" => $row['amount'],
                                        "price" => $row['price'],
                                        "total" => $row['total'],
                                        "costo" => $costo
                                    ];
                                }
                            }
                        }
                    }
                    $instance->products()->attach($insert);
                }
            });
        }
        return response()->json(count($salidas));
    }
}