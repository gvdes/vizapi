<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\WorkPoint;
use App\Product;
use App\CashRegister;
use App\Sales;
use App\PaidMethod;

class VentasController extends Controller{
  /**
   * Create a new controller instance.
   *
   * @return void
   */
  public function __construct(){
  }

  public function index(Request $request){
    $workpoints = WorkPoint::with(['cash' => function($query) use($request){
      $query->with(['sales' => function($query) use($request){
        if(isset($request->date_from) && isset($request->date_to)){
          $date_from = $request->date_from;
          $date_to = $request->date_to;
        }else{
          $date_from = new \DateTime();
          $date_from->setTime(0,0,0);
          $date_to = new \DateTime();
          $date_to->setTime(23,59,59);
        }
        $query->where('created_at',">=", $date_from)
        ->where('created_at',"<=", $date_to)->with('products');
      }]);
    }])->where('_type', 2)->get();
    $ventas = $workpoints->map(function($workpoint){
      $paid_methods = PaidMethod::all();
      define('formas_pago' , $paid_methods->map(function($method){
        $method->total = 0;
        return $method;
      }));
      $store = $workpoint->cash->map(function($cash){
        $cash->sales->map(function($sale){
          $sale->venta = $sale->products->sum(function($product){
            return $product->pivot->total;
          });
          return $sale;
        });
        return $cash;
      });
      $venta = $store->sum(function($cash){
        return $cash->sales->sum("venta");
      });
      $metodos_pago = $store->reduce(function($res, $cash){
        foreach($cash->sales as $sale){
          $res[$sale->_paid_by-1]->total = $res[$sale->_paid_by-1]->total + $sale->venta;
        }
        return $res;
      },$formas_pago);
      $tickets = $workpoint->cash->sum(function($cash){
        return $cash->sales->count();
      });
      $ticket_promedio = round($venta / ($tickets>0 ? $tickets : 1), 2);
      return [
        "id" => $workpoint->id,
        "name" => $workpoint->name,
        "alias" => $workpoint->alias,
        "venta" => $venta,
        "tickets" => $tickets,
        "ticket_promedio" => $ticket_promedio,
        "metodos_pago" => $metodos_pago
      ];
    });

    $venta = $ventas->sum('venta');
    $tickets = $ventas->sum('tickets');
    $tickets_promedio = $venta / ($tickets>0 ? $tickets : 1);

    return response()->json([
      "venta" => $venta,
      "tickets" => $tickets,
      "ticket_promedio" => round($tickets_promedio, 2),
      /* "metodos_de_pago" => [
        [ "name" => "Efectivo", "total" => $efectivo],
        [ "name" => "Transferencia", "total" => $transferencia]
      ], */
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

  public function getVentas(Request $request){
    try{
      $start = microtime(true);
      $client = curl_init();
      curl_setopt($client, CURLOPT_URL, "192.168.1.24/access/public/ventas");
      curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
      curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
      $ventas = json_decode(curl_exec($client), true);
      curl_close($client);
      if($ventas){
        $products = Product::all()->toArray();
        $cash_registers = CashRegister::where('_workpoint', 3)->get()->toArray();
        $codes = array_column($products, 'code');
        $cajas = array_column($cash_registers, 'num_cash');
        DB::transaction(function() use ($ventas, $codes, $products, $cajas, $cash_registers){
          foreach($ventas as $venta){
            $index_caja = array_search($venta['_cash'], $cajas);
            $instance = Sales::create([
              "num_ticket" => $venta['num_ticket'],
              "_cash" => $cash_registers[$index_caja]['id'],
              "created_at" => $venta['created_at'],
              "_client" => $venta['_client'],
              "_paid_by" => $venta['_paid_by'],
              "name" => $venta['name'],
            ]);
            $insert = [];
            foreach($venta['body'] as $row){
              $index = array_search($row['_product'], $codes);
              if($index === 0 || $index > 0){
                $insert[$products[$index]['id']] = [
                  "amount" => $row['amount'],
                  "price" => $row['price'],
                  "total" => $row['total'],
                  "costo" => $row['costo']
                ];
              }
            }
            $instance->products()->attach($insert);
          }
        });
        return response()->json([
          "success" => true,
          "ventas" => count($ventas),
          "time" => microtime(true) - $start
        ]);
      }
      return response()->json(["message" => "No se obtuvo respuesta del servidor de factusol"]);
    }catch(Exception $e){
        return response()->json(["message" => "No se ha podido poblar la base de datos"]);
    }
  }
}
