<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Provider;
use App\Exports\WithMultipleSheetsExport;
use Maatwebsite\Excel\Facades\Excel;

class ProviderController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public $account = null;
    public function __construct(){
        $this->account = Auth::payload()['workpoint'];
    }

    public function updateProviders(Request $request){// Función para actualizar el catalogo de proveedores
        $start = microtime(true);
        $clouster = \App\WorkPoint::find(1); // Se obtienen los datos de CEDIS SP (Lugar donde sacaremos los datos)
        $access_clouster = new AccessController($clouster->dominio); // Se hace la conexión al ACCESS de CEDISSP
        $date = $request->date ? $request->date : null; // Se valida si se manda la sucursal
        $providers = $access_clouster->getProviders($date); // Se obtienen los proveedores para almacenarlos en MySQL
        $rawProviders = $access_clouster->getRawProviders($date); // Se obtienen los proveedores de forma nativa para mandarlos a todas las sucursales
        /* $stores = $request->stores ? $request->stores : range(3,13); */
        $sync = [];
        if($providers && $rawProviders){
            DB::transaction(function() use ($providers){
                foreach($providers as $provider){
                    // Se obtiene el primer proveedor y si no existe se crea (La busqueda se realiza por ID)
                    $instance = Provider::firstOrCreate([
                        'id'=> $provider['id']
                    ], [
                        'rfc' => $provider['rfc'],
                        'name' => $provider['name'],
                        'alias' => $provider['alias'],
                        'description' => $provider['description'],
                        'adress' => $provider['adress'],
                        'phone' => $provider['phone']
                    ]);
                    // Datos para actualizar al proveedor
                    $instance->id = $provider['id'];
                    $instance->rfc = $provider['rfc'];
                    $instance->name = $provider['name'];
                    $instance->alias = $provider['alias'];
                    $instance->description = $provider['description'];
                    $instance->adress = $provider['adress'];
                    $instance->phone = $provider['phone'];
                    $instance->save(); // Se guardan los cambios
                }
            });
            return response()->json(["msg" => "Successful"]);
        }
        return response()->json(["message" => "No se obtuvo respuesta del servidor de factusol"]);
    }

    public function getAllOrders(){
        $start = microtime(true);
        $CEDIS = \App\WorkPoint::find(1);
        $access = new AccessController($CEDIS->dominio);
        $orders = $access->getAllProviderOrders();

        $products = \App\Product::all()->toArray();
        $variants = \App\ProductVariant::all()->toArray();
        $codes = array_column($products, 'code');
        $ids_products = array_column($products, 'id');
        $related_codes = array_column($variants, 'barcode');

        DB::transaction(function() use($orders, $codes, $products, $related_codes, $variants, $ids_products){
            foreach($orders as $order){
                $instance = \App\ProviderOrder::create([
                    "serie" => $order["serie"],
                    "code" => $order["code"],
                    "ref" => $order["ref"],
                    "_provider" => $order["_provider"],
                    "_status" => $order["_status"],
                    "description" => $order["description"],
                    "total" => $order["total"],
                    "received_at" => $order["received_at"],
                    "created_at" => $order["created_at"]
                    
                ]);
                $insert = [];
                foreach($order['body'] as $row){
                    $index = array_search($row["_product"], $codes);
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
        });
    }
}
