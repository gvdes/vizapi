<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Resources\Cash as CashResource;
use App\Exports\WithMultipleSheetsExport;
use Maatwebsite\Excel\Facades\Excel;
use App\WorkPoint;
use App\Product;
use App\CashRegister;
use App\Sales;
use App\PaidMethod;
use App\Client;
use App\Seller;
use App\Exports\ArrayExport;
use App\Exports\InvoicesPerCategorySheet;

class VentasController extends Controller{
  /**
   * Create a new controller instance.
   *
   * @return void
   */
  public function __construct(){
  }

  public function index2(Request $request){
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
    }])->get();
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

    $sales = Sales::where([
      ["created_at", ">=", $date_from],
      ["created_at", "<=", $date_to]
    ])->with(["cash", "paid_by"])->get();

    $venta = $sales->sum("total");
    $tickets = count($sales);
    $paidMethods = PaidMethod::where('id', ">", 0)->get();
    $sales_groupBy_paid_methods = $sales->groupBy("_paid_by");

    $total_paid_methods = $paidMethods->map(function($method) use($sales_groupBy_paid_methods){
      $result = array_key_exists($method->id, $sales_groupBy_paid_methods->toArray());
      $total = $result ? $sales_groupBy_paid_methods[$method->id]->sum("total") : 0;
      return [
        "id" => $method->id,
        "name" => $method->name,
        "alias" => $method->alias,
        "total" => $total
      ];
    });

    $workpoints = WorkPoint::whereIn("_type", [1,2])->get();
    $sales_groupBy_workpoint = $sales->groupBy(function($sale){
      return $sale->cash->_workpoint;
    });
    $result_workpoints = $workpoints->map(function($workpoint) use($sales_groupBy_workpoint, $paidMethods){
      $result = array_key_exists($workpoint->id, $sales_groupBy_workpoint->toArray());
      $workpoint->venta = $result ? $sales_groupBy_workpoint[$workpoint->id]->sum("total") : 0;
      $workpoint->tickets = $result ? count($sales_groupBy_workpoint[$workpoint->id]) : 0;
      $workpoint->ticket_promedio = round($workpoint->venta / ($workpoint->tickets>0 ? $workpoint->tickets : 1), 2);
      $sales_groupBy_paid_methods = $result ? $sales_groupBy_workpoint[$workpoint->id]->groupBy('_paid_by') : false;
      if($sales_groupBy_paid_methods){
        $workpoint->metodos_pago = $paidMethods->map(function($method) use($sales_groupBy_paid_methods){
          $result = array_key_exists($method->id, $sales_groupBy_paid_methods->toArray());
          $total = $result ? $sales_groupBy_paid_methods[$method->id]->sum("total") : 0;
          return [
            "id" => $method->id,
            "name" => $method->name,
            "alias" => $method->alias,
            "total" => $total
          ];
        });
      }else{
        $workpoint->metodos_de_pago = $paidMethods;
      }
      return $workpoint;
    });
    return response()->json([
      "venta" => $venta,
      "tickets" => $tickets,
      "ticket_promedio" => round($venta / ($tickets>0 ? $tickets : 1), 2),
      "metodos_de_pago" => $total_paid_methods,
      "sucursales" => $result_workpoints
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
    })->where('created_at',">=", $date_from)->where('created_at',"<=", $date_to)->get();

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

  public function tiendaXSeller(Request $request){
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

    $paid_methods = PaidMethod::all();

    $sales = Sales::with('seller', 'client')->withCount('products')->whereHas("cash", function($query) use($workpoint){
      $query->where('_workpoint', $workpoint->id);
    })->where('created_at',">=", $date_from)->where('created_at',"<=", $date_to)->get()->groupBy(function($sale){
      return $sale->seller->name;
    });
    $vendedores = array_keys($sales->toArray());
    $result = [];
    foreach($vendedores as $vendedor){
      $ventas = collect($sales[$vendedor]);
      $total = $ventas->sum('total');
      $result[] = ["vendedor"=> $vendedor/* , "ventas" => $ventas */, "total" => $total, "tickets" => count($ventas), "productos" => $ventas->sum('products_count')];
    }
    /* return response()->json($result); */
    $export = new ArrayExport($result);
    $date = new \DateTime();
    return Excel::download($export, $date->format("Y-m-d")."_ventas.xlsx");
  }

  public function tiendasXArticulos(Request $request){
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
    $categories = \App\ProductCategory::where('deep', 0)->get();
    $ids_categories = array_column($categories->toArray(), 'id');
    $result = [];
    $products = Product::whereHas("sales", function($query) use($workpoint, $date_from, $date_to){
      $query->where([['created_at',">=", $date_from], ['created_at',"<=", $date_to]])->whereHas("cash", function($query) use($workpoint){
        $query->where('_workpoint', $workpoint->id);
      });
    })->with(["sales" => function($query) use($workpoint, $date_from, $date_to){
      $query->where([['created_at',">=", $date_from], ['created_at',"<=", $date_to]])->whereHas("cash", function($query) use($workpoint){
        $query->where('_workpoint', $workpoint->id);
      });
    }, 'category'])->get()->map(function($product) use($categories, $ids_categories){
      $venta = $product->sales->sum(function($product){
        return $product->pivot->total;
      });
      $piezas = $product->sales->sum(function($product){
        return $product->pivot->amount;
      });

      $costos = $product->sales->sum(function($product){
        return $product->pivot->costo;
      });

      $tickets = count($product->sales);

      $costoPromedio = $costos/$tickets;
      $precioPromedio = $venta/$tickets;
      if($product->category->deep == 0){
        $category = $product->category->name;
      }else{
        $key = array_search($product->category->root, $ids_categories);
        if($key === 0 || $key>0){
          $category = $categories[$key]->name;
        }else{
          $category = $product->category->root;
        }
      }
      return [
        "Codigo" => $product->code,
        "Modelo" => $product->name,
        "Descripción" => $product->description,
        "_category" => $category,
        "venta" => $venta,
        "tickets" => $tickets,
        "Unidades vendidas" => $piezas,
        "Costo Promedio" => $costoPromedio,
        "Precio Promedio" => $precioPromedio
      ];
      return $product;
    })->groupBy("_category")->toArray();
    $resumen = [];
    foreach($products as $key => $category){
    array_push($resumen, [
      "Categoría" => $key,
      "venta" => collect($category)->sum('venta'),
      "Unidades vendidas" => collect($category)->sum('Unidades vendidas')
      ]);
    }
    $resumen = collect($resumen)->sortByDesc('venta');
    $total = $resumen->sum('venta');
    $unidades = $resumen->sum('Unidades vendidas');
    $resumen = $resumen->toArray();
    array_push($resumen, [
      "Categoría" => "",
      "venta" => $total,
      "Unidades vendidas" => $unidades
    ]);
    $products["A"] = $resumen;
    ksort($products);
    $export = new WithMultipleSheetsExport($products);
    return Excel::download($export, "prueba.xlsx");
    return response()->json([
      "id" => $workpoint->id,
      "name" => $workpoint->name,
      "alias" => $workpoint->alias,
      "products" => $products,
    ]);

    return response()->json($result);
  }

  public function tiendasXArticulosF(Request $request){
    $workpoint = WorkPoint::find($request->_workpoint);
    $start = new \DateTime($request->date_from);
    $end = new \DateTime($request->date_to);
    $days = [];
    for($i = $start; $i <= $end; $i->modify('+1 day')){
      $date_from = new \DateTime($i->format("Y-m-d"));
      $date_to = new \DateTime($i->format("Y-m-d"));
      $date_from->setTime(0,0,0);
      $date_to->setTime(23,59,59);
      $categories = \App\ProductCategory::where('deep', 0)->get();
      $ids_categories = array_column($categories->toArray(), 'id');
      $result = [];
      $products = Product::whereHas("sales", function($query) use($workpoint, $date_from, $date_to){
        $query->where([['created_at',">=", $date_from], ['created_at',"<=", $date_to]])->whereHas("cash", function($query) use($workpoint){
          $query->where('_workpoint', $workpoint->id);
        });
      })->with(["sales" => function($query) use($workpoint, $date_from, $date_to){
        $query->where([['created_at',">=", $date_from], ['created_at',"<=", $date_to]])->whereHas("cash", function($query) use($workpoint){
          $query->where('_workpoint', $workpoint->id);
        });
      }, 'category'])->get()->map(function($product) use($categories, $ids_categories){
        $venta = $product->sales->sum(function($product){
          return $product->pivot->total;
        });
        $piezas = $product->sales->sum(function($product){
          return $product->pivot->amount;
        });

        $costos = $product->sales->sum(function($product){
          return $product->pivot->costo;
        });

        $tickets = count($product->sales);

        $costoPromedio = $costos/$tickets;
        $precioPromedio = $venta/$tickets;
        if($product->category->deep == 0){
          $category = $product->category->name;
        }else{
          $key = array_search($product->category->root, $ids_categories);
          if($key === 0 || $key>0){
            $category = $categories[$key]->name;
          }else{
            $category = $product->category->root;
          }
        }
        return [
          "Codigo" => $product->code,
          "Modelo" => $product->name,
          "Descripción" => $product->description,
          "_category" => $category,
          "venta" => $venta,
          "tickets" => $tickets,
          "Unidades vendidas" => $piezas,
          "Costo Promedio" => $costoPromedio,
          "Precio Promedio" => $precioPromedio
        ];
        return $product;
      })->groupBy("_category")->toArray();
      $resumen = [];
      foreach($products as $key => $category){
      array_push($resumen, [
        "Categoría" => $key,
        "venta" => collect($category)->sum('venta'),
        "Unidades vendidas" => collect($category)->sum('Unidades vendidas')
        ]);
      }
      $resumen = collect($resumen)->sortByDesc('venta');
      $total = $resumen->sum('venta');
      $unidades = $resumen->sum('Unidades vendidas');
      $resumen = $resumen->toArray();
      array_push($resumen, [
        "Categoría" => "",
        "venta" => $total,
        "Unidades vendidas" => $unidades
      ]);
      $products["A"] = $resumen;
      ksort($products);
      $export = new WithMultipleSheetsExport($products);
      /* return $products; */
      /* $days[] = $workpoint->alias."_".$i->format("Y-m-d"); */
      /* return */ /* $days[] = Excel::download($export, $workpoint->alias."_".$i->format("Y-m-d").".xlsx"); */
      /* Excel::store($export, $workpoint->alias."_".$i->format("Y-m-d").".xlsx"); */
      return Excel::download($export, $workpoint->alias."_".$i->format("Y-m-d").".xlsx");
    }
    /* return $days; */
  }

  public function tiendasXArticulosFor(Request $request){
    $workpoint = WorkPoint::find($request->_workpoint);
    $sales = Sales::where([['created_at', '>', $request->date_from], ['created_at', '<', $request->date_to]])->whereHas("cash", function($query) use($workpoint){
      $query->where('_workpoint', $workpoint->id);
    })->with('cash', 'products')->get()->map(function($sale){
      $body = $sale->products->map(function($product){
        return [
          "_product" => $product->code,
          "amount" => $product->pivot->amount,
          "price" => $product->pivot->price,
          "total" => $product->pivot->total,
          "costo" => $product->pivot->costo
        ];
      });
      return [
        "_cash" => $sale->cash->num_cash,
        "num_ticket" => $sale->num_ticket,
        "total" => $sale->total,
        "created_at" => $sale->created_at,
        "_client" => $sale->_client,
        "name" => $sale->name,
        "_seller" => 5,
        "_paid_by" => $sale->_paid_by,
        "body" => $body
      ];
    });
    /* return response()->json($sales); */
    $workpoint = WorkPoint::find($request->_workpoint);
    $start = new \DateTime($request->date_from);
    $end = new \DateTime($request->date_to);
    $days = [];
    $categories = \App\ProductCategory::where('deep', 0)->get();
    $ids_categories = array_column($categories->toArray(), 'id');
    for($i = $start; $i <= $end; $i->modify('+1 day')){
      $date_from = new \DateTime($i->format("Y-m-d"));
      $date_to = new \DateTime($i->format("Y-m-d"));
      $date_from->setTime(0,0,0);
      $date_to->setTime(23,59,59);
      $products = Product::where('_category', 119)->/* whereHas("sales", function($query) use($workpoint, $date_from, $date_to){
        $query->where([['created_at',">=", $date_from], ['created_at',"<=", $date_to]])->whereHas("cash", function($query) use($workpoint){
          $query->where('_workpoint', $workpoint->id);
        });
      })-> */with(["sales" => function($query) use($workpoint, $date_from, $date_to){
        $query->where([['created_at',">=", $date_from], ['created_at',"<=", $date_to]])/* ->whereHas("cash", function($query) use($workpoint){
          $query->where('_workpoint', $workpoint->id);
        }) */;
      }, 'category'])->get()/* ->filter(function($product){
        return count($product->sales)>0;
      }) */->map(function($product) use($categories, $ids_categories, $i, $workpoint){
        $venta = $product->sales->sum(function($product){
          return $product->pivot->total;
        });
        $piezas = $product->sales->sum(function($product){
          return $product->pivot->amount;
        });
        $piezas2 = $piezas;
        $piezas = $piezas == 0 ? 1 : $piezas;
        $costo_real = $product->cost;
        $costos = $product->sales->sum(function($product) use($costo_real){
          $costo = intval($product->pivot->costo) == 0 ? $costo_real : $product->pivot->costo;
          return $costo * $product->pivot->amount;
        });

        $tickets = count($product->sales);

        $costoPromedio = $costos/abs($piezas);
        $precioPromedio = $venta/$piezas;
        if($product->category->deep == 0){
          $familia = $product->category->name;
          $category = "";
        }else{
          $key = array_search($product->category->root, $ids_categories, true);
          $familia = $categories[$key]->name;
          $category = $product->category->name;
        }
        return [
          "Fecha" => $i->format("Y-m-d"),
          "Año" => $i->format("Y"),
          "Mes" => $i->format("m"),
          "Día" => $i->format("d"),
          /* "Sucursal" => $workpoint->name, */
          "Codigo" => $product->code,
          "Modelo" => $product->name,
          "Descripción" => $product->description,
          "Unidad de medida" => $product->pieces,
          "Familia" => $familia,
          "Categoría" => $category,
          "tickets" => $tickets,
          "Unidades vendidas" => $piezas2,
          "Costo Promedio" => $costoPromedio,
          "Precio Promedio" => $precioPromedio,
          "venta" => $venta,
          "Movimiento" => $piezas2 > 0 ? "VENTA" : "DEVOLUCIÓN"
        ];
        return $product;
      })->toArray();
      if(count($products)>0){
        $days = array_merge($days, $products);
        /* $days = count($products); */
      }
    }
    /* return $days; */
    $export = new ArrayExport($days);
    $date = new \DateTime();
    return Excel::download($export, $request->name.".xlsx");
  }

  public function pedidosXArticulos(Request $request){
    $workpoint = WorkPoint::find($request->_workpoint);
    $start = new \DateTime($request->date_from);
    $end = new \DateTime($request->date_to);
    $days = [];
    $categories = \App\ProductCategory::where('deep', 0)->get();
    $ids_categories = array_column($categories->toArray(), 'id');
    for($i = $start; $i <= $end; $i->modify('+1 day')){
      $date_from = new \DateTime($i->format("Y-m-d"));
      $date_to = new \DateTime($i->format("Y-m-d"));
      $date_from->setTime(0,0,0);
      $date_to->setTime(23,59,59);
      $products = Product::whereHas("requisitions", function($query) use($workpoint, $date_from, $date_to){
        $query->where([['created_at',">=", $date_from], ['created_at',"<=", $date_to], ["_workpoint", $workpoint->id]]);
      })->with(["requisitions" => function($query) use($workpoint, $date_from, $date_to){
        $query->where([['created_at',">=", $date_from], ['created_at',"<=", $date_to], ["_workpoint", $workpoint->id]]);
      }, 'category'])->get()/* ->map(function($product) use($categories, $ids_categories, $i, $workpoint){
        $venta = $product->requisition->sum(function($product){
          return $product->pivot->units;
        });
        $piezas = $product->sales->sum(function($product){
          return $product->pivot->units;
        });
        $piezas2 = $piezas;
        $piezas = $piezas == 0 ? 1 : $piezas;
        $costo_real = $product->cost;
        $costos = $product->sales->sum(function($product) use($costo_real){
          $costo = intval($product->pivot->costo) == 0 ? $costo_real : $product->pivot->costo;
          return $costo * $product->pivot->amount;
        });

        $tickets = count($product->sales);

        $costoPromedio = $costos/abs($piezas);
        $precioPromedio = $venta/$piezas;
        if($product->category->deep == 0){
          $familia = $product->category->name;
          $category = "";
        }else{
          $key = array_search($product->category->root, $ids_categories, true);
          $familia = $categories[$key]->name;
          $category = $product->category->name;
        }
        return [
          "Fecha" => $i->format("Y-m-d"),
          "Año" => $i->format("Y"),
          "Mes" => $i->format("m"),
          "Día" => $i->format("d"),
          "Sucursal" => $workpoint->name,
          "Codigo" => $product->code,
          "Modelo" => $product->name,
          "Descripción" => $product->description,
          "Unidad de medida" => $product->pieces,
          "Familia" => $familia,
          "Categoría" => $category,
          "tickets" => $tickets,
          "Unidades vendidas" => $piezas2,
          "Costo Promedio" => $costoPromedio,
          "Precio Promedio" => $precioPromedio,
          "venta" => $venta,
          "Movimiento" => $piezas2 > 0 ? "VENTA" : "DEVOLUCIÓN"
        ];
        return $product;
      })->toArray() */;
      /* if(count($products)>0){
        $days = array_merge($days, $products);
      } */
      $days[] = $products;
    }
    return $days;
    $export = new ArrayExport($days);
    $date = new \DateTime();
    return Excel::download($export, $workpoint->alias."_".$start->format("Y-m-d")."_".$end->format("Y-m-d")."_ventas.xlsx");
  }

  public function getVentasX(Request $request){
    try{
      $clientes = Client::all()->toArray();
      $ids_clients = array_column($clientes, 'id');
      $sellers = Seller::all()->toArray();
      $ids_sellers = array_column($sellers, 'id');
      $cash_registers = CashRegister::all()->groupBy('_workpoint')->toArray();
      $series = range(1,9);
      $ventas = [];
      $products = Product::all()->toArray();
      $codes = array_column($products, 'code');
      foreach($series as $serie){
        $sale = Sales::whereDate('created_at', '>','2021-01-10')->where("serie", $serie)->max('num_ticket');
        if(!$sale){
          $sale = 0;
        }
        $fac = new FactusolController();
        $fac_sales = $fac->getSales($sale, $serie);
        if($fac_sales){
          foreach($fac_sales as $venta){
            $arr_cajas = array_column($cash_registers[$venta['_workpoint']], "num_cash");
            $key = array_search($venta["_cash"], $arr_cajas);
            $_cash = ($key == 0 || $key>0) ? $cash_registers[$venta['_workpoint']][$key]['id'] : $cash_registers[$venta['_workpoint']][0]['id'];
            $instance = Sales::create([
              "num_ticket" => $venta['num_ticket'],
              "_cash" => $_cash,
              "total" => $venta['total'],
              "created_at" => $venta['created_at'],
              "_client" => (array_search($venta['_client'], $ids_clients) > 0 || array_search($venta['_client'], $ids_clients) === 0) ? $venta['_client'] : 3,
              "_paid_by" => $venta['_paid_by'],
              "name" => $venta['name'],
              "serie" => $venta['serie'],
              "_seller" => (array_search($venta['_seller'], $ids_sellers) > 0 || array_search($venta['_seller'], $ids_sellers) === 0) ? $venta['_seller'] : 404
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
      return response()->json(["success" => true]);
    }catch(Exception $e){
        return response()->json(["message" => "No se ha podido poblar la base de datos"]);
    }
  }

  public function getVentas(Request $request){
    $sale = Sales::where("serie", 6)->get();
    $consecutivos = range(1, 2213);
    $consecutivos2 = array_column($sale->toArray(), "num_ticket");
    $res = [];
    foreach($consecutivos as $i){
      $exist = array_search($i, $consecutivos2);
      if($exist === 0 || $exist > 0){

      }else{
        $res [] = $i;
      }
    }
    return $res;
  }

  public function getLastVentas(){ // Función para actualizar las ventas (trae las ultimas ventas)
    $workpoints = WorkPoint::where([['active', true], ['_type', 2]])->orWhere('id', 1)->get();

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
          }else{
            $sale = "2021-01-27 00:00:00";
            $ticket = $sale ? : 0;
            $date = explode(' ', $sale);
            $caja_x_ticket[] = ["_cash" => $cash->num_cash, "num_ticket" => $ticket, "date" => $date[0], 'last_date' => $sale];
          }
        }
        if(count($caja_x_ticket)>0){
          $access = new AccessController($workpoint->dominio);
          $ventas = $access->getLastSales($caja_x_ticket);
          $resumen[$workpoint->alias] = $caja_x_ticket;
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
    return response()->json([
      "success" => true,
      "time" => microtime(true) - $start,
      "resumen" => $resumen
    ]);
  }

  public function restoreSales(){ // Función para eliminar las ventas del día de operación
    $today = date("Y-m-d");
    $sales = Sales::where('created_at', '>=', $today)->get();
    $ids_sales = array_column($sales->toArray(), "id");
    $success = DB::transaction(function() use($ids_sales, $today){
      $delete_body = DB::table('product_sold')->whereIn('_sale', $ids_sales)->delete();
      $delete_header = Sales::where("created_at", ">=", $today)->delete();
      return $delete_header && $delete_body;
    });
    return response()->json([
        "ventas" => count($sales),
        "success" => $success
    ]);
  }

  public function getVentas2019(Request $request){
    $workpoint = WorkPoint::find($request->_workpoint);
    $start = microtime(true);
    $clientes = Client::all()->toArray();
    $ids_clients = array_column($clientes, 'id');
    $cash_registers = CashRegister::where('_workpoint', $workpoint->id)->get();
    $client = curl_init();
    curl_setopt($client, CURLOPT_URL, "localhost/access/public/sale/all");
    curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($client,CURLOPT_TIMEOUT,10);
    $ventas = json_decode(curl_exec($client), true);
    curl_close($client);
    if($ventas){
      $products = Product::all()->toArray();
      $variants = \App\ProductVariant::all()->toArray();
      $codes = array_column($products, 'code');
      $ids_products = array_column($products, 'id');
      $related_codes = array_column($variants, 'barcode');
      $cajas = array_column($cash_registers->toArray(), 'num_cash');
      $dontMatch = DB::transaction(function() use ($ventas, $codes, $products, $cajas, $cash_registers, $ids_clients, $related_codes, $variants, $ids_products){
        $dontMatch = [];
        foreach($ventas as $venta){
          $index_caja = array_search($venta['_cash'], $cajas);
          $index_client = array_search($venta['_client'], $ids_clients);
          $instance = Sales::create([
            "num_ticket" => $venta['num_ticket'],
            "_cash" => $cash_registers[$index_caja]['id'],
            "total" => $venta['total'],
            "created_at" => $venta['created_at'],
            "updated_at" => $venta['created_at'],
            "_client" => ($index_client > 0 || $index_client === 0) ? $venta['_client'] : 3,
            "_paid_by" => $venta['_paid_by'],
            "name" => $venta['name'],
            "_seller"=> $venta['_seller']
          ]);
          $insert = [];
          foreach($venta['body'] as $row){
            $index = array_search($row['_product'], $codes);
            if($index === 0 || $index > 0){
              $costo = ($row['costo'] == 0 || $row['costo'] > $products[$index]['cost']) ? $products[$index]['cost'] : $row['costo'];
              if(array_key_exists($products[$index]['id'], $insert)){
                $insert[$products[$index]['id']] = [
                  "amount" => $row['amount'] + $insert[$products[$index]['id']]['amount'],
                  "price" => $row['price'],
                  "total" => $row['total'] + $insert[$products[$index]['id']]['total'],
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
              }else{
                $dontMatch[] = [$instance->num_ticket, $row['_product']];
              }
            }
          }
          $instance->products()->attach($insert);
        }
        return $dontMatch;
      });
    }

    return response()->json([
      "success" => true,
      "time" => microtime(true) - $start,
      "dontMatch" => $dontMatch
    ]);
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

    if(isset($request->products)){
      $p = array_column($request->products,"code");
      $products = [];
      $notFound = [];
      if($request->validate){
        foreach($p as $code){
          $product = Product::where('code', $code)->orWhere('name', $code)
          ->whereHas('variants', function($query) use ($code){
            $query->where('barcode', $code);
          })
          ->with(['sales' => function($query) use($date_from, $date_to){
            $query->where('created_at',">=", $date_from)->where('created_at',"<=", $date_to);
          }])->first();
          if(!$product){
            $notFound[] = $code;
          }
        }
        /* return $notFound; */
      }else{
        $products = Product::whereIn('code', $p)
        ->with(['sales' => function($query) use($date_from, $date_to){
          $query->where('created_at',">=", $date_from)->where('created_at',"<=", $date_to);
        }, 'category'])->get();
      }
    }else{
      $products = Product::/* where('_status', '!=', 4)-> */with(['prices','sales' => function($query) use($date_from, $date_to){
        $query->where('created_at',">=", $date_from)->where('created_at',"<=", $date_to)->with('cash');
      }, 'stocks'])->get();
    }

    $workpoints = Workpoint::all();
    $categories = \App\ProductCategory::all();
    $arr_categories = array_column($categories->toArray(), "id");

    $result = $products->map(function($product) use($workpoints, $arr_categories, $categories){
      $vendidos = $product->sales->reduce(function($total, $sale){
        return $total + $sale->pivot->amount;
      }, 0);
      if($product->category->deep == 0){
          $familia = $product->category->name;
          $category = "";
      }else{
          $key = array_search($product->category->root, $arr_categories, true);
          $familia = $categories[$key]->name;
          $category = $product->category->name;
      }

      $a = [
        "Modelo" => $product->code,
        "Código" => $product->name,
        "Descripción" => $product->description,
        "Codigo de barras" => $product->barcode,
        "Piezas por caja" => $product->pieces,
        "Costo" => $product->cost,
        "Familia" => $familia,
        "Categoría" => $category,
        "Total" => $vendidos,
        "venta total" => $product->sales->unique('id')->values()->reduce(function($total, $sale){
          return $total + $sale->pivot->total;
        }, 0)
      ];
      /* $x = array_merge($a, $desgloce);
      return $x; */
      return $a;
    });
    $export = new ArrayExport($result->toArray());
    $date = new \DateTime();
    return Excel::download($export, $request->name.".xlsx");
    return response()->json($result);
  }

  public function getStocks(Request $request){

    $workpoint = WorkPoint::find($request->_workpoint);
    $workpoints = Workpoint::all();

    if(isset($request->products)){
      $p = array_column($request->products,"code");
      $products = [];
      $notFound = [];
      $variants = [];
      if($request->validate){
        foreach($p as $code){
          $product = Product::selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS categoryy')->whereHas('variants', function($query) use ($code){
            $query->where('barcode', $code);
          })->with(['stocks', 'prices', 'provider', 'status'])->first();
          if($product){
            $product->original = $code;
            $products[] = $product;
          }else{
            $product = Product::selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS categoryy')->where([['code', $code]/* , ['_status', '!=', 4] */])->orWhere([['name', $code], ['_status', '!=', 4]])->with(['stocks', 'prices', 'provider', 'status'])->first();
            if($product){
              $product->original = $code;
              $products[] = $product;
            }else{
              $notFound[] = $code;
            }
          }
        }
        /* return response()->json(["products" => $products, "variants" => $variants, "notFound" => $notFound]); */
      }else{
        $products = Product::selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS categoryy')->whereIn('code', $p)->orWhereIn('name', $p)
        ->whereHas('variants', function($query) use ($p){
          $query->whereIn('barcode', $p);
        })
        ->with(['stocks', 'prices', 'provider', 'status'])->get();
      }
    }else{
      $products = Product::selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS categoryy')->where('_status', '!=', 4)->with(['prices', 'stocks', 'provider', 'units'])->get();
    }
    $prepare_stocks = [];
    foreach($workpoints as $workpoint){
      $prepare_stocks["stock_".$workpoint->name] = 0;
    }
    $products = collect($products);
    $result = $products->map(function($product) use($workpoints, $prepare_stocks){
      $prices = $product->prices->sortBy('id')->reduce(function($res, $price){
        $res[$price->name] = $price->pivot->price;
        return $res;
      }, []);
      $stocks = $product->stocks->sortBy('id')->unique('id')->values()->reduce(function($res, $stock){
        $res["stock_".$stock->name] = $stock->pivot->stock;
        return $res;
      }, $prepare_stocks);
      $provider = $product->provider ? $product->provider->name : "";
      $a = [
        "original" => $product->original,
        "Modelo" => $product->code,
        "Código" => $product->name,
        "Fecha alta" => $product->created_at,
        "Status" => $product->status->name,
        "Unidad de medida" => $product->units->name,
        "Descripción" => $product->description,
        "Referencia" => $product->reference,
        "Código de barras" => $product->barcode,
        "Piezas por caja" => $product->pieces,
        "Costo" => $product->cost,
        "Sección" => $product->section,
        "Familia" => $product->family,
        "Categoría" => $product->categoryy,
        "Proveedor" => $provider,
        "stock" => $product->stocks->unique('id')->values()->reduce(function($total, $store){
          return $store->pivot->stock + $total;
        }, 0)
      ];
      /* return array_merge($a, $prices); */
      $a = array_merge($a, $stocks);
      return array_merge($a, $prices);
    });
    $export = new ArrayExport($result->toArray());
    $date = new \DateTime();
    return Excel::download($export, $request->name.".xlsx");
    return response()->json($result);
  }

  public function getCompras(Request $request){
    $products = DB::table('products')
      ->join('product_received', 'products.id', '=', 'product_received._product')
      ->join('invoices_received', 'product_received._order', '=', 'invoices_received.id')
      ->where('products._status', '!=', 4)
      ->where([['invoices_received.created_at', '>=', $request->date_from], ['invoices_received.created_at', '<=', $request->date_to]])
      ->groupBy('products.id')
      ->selectRaw('products.code as "modelo", products.name as "codigo", products.description as "descripcion", sum(product_received.amount) as "unidades", sum(product_received.total) as "compra"')
      ->get()->map(function($row){
        return [
          "Modelo" => $row->modelo,
          "Código" => $row->codigo,
          "Descripción" => $row->descripcion,
          "Unidades adquiridas" => $row->unidades,
          "Costo de compra" => $row->compra
        ];
      })->toArray();
    $export = new ArrayExport($products);
    return Excel::download($export, $request->name.".xlsx");
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

  public function seederSellers(){ // Función que actualiza el catalogo activo de vendedores
    $start = microtime(true);
    $cedis = \App\WorkPoint::find(1);
    $access = new AccessController($cedis->dominio);
    $sellers = $access->getSellers();
    try{
      if($sellers){
        DB::transaction(function() use ($sellers){
          DB::table('sellers')->delete(); // Eliminar los vendedores
          $success = DB::table('sellers')->insert($sellers); //Insertarlos de nuevo
        });
        return response()->json([
          "success" => true,
          "agentes" => count($sellers),
          "time" => microtime(true) - $start
        ]);
      }
      return response()->json(["message" => "No se obtuvo respuesta del servidor de factusol"]);
    }catch(Exception $e){
        return response()->json(["message" => "No se ha podido poblar la base de datos"]);
    }
  }

  public function exportExcelByProvider(Request $request){
    $sales = [];
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
    $providers = \App\Provider::whereIn('id',array_column($request->data, "_provider"))->get();
    foreach($providers as $provider){
      $_provider = $provider->id;
      $sales[$provider->name] = Product::with(["sales" => function($query) use($date_from, $date_to){
        $query->where('created_at',">=", $date_from)->where('created_at',"<=", $date_to);
      }])->whereHas('sales', function($query) use($date_from, $date_to){
        $query->where('created_at',">=", $date_from)->where('created_at',"<=", $date_to);
      })->where("_provider", $_provider)->get()->map(function($product){
        $vendidos = $product->sales->reduce(function($total, $sale){
          return $total + $sale->pivot->amount;
        }, 0);
        return [
          "id" => $product->id,
          "Modelo" => $product->code,
          "Código" => $product->name,
          "Descripción" => $product->description,
          "Codigo de barras" => $product->barcode,
          "Piezas por caja" => $product->pieces,
          "Costo" => $product->cost,
          "Total" => $vendidos,
          "venta total" => $product->sales->unique('id')->values()->reduce(function($total, $sale){
            return $total + $sale->pivot->total;
          }, 0)
        ];
      })->toArray();
    }

    return response()->json($sales);
    $format = [
        'A' => "NUMBER",
        'B' => "TEXT",
        'C' => "NUMBER",
        'D' => "TEXT",
        'E' => "NUMBER",
        'F' => "NUMBER",
        'G' => "NUMBER",
        'H' => "NUMBER",
        'I' => "NUMBER",
    ];
    $export = new WithMultipleSheetsExport($sales, $format);
    return Excel::download($export, "pedidos_proveedor".date("d-m-Y_H:m:s").".xlsx");
  }
}
