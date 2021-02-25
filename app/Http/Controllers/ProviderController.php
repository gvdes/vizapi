<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

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

    public function seeder(){
        $start = microtime(true);
        $workpoint = \App\WorkPoint::find($this->account->_workpoint);
        $client = curl_init();
        //curl_setopt($client, CURLOPT_URL, "192.168.1.24/access/public/provider");
        curl_setopt($client, CURLOPT_URL, $workpoint->dominio."/access/public/provider");
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        $providers = json_decode(curl_exec($client), true);
        curl_close($client);
        try{
            if($providers){
                $success = DB::transaction(function() use ($providers){
                    $success = DB::table('providers')->insert($providers);
                    return $success;
                });
                return response()->json([
                    "success" => $success,
                    "products" => count($providers),
                    "time" => microtime(true) - $start
                ]);
            }
            return response()->json(["message" => "No se obtuvo respuesta del servidor de factusol"]);
        }catch(\Exception $e){
            return response()->json(["message" => "No se ha podido poblar la base de datos de proveedores"]);
        }
    }
}
