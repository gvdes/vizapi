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

  public function update(Request $request){
    try{
      $workpoint = \App\WorkPoint::find(1);

      $access = new AccessController($workpoint->dominio);
      $date = isset($request->date) ? $request->date : null;
      $clients = $access->getClients($date);
      $rows = [];
      $store_success = [];
      $store_fail = [];
      if($clients){
        foreach($clients as $client){
          $rows[] = Client::updateOrCreate(
            ['id' => $client["id"]],
            [
              'name' => $client["name"],
              'phone' => $client["phone"],
              'email' => $client["email"],
              'rfc' => $client["rfc"],
              'address' => $client["address"],
              '_price_list' => $client["_price_list"],
              'created_at' => $client["created_at"]
            ]
          );
        }

        $stores = \App\Workpoint::whereIn('id', [3,4,5,6,7,8,9,10,11,12,13,17])->get();
        $raw_clients = $access->getRawClients($date);
        if($raw_clients){
          foreach($stores as $store){
            $access_store = new AccessController($store->dominio);
            $result = $access_store->syncClients($raw_clients);
            if($result){
              if($result["success"]){
                $store_success[] = $store->alias;
              }else{
                $store_fail[] = $store->alias;
              }
            }else{
              $store_fail[] = $store->alias;
            }
          }
        }

        return response()->json([
          "clients" => count($clients),
          "Tiendas actualizadas" => $store_success,
          "Tiendas no actualizadas" => $store_fail,
          "updated" => $rows,
          "raw" => count($raw_clients)
        ]);
      }
      return response()->json(["message" => "No se obtuvo respuesta del servidor de factusol"]);
    }catch(Exception $e){
        return response()->json(["message" => "No se ha podido poblar la base de datos"]);
    }
  }

  public function getStoreClients(Request $request){
    $workpoint = \App\WorkPoint::find($request->_workpoint);
    $access = new AccessController($workpoint->dominio);
    $clients = $access->getClients();
    $notFound = [];
    foreach($clients as $client){
      $row = Client::find($client['id']);
      if($row){
        $row->store_name = $client['name'];
        $row->save();
      }else{
        $notFound[] = $client;
      }
    }
    return response()->json(["notFound" => $notFound]);
  }

  public function autocomplete(Request $request){
    $clientes = Client::where('name', 'LIKE', '%'.$request->name.'%')->orWhere('id', $request->name)->limit(20)->get();
    return response()->json($clientes);
  }
}
