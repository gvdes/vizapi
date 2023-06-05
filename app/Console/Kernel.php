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
        /***************
        *    VENTAS    *
        ****************/
        /* Actualización cada 5 minutos */
        $schedule->call('App\Http\Controllers\VentasController@getLastVentas')->everyFiveMinutes();
        /* Depuración de ventas diarias */
        $schedule->call('App\Http\Controllers\VentasController@restoreSales')->dailyAt('23:10');
        /* Se solicita la actualización de las ventas despues de ser eliminadas */
        $schedule->call('App\Http\Controllers\VentasController@getLastVentas')->dailyAt('23:15');

        /****************
        *  INVENTARIOS  *
        *****************/
        /* Actualización de stock cada 3 minutos */
        //$schedule->call('App\Http\Controllers\LocationController@updateStocks')->everyThreeMinutes()->between('9:00', '23:00');
        /* Almacenar los stocks al cierre del día */
        $schedule->call('App\Http\Controllers\LocationController@saveStocks')->dailyAt('23:00');

        /****************
        *   RECEPCIÓN   *
        *****************/
        /* Actualización de salidas cada hora */
        $schedule->call('App\Http\Controllers\SalidasController@LastSalidas')->hourly()->between('9:00', '22:00');

        /**************
        *   COMPRAS   *
        ***************/
        /* Actualización de las compras de CEDIS al termino del día */
        $schedule->call('App\Http\Controllers\InvoicesReceivedController@newOrders')->dailyAt('23:00');
        /* Depuración de compras fin de semana */
        $schedule->call('App\Http\Controllers\InvoicesReceivedController@restore')->weeklyOn(7, '8:00');
        /* Se solicita la actualización de los compras despues de ser eliminados */
        $schedule->call('App\Http\Controllers\InvoicesReceivedController@newOrders')->weeklyOn(7,'8:05');

        /***************
        *    GASTOS    *
        ****************/
        /* Actualización de gastos CEDIS al termino del día */
        $schedule->call('App\Http\Controllers\AccountingController@getNew')->dailyAt('23:00');
        /* Depuración de gastos fin de semana */
        $schedule->call('App\Http\Controllers\AccountingController@restore')->weeklyOn(7, '8:00');
        /* Se solicita la actualización de los gastos despues de ser eliminados */
        $schedule->call('App\Http\Controllers\AccountingController@getNew')->weeklyOn(7,'8:05');

        /***************
        *    requisitions    *
        ****************/
        //reporte que todos los pedidos han sido impresos
        $schedule->call('App\Http\Controllers\RequisitionController@missingPrint')->everyFiveMinutes();

        /* Actualización de retiradas de las sucursales al termino del día */
       // $schedule->call('App\Http\Controllers\WithdrawalsController@getLatest')->dailyAt('23:00'); // a peticion del nachotas
        /* Depuración de retiradas fin de semana */
       // $schedule->call('App\Http\Controllers\WithdrawalsController@restore')->weeklyOn(7,'8:00'); // a peticion del nacho
        /* Se solicita la actualización de las retirafas despues de ser eliminadas */
       // $schedule->call('App\Http\Controllers\WithdrawalsController@getLatest')->weeklyOn(7,'8:05'); // a peticion del nacho
    }
}
