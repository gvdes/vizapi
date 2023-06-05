<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\OrderSupply;
use App\OrderSupplied;
use App\Product;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
        $access = new AccessController("192.168.10.3:1618");
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

    public function LastSalidas(){
        $CEDIS = \App\WorkPoint::find(1);
        $access = new AccessController($CEDIS->dominio);
        $series = range(1,9);
        $caja_x_ticket = [];
        foreach($series as $cash){
            $sale = OrderSupply::where('serie', $cash)->whereDate('created_at', '>' ,'2021-01-27')->max('created_at');
            if($sale){
                $date = explode(' ', $sale);
                $caja_x_ticket[] = ["_cash" => $cash, "created_at" => $sale, "date" => $date[0], "hour" => $date[1]];
            }else{
                $caja_x_ticket[] = ["_cash" => $cash, "created_at" => "2021-01-27 00:00:00", "date" => "2021-01-27", "hour" => "00:00:00"];
            }
        }
        $salidas = $access->getLastSalidas($caja_x_ticket);
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

    public function seederEntradas(){
        $workpoints = \App\WorkPoint::where([['active', true], ['id', '!=', 2]])->get();
        $final = [];
        foreach($workpoints as $workpoint){
            $access = new AccessController($workpoint->dominio);
            $entradas = $access->getEntradas($workpoint->id);
            if($entradas){
                $products = Product::where('_status', '!=', 4)->get()->toArray();
                $variants = \App\ProductVariant::all()->toArray();
                $codes = array_column($products, 'code');
                $ids_products = array_column($products, 'id');
                $related_codes = array_column($variants, 'barcode');
                DB::transaction(function() use($entradas, $codes, $products, $related_codes, $variants, $ids_products){
                    foreach($entradas as $row){
                        $instance = OrderSupplied::create([
                            "serie" => $row['serie'],
                            "num_ticket" => $row['num_ticket'],
                            "name" => $row["name"],
                            "reference" => intval($row["reference"]),
                            "_workpoint" => $row["_workpoint"],
                            "_workpoint_from" => $row["_workpoint_from"],
                            "created_at" => $row["created_at"],
                            "total" => $row["total"],
                            "folio_fac" => $row["folio_fac"],
                            "serie_fac" => $row["serie_fac"]
                        ]);
                        $insert = [];
                        foreach($row['body'] as $row){
                            $index = array_search($row["_product"], $codes);
                            if($index === 0 || $index > 0){
                                if(array_key_exists($products[$index]['id'], $insert)){
                                    $amount = $row['amount'] + $insert[$products[$index]['id']]['amount'];
                                    $total = $row['total'] + $insert[$products[$index]['id']]['total'];
                                    $price = $total / $amount;
                                    $insert[$products[$index]['id']] = [
                                        "amount" => $amount,
                                        "price" => $price,
                                        "total" => $total,
                                    ];
                                    }else{
                                        $insert[$products[$index]['id']] = [
                                            "amount" => $row['amount'],
                                            "price" => $row['price'],
                                            "total" => $row['total'],
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
                                            "total" => $total,
                                        ];
                                    }else{
                                        $insert[$variants[$index]['_product']] = [
                                            "amount" => $row['amount'],
                                            "price" => $row['price'],
                                            "total" => $row['total'],
                                        ];
                                    }
                                }
                            }
                        }
                        $instance->products()->attach($insert);
                    }
                });
                $final[] = [
                    "sucursal" => $workpoint->alias,
                    "entradas" => count($entradas)
                ];
            }
        }
        return response()->json($final);
    }

    public function missingPrint(){
        $date = Carbon::now()->format('Y-m-d');

        $pedidos = DB::table('requisition')
        ->whereDate('created_at',$date)
        ->where('printed',0)
        // ->where('_status',2)
        ->get();

        if(count($pedidos) == 0){
            return response()->json("no hay brou");
        }else {
            foreach($pedidos as $pedido){
                $ped[] = $pedido;
                }
                return $ped;
            $req = implode(", ",$ped);
            $msg = "No se han impreso los pedidos ".$req;
            $nme = "120363157493041484@g.us";
            $this->sendWhatsapp($nme,$msg);
        }

    }
}
