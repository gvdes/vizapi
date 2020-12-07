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
    $venta = rand(10000,50000);
    $tickets_promedio = rand(20,60);
    $cajeros = rand(1,6);
    $caj = [];
    for($i=0; $i<$cajeros; $i++){
      $caj[$i] = [
        "id" => $i,
        "name" => $i,
        "tickets" => "",
        "venta" => ""
      ];
    }
  }
}
