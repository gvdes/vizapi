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
use App\Client;

class VentasController extends Controller{
  /**
   * Create a new controller instance.
   *
   * @return void
   */
  public function __construct(){
  }

  public function index(Request $request){
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
    $workpoints = WorkPoint::with(['cash' => function($query) use($date_from, $date_to){
      $query->with(['sales' => function($query) use($date_from, $date_to){
        $query->where('created_at',">=", $date_from)
        ->where('created_at',"<=", $date_to);
      }]);
    }])/* ->where('_type', 2) */->get();
    $paid_methods = PaidMethod::all();
    $formas_pago = $paid_methods->map(function($method){
      $method->total = 0;
      return $method;
    });
    $ventas = $workpoints->map(function($workpoint) use($date_from, $date_to, $paid_methods){
      $venta = Sales::where([['created_at',">=", $date_from], ['created_at',"<=", $date_to]])->whereHas('cash', function($query) use($workpoint){
          $query->where('_workpoint', $workpoint->id);
        })->sum("total");

      $p = collect(json_decode(json_encode($paid_methods)));
      $metodos_pago = $p->map(function($method) use($date_from, $date_to, $workpoint){
        $method->total = Sales::where([
          ['created_at',">=", $date_from],
          ['created_at',"<=", $date_to],
          ['_paid_by', $method->id]
        ])->whereHas('cash', function($query) use($workpoint){
          $query->where('_workpoint', $workpoint->id);
        })->sum("total");
        return $method;
      });
      
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
      $res[$sale->_paid_by-1]->total = $res[$sale->_paid_by-1]->total + $sale->total;
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
      ]
    ]);
  }

  public function getVentas(Request $request){
    try{
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
            $instance = Sales::create([
              "num_ticket" => $venta['num_ticket'],
              "_cash" => $cash_registers[$venta['_workpoint']][0]['id'],
              "total" => $venta['total'],
              "created_at" => $venta['created_at'], 
              "_client" => (array_search($venta['_client'], $ids_clients) > 0 || array_search($venta['_client'], $ids_clients) === 0) ? $venta['_client'] : 3,
              "_paid_by" => $venta['_paid_by'],
              "name" => $venta['name'],
              "serie" => $venta['_cash']
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
      return response()->json(["ventas" => $cash__]);
    }catch(Exception $e){
        return response()->json(["message" => "No se ha podido poblar la base de datos"]);
    }
  }

  public function getLastVentas(Request $request){
    $workpoints = WorkPoint::where('_type', 2)->get();
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
          $sale = Sales::where('_cash', $cash->id)->whereYear('created_at', '2020')->max('num_ticket');
          if($sale){
            array_push($caja_x_ticket, ["_cash" => $cash->num_cash, "num_ticket" => $sale]);
          }
        }
        if(count($caja_x_ticket)>0){
          /* $resumen[$workpoint->alias] = $caja_x_ticket; */
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
            $resumen[$workpoint->alias] = $caja_x_ticket;
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
    return response()->json([
      "success" => true,
      "time" => microtime(true) - $start,
      "a" => $a,
      "resumen" => $resumen
      ]);
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

  public function VentasxArticulos(Request $request){
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
    $workpoints = Workpoint::all();

    if(isset($request->products)){
      $p = array_column($request->products,"code")/* $request->products */;
      $products = Product::whereIn('code', $p)
      ->with(['sales' => function($query) use($date_from, $date_to){
        $query->where('created_at',">=", $date_from)->where('created_at',"<=", $date_to);
      }])->get();
    }else{
      /* $cash = CashRegister::where('_workpoint', $request->_workpoint)->get()->toArray(); */
      $cash = CashRegister::all()->toArray();
      $products = Product::whereIn('_category', range(37,57))->/* whereHas('sales', function($query) use($date_from, $date_to, $cash){
        $query->where('created_at',">=", $date_from)->where('created_at',"<=", $date_to)->whereIn('_cash', array_column($cash, 'id'));
      })-> */with(['sales' => function($query) use($date_from, $date_to, $cash){
        $query->where('created_at',">=", $date_from)->where('created_at',"<=", $date_to)->whereIn('_cash', array_column($cash, 'id'));
      }])->get();
    }

    $result = $products->map(function($product) use($workpoints){
      $desgloce = $workpoints->map(function($workpoint) use($product){
        $vendidos = $product->sales->reduce(function($total, $sale) use($workpoint){
          if($sale->cash->_workpoint == $workpoint->id){
            return $total + $sale->pivot->amount;
          }else{
            return $total;
          }
        }, 0);
        return [$workpoint->alias => $vendidos];
      });
      $vendidos = $product->sales->reduce(function($total, $sale){
        return $total + $sale->pivot->amount;
      }, 0);

      $tickets = count($product->sales->toArray());
      $a = [
        "code" => $product->code,
        "name" => $product->name,
        "description" => $product->description,
        "pieces" => $product->pieces,
        "total" => $vendidos,
        "tickets" => $tickets,
        "variants" => $product->variants
      ];
      return array_merge($a, $desgloce->toArray());
    });

    return response()->json($result);
  }

  public function insertVentas(){
    $sales = Sales::with('paid_by')->whereHas("cash", function($query){
      $query->where('_workpoint', 10);
    })->with(['products'])->get();

    $access = "C:\Users\Carlo\Desktop\access\RAC22020";
    $db = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
    $con = $db;
    $query = "INSERT INTO F_ALB (TIPALB, CODALB, CNOALB, FECALB, ALMALB, AGEALB, CLIALB, NET1ALB, BAS1ALB, TOTALB, FOPALB, HORALB) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);";
    $exec_insert = $con->prepare($query);
    $result = $sales->map(function($sale) use ($exec_insert){
      $date = explode(" ", $sale->created_at)[0];
      $hour = explode(" ", $sale->created_at)[1];
      /* return [$sale->_cash, $sale->num_ticket, $sale->name, $date, "GEN", 1, $sale->_client, $sale->total, $sale->total, $sale->total, $sale->paid_by->alias, $hour]; */
      $res = $exec_insert->execute([$sale->_cash, $sale->num_ticket, $sale->name, $date, "GEN", 1, $sale->_client, $sale->total, $sale->total, $sale->total, $sale->paid_by->alias, $hour]);
      return $res;
    })->toArray();
    return response()->json($result);
    $a = [];
    /**
     * INICIO
     */
    $dataToInsert = array();
    $colNames = ["TIPALB", "CODALB", "CNOALB", "FECALB", "ALMALB", "AGEALB", "CLIALB", "NET1ALB", "BAS1ALB", "TOTALB", "FOPALB", "HORALB"];
    foreach ($result as $row => $data) {
      foreach($data as $val) {
        $dataToInsert[] = $val;
      }
    }
    foreach ($colNames as $curCol) {
      $updateCols[] = $curCol . " = VALUES($curCol)";
    }
  
    $onDup = implode(', ', $updateCols);
  
    // setup the placeholders - a fancy way to make the long "(?, ?, ?)..." string
    $rowPlaces = '(' . implode(', ', array_fill(0, count($colNames), '?')) . ')';
    $allPlaces = implode(', ', array_fill(0, count($result), $rowPlaces));
    
    $query = "INSERT INTO F_ALB (" . implode(', ', $colNames) . ") VALUES " . $allPlaces;
  
    $exec_insert = $con->prepare($query);
    $res = $exec_insert->execute($dataToInsert);
    array_push($a, $res);
    /**
     * FIN
     */
    /* foreach (array_chunk($result, 500) as $insert) {
    } */
    return response()->json($a);
    return response()->json(count($result));
  }

  public function insertProductVentas(){
    $sales = Sales::with('paid_by', 'products')->whereHas("cash", function($query){
      $query->where('_workpoint', 10);
    })->with(['products'])->get();

    $access = "C:\Users\Carlo\Desktop\access\RAC22020";
    $db = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
    $con = $db;
    $query = "INSERT INTO F_LAL (TIPLAL, CODLAL, ARTLAL, DESLAL, CANLAL, PRELAL, TOTLAL, COSLAL) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $exec_insert = $con->prepare($query);
    foreach($sales as $sale){
      foreach($sale->products as $product){
        $res = $exec_insert->execute([$sale->_cash, $sale->num_ticket, $product->code, $product->description, $product->pivot->amount, $product->pivot->price, $product->pivot->total, $product->pivot->costo]);
      }
    }
    /* $result = $sales->map(function($sale) use ($exec_insert){
      return $res;
    })->toArray(); */
    return response()->json(true);
  }

  public function getSales(){
    //Obtener el ultimo folio del año
    //Ordenar por agente
    //Insertar
    //Realizarlo cada 2 minutos
  }
}
