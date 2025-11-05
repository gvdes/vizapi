<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Wildrawals;
use Carbon\Carbon;

class WithdrawalsController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
    }

    public function seeder(){
        //Obtener las retiradas de todos los puntos de trabajo de tipo sucursal activas
        // $workpoints = \App\WorkPoint::where([['_type',2], ['active', true]])->get();
        $workpoints = \App\WorkPoint::where('id',1)->get();

        $success = [];
        foreach($workpoints as $workpoint){
            $access = new AccessController($workpoint->dominio); //Conexión al servidor de la sucursal
            $wildrawals = $access->getAllWithdrawals(); //Obtener todas las retiradas
            // return $wildrawals;
            $toInsert = $this->toInsertFormat(collect($wildrawals),$workpoint); //Se formatean los datos para su inserción
            // return $toInsert;
            // $result = DB::transaction(
            //     function() use($toInsert){
            //         $success = Wildrawals::insert($toInsert);
            //         return $success ? true : false;
            //     }
            // );

            $result = DB::transaction(function () use ($toInsert) {
                $inserted = true;
                collect($toInsert)->chunk(1000)->each(function ($chunk) use (&$inserted) {
                    $ok = Wildrawals::insert($chunk->toArray());
                    if (!$ok) {
                        $inserted = false;
                    }
                });

                return $inserted;
            });
            $success[] = [
                "workpoint" => $workpoint->name,
                "success" => $result
            ];
        }

        return response()->json(["report" => $success]);
    }

    public function getLatest(){
        // Obtener las ultimas retiradas de todos los puntos de trabajo de tipo sucursal activas
        $workpoints = \App\WorkPoint::where([['_type', 2], ['active', true]])->get();
        $success = [];
        foreach($workpoints as $workpoint){
            $access = new AccessController($workpoint->dominio);//Conexión al servidor de la sucursal
            $lastCode = Wildrawals::where([['_workpoint', $workpoint->id],['created_at', '>=', '2022-01-12']])->max("code"); //Se puso una fecha estatica, se tendra que actualizar cada cambio de temporada
            $wildrawals = $access->getLatestWithdrawals($lastCode);//Obtener las ultimas retiradas de la sucursal
            $toInsert = $this->toInsertFormat(collect($wildrawals), $workpoint);//Se formatean los datos para su inserción
            $result = DB::transaction(
                function() use($toInsert){
                    $success = Wildrawals::insert($toInsert);
                    return $success ? true : false;
                }
            );
            $success[] = [
                "workpoint" => $workpoint->name,
                "success" => $result
            ];
        }
        return response()->json(["report" => $success]);
    }

    public function restore(){ // Función para eliminar las retiradas de los ultimos 7 días
        $day = date('Y-m-d', strtotime("-7 days")); // Se obtiene la fecha (Hace 7 días)
        $retiradas = Wildrawals::where("created_at", ">=", $day)->get(); // Se obtienen las retiradas que se eliminaran
        $success = DB::transaction(function() use($day){ // Transacción para validar que todas las retiradas seran eliminadas
            $delete = Wildrawals::where("created_at", ">=", $day)->delete(); // Eliminar datos
            return $delete ? true : false; // Se logro
        });
        return response()->json([
            "facturas" => count($retiradas),
            "success" => $success
        ]);
    }

    public function toInsertFormat($rows, $workpoint){ // Función para dar formato a las retiradas que seran insertadas
        $providers = \App\Provider::all(); // Se obtienen todos los proveedores
        $_providers = array_column($providers->toArray(), "id"); // Se obtiene un <array> con los ids de todos los proveedores
        return $rows->map(function($row) use($workpoint, $_providers){ // Se retorna un formato con las retiradas
            $key = array_search($row["_provider"], $_providers); // Se busca el proveedor
            if($key === 0 || $key > 0){
                return [
                    "code" => $row["code"],
                    "_cash" => $row["_cash"],
                    "description" => $row["description"],
                    "total" => $row["total"],
                    "_provider" => $row["_provider"],
                    "_workpoint" => $workpoint->id,
                    "created_at" =>  \Carbon\Carbon::parse(trim($row["created_at"]))->format('Y-m-d H:i:s')
                    // "created_at" => $row["created_at"] \Carbon\Carbon::parse(trim($item['created_at']))->format('Y-m-d H:i:s');
                ];
            }else{
                return [
                    "code" => $row["code"],
                    "_cash" => $row["_cash"],
                    "description" => $row["description"],
                    "total" => $row["total"],
                    "_provider" => 404,
                    "_workpoint" => $workpoint->id,
                    // "created_at" => $row["created_at"]
                    "created_at" =>  \Carbon\Carbon::parse(trim($row["created_at"]))->format('Y-m-d H:i:s')
                ];
            }
        })->toArray();
    }
}
