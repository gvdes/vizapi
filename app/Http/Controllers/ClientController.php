<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class ClientController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
    }

  public function Seeder(){
    try{
      $start = microtime(true);
      $client = curl_init();
      curl_setopt($client, CURLOPT_URL, "192.168.1.224:1618/access/public/clientes");
      curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
      curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
      $clients = json_decode(curl_exec($client), true);
      curl_close($client);
      if($clients){
        DB::transaction(function() use ($clients){
          $success = DB::table('client')->insert($clients);
        });
        return response()->json([
          "success" => true,
          "products" => count($clients),
          "time" => microtime(true) - $start
        ]);
      }
      return response()->json(["message" => "No se obtuvo respuesta del servidor de factusol"]);
    }catch(Exception $e){
        return response()->json(["message" => "No se ha podido poblar la base de datos"]);
    }
  }
}
