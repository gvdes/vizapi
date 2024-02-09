<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Provider;
use App\Invoice;
use App\Exports\WithMultipleSheetsExport;
use Maatwebsite\Excel\Facades\Excel;

class InvoicesReceivedController extends Controller{
    // FACTURAS RECIBIDAS 'COMPRAS'
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public $account = null;
    public function __construct(){
        $this->account = Auth::payload()['workpoint'];
    }

    public function getInvoices(){ // Función para obtener todas las compras de CEDISSP
        /* Lo uso para cuando es el cambio de temporada */
        $start = microtime(true);
        $CEDIS = \App\WorkPoint::find(1); // Se busca CEDISSP
        $access = new AccessController($CEDIS->dominio); // Se hace la conexión al ACCESS de la sucursal
        $invoices = $access->getAllInvoicesReceived(); // Se obtienen todas las compras que hay en ACCESS
        $success = $this->insertOrders($invoices); // Se insertan las compras en otro metodo
        return response()->json([
            "orders" => count($invoices),
            "success" => $success
        ]);
    }

    public function newOrders(){ // Función para actualizar las ultimas compras de CEDIS
        // Se obtiene el ultimo por serie
        $facturas = Invoice::selectRaw("serie, max(code) as code")->where('created_at', ">=", "2021-01-10")->groupBy('serie')->get()->toArray();
        // Se obtiene un <array> de los códigos por serie
        $array_series = array_column($facturas, "serie");
        // Se hace para todas las series en caso de que una serie no cuente con compras previas
        $series = range(1,9);
        // Se genera la estructura para ir a pedir los datos
        $last_data = array_map(function($serie) use($array_series, $facturas){
            $key = array_search($serie, $array_series, true);
            $code = $key !== false ? $facturas[$key]["code"] : 0;
            return ["serie" => $serie, "code" => $code];
        }, $series);

        $CEDIS = \App\WorkPoint::find(1); // Se busca CEDISSP
        $access = new AccessController($CEDIS->dominio); // Se hace la conexión al ACCESS de la secursal
        $invoices = $access->getNewInvoicesReceived($last_data); // Se obtiene las ultimas compras que hay en el ACCESS
        $success = $this->insertOrders($invoices); // Se insertan las compras en otro metodo
        return response()->json([
            "orders" => count($invoices),
            "success" => $success
        ]);
    }

    public function restore(){ // Función eliminar las compras de los ultimos 7 días y posteriormente agregarlas de nuevo
        $day = date('Y-m-d', strtotime("-7 days")); // Se obtiene la fecha (Hace 7 días)
        // Se obtiene las ultimas compras de estos últimos 7 días
        $facturas = Invoice::where("created_at", ">=", $today)->get();
        // Se obtiene los ids de dichas facturas para eliminar los cuerpos (productos que tienen esas facturas)
        $ids_facturas = array_column($facturas->toArray(), "id");
        $success = DB::transaction(function() use($ids_facturas, $today){ // Transacción para asegurar que se eliminen todos los cuerpos y encabezados
            $delete_header = Invoice::where("created_at", ">=", $today)->delete(); // Se eliminan los encabezados
            $delete_body = DB::table('product_received')->whereIn('_order', $ids_facturas)->delete(); // Se eliminan los cuerpos
            return $delete_header && $delete_body; // Se valida que se haya eliminado cuerpos y encabezados
        });
        return response()->json([
            "facturas" => count($facturas),
            "success" => $success
        ]);
    }

    public function insertOrders($orders){ // Se insertan las compras en este metodo
        $success = DB::transaction(function() use($orders){ // Se hace una transacción para garantizar que los encabezados y los cuerpos se inserten
            $products = \App\Product::where('_status', '!=', 4)->get()->toArray(); // Se obtienen todos los productos que no esten eliminados
            $variants = \App\ProductVariant::all()->toArray(); // Se obtienen todos los códigos relacionados
            $codes = array_column($products, 'code'); // Se hace un <array> de todos los modelos de los productos
            $ids_products = array_column($products, 'id'); // Se hace un <array> de todos los ids de los productos
            $related_codes = array_column($variants, 'barcode'); // Se hace un <array> de todos los códigos relacionados
            foreach($orders as $order){ // Todas las compras se intentarán almacenar
                $relationship = \App\ProviderOrder::where([["serie", $order["_serie_order"]], ["code", $order["_code_order"]]])->first(); // Se busca si se realizo un pedido a proveedor antes
                $_order = $relationship ? $relationship->id : null; // Si hay pedido a proveedor se alamcena el ID
                $instance = \App\Invoice::create([
                    "serie" => $order["serie"],
                    "code" => $order["code"],
                    "ref" => $order["ref"],
                    "_provider" => $order["_provider"],
                    "description" => $order["description"],
                    "total" => $order["total"],
                    "created_at" => $order["created_at"],
                    "_order" => $_order
                ]); // Se inserta el encabezado de la compra
                $insert = [];
                foreach($order['body'] as $row){ // Se insertar cuerpo de la compra
                    $index = array_search($row["_product"], $codes);
                    // Se busca si es un producto o un código relacionado
                    // Se ya se ha insertado el producto previamente se suma y/o promendia (precios y total)
                    if($index === 0 || $index > 0){
                        if(array_key_exists($products[$index]['id'], $insert)){
                            $amount = $row['amount'] + $insert[$products[$index]['id']]['amount'];
                            $total = $row['total'] + $insert[$products[$index]['id']]['total'];
                            $price = $total / $amount;
                            $insert[$products[$index]['id']] = [
                                "amount" => $amount,
                                "price" => $price,
                                "total" => $total
                            ];
                            }else{
                                $insert[$products[$index]['id']] = [
                                    "amount" => $row['amount'],
                                    "price" => $row['price'],
                                    "total" => $row['total']
                                ];
                            }
                    }else{
                        $index = array_search($row['_product'], $related_codes);
                        if($index === 0 || $index > 0){
                            $key = array_search($variants[$index]['_product'], $ids_products);
                            if(array_key_exists($variants[$index]['_product'], $insert)){
                                $insert[$variants[$index]['_product']] = [
                                    "amount" => $amount,
                                    "price" => $price,
                                    "total" => $total
                                ];
                            }else{
                                $insert[$variants[$index]['_product']] = [
                                    "amount" => $row['amount'],
                                    "price" => $row['price'],
                                    "total" => $row['total']
                                ];
                            }
                        }
                    }
                }
                $instance->products()->attach($insert);
            }
            return true;
        });
        return $success;
    }
}
