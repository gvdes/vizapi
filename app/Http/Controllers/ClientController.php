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

  public function update(){
    try{
      $workpoint = \App\WorkPoint::find(1);
      $access = new AccessController($workpoint->dominio);
      $clients = $access->getClients();
      $rows = [];
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
        return response()->json([
          "success" => true,
          "clients" => count($clients),
          "updated" => $rows
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
