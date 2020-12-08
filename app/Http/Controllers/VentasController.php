<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\WorkPoint;

class VentasController extends Controller{
  /**
   * Create a new controller instance.
   *
   * @return void
   */
  public function __construct(){
  }

  public function index(Request $request){
    $workpoints = WorkPoint::where('_type', 2)->get();
    $ventas = $workpoints->map(function($workpoint){
      $workpoint->venta = rand(10000,50000);
      $workpoint->tickets = rand(20,60);
      $workpoint->ticket_promedio = round($workpoint->venta / $workpoint->tickets,2);
      return $workpoint;
    });

    $venta = $ventas->sum('venta');
    $tickets = $ventas->sum('tickets');
    $tickets_promedio = $venta / $tickets;
    $efectivo = rand(0, $venta);
    $transferencia = $venta-$efectivo;

    return response()->json([
      "venta" => $venta,
      "tickets" => $tickets,
      "ticket_promedio" => round($tickets_promedio, 2),
      "metodos_de_pago" => [
        [ "name" => "Efectivo", "total" => $efectivo],
        [ "name" => "Transferencia", "total" => $transferencia]
      ],
      "sucursales" => $ventas
    ]);
  }

  public function tienda(Request $request){
    $workpoint = WorkPoint::find($request->id);
    $cajeros = rand(1,6);
    $venta = rand(10000,50000);
    $tickets_num = rand(20,60);
    $tickets = [];
    for($i=0; $i<$tickets_num; $i++){
      $tickets[$i] = [
        "id" => $i,
        "_cajero" => "",
        "folio" => "",
        "created_at" => "",
        "_cliente" => "",
        "_price_list" => "",
        "total" => "",
        "_forma_pago" => ""
      ];
    }
    /* $res_tickets = $tickets;
    $res_venta = $venta; */
    $caj = [];
    for($i=1; $i<=$cajeros; $i++){
      $caj[$i-1] = [
        "id" => $i,
        "name" => "Cajero ".$i,
        "tickets" => $i == $cajeros ? $tickets : rand(0, $tickets),
        "venta" => $i == $cajeros ? $venta : rand(0, $venta)
      ];
    }
    return response()->json([
      "id" => $workpoint->id,
      "name" => $workpoint->name,
      "alias" => $workpoint->alias,
      "tickets" => $tickets,
      "venta" => $venta,
      "cajeros" => $caj
    ]);
  }
}
