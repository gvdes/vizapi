<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Provider;
use App\Exports\WithMultipleSheetsExport;
use Maatwebsite\Excel\Facades\Excel;

class InvoicesReceivedController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public $account = null;
    public function __construct(){
        $this->account = Auth::payload()['workpoint'];
    }

    public function getAllOrders(){
        $start = microtime(true);
        $CEDIS = \App\WorkPoint::find(1);
        $access = new AccessController($CEDIS->dominio);
        $orders = $access->getAllInvoicesReceived();

        $products = \App\Product::all()->toArray();
        $variants = \App\ProductVariant::all()->toArray();
        $codes = array_column($products, 'code');
        $ids_products = array_column($products, 'id');
        $related_codes = array_column($variants, 'barcode');

        DB::transaction(function() use($orders, $codes, $products, $related_codes, $variants, $ids_products){
            foreach($orders as $order){
                $relationship = \App\ProviderOrder::where([["serie", $order["_serie_order"]], ["code", $order["_code_order"]]])->first();
                $_order = $relationship ? $relationship->id : null;
                $instance = \App\Invoice::create([
                    "serie" => $order["serie"],
                    "code" => $order["code"],
                    "ref" => $order["ref"],
                    "_provider" => $order["_provider"],
                    "description" => $order["description"],
                    "total" => $order["total"],
                    "created_at" => $order["created_at"],
                    "_order" => $_order
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
