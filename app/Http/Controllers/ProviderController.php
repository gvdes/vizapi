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

    public function updateProviders(Request $request){
        $date = date("d_m_Y H_i_s", time());
        $start = microtime(true);
        $clouster = \App\Workpoint::find(1);
        $access_clouster = new AccessController($clouster->dominio);
        $date = $request->date ? $request->date : null;
        $providers = $access_clouster->getProviders($date);
        $rawProviders = $access_clouster->getRawProviders($date);
        $stores = $request->stores ? $request->stores : range(3,13);
        $sync = [];
        if($providers && $rawProviders){
            DB::transaction(function() use ($providers){
                foreach($providers as $provider){
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
                    $instance->id = $provider['id'];
                    $instance->rfc = $provider['rfc'];
                    $instance->name = $provider['name'];
                    $instance->alias = $provider['alias'];
                    $instance->description = $provider['description'];
                    $instance->adress = $provider['adress'];
                    $instance->phone = $provider['phone'];
                    $instance->save();
                }
            });
            /* $workpoints = \App\WorkPoint::whereIn('id', $stores)->get();
            foreach($workpoints as $workpoint){
                $access_store = new AccessController($workpoint->dominio);
                $sync[$workpoint->alias] = $access_store->syncProviders($rawProviders);
            }
            $format = [
                'A' => "NUMBER",
                'B' => "TEXT",
                'C' => "TEXT"
            ];
            $export = new WithMultipleSheetsExport($sync, $format);
            return Excel::download($export, "sincronizar_proveedores_".$date.".xlsx"); */
            return response()->json(["msg" => "Successful"]);
            /* return response()->json($sync); */
        }
        return response()->json(["message" => "No se obtuvo respuesta del servidor de factusol"]);
        /* try{
        }catch(\Exception $e){
            return response()->json(["message" => "No se ha podido poblar la base de datos de proveedores"]);
        } */
    }
}
