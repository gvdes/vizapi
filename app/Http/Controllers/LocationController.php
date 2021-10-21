<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\WorkPoint;
use App\Product;
use App\Celler;
use App\CellerSection;
use App\Exports\ArrayExport;
use Maatwebsite\Excel\Facades\Excel;

class LocationController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public $account = null;
    public function __construct(){
        $this->account = Auth::payload()['workpoint'];
    }

    /**
     * Create celler
     * @param object request
     * @param string request[].name
     * @param string request[]._workpoint
     * @param string request[]._type
     */
    public function createCeller(Request $request){
        $celler = \App\Celler::create([
            'name' => $request->name,
            '_workpoint' => $this->account->_workpoint,
            '_type' => $request->_type
        ]);
        return response()->json([
            'success' => true,
            'celler' => $celler
        ]);
    }

    public function updateCeller(Request $request){
        $celler = \App\Celler::find($request->_celler);
        if($celler){
            $celler->name = isset($request->name) ? $request->name : $celler->name;
            $celler->_workpoint = isset($request->_workpoint) ? $request->_workpoint : $celler->_workpoint;
            $celler->_type = isset($request->_type) ? $request->_type : $celler->_type;
            $res = $celler->save();
            return response()->json([ 'success' => $res ]);
        }
        return response()->json([ 'success' => false ]);
    }

    public function updateSection(Request $request){
        $section = CellerSection::find($request->_section);
        if($section){
            $section->name = isset($request->name) ? $request->name : $section->name;
            $section->alias = isset($request->alias) ? $request->alias : $section->alias;
            $section->path = isset($request->path) ? $request->path : $section->path;
            $section->root = isset($request->root) ? $request->root : $section->root;
            $section->deep = isset($request->deep) ? $request->deep : $section->deep;
            $section->details = isset($request->details) ? $request->details : $section->details;
            $section->root = isset($request->root) ? $request->root : $section->root;
            $res = $section->save();
            return response()->json([ 'success' => $res ]);
        }
        return response()->json([ 'success' => false ]);
    }

    /**
     * Create section
     * @param object request
     * @param string request[].name
     * @param string request[].alias
     * @param string request[].path
     * @param int request[].root
     * @param int request[].deep
     * @param json request[].details
     * @param int request[].celler
     */
    public function createSection(Request $request){
        $sections = [];
        if($request->autoincrement || $request->items > 1){
            $increment = true;
        }else{
            $increment = false;
        }
        if($request->root>0){
            $siblings = \App\CellerSection::where([['root', $request->root], ["alias", "LIKE", "%".$request->alias."%"]])->count();
            $root = \App\CellerSection::find($request->root);
            $items = isset($request->items) ? $request->items : 1;
            for($i = 0; $i<$items; $i++){
                $index = $siblings+$i+1;
                if(!$increment){
                    $index = '';
                }
                $section = \App\CellerSection::create([
                    'name' => $request->name.' '.$index,
                    'alias' => $request->alias.''.$index,
                    'path' => $root->path.'-'.$request->alias.''.$index,
                    'root' => $root->id,
                    'deep' => ($root->deep + 1),
                    'details' => json_encode($request->details),
                    '_celler' => $root->_celler
                ]);
                array_push($sections, $section);
            }
        }else{
            $siblings = \App\CellerSection::where([
                ['root', 0],
                ['_celler', $request->_celler]
            ])->count();
            $items = isset($request->items) ? $request->items : 1;
            for($i = 0; $i<$items; $i++){
                $index = $siblings+$i+1;
                if(!$increment){
                    $index = '';
                }
                $section = \App\CellerSection::create([
                    'name' => $request->name.' '.$index,
                    'alias' => $request->alias.''.$index,
                    'path' => $request->alias.''.$index,
                    'root' => 0,
                    'deep' => 0,
                    'details' => json_encode($request->details),
                    '_celler' => $request->_celler
                ]);
                array_push($sections, $section);
            }
        }

        return response()->json([
            'success' => true,
            'celler' => $sections
        ]);
    }

    public function deleteSection(Request $request){
        $section = CellerSection::find($request->_section);
        if($section){
            $section->children = $this->getDescendentsSection($section);
            $ids = $this->getIdsTree($section);
            $res = CellerSection::destroy($ids);
            return response()->json(["success" => true, "elementos" => $res]);
        }
        return response()->json([
            'success' => false,
            'msg' => 'No se ha encontrado la sección'
        ]);
    }

    public function removeLocations(Request $request){
        $res = [];
        if(isset($request->_section) && isset($request->_category)){
            /* ELIMINAR POR SECCION Y CATEGORIAS */
            $section = CellerSection::find($request->_section);
            $category = \App\ProductCategory::find($request->_category);
            if($section && $category){
                $section->children = $this->getDescendentsSection($section);
                $ids = $this->getIdsTree($section);
                $category->children = $this->getDescendentsCategory($category);
                $ids_categories = $this->getIdsTree($category);
                $sections = CellerSection::has('products')->whereIn('id', $ids)->get();
                /* $products_counted = Product::whereHas('locations', function($query){
                    $query->where('_workpoint', $this->account->_workpoint);
                })->whereIn('_category', $ids_categories)->count(); */
                $products = Product::has('locations')->whereIn('_category', $ids_categories)->get();
                $ids_sections = array_column($sections->toArray(), 'id');
                foreach($products as $product){
                    $product->locations()->wherePivotIn('_location', $ids_sections)->detach();
                }
            }
            $products_counted = 0;
            return response()->json(["res" => $res, "products" => $products_counted]);
        }
        if(isset($request->_section)){
            /* ELIMINAR POR SECCION TODO */
            $section = CellerSection::find($request->_section);
            if($section){
                $section->children = $this->getDescendentsSection($section);
                $ids = $this->getIdsTree($section);
                $sections = CellerSection::whereIn('id', $ids)->get();
                foreach($sections as $location){
                    $location->products()->detach();
                }
            }
            $products_counted = 0;
            return response()->json(["res" => true, "products" => $products_counted]);
        }
        if(isset($request->_category)){
            /* ELIMINAR POR CATEGORIAS TODOS LADOS */
            $category = \App\ProductCategory::find($request->_category);
            if($category){
                $category->children = $this->getDescendentsCategory($category);
                $ids = $this->getIdsTree($category);
            }
            $productos = Product::whereIn('_category', $ids)->whereHas('locations', function($query){
                $query->whereIn('_location', $ids);
            },'>', 0)->get();
            $products_counted = 0;
            return response()->json(["res" => true, "products" => $products_counted]);
        }

    }

    /**
     * Get section in celler or children's sections
     * @param object request
     * @param int request[].celler | null
     * @param int request[].section | null
     */
    public function getSections(Request $request){
        $celler = $request->_celler ? $request->_celler : null;
        $section = $request->_section ? CellerSection::find($request->_section) : null;
        $products = [];
        $paginate = $request->paginate ? $request->paginate : 20;
        if($celler && !$section){
            $section = CellerSection::where([
                ['_celler', '=' , $celler],
                ['deep', '=' , 0],
            ])->get()->map(function($section){
                $section->sections = CellerSection::where('root', $section->id)->get();
                return $section;
            });
            if($request->products){
                $sections = CellerSection::where([
                    ['_celler', '=' ,$celler],
                ])->get()->reduce(function($res, $section){
                    array_push($res, $section->id);
                    return $res;
                },[]);
                $products = \App\Product::whereHas('locations', function($query) use ($sections){
                    return $query->whereIn('_location', $sections);
                })->with(['locations' => function($query) use ($sections){
                    return $query->whereIn('_location', $sections);
                }])->paginate($paginate);
            }
        }else{
            $section->sections = CellerSection::where('root', $section->id)->get();
            if($request->products){
                $sections = $this->getSectionsChildren($section->id);
                $products = \App\Product::whereHas('locations', function($query) use ($sections){
                    return $query->whereIn('_location', $sections);
                })->with(['locations' => function($query) use ($sections){
                    return $query->whereIn('_location', $sections);
                }])->paginate($paginate);
            }
        }
        return response()->json([
            "sections" => $section,
            "products" => $products
        ]);
    }

    /**
     * Get cellers in workpoint
     */
    public function getCellers(){
        $workpoint = $this->account->_workpoint;
        if($workpoint){
            $cellers = \App\Celler::where('_workpoint', $workpoint)->get();
            
            $res = $cellers->map(function($celler){
                $celler->sections = \App\CellerSection::where([
                    ['_celler', '=',$celler->id],
                    ['deep', '=', 0],
                ])->get();
                return $celler;
            });
            return response()->json([
                'cellers' => $res,
            ]);
        }else{
            return response()->json([
                'msg' => 'Usuario no autenticado'
            ]);
        }
    }

    /**
     * Get section in celler or children's sections
     * @param object request
     * @param int request[].code
     */
    public function getProduct(Request $request){
        $code = $request->id;
        $stocks_required = $request->stocks;
        $date_from = new \DateTime();
        $date_from->setTime(0,0,0);
        $date_to = new \DateTime();
        $date_to->setTime(23,59,59);
        $product = Product::with(['locations' => function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        },'stocks' => function($query) use($stocks_required){
            if($stocks_required){
                $query->where([
                    ['_workpoint', $this->account->_workpoint]
                ])->orWhere('_type', 1);
            }else{
                $query->where([
                    ['_workpoint', $this->account->_workpoint]
                ]);
            }
        },'category', 'units', 'status',
        'cyclecounts' => function($query) use($date_to, $date_from){
            $query->where([["_workpoint", $this->account->_workpoint], ['_created_by', $this->account->_account], ['created_at', '>=', $date_from], ['created_at', '<=', $date_to]])
            ->whereIn("_status", [1,2])
            ->orWhere(function($query) use($date_to, $date_from){
                $query->whereHas('responsables', function($query){
                    $query->where([['_account', $this->account->_account], ['_workpoint', $this->account->_workpoint]]);
                })->where([['created_at', '>=', $date_from], ['created_at', '<=', $date_to]]);
            });
        }])->find($code);
        $stock = $product->stocks->filter(function($stocks){
            return $stocks->id == $this->account->_workpoint;
        })->values()->all();

        if(count($product->cyclecounts)>0){
            $product->stock = "Inventario";
            $product->min = $stock[0]->pivot->min;
            $product->max = $stock[0]->pivot->max;
            /* $_status = $stock[0]->pivot->_status; */
        }else if(count($stock)>0){
            $product->stock = $stock[0]->pivot->stock;
            $product->min = $stock[0]->pivot->min;
            $product->max = $stock[0]->pivot->max;
            /* $_status = $stock[0]->pivot->_status; */

        }else{
            $product->stock = 0;
            $product->min = 0;
            $product->max = 0;
            $_status = $product->_status;
        }
        /* $product->status = \App\ProductStatus::find($_status); */
        $product->stocks_stores = $product->stocks->filter(function($stocks){
            return $stocks->id != $this->account->_workpoint;
        })->values()->map(function($stock){
            return ["alias" => $stock->alias, "stocks" => $stock->pivot->stock];
        })->values()->all();
        if($product){
            return response()->json($product);
        }
        return response()->json([
            "msg" => "Producto no encontrado"
        ]);
    }

    /**
     * Set locations to multiples products
     * @param object request
     * @param int request._product
     * @param int request._section
     */
    public function setLocation(Request $request){
        $product = \App\Product::find($request->_product);
        if($product && !is_null($request->_section)){
            return response()->json([
                'success' => $product->locations()->toggle($request->_section)
            ]);            
        }
        return response()->json([
            'msg' => "Código no válido"
        ]);
    }

    /**
     * Set min and max to products
     * @param object request
     * @param int request.code
     * @param int request.min
     * @param int request.max
     */
    public function setMax(Request $request){
        $workpoint = WorkPoint::find($this->account->_workpoint);
        $product = Product::where('code', $request->code)->first();
        if($product){
            $product->stocks()->updateExistingPivot($workpoint->id, ['min' => $request->min, 'max' => $request->max]);
            return response()->json(["success" => true]);
        }
        return response()->json(["success" => false]);
    }

    public function setMassiveMax(Request $request){
        $workpoint = WorkPoint::find($request->_workpoint);
        $found = [];
        $notFound = [];
        foreach($request->products as $row){
            $product = Product::where('code', $row['Modelo'])->first();
            if($product){
                $product->stocks()->updateExistingPivot($workpoint->id, ['min' => $row['min'], 'max' => $row['max']]);
                $found[] = ["Modelo" => $product["code"], "Min" => $row['min'], "Max" => $row['max']];
            }else{
                $notFound[] = ["Modelo" => $row["Modelo"], "Min" => $row['min'], "Max" => $row['max']];
            }
        }
        return response()->json(["found" => $found, "notFound" => $notFound]);
    }

    public function index(){
        /**
         * INDICAR ALMACEN DONDE SE DESEA TRABAJAR
         */
        $counterProducts = Product::where('_status', '!=', 4)->count();
        $withStock = Product::whereHas('stocks', function($query){
            $query->where([["gen", ">", 0], ["_workpoint", $this->account->_workpoint]])
            ->orWhere([["exh", ">", 0], ["_workpoint", $this->account->_workpoint]]);
        })->where('_status', '!=', 4)->count();
        $withoutStock = Product::whereHas('stocks', function($query){
            $query->where([["gen", "<=", 0],["exh", "<=", 0], ["_workpoint", $this->account->_workpoint]]);
        })->where('_status', '!=', 4)->count();
        $withLocation = Product::whereHas('stocks', function($query){
            $query->where([["gen", ">", 0], ["_workpoint", $this->account->_workpoint]])
            ->orWhere([["exh", ">", 0], ["_workpoint", $this->account->_workpoint]]);
        })->whereHas('locations', function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        },'>',0)->where('_status', '!=', 4)->count();
        $withoutLocation = Product::whereHas('stocks', function($query){
            $query->where([["gen", ">", 0], ["_workpoint", $this->account->_workpoint]])
            ->orWhere([["exh", ">", 0], ["_workpoint", $this->account->_workpoint]]);
        })->whereHas('locations', function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        },'<=',0)->where('_status', '!=', 4)->count();
        $withLocationWithoutStock = Product::whereHas('stocks', function($query){
            $query->where([["gen", "<=", 0], ["gen", "<=", 0], ["_workpoint", $this->account->_workpoint]]);
        })->whereHas('locations', function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        },'>',0)->where('_status', '!=', 4)->count();

        $generalVsExhibicion = Product::whereHas('stocks', function($query){
            $query->where([["gen", ">", 0], ["exh", "<=", 0], ["_workpoint", $this->account->_workpoint]]);
        })->where('_status', '!=', 4)->count();
        if($this->account->_workpoint == 1){
            $cedis = Product::whereHas('stocks', function($query){
                $query->where([["stock", ">", 0], ["_workpoint", 2]]);
            })->where('_status', '!=', 4)->get();
        }else{
            $cedis = Product::whereHas('stocks', function($query){
                $query->where([["gen", ">", 0], ["_workpoint", 1]]);
            })->where('_status', '!=', 4)->get();
        }

        $general = Product::whereHas('stocks', function($query){
            $query->where([["gen", ">", 0], ["_workpoint", $this->account->_workpoint]]);
        })->where('_status', '!=', 4)->get();
        $generalVsCedis = [];
        $arr_general = array_column($general->toArray(), 'code');
        foreach($cedis as $product){
            $key = array_search($product->code, $arr_general);
            if($key === 0 || $key>0){
                //exist
            }else{
                array_push($generalVsCedis, $product);
            }
        }

        $sinMaximos = Product::whereHas('stocks', function($query){
            $query->where([["stock", ">", 0], ["min", "<=", 0], ["max", "<=", 0], ["_workpoint", $this->account->_workpoint]]);
        })->where('_status', '!=', 4)->count();

        $conMaximos = Product::whereHas('stocks', function($query){
            $query->where([["stock", ">", 0], ["min", ">", 0], ["max", ">", 0], ["_workpoint", $this->account->_workpoint]]);
        })->where('_status', '!=', 4)->count();

        $negativos = Product::whereHas('stocks', function($query){
            $query->where([["_workpoint", $this->account->_workpoint], ['gen', '<', 0]])->orWhere([["_workpoint", $this->account->_workpoint], ['exh', '<', 0]]);
        })->where('_status', '!=', 4)->count();

        return response()->json([
            ["alias" => "catalogo", "value" => $counterProducts, "description" => "Artículos en catalogo", "_excel" => 12],
            ["alias" => "stock", "value" => $withStock, "description" => "Con stock", "_excel" => 1],
            ["alias" => "withLocation", "value" => $withLocation, "description" => "Con stock y ubicados", "_excel" => 2],
            ["alias" => "withoutLocation", "value" => $withoutLocation, "description" => "Con stock sin ubicar", "_excel" => 3],
            ["alias" => "generalVsExhibicion", "value" => $generalVsExhibicion, "description" => "Con stock sin exhibir", "_excel" => 7],
            ["alias" => "sinMaximos", "value" => $sinMaximos, "description" => "Con stock sin máximos", "_excel" => 6],
            ["alias" => "conMaximos", "value" => $conMaximos, "description" => "Con stock con máximos", "_excel" => 9],
            ["alias" => "cedis", "value" => count($cedis), "description" => "Con stock en CEDIS", "_excel" => 11],
            ["alias" => "stock", "value" => $withoutStock, "description" => "Sin stock", "_excel" => 4],
            ["alias" => "withLocation", "value" => $withLocationWithoutStock, "description" => "Sin stock con ubicación", "_excel" => 5],
            ["alias" => "generalVsCedis", "value" => count($generalVsCedis), "description" => "Almacen general vs CEDIS", "_excel" => 8],
            ["alias" => "negativos", "value" => $negativos, "description" => "Productos en negativo", "_excel" => 10],
        ]);
    }

    public function getSectionsChildren($id){
        $sections = CellerSection::where('root', $id)->get();
        if(count($sections)>0){
            $res = $sections->map(function($section){
                $children = $this->getSectionsChildren($section->id);
                if(count($children)>0){
                }else{

                }
                return $children;
            })->reduce(function($res, $section){
                return array_merge($res, $section);
            }, []);
            array_push($res, $id);
            return $res;
        }else {
            return [$id];
        }
    }

    public function getAllSections(Request $request){
        $sections = \App\CellerSection::where('_celler', $request->_celler)->get();
        $roots = $sections->filter(function($section){
            return $section->root == 0;
        })->map(function($section) use ($sections){
            $section->children = $this->getChildren($sections, $section->id);
            return $section;
        })->values()->all();
        return response()->json($roots);
    }

    public function getChildren($sections, $root){
        return $sections->filter(function($section) use($root){
            return $section->root == $root;
        })->map(function($section) use ($sections){
            $section->children = $this->getChildren($sections, $section->id);
            return $section;
        })->values()->all();
    }

    public function getProductByCategory(Request $request){
        $category = \App\ProductCategory::where('root', 0)->get();
        $products = [];
        $filter = null;
        if(isset($request->_category)){
            $category = \App\ProductCategory::find($request->_category);
            $category->children = \App\ProductCategory::where('root', $request->_category)->get();
            $filter = $category->attributes;
        }
        if(isset($request->products)){
            if(isset($request->_category)){
                $ids = [$category->id];
                $ids = $category->children->reduce(function($res, $category){
                    array_push($res, $category->id);
                    return $res;
                }, $ids);
                $products = Product::whereIn('_category', $ids)->get();
            }else{
                $products = Product::limit(100)->get();
            }
        }
        return response()->json([
            "categories" => $category,
            "filter" => $filter,
            "products" => $products,
        ]);
    }

    public function updateStocks(){
        $workpoints = WorkPoint::whereIn('id', [1,13])->get();
        foreach($workpoints as $workpoint){
            $client = curl_init();
            curl_setopt($client, CURLOPT_URL, $workpoint->dominio."/access/public/celler/stock");
            curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
            $stocks = json_decode(curl_exec($client), true);
            if($stocks){
                $products = Product::with(["stocks" => function($query) use($workpoint){
                    $query->where('_workpoint', $workpoint->id);
                }])->where('_status', 1)->get();
                $codes_stocks = array_column($stocks, 'code');
                foreach($products as $product){
                    $key = array_search($product->code, $codes_stocks, true);
                    if($key === 0 || $key > 0){
                        $stock = count($product->stocks)>0 ? $product->stocks[0]->pivot->stock : false;
                        if(gettype($stock) == "boolean"){
                            $product->stocks()->attach($workpoint->id, ['stock' => $stocks[$key]["stock"], 'min' => 0, 'max' => 0]);
                        }elseif($stock != $stocks[$key]["stock"]){
                            $product->stocks()->updateExistingPivot($workpoint->id, ['stock' => $stocks[$key]["stock"]]);
                        }
                    }
                }
            }
        }
        return response()->json(["success" => true]);
    }

    public function getReport(Request $request){
        switch($request->_type){
            case 1:
                $res = $this->conStock();
                $name = "conStock";
                break;
            case 2:
                $res = $this->conStockUbicados();
                $name = "conStockUbicados";
                break;
            case 3:
                $res = $this->conStockSinUbicar();
                $name = "conStockSinUbicar";
                break;
            case 4:
                $res = $this->sinStock();
                $name = "sinStock";
                break;
            case 5:
                $res = $this->sinStockUbicados();
                $name = "sinStockUbicados";
                break;
            case 6:
                $res = $this->sinMaximos();
                $name = "sinMaximos";
                break;
            case 7:
                $res = $this->generalVsExhibicion();
                $name = "generalVsExhibicion";
                break;
            case 8:
                $res = $this->generalVsCedis();
                $name = "generalVsCedis";
                break;
            case 9:
                $res = $this->conMaximos();
                $name = "conMaximo";
                break;
            case 10:
                $res = $this->negativos();
                $name = "negativos";
                break;
            case 11:
                $res = $this->cedisStock();
                $name = "cedisStock";
                break;
            case 12:
                $res = $this->catologo();
                $name = "catalogoCompleto";
                break;
            default:
                $res = ["NOT"=>"4", "_" => "0", "FOUND" =>"4"];
                $name = "noFound";
                break;
        }
        $export = new ArrayExport($res);
        $date = new \DateTime();
        return Excel::download($export, $name.".xlsx");
    }

    public function demo(){
        $sections = \App\ProductCategory::where([['id', '>', 403], ['deep', 0]])->get();


        $families = \App\ProductCategory::where([['id', '>', 403], ['deep', 1]])->get()->groupBy('root');
        $_families = $families->map(function($family, $key){
            return array_column($family->toArray(), "id");
        });

        $categories = \App\ProductCategory::where([['id', '>', 403], ['deep', 2]])->get()->groupBy('root');
        $_categories = $categories->map(function($category, $key){
            return array_column($category->toArray(), "id");
        });

        $product = Product::where("_status", "!=", 4)->first();

        $key = $_families->filter(function($_family) use($product){
            $key = array_search($product->_category, $_family);
            return $key === 0 || $key > 0;
        });

        return response()->json($key);;
    }

    public function conStock(){
        $productos = Product::selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS categoryy')->
            with(['stocks' => function($query){
                $query->where([["gen", ">", 0], ["_workpoint", $this->account->_workpoint]])
                ->orWhere([["exh", ">", 0], ["_workpoint", $this->account->_workpoint]]);
        }, 'locations' => function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        }, 'category'])->whereHas('stocks', function($query){
            $query->where([["gen", ">", 0], ["_workpoint", $this->account->_workpoint]])
            ->orWhere([["exh", ">", 0], ["_workpoint", $this->account->_workpoint]]);
        })->where('_status', '!=', 4)->get();
        $res = $productos->map(function($producto){
            $locations = $producto->locations->reduce(function($res, $location){
                return $res.$location->path.",";
            }, '');
            return [
                "codigo" => $producto->name,
                "modelo" => $producto->code,
                "descripcion" => $producto->description,
                "Sección" => $producto->section,
                "Familia" => $producto->family,
                "Categoría" => $producto->categoryy,
                "piezas x caja" => $producto->pieces,
                "stock" => $producto->stocks[0]->pivot->stock,
                "general" => $producto->stocks[0]->pivot->gen,
                "exhibición" => $producto->stocks[0]->pivot->exh,
                "máximo" => $producto->stocks[0]->pivot->max,
                "minimo" => $producto->stocks[0]->pivot->min,
                "locations" => $locations,
            ];
        })->toArray();
        return $res;
    }

    public function negativos(){
        $productos = Product::selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS categoryy')->
        with(['stocks' => function($query){
            $query->where([["_workpoint", $this->account->_workpoint], ['gen', '<', 0]])->orWhere([["_workpoint", $this->account->_workpoint], ['exh', '<', 0]]);
        }, 'locations' => function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        }, 'category'])->whereHas('stocks', function($query){
            $query->where([["_workpoint", $this->account->_workpoint], ['gen', '<', 0]])->orWhere([["_workpoint", $this->account->_workpoint], ['exh', '<', 0]]);
        })->where('_status', '!=', 4)->get();
        $res = $productos->map(function($producto){
            $locations = $producto->locations->reduce(function($res, $location){
                return $res.$location->path.",";
            }, '');
            return [
                "codigo" => $producto->name,
                "modelo" => $producto->code,
                "descripcion" => $producto->description,
                "Sección" => $producto->section,
                "Familia" => $producto->family,
                "Categoría" => $producto->categoryy,
                "piezas x caja" => $producto->pieces,
                "stock" => $producto->stocks[0]->pivot->stock,
                "General" => $producto->stocks[0]->pivot->gen,
                "Exhibición" => $producto->stocks[0]->pivot->exh,
                "máximo" => $producto->stocks[0]->pivot->max,
                "minimo" => $producto->stocks[0]->pivot->min,
                "locations" => $locations,
            ];
        })->toArray();
        return $res;
    }

    public function sinStock(){
        $productos = Product::selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS categoryy')
        ->with(['stocks' => function($query){
            $query->where([["gen", "<=", 0],["exh", "<=", 0], ["_workpoint", $this->account->_workpoint]]);
        }, 'locations' => function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        }, 'category'])->whereHas('stocks', function($query){
            $query->where([["gen", "<=", 0],["exh", "<=", 0], ["_workpoint", $this->account->_workpoint]]);
        })->where('_status', '!=', 4)->get();
        $res = $productos->map(function($producto){
            $locations = $producto->locations->reduce(function($res, $location){
                return $res.$location->path.",";
            }, '');
            return [
                "Código" => $producto->name,
                "Modelo" => $producto->code,
                "Descripcion" => $producto->description,
                "Sección" => $producto->section,
                "Familia" => $producto->family,
                "Categoría" => $producto->categoryy,
                "Piezas x caja" => $producto->pieces,
                "Stock" => $producto->stocks[0]->pivot->stock,
                "Ubicaciones" => $locations
            ];
        })->toArray();
        return $res;
    }

    public function conStockUbicados(){
        $productos = Product::selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS categoryy')->with(['stocks' => function($query){
            $query->where([["gen", ">", "0"], ["_workpoint", $this->account->_workpoint]])
            ->orWhere([["exh", ">", 0], ["_workpoint", $this->account->_workpoint]]);
        }, 'locations' => function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        }, 'category'])->whereHas('stocks', function($query){
            $query->where([["gen", ">", 0], ["_workpoint", $this->account->_workpoint]])
            ->orWhere([["exh", ">", 0], ["_workpoint", $this->account->_workpoint]]);
        })->whereHas('locations', function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        },'>',0)->where('_status', '!=', 4)->get();
        $res = $productos->map(function($producto){
            $locations = $producto->locations->reduce(function($res, $location){
                return $res.$location->path.",";
            }, '');
            return [
                "Código" => $producto->name,
                "Modelo" => $producto->code,
                "Descripción" => $producto->description,
                "Sección" => $producto->section,
                "Familia" => $producto->family,
                "Categoría" => $producto->categoryy,
                "Piezas x caja" => $producto->pieces,
                "Stock" => $producto->stocks[0]->pivot->stock,
                "Ubicaciones" => $locations
            ];
        })->toArray();
        return $res;
    }

    public function conStockSinUbicar(){
        $productos = Product::selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS categoryy')->
        with(['stocks' => function($query){
            $query->where([["gen", ">", 0], ["_workpoint", $this->account->_workpoint]])
            ->orWhere([["exh", ">", 0], ["_workpoint", $this->account->_workpoint]]);
        }, 'locations' => function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        }, 'category'])->whereHas('stocks', function($query){
            $query->where([["gen", ">", 0], ["_workpoint", $this->account->_workpoint]])
            ->orWhere([["exh", ">", 0], ["_workpoint", $this->account->_workpoint]]);
        })->whereHas('locations', function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        },'<=',0)->where('_status', '!=', 4)->get();
        $res = $productos->map(function($producto){
            $locations = $producto->locations->reduce(function($res, $location){
                return $res.$location->path.",";
            }, '');
            return [
                "Código" => $producto->name,
                "Modelo" => $producto->code,
                "Descripción" => $producto->description,
                "Sección" => $producto->section,
                "Familia" => $producto->family,
                "Categoría" => $producto->categoryy,
                "Piezas x caja" => $producto->pieces,
                "Stock" => $producto->stocks[0]->pivot->stock,
                "Ubicaciones" => $locations
            ];
        })->toArray();
        return $res;
    }

    public function sinStockUbicados(){
        $productos = Product::selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS categoryy')->
        with(['stocks' => function($query){
            $query->where([["gen", "<=", "0"], ["_workpoint", $this->account->_workpoint]]);
        }, 'locations' => function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        }, 'category'])->whereHas('stocks', function($query){
            $query->where([["gen", "<=", 0], ["gen", "<=", 0], ["_workpoint", $this->account->_workpoint]]);
        })->whereHas('locations', function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        },'>',0)->where('_status', '!=', 4)->get();
        $res = $productos->map(function($producto){
            $locations = $producto->locations->reduce(function($res, $location){
                return $res.$location->path.",";
            }, '');
            return [
                "Código" => $producto->name,
                "Modelo" => $producto->code,
                "Descripción" => $producto->description,
                "Sección" => $producto->section,
                "Familia" => $producto->family,
                "Categoría" => $producto->categoryy,
                "Piezas x caja" => $producto->pieces,
                "stock" => $producto->stocks[0]->pivot->gen,
                "locations" => $locations
            ];
        })->toArray();
        return $res;
    }

    public function generalVsExhibicion(){
        $productos = Product::selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS categoryy')->with(['stocks' => function($query){
            $query->where([["gen", ">", "0"], ["exh", "<=", 0], ["_workpoint", $this->account->_workpoint]]);
        }, 'locations' => function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        }, 'category'])->whereHas('stocks', function($query){
            $query->where([["gen", ">", "0"], ["exh", "<=", 0], ["_workpoint", $this->account->_workpoint]]);
        })->where('_status', '!=', 4)->get();
        $res = $productos->map(function($producto){
            $locations = $producto->locations->reduce(function($res, $location){
                return $res.$location->path.",";
            }, '');
            return [
                "Código" => $producto->name,
                "Modelo" => $producto->code,
                "Descripción" => $producto->description,
                "Sección" => $producto->section,
                "Familia" => $producto->family,
                "Categoría" => $producto->categoryy,
                "Piezas por caja" => $producto->pieces,
                "GENERAL" => $producto->stocks[0]->pivot->gen,
                "EXHIBICION" => $producto->stocks[0]->pivot->exh,
                "Ubicaciones" => $locations
            ];
        })->toArray();
        return $res;
    }

    public function generalVsCedis(){
        if($this->account->_workpoint == 1){
            $cedis = Product::selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS categoryy')->with(['category', 'stocks' => function($query){
                $query->where("_workpoint", 2);
            }, 'locations' => function($query){
                $query->whereHas('celler', function($query){
                    $query->where('_workpoint', 2);
                });
            }])->whereHas('stocks', function($query){
                $query->where([["stock", ">", 0], ["_workpoint", 2]]);
            })->where('_status', '!=', 4)->get();
        }else{
            $cedis = Product::selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS categoryy')->with(['category', 'stocks' => function($query){
                $query->where("_workpoint", 1);
            }, 'locations' => function($query){
                $query->whereHas('celler', function($query){
                    $query->where('_workpoint', 1);
                });
            }])->whereHas('stocks', function($query){
                $query->where([["gen", ">", 0], ["_workpoint", 1]]);
            })->where('_status', '!=', 4)->get();
        }

        $general = Product::with(['category', 'locations' => function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        }])->whereHas('stocks', function($query){
            $query->where([["gen", ">", 0], ["_workpoint", $this->account->_workpoint]]);
        })->where('_status', '!=', 4)->get();

        $generalVsCedis = [];
        $arr_general = array_column($general->toArray(), 'code');
        foreach($cedis as $product){
            $key = array_search($product->code, $arr_general);
            if($key === 0 || $key>0){
                //exist
            }else{

                array_push($generalVsCedis, $product);
            }
        }

        $res = collect($generalVsCedis)->map(function($producto){
            $locations = $producto->locations->reduce(function($res, $location){
                return $res.$location->path.",";
            }, '');
            return [
                "Código" => $producto->name,
                "Modelo" => $producto->code,
                "Descripción" => $producto->description,
                "Sección" => $producto->section,
                "Familia" => $producto->family,
                "Categoría" => $producto->categoryy,
                "Piezas x caja" => $producto->pieces,
                "CEDIS" => intval($producto->stocks[0]->pivot->stock),
                "GENERAL" => 0,
                "Ubicaciones" => $locations
            ];
        })->toArray();
        return $res;
    }

    public function cedisStock(){
        $productos = Product::selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS categoryy')->
        with(['stocks' => function($query){
            $query->where([["stock", ">", "0"], ["_workpoint", 1]]);
        }, 'locations' => function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', 1);
            });
        }, 'category'])->whereHas('stocks', function($query){
            $query->where([["stock", ">", "0"], ["_workpoint", 1]]);
        })->where('_status', '!=', 4)->get();
        $res = $productos->map(function($producto){
            $locations = $producto->locations->reduce(function($res, $location){
                return $res.$location->path.",";
            }, '');
            return [
                "codigo" => $producto->name,
                "modelo" => $producto->code,
                "descripcion" => $producto->description,
                "Sección" => $producto->section,
                "Familia" => $producto->family,
                "Categoría" => $producto->categoryy,
                "piezas x caja" => $producto->pieces,
                "stock" => $producto->stocks[0]->pivot->stock,
                "máximo" => $producto->stocks[0]->pivot->max,
                "minimo" => $producto->stocks[0]->pivot->min,
                "locations" => $locations,
            ];
        })->toArray();
        return $res;
    }

    public function sinMaximos(){
        $productos = Product::selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS categoryy')->
        with(["stocks" => function($query){
            $query->where([["stock", ">", 0], ["min", "<=", 0], ["max", "<=", 0], ["_workpoint", $this->account->_workpoint]]);
        }, 'category'])->whereHas('stocks', function($query){
            $query->where([["stock", ">", 0], ["min", "<=", 0], ["max", "<=", 0], ["_workpoint", $this->account->_workpoint]]);
        })->where('_status', '!=', 4)->get();

        $res = $productos->map(function($producto){
            $locations = $producto->locations->reduce(function($res, $location){
                return $res.$location->path.",";
            }, '');
            return [
                "Código" => $producto->name,
                "Modelo" => $producto->code,
                "Descripción" => $producto->description,
                "Piezas por caja" => $producto->pieces,
                "Sección" => $producto->section,
                "Familia" => $producto->family,
                "Categoría" => $producto->categoryy,
                "Stock" => $producto->stocks[0]->pivot->stock,
                "Minimo" => $producto->stocks[0]->pivot->min,
                "Máximo" => $producto->stocks[0]->pivot->max
            ];
        })->toArray();
        
        return $res;
    }

    public function conMaximos(){
        $productos = Product::selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS categoryy')->
        with(["stocks" => function($query){
            $query->where([["stock", ">", 0], ["min", ">", 0], ["max", ">", 0], ["_workpoint", $this->account->_workpoint]]);
        }, 'category'])->whereHas('stocks', function($query){
            $query->where([["stock", ">", 0], ["min", ">", 0], ["max", ">", 0], ["_workpoint", $this->account->_workpoint]]);
        })->where('_status', '!=', 4)->get();

        $res = $productos->map(function($producto){
            $locations = $producto->locations->reduce(function($res, $location){
                return $res.$location->path.",";
            }, '');
            return [
                "Código" => $producto->name,
                "Modelo" => $producto->code,
                "Descripción" => $producto->description,
                "Piezas por caja" => $producto->pieces,
                "Sección" => $producto->section,
                "Familia" => $producto->family,
                "Categoría" => $producto->categoryy,
                "Stock" => $producto->stocks[0]->pivot->stock,
                "Minimo" => $producto->stocks[0]->pivot->min,
                "Máximo" => $producto->stocks[0]->pivot->max,
                "Diferencia" => $producto->stocks[0]->pivot->max - $producto->stocks[0]->pivot->min
            ];
        })->toArray();
        
        return $res;
    }

    public function catologoStocks(){
        $productos = Product::selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS categoryy')->
        with(['stocks' => function($query){
            $query->where("_workpoint", $this->account->_workpoint);
        }, 'locations' => function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        }, 'category'])->where('_status', '!=', 4)->get();
        $res = $productos->map(function($producto){
            $locations = $producto->locations->reduce(function($res, $location){
                return $res.$location->path.",";
            }, '');
            return [
                "codigo" => $producto->name,
                "modelo" => $producto->code,
                "descripcion" => $producto->description,
                "Sección" => $producto->section,
                "Familia" => $producto->family,
                "Categoría" => $producto->categoryy,
                "piezas x caja" => $producto->pieces,
                "stock" => count($producto->stocks)>0 ? $producto->stocks[0]->pivot->stock : 0,
                "máximo" => count($producto->stocks)>0 ? $producto->stocks[0]->pivot->max : 0,
                "minimo" => count($producto->stocks)>0 ? $producto->stocks[0]->pivot->min : 0,
                "locations" => $locations,
            ];
        })->toArray();
        return $res;
    }

    public function catologo(){
        $productos = Product::selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS categoryy')->
        with(['stocks' => function($query){
            $query->where("_workpoint", $this->account->_workpoint);
        }, 'locations' => function($query){
            $query->whereHas('celler', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            });
        } , 'category', 'status'])->where('_status', '!=', 4)->get();
        $res = $productos->map(function($producto){
            $locations = $producto->locations->reduce(function($res, $location){
                return $res.$location->path.",";
            }, '');
            return [
                "Código" => $producto->name,
                "Modelo" => $producto->code,
                "Descripción" => $producto->description,
                "Status" => $producto->status->name,
                "Sección" => $producto->section,
                "Familia" => $producto->family,
                "Categoría" => $producto->categoryy,
                "Piezas x caja" => $producto->pieces,
                "Stock" => count($producto->stocks)>0 ? $producto->stocks[0]->pivot->stock : 0,
                "General" => count($producto->stocks)>0 ? $producto->stocks[0]->pivot->gen : 0,
                "Exhibición" => count($producto->stocks)>0 ? $producto->stocks[0]->pivot->exh : 0,
                "locations" => $locations
            ];
        })->toArray();
        return $res;
    }

    public function catologo2(){
        $productos = Product::selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS categoryy')->
        with(['stocks', 'category', 'prices' => function($query){
            $query->where('_type', 7);
        }])->get();
        $result = $productos->map(function($producto){
            $body = [
                "Código" => $producto->name,
                "Modelo" => $producto->code,
                "Descripción" => $producto->description,
                "Sección" => $producto->section,
                "Familia" => $producto->family,
                "Categoría" => $producto->categoryy,
                "Piezas x caja" => $producto->pieces,
                "Precio AAA" => count($producto->prices)>0 ? $producto->prices[0]->pivot->price : 0,
                "Costo" => $producto->cost
            ];

            $stocks = [];
            foreach($producto->stocks as $stock){
                $stocks[$stock->name] = $stock->pivot->stock;
            }
            return array_merge($body, $stocks);
        })->toArray();
        return $result;
    }

    public function updateStocks2(){
        $workpoints = WorkPoint::whereIn('id', [1,2,3,4,5,6,7,8,9,10,11,12,13,17,19])->get();
        /* $workpoints = WorkPoint::whereIn('id', [19])->get(); */
        $success = 0;
        $_success = [];
        $res = [];
        foreach($workpoints as $workpoint){
            $access = new AccessController($workpoint->dominio);
            $stocks = $access->getStocks($workpoint->id);
            if($stocks){
                $success++;
                array_push($_success, $workpoint->alias);
                $products = Product::with(["stocks" => function($query) use($workpoint){
                    $query->where('_workpoint', $workpoint->id);
                }])->where('_status', '!=', 4)->get();
                $codes_stocks = array_column($stocks, 'code');
                foreach($products as $product){
                    $key = array_search($product->code, $codes_stocks, true);
                    if($key === 0 || $key > 0){
                        $gen = count($product->stocks)>0 ? $product->stocks[0]->pivot->gen : false;
                        $exh = count($product->stocks)>0 ? $product->stocks[0]->pivot->exh : false;
                        $des = count($product->stocks)>0 ? $product->stocks[0]->pivot->des : false;
                        $fdt = count($product->stocks)>0 ? $product->stocks[0]->pivot->fdt : false;
                        if(gettype($gen) == "boolean" || gettype($exh) == "boolean" || gettype($des) == "boolean" || gettype($fdt) == "boolean"){
                            $product->stocks()->attach($workpoint->id, ['stock' => $stocks[$key]["stock"], 'min' => 0, 'max' => 0, 'gen' => $stocks[$key]["gen"], 'exh' => $stocks[$key]["exh"], 'des' => $stocks[$key]["des"], 'fdt' => $stocks[$key]["fdt"]]);
                        }elseif($gen != $stocks[$key]["gen"] || $exh != $stocks[$key]["exh"] || $des != $stocks[$key]["des"] || $fdt != $stocks[$key]["fdt"]){
                            $product->stocks()->updateExistingPivot($workpoint->id, ['stock' => $stocks[$key]["stock"], 'gen' => $stocks[$key]["gen"], 'exh' => $stocks[$key]["exh"], 'des' => $stocks[$key]["des"], 'fdt' => $stocks[$key]["fdt"]]);
                        }
                    }
                }
            }
        }
        return response()->json(["completados" => $success, "tiendas" => $_success]);
    }

    public function getDescendentsSection($section){
        $children = CellerSection::where('root', $section->id)->get();
        if(count($children)>0){
            return $children->map(function($section){
                $section->children = $this->getDescendentsSection($section);
                return $section;
            });
        }
        return $children;
    }

    public function getDescendentsCategory($category){
        $children = \App\ProductCategory::where('root', $category->id)->get();
        if(count($children)>0){
            return $children->map(function($category){
                $category->children = $this->getDescendentsCategory($category);
                return $category;
            });
        }
        return $children;
    }

    public function getIdsTree($celler){
        $children = collect($celler->children);
        $children_ids = $children->reduce(function($ids, $celler){
            $ids_children = $this->getIdsTree($celler);
            return array_merge($ids, $ids_children);
        }, []);
        $id = [$celler->id];
        return array_merge($children_ids, $id);
    }

    public function getStructureCellers(Request $request){
        $cellers = Celler::with(['sections' => function($query){
            $query->where('deep', 0);
        }])->where('_workpoint', $request->_workpoint)->get();
        $sections = [];

        foreach($cellers as $celler){
            foreach($celler->sections as $section){
                $descendents = $this->getDescendentsSection2($section);
                if(count($descendents)>0){
                    /* $section->children = $descendents; */
                    $section->aux = explode(' ',$descendents[0]->name)[0].' ('.count($descendents).')';
                }else{
                    $section->children = null;
                    $section->aux = '';
                }
                $sections[] = $section;
            }
        }
        /* $export = new ArrayExport($sections);
        $date = new \DateTime();
        return Excel::download($export,"Tienda".$request->_workpoint.".xlsx"); */
        return response()->json($sections);
    }

    public function getDescendentsSection2($section){
        $children = CellerSection::where('root', $section->id)->get();
        if(count($children)>0){
            return $children->map(function($section){
                $descendents = $this->getDescendentsSection($section);
                if(count($descendents)>0){
                    $section->children = $descendents;
                    $section->aux = explode(' ',$descendents[0]->name)[0].' ('.count($descendents).')';
                }else{
                    $section->children = null;
                    $section->aux = '';
                }
                return $section;
            });
        }
        return $children;
    }

    public function setMassiveLocations(Request $request){
        $_workpoint = $request->_workpoint;
        $locations = CellerSection::whereHas('celler', function($query) use($_workpoint){
            $query->whereHas('workpoint', function($query) use($_workpoint){
                $query->where('_workpoint', $_workpoint);
            });
        })->get()->toArray();
        $locations_path = array_column($locations, 'path');
        $rows = $request->rows;
        $result = [];
        foreach($rows as $row){
            $product = Product::where('code', $row['code'])->first();
            if($product){
                $paths = [];
                $notFound = [];
                $found = [];
                $elements = explode(",",$row["location"]);
                foreach($elements as $path){
                    $final_path = implode('-T',explode("-",$path));
                    $final_path = implode('D-P',explode("D",$final_path));
                    $key = array_search( $final_path, $locations_path);
                    if($key === 0 || $key>0){
                        $paths[] = $locations[$key]['id'];
                        $found[] = $final_path;
                    }else{
                        $notFound[] = $final_path;
                    }
                }
                $product->locations()->syncWithoutDetaching($paths);
                $result[] = ["Modelo" => $product->code,"found" => implode(", ", $found), "notFound" => implode(", ", $notFound)];
            }else{
                $result[$row["code"]] = ["found" => "", "notFound" => $row["path"], "status" => "Codigo no encontrado"];
            }
        }
        return response()->json($result);
    }

    public function getLocations(Request $request){
        $result = [];
        foreach($request->products as $product){
            $res = Product::with(['locations' => function($query){
                $query->with("celler");
            }])->where("code", $product["code"])->first();
            if($res){
                $result[] = $res->locations->map(function($location) use($res){
                    return [
                        "code" => $res->code,
                        "location" => $location->path,
                        "_workpoint" => $location->celler->_workpoint
                    ];
                })->toArray();
            }
        }
        return response()->json(array_merge_recursive(...$result));
    }

    public function test(){
        $products = Product::selectRaw('products.*, getSection(products._category) AS Sección, getFamily(products._category) AS Familia, getCategory(products._category) AS Categoría')->where([['_provider', 15], ['_status', '!=', 4]])->get();
        return response()->json($products);

    }
}