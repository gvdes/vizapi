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
use App\OrderSupply;
use App\Http\Controllers\FactusolController;
use App\Http\Controllers\AccessController;

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
        $schedule->call(function(){
            $_workpoints = range(3,13);
            $_workpoints[] = 1;
            $_workpoints[] = 17;
            $workpoints = WorkPoint::whereIn('id', $_workpoints)->get();
            $resumen = [];
            $start = microtime(true);
            $a = 0;
            $clientes = Client::all()->toArray();
            $ids_clients = array_column($clientes, 'id');
            foreach($workpoints as $workpoint){
                $cash_registers = CashRegister::where('_workpoint', $workpoint->id)->get();
                $caja_x_ticket = [];
                if(count($cash_registers)>0){
                    foreach($cash_registers as $cash){
                        $sale = Sales::where('_cash', $cash->id)->max('created_at');
                        if($sale){
                            $ticket = $sale ? : 0;
                            $date = explode(' ', $sale);
                            $caja_x_ticket[] = ["_cash" => $cash->num_cash, "num_ticket" => $ticket, "date" => $date[0], 'last_date' => $sale];
                        }
                    }
                    if(count($caja_x_ticket)>0){
                        $access = new AccessController($workpoint->dominio);
                        $ventas = $access->getLastSales($caja_x_ticket);
                        $resumen[$workpoint->alias] = [$caja_x_ticket];
                        if($ventas){
                            $a++;
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
                                    "name" => $venta['name'],
                                    "_seller" => $venta['_seller']
                                    ]);
                                    $insert = [];
                                    foreach($venta['body'] as $row){
                                        $index = array_search($row['_product'], $codes, true);
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
        })->everyFiveMinutes()->between('9:00', '19:00');

        $schedule->call(function(){
            $workpoints = WorkPoint::whereIn('id', [1,2,3,4,5,6,7,8,9,10,11,12,13,17])->get();
            foreach($workpoints as $workpoint){
                $access = new AccessController($workpoint->dominio);
                $stocks = $access->getStocks($workpoint->id);
                if($stocks){
                    $products = Product::with(["stocks" => function($query) use($workpoint){
                        $query->where('_workpoint', $workpoint->id);
                    }])->where('_status', '!=', 4)->get();
                    $codes_stocks = array_column($stocks, 'code');
                    foreach($products as $product){
                        $key = array_search($product->code, $codes_stocks, true);
                        if($key === 0 || $key > 0){
                            $gen = count($product->stocks)>0 ? $product->stocks[0]->pivot->gen : false;
                            $exh = count($product->stocks)>0 ? $product->stocks[0]->pivot->exh : false;
                            if(gettype($gen) == "boolean" || gettype($exh) == "boolean"){
                                $product->stocks()->attach($workpoint->id, ['stock' => $stocks[$key]["stock"], 'min' => 0, 'max' => 0, 'gen' => $stocks[$key]["gen"], 'exh' => $stocks[$key]["exh"]]);
                            }elseif($gen != $stocks[$key]["gen"] || $exh != $stocks[$key]["exh"]){
                                $product->stocks()->updateExistingPivot($workpoint->id, ['stock' => $stocks[$key]["stock"], 'gen' => $stocks[$key]["gen"], 'exh' => $stocks[$key]["exh"]]);
                            }
                        }
                    }
                }
            }
        })->everyThreeMinutes()->between('9:00', '22:00');
        /* SALIDAS */
        $schedule->call(function(){
            $CEDIS = \App\WorkPoint::find(1);
            $access = new AccessController($CEDIS->dominio);
            $series = range(1,9);
            $caja_x_ticket = [];
            foreach($series as $cash){
                $sale = OrderSupply::where('serie', $cash)->whereDate('created_at', '>' ,'2021-01-27')->max('num_ticket');
                if($sale){
                $caja_x_ticket[] = ["_cash" => $cash, "num_ticket" => $sale];
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
        })->hourly()->between('9:00', '22:00');
        /* Historico de stocks */
        $schedule->call(function(){
            $products = Product::whereHas('stocks')->with('stocks')->get();
            $stocks = $products->map(function($product){
                $a = $product->stocks->unique('id')->values()->map(function($stock){
                    return $stock->pivot;
                });
                $a->created_at = date("Y/m/d h:m");
                return $a;
            })->toArray();
            $insert = array_merge(...$stocks);
            foreach(array_chunk($insert, 1000) as $toInsert){
                DB::table('stock_history')->insert($toInsert);
            }
        })->dailyAt('22:00');;
    }
}
