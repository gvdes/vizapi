<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Resources\Cash as CashResource;
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
          $date_from = new \DateTime($request->date_from);
          $date_to = new \DateTime($request->date_to);
          if($request->date_from == $request->date_to){
            $date_from->setTime(0,0,0);
            $date_to->setTime(23,59,59);
          }
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
    $paid_methods = PaidMethod::all();
    $formas_pago = $paid_methods->map(function($method){
      $method->total = 0;
      return $method;
    });
    $ventas = $workpoints->map(function($workpoint) use($formas_pago){
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
      },json_decode(json_encode($formas_pago)));
      
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
    $method = $ventas->reduce(function($res, $workpoint){
      foreach($workpoint['metodos_pago'] as $metodo){
        $res[$metodo->id-1]->total = $res[$metodo->id-1]->total + $metodo->total;
      }
      return $res;
    }, $formas_pago);
    return response()->json([
      "venta" => $venta,
      "tickets" => $tickets,
      "ticket_promedio" => round($tickets_promedio, 2),
      "metodos_de_pago" => $method,
      "sucursales" => $ventas
    ]);
  }

  public function tienda(Request $request){
    if(isset($request->date_from) && isset($request->date_to)){
      $date_from = new \DateTime($request->date_from);
      $date_to = new \DateTime($request->date_to);
      if($request->date_from == $request->date_to){
        $date_from->setTime(0,0,0);
        $date_to->setTime(23,59,59);
      }
    }else{
      $date_from = new \DateTime();
      $date_from->setTime(0,0,0);
      $date_to = new \DateTime();
      $date_to->setTime(23,59,59);
    }
    
    $workpoint = WorkPoint::find($request->_workpoint);

    $cash = CashRegister::where('_workpoint', $request->_workpoint)->with(['sales' => function($query) use($date_from, $date_to){
      $query->with('products', 'client')->where('created_at',">=", $date_from)->where('created_at',"<=", $date_to);
    }])->get();
    
    $paid_methods = PaidMethod::all();
    $formas_pago = $paid_methods->map(function($method){
      $method->total = 0;
      return $method;
    });

    $sales = Sales::whereHas("cash", function($query) use($workpoint){
      $query->where('_workpoint', $workpoint->id);
    })->with(['products'])->where('created_at',">=", $date_from)->where('created_at',"<=", $date_to)->get();

    $metodos_pago = collect($sales->reduce(function($res, $sale){
      $res[$sale->_paid_by-1]->total = $res[$sale->_paid_by-1]->total + $sale->venta;
      return $res;
    },json_decode(json_encode($formas_pago))));

    $venta = $metodos_pago->sum('total');
    $tickets = $sales->count();

    return response()->json([
      "workpoint" => [
        "id" => $workpoint->id,
        "name" => $workpoint->name,
        "alias" => $workpoint->alias,
        "venta" => $venta,
        "tickets" => $tickets,
        "ticket_promedio" => $venta/($tickets == 0 ? 1 : $tickets),
        "metodos_pago" => $metodos_pago,
        "cajas" => CashResource::collection($cash)
      ],
      /* "ventas" => $cash */
    ]);

    return response()->json([
      "workpoint" => [
        "id" => 3,
        "name" => "San Pablo 1",
        "alias" => "SP1",
        "venta" => 0,
        "tickets" => 0,
        "ticket_promedio" => 0,
        "metodos_pago" => [[
          "id" => 1,
          "name" => "Efectivo",
          "alias" => "EFE",
          "total" => 0
        ]],
        "cajas" => [[
          "id" => 1,
          "name" => "Caja 1",
          "num_cash" => 1,
          "sales" => [[
            "id" => 1,
            "num_ticket" => 1,
            "name" => "",
            "created_at" => "",
            "updated_at" => "",
            "client" => "",
            "paid_by" => "",
            "products" => [[
              "id" =>   1,
              "code" => "",
              "name" => "",
              "description" => "",
              "sold" => [
                "amount" => 0,
                "costo" => 0,
                "price" => 0,
                "total" => 0,
              ]
            ]]
          ]]
        ]]
      ],
      "cash" => $cash
    ]);
  }

  public function getVentas(Request $request){
    try{
      $start = microtime(true);
      $client = curl_init();
      $workpoint = WorkPoint::find($request->_workpoint);
      curl_setopt($client, CURLOPT_URL, $workpoint->dominio."/access/public/ventas");
      curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
      curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
      $ventas = json_decode(curl_exec($client), true);
      curl_close($client);
      if($ventas){
        $products = Product::all()->toArray();
        $cash_registers = CashRegister::where('_workpoint', $workpoint->id)->get()->toArray();
        $codes = array_column($products, 'code');
        $cajas = array_column($cash_registers, 'num_cash');
        DB::transaction(function() use ($ventas, $codes, $products, $cajas, $cash_registers){
          foreach($ventas as $venta){
            $index_caja = array_search($venta['_cash'], $cajas);
            $instance = Sales::create([
              "num_ticket" => $venta['num_ticket'],
              "_cash" => $cash_registers[$index_caja]['id'],
              "total" => $venta['total'],
              "created_at" => $venta['created_at'],
              "_client" => $venta['_client'],
              "_paid_by" => $venta['_paid_by'],
              "name" => $venta['name'],
            ]);
            $insert = [];
            foreach($venta['body'] as $row){
              $index = array_search($row['_product'], $codes);
              if($index === 0 || $index > 0){
                /* $insert[$products[$index]['id']] = [
                  "amount" => $row['amount'],
                  "price" => $row['price'],
                  "total" => $row['total'],
                  "costo" => $row['costo']
                ]; */
                $instance->products()->attach($products[$index]['id'], [
                  "amount" => $row['amount'],
                  "price" => $row['price'],
                  "total" => $row['total'],
                  "costo" => $row['costo']
                ]);
              }
            }
            /* $instance->products()->attach($insert); */
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

  public function getLastVentas(Request $request){
    $workpoints = WorkPoint::where('_type', 2)->get();
    $resumen = [];
    foreach($workpoints as $workpoint){
      $cash_registers = CashRegister::where('_workpoint', $workpoint->id)->get();
      $caja_x_ticket = [];
      if(count($cash_registers)>0){
        foreach($cash_registers as $cash){
          $sale = Sales::where('_cash', $cash->id)->max('num_ticket');
          if($sale){
            array_push($caja_x_ticket, ["_cash" => $cash->id, "num_ticket" => $sale]);
          }
        }
        $resumen[$workpoint->alias] = $caja_x_ticket;
      }
    }
    return response()->json($resumen);
    /* $res = $workpoints->map(function($workpoint){
      $cash_registers = CashRegister::where('_workpoint', $workpoint->id)->get();
      $workpoint->cajas =$cash_registers->map(function($cash){
        $cash->ultima_venta = Sales::where('_cash', $cash->id)->max('num_ticket');
        return $cash;
      });
      return $workpoint;
    });

    return response()->json($res); */
  }
}
