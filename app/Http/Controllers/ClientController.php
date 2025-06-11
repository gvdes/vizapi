<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Client;

class ClientController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
    }

  public function update(Request $request){ // Función para actualizar el catalogo maestro de clientes en MySQL y los ACCESS de todas las sucursales
    try{
      $workpoint = \App\WorkPoint::find(1); // Se obtienen los clientes de CEDIS

      $access = new AccessController($workpoint->dominio); // Se hace la conexión al ACCESS de la base de datos de CEDIS
      $date = isset($request->date) ? $request->date : null; // Se pregunta por la fecha desde la cual se hará la actualización
      $clients = $access->getClients($date); // Se obtienen todos los clientes que seran actualizados
      $rows = []; // <array> para guardar los clientes que fueron modificados
      $store_success = []; // <array> que alamcena las tiendas que se pudieron actualizar
      $store_fail = []; // <array> que alamcena las tiendas que no se pudieron actualizar
      if($clients){
        Client::insert($clients);
        // foreach($clients as $client){
        //   //Se creara o actualizarán los datos de los clientes
        //   $rows[] = Client::insert(
        //     ['id' => $client["id"],
        //       'name' => $client["name"],
        //       'phone' => $client["phone"],
        //       'email' => $client["email"],
        //       'rfc' => $client["rfc"],
        //       'address' => $client["address"],
        //       '_price_list' => $client["_price_list"],
        //       'created_at' => $client["created_at"]
        //     ]
        //   );
        // }
        //Obtener las sucursales que estan activas y son de tipo tienda
        // $stores = \App\Workpoint::whereIn([["active", true], ["_type", 2]])->get();
        // $raw_clients = $access->getRawClients($date);
        // if($raw_clients){
        //   foreach($stores as $store){ //Para cada una de las tiendas se enviaran los clientes
        //     $access_store = new AccessController($store->dominio); // Conexión a la sucursal
        //     $result = $access_store->syncClients($raw_clients); // Sincronizar clientes
        //     if($result){
        //       if($result["success"]){ // Evaluación de si se ha logrado enviar los clientes
        //         $store_success[] = $store->alias;
        //       }else{
        //         $store_fail[] = $store->alias;
        //       }
        //     }else{
        //       $store_fail[] = $store->alias;
        //     }
        //   }
        // }

        return response()->json([
          "clients" => count($clients),
        //   "Tiendas actualizadas" => $store_success,
        //   "Tiendas no actualizadas" => $store_fail,
        //   "updated" => $rows,
        //   "raw" => count($raw_clients)
        ]);
      }
      return response()->json(["message" => "No se obtuvo respuesta del servidor de factusol"]);
    }catch(Exception $e){
        return response()->json(["message" => "No se ha podido poblar la base de datos"]);
    }
  }

  public function autocomplete(Request $request){ // Función para buscar cliente por ID o coincidencia más acertada
    $clientes = Client::where('name', 'LIKE', '%'.$request->name.'%')->orWhere('id', $request->name)->limit(20)->get();
    return response()->json($clientes);
  }
}
