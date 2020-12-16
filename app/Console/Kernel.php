<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;

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
            $workpoints = WorkPoint::where('_type', 2)->get();
            $clientes = Client::all()->toArray();
            $ids_clients = array_column($clientes, 'id');
            foreach($workpoints as $workpoint){
                $cash_registers = CashRegister::where('_workpoint', $workpoint->id)->get();
                $caja_x_ticket = [];
                if(count($cash_registers)>0){
                    foreach($cash_registers as $cash){
                    $sale = Sales::where('_cash', $cash->id)->max('num_ticket');
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
        })->everyTwoMinutes();
    }
}
