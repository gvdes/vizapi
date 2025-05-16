<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\WorkPoint;
use App\Product;
use App\ProductCategory;
use App\Celler;
use App\Sales;
use App\CashRegister;
use App\CellerSection;
use App\Exports\ArrayExport;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class ReportVHController extends Controller{

    public function index(){
        $seccion = ProductCategory::where([['deep',0],['alias','!=',null]])->get();
        $familia = ProductCategory::where([['deep',1],['alias','!=',null]])->get();
        $categoria = ProductCategory::where([['deep',2],['alias','!=',null]])->get();

        $res = [
            "seccion"=>$seccion,
            "familia"=>$familia,
            "categoria"=>$categoria
        ];
        return response()->json($res);
    }

    public function getReport(Request $request){
        // return $request->all();
        $workpoint = $request->workpoint;
        $secciones = $request->secciones;
        $catalogo = $this->catalogo($workpoint,$secciones);
        $conStock = $this->conStock($workpoint,$secciones);
        $conStockUbicados = $this->conStockUbicados($workpoint,$secciones);
        $conStockSinUbicar = $this->conStockSinUbicar($workpoint,$secciones);
        $sinStock = $this->sinStock($workpoint,$secciones);
        $sinStockUbicados = $this->sinStockUbicados($workpoint,$secciones);
        $sinMaximos = $this->sinMaximos($workpoint,$secciones);
        $generalVsExhibicion = $this->generalVsExhibicion($workpoint,$secciones);
        $generalVsCedis = $this->generalVsCedis($workpoint,$secciones);
        $conMaximos = $this->conMaximos($workpoint,$secciones);
        $negativos = $this->negativos($workpoint,$secciones);
        $cedisStock = $this->cedisStock($workpoint,$secciones);

        $res = [
            "catalogo"=>$catalogo,
            "conStock"=>$conStock,
            "conStockUbicados"=>$conStockUbicados,
            "conStockSinUbicar"=>$conStockSinUbicar,
            "sinStock"=>$sinStock,
            "sinStockUbicados"=>$sinStockUbicados,
            "sinMaximos"=>$sinMaximos,
            "generalVsExhibicion"=>$generalVsExhibicion,
            "generalVsCedis"=>$generalVsCedis,
            "conMaximos"=>$conMaximos,
            "negativos"=>$negativos,
            "cedisStock"=>$cedisStock,
        ];
        return response()->json($res);
    }


    public function catalogo($workpoint,$seccion){
        $productos = Product::with([
        'provider',
        'maker',
        'stocks' => function($query) use ($workpoint){
            $query->where("_workpoint", $workpoint);
        },
        'locations' => function($query)use ($workpoint){
            $query->whereHas('celler', function($query) use ($workpoint){
                $query->where('_workpoint', $workpoint);
            });
        } ,
        'category.familia.seccion',
         'status'
         ])->where('_status', '!=', 4)
         ->whereHas('category.familia.seccion', function($query) use ($seccion) {
            $query->whereIn('id',$seccion);
            })
         ->get();
        return $productos;
    }


    public function conStock($workpoint,$seccion){
        $productos = Product::with([
        'maker',
        'provider',
        'stocks' => function($query) use($workpoint){
                $query->where([["gen", ">", 0], ["_workpoint", $workpoint]])
                ->orWhere([["exh", ">", 0], ["_workpoint", $workpoint]]);
        },
        'locations' => function($query) use($workpoint){
            $query->whereHas('celler', function($query) use($workpoint){
                $query->where('_workpoint', $workpoint);
            });
        },
        'category.familia.seccion',
        'status'
        ])
        ->whereHas('stocks', function($query) use($workpoint){
            $query->where([["gen", ">", 0], ["_workpoint", $workpoint]])
            ->orWhere([["exh", ">", 0], ["_workpoint", $workpoint]]);
        })
        ->whereHas('category.familia.seccion', function($query) use ($seccion) {
            $query->whereIn('id',$seccion);
            })
        ->where('_status', '!=', 4)->get();
        return $productos;
    }

    public function conStockUbicados($workpoint,$seccion){
        $productos = Product::with([
        'provider',
        'maker',
        'stocks' => function($query) use ($workpoint){
            $query->where([["gen", ">", "0"], ["_workpoint", $workpoint]])
            ->orWhere([["exh", ">", 0], ["_workpoint", $workpoint]]);
        },
        'locations' => function($query) use ($workpoint){
            $query->whereHas('celler', function($query) use ($workpoint){
                $query->where('_workpoint', $workpoint);
            });
        },
        'category.familia.seccion',
        'status'
        ])
        ->whereHas('stocks', function($query) use ($workpoint){
            $query->where([["gen", ">", 0], ["_workpoint", $workpoint]])
            ->orWhere([["exh", ">", 0], ["_workpoint", $workpoint]]);})
        ->whereHas('locations', function($query) use ($workpoint){
            $query->whereHas('celler', function($query) use ($workpoint){
                $query->where('_workpoint', $workpoint);
            });},'>',0)
        ->whereHas('category.familia.seccion', function($query) use ($seccion) {
            $query->whereIn('id',$seccion);
            })
        ->where('_status', '!=', 4)->get();

        return $productos;
    }

    public function conStockSinUbicar($workpoint,$seccion){
        $productos = Product::with([
        'provider',
        'maker',
        'stocks' => function($query) use ($workpoint) {
            $query->where([["gen", ">", 0], ["_workpoint", $workpoint]])
            ->orWhere([["exh", ">", 0], ["_workpoint", $workpoint]]);
        },
        'locations' => function($query) use ($workpoint) {
            $query->whereHas('celler', function($query) use ($workpoint) {
                $query->where('_workpoint', $workpoint);
            });
        },
        'category.familia.seccion',
        'status'
        ])
        ->whereHas('stocks', function($query) use ($workpoint) {
            $query->where([["gen", ">", 0], ["_workpoint", $workpoint]])
            ->orWhere([["exh", ">", 0], ["_workpoint", $workpoint]]);
        })
        ->whereHas('locations', function($query) use ($workpoint) {
            $query->whereHas('celler', function($query) use ($workpoint) {
                $query->where('_workpoint', $workpoint);
            });},'<=',0)
        ->whereHas('category.familia.seccion', function($query) use ($seccion) {
            $query->whereIn('id',$seccion);
            })
        ->get();
        return $productos;
    }

    public function sinStock($workpoint,$seccion){
        $productos = Product::with([
            'provider',
            'maker',
            'stocks' => function($query) use ($workpoint) {
                $query->where([["gen", "<=", 0],["exh", "<=", 0], ["_workpoint", $workpoint]]);
            },
            'locations' => function($query) use ($workpoint) {
                $query->whereHas('celler', function($query) use ($workpoint) {
                    $query->where('_workpoint', $workpoint);
                });
            },
            'category.familia.seccion',
            'status'
            ])
            ->whereHas('stocks', function($query) use ($workpoint) {
                $query->where([["gen", "<=", 0],["exh", "<=", 0], ["_workpoint", $workpoint]]);})
            ->whereHas('category.familia.seccion', function($query) use ($seccion) {
                $query->whereIn('id',$seccion);
                })
            ->where('_status', '!=', 4)->get();


        return $productos;
    }

    public function sinStockUbicados($workpoint,$seccion){
        $productos = Product::with([
        'provider',
        'maker',
        'stocks' => function($query) use($workpoint) {
            $query->where([["stock", "<=", "0"], ["_workpoint", $workpoint]]);
        },
        'locations' => function($query)use($workpoint){
            $query->whereHas('celler', function($query) use($workpoint) {
                $query->where('_workpoint', $workpoint);
            });
        },
        'category.familia.seccion','status'])
        ->whereHas('stocks', function($query) use($workpoint) {
            $query->where([["stock", "<=", 0], ["stock", "<=", 0], ["_workpoint", $workpoint]]);
        })
        ->whereHas('locations', function($query) use($workpoint) {
            $query->whereHas('celler', function($query) use($workpoint) {
                $query->where('_workpoint', $workpoint);});},'>',0)
        ->whereHas('category.familia.seccion', function($query) use ($seccion) {
            $query->whereIn('id',$seccion);
            })
        ->where('_status', '!=', 4)
        ->get();
        return $productos;
    }

    public function sinMaximos($workpoint,$seccion){ // Función que retorna todos los productos que no tiene máximo y si stock
        $productos = Product::with([
        'provider',
        'maker',
        "stocks" => function($query) use($workpoint) {
            $query->where([["stock", ">", 0], ["min", "<=", 0], ["max", "<=", 0], ["_workpoint", $workpoint]]);
        },
        'category.familia.seccion',
        'locations' => function($query)use($workpoint){
            $query->whereHas('celler', function($query) use($workpoint) {
                $query->where('_workpoint', $workpoint);
            });
        },
        'status'])
        ->whereHas('stocks', function($query) use($workpoint) {
            $query->where([["stock", ">", 0], ["min", "<=", 0], ["max", "<=", 0], ["_workpoint", $workpoint]]);
        })
        ->whereHas('category.familia.seccion', function($query) use ($seccion) {
            $query->whereIn('id',$seccion);
        })
        ->where('_status', '!=', 4)
        ->get();
        return $productos;
    }

    public function generalVsExhibicion($workpoint,$seccion){
        $productos = Product::with([
        'provider',
        'maker',
        'stocks' => function($query) use ($workpoint) {
            $query->where([["gen", ">", "0"], ["exh", "<=", 0], ["_workpoint", $workpoint]]);
        },
        'locations' => function($query) use ($workpoint) {
            $query->whereHas('celler', function($query) use ($workpoint) {
                $query->where('_workpoint', $workpoint);
            });
        },
        'category.familia.seccion',
        'status'
        ])
        ->whereHas('stocks', function($query) use ($workpoint) {
            $query->where([["gen", ">", "0"], ["exh", "<=", 0], ["_workpoint", $workpoint]]);
        })
        ->whereHas('category.familia.seccion', function($query) use ($seccion) {
            $query->whereIn('id',$seccion);
        })
        ->where('_status', '!=', 4)
        ->get();

        return $productos;
    }

    public function generalVsCedis($workpoint,$seccion){
        $products = Product::with([
            'locations' => function($query) use ($workpoint) {
                $query->whereHas('celler', function($query) use ($workpoint) {
                    $query->where('_workpoint', $workpoint);
                });
            },
            'provider',
            'maker',
            'category.familia.seccion',
            'status',
            'stocks' => function($query) use ($workpoint) { //Se obtiene el stock de la sucursal
                $query->whereIn('_workpoint',[1,2,$workpoint])->distinct();
            }
            ])
            ->whereHas('categories.familia.seccion', function($query) use ($seccion) { // Aplicamos el filtro en la relación seccion
                $query->whereIn('id',$seccion);
            })
            ->whereHas('stocks', function($query) { // Solo productos con stock mayor a 0 en el workpoint
                $query->whereIn('_workpoint', [1, 2])
                      ->where('stock', '>', 0); // Filtra solo aquellos con stock positivo
            })
            ->whereHas('stocks', function($query) use ($workpoint) { // Solo productos con stock mayor a 0 en el workpoint
                $query->where('_workpoint',$workpoint)
                      ->where('stock', '=', 0); // Filtra solo aquellos con stock positivo
            })
            ->where('_status','!=',4)->get();
        return $products;
    }

    public function conMaximos($workpoint,$seccion){
        $productos = Product::with([
        'provider',
        'maker',
        "stocks" => function($query) use ($workpoint) {
            $query->where([["stock", ">", 0], ["min", ">", 0], ["max", ">", 0], ["_workpoint", $workpoint]]);
        },
        'locations' => function($query)use($workpoint){
            $query->whereHas('celler', function($query) use($workpoint) {
                $query->where('_workpoint', $workpoint);
            });
        },
        'category.familia.seccion',
        'status'
        ])->whereHas('stocks', function($query) use ($workpoint) {
            $query->where([["stock", ">", 0], ["min", ">", 0], ["max", ">", 0], ["_workpoint", $workpoint]]);
        })
        ->whereHas('categories.familia.seccion', function($query) use ($seccion) { // Aplicamos el filtro en la relación seccion
            $query->whereIn('id',$seccion);
        })
        ->where('_status', '!=', 4)
        ->get();
        return $productos;
    }

    public function negativos($workpoint,$seccion){

        $productos = Product::with([
        'maker',
        'provider',
        'stocks' => function($query) use ($workpoint) {
            $query->where([["_workpoint", $workpoint], ['gen', '<', 0]])->orWhere([["_workpoint", $workpoint], ['exh', '<', 0]]);
        },
        'locations' => function($query) use ($workpoint) {
            $query->whereHas('celler', function($query) use ($workpoint) {
                $query->where('_workpoint', $workpoint);
            });
        },
        'category.familia.seccion',
        'status'
        ])->whereHas('stocks', function($query) use ($workpoint) {
            $query->where([["_workpoint", $workpoint], ['gen', '<', 0]])
            ->orWhere([["_workpoint", $workpoint], ['exh', '<', 0]]);
        })
        ->whereHas('categories.familia.seccion', function($query) use ($seccion) { // Aplicamos el filtro en la relación seccion
            $query->whereIn('id',$seccion);
        })
        ->where('_status', '!=', 4)
        ->get();
        return $productos;
    }

    public function cedisStock($workpoint,$seccion){
        $products = Product::with([
            'categories.familia.seccion',
            'status',
            'locations' => function($query)use($workpoint){
                $query->whereHas('celler', function($query) use($workpoint) {
                    $query->where('_workpoint', $workpoint);
                });
            },
            'stocks' => function($query) use ($workpoint) { //Se obtiene el stock de la sucursal
                $query->whereIn('_workpoint',[1,2])->distinct();}
            ])
            ->whereHas('categories.familia.seccion', function($query) use ($seccion) { // Aplicamos el filtro en la relación seccion
                $query->whereIn('id',$seccion);
            })
            ->whereHas('stocks', function($query) { // Solo productos con stock mayor a 0 en el workpoint
                $query->whereIn('_workpoint', [1, 2])
                      ->where('stock', '>', 0); // Filtra solo aquellos con stock positivo
            })
            ->where('_status','!=',4)->get();
        return $products;
    }

}
