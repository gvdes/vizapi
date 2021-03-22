<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use App\WorkPoint;
use App\Product;
use App\CashRegister;
use App\Sales;
use App\PaidMethod;
use App\Client;
use App\Http\Controllers\FactusolController;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule){
        /* $schedule->call(function(){
            $workpoints = WorkPoint::where('_type', 2)->get();
            $clientes = Client::all()->toArray();
            $ids_clients = array_column($clientes, 'id');
            foreach($workpoints as $workpoint){
                $cash_registers = CashRegister::where('_workpoint', $workpoint->id)->get();
                $caja_x_ticket = [];
                if(count($cash_registers)>0){
                    foreach($cash_registers as $cash){
                        //OBTENER AÑO
                        $sale = Sales::where('_cash', $cash->id)->whereYear('created_at', '2021')->max('num_ticket');
                        if($sale){
                            array_push($caja_x_ticket, ["_cash" => $cash->num_cash, "num_ticket" => $sale]);
                        }
                    }
                    if(count($caja_x_ticket)>0){
                        $client = curl_init();
                        curl_setopt($client, CURLOPT_URL, $workpoint->dominio."/access/public/ventas/new");
                        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
                        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($client, CURLOPT_POST, 1);  
                        curl_setopt($client,CURLOPT_TIMEOUT,10);
                        $data = http_build_query(["cash" => $caja_x_ticket]);
                        curl_setopt($client, CURLOPT_POSTFIELDS, $data);
                        $ventas = json_decode(curl_exec($client), true);
                        curl_close($client);
                        if($ventas){
                            $products = Product::all()->toArray();
                            $codes = array_column($products, 'code');
                            $cajas = array_column($cash_registers->toArray(), 'num_cash');
                            DB::transaction(function() use ($ventas, $codes, $products, $cajas, $cash_registers, $ids_clients){
                                foreach($ventas as $venta){
                                    $index_caja = array_search($venta['_cash'], $cajas);
                                    $instance = Sales::create([
                                        "num_ticket" => $venta['num_ticket'],
                                        "_cash" => $cash_registers[$index_caja]['id'],
                                        "total" => $venta['total'],
                                        "created_at" => $venta['created_at'],
                                        "_client" => (array_search($venta['_client'], $ids_clients) > 0 || array_search($venta['_client'], $ids_clients) === 0) ? $venta['_client'] : 3,
                                        "_paid_by" => $venta['_paid_by'],
                                        "name" => $venta['name']
                                    ]);
                                    $insert = [];
                                    foreach($venta['body'] as $row){
                                        $index = array_search($row['_product'], $codes);
                                        if($index === 0 || $index > 0){  
                                            $instance->products()->attach($products[$index]['id'], [
                                            "amount" => $row['amount'],
                                            "price" => $row['price'],
                                            "total" => $row['total'],
                                            "costo" => $row['costo']
                                            ]);
                                        }
                                    }
                                }
                            });
                        }
                    }
                }
            }
        })->everyFiveMinutes(); */

        $schedule->call(function(){
            $clientes = Client::all()->toArray();
            $ids_clients = array_column($clientes, 'id');
            $cash_registers = CashRegister::all()->groupBy('_workpoint')->toArray();
            $series = range(1,9);
            $ventas = [];
            $products = Product::all()->toArray();
            $codes = array_column($products, 'code');
            $cash__ =[];
            foreach($series as $serie){
                $sale = Sales::whereDate('created_at', '>','2021-01-10')->where("serie", $serie)->max('num_ticket');
                if(!$sale){
                    $sale = 0;
                }
                $cash__[$serie] = $sale;
                $fac = new FactusolController();
                $fac_sales = $fac->getSales($sale, $serie);
                if($fac_sales){
                    foreach($fac_sales as $venta){
                        $arr_cajas = array_column($cash_registers[$venta['_workpoint']], "num_cash");
                        $key = array_search($venta["_cash"], $arr_cajas);
                        $_cash = ($key == 0 || $key>0) ? $cash_registers[$venta['_workpoint']][$key]['id'] : $cash_registers[$venta['_workpoint']][0]['id'];
                        $instance = Sales::create([
                            "num_ticket" => $venta['num_ticket'],
                            "_cash" => $_cash/* $cash_registers[$venta['_workpoint']][0]['id'] */,
                            "total" => $venta['total'],
                            "created_at" => $venta['created_at'], 
                            "_client" => (array_search($venta['_client'], $ids_clients) > 0 || array_search($venta['_client'], $ids_clients) === 0) ? $venta['_client'] : 3,
                            "_paid_by" => $venta['_paid_by'],
                            "name" => $venta['name'],
                            "serie" => $venta['serie']
                        ]);
                        $toAttach = [];
                        foreach($venta['body'] as $row){
                            $index = array_search($row['_product'], $codes);
                            if($index === 0 || $index > 0){
                                $toAttach[$products[$index]['id']] = [
                                "amount" => $row['amount'],
                                "price" => $row['price'],
                                "total" => $row['total'],
                                "costo" => $row['costo']
                                ];
                            }
                        }
                        $instance->products()->attach($toAttach);
                    }
                }
            }
        })->everyTwoMinutes();

        $schedule->call(function(){
            $workpoints = WorkPoint::whereIn('id', [1,3,4,5,6,7,8,9,10,11,12,13,14,15])->get();
            $success = 0;
            $_success = [];
            $fac = new FactusolController();
            foreach($workpoints as $workpoint){
                $stocks = $fac->getStocks($workpoint->id);
                if($stocks){
                    $success++;
                    array_push($_success, $workpoint->alias);
                    $products = Product::with(["stocks" => function($query) use($workpoint){
                        $query->where('_workpoint', $workpoint->id);
                    }])->where('_status', 1)->whereNotIn('_category', range(130,172))->get();
                    $codes_stocks = array_column($stocks, 'code');
                    foreach($products as $product){
                        $key = array_search($product->code, $codes_stocks, true);
                        if($key === 0 || $key > 0){
                            $stock = count($product->stocks)>0 ? $product->stocks[0]->pivot->stock : false;
                            if(gettype($stock) == "boolean"){
                                $product->stocks()->attach($workpoint->id, ['stock' => $stocks[$key]["stock"], 'min' => 0, 'max' => 0, 'gen' => $stocks[$key]["gen"], 'exh' => $stocks[$key]["exh"]]);
                            }elseif($stock != $stocks[$key]["stock"]){
                                $product->stocks()->updateExistingPivot($workpoint->id, ['stock' => $stocks[$key]["stock"], 'gen' => $stocks[$key]["gen"], 'exh' => $stocks[$key]["exh"]]);
                            }
                        }
                    }
                }
            }
            return response()->json(["completados" => $success, "tiendas" => $_success]);
        })->everyFourMinutes();
    }
}
