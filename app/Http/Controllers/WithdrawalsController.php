<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Wildrawals;

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
        $_workpoints = range(3,13);
        $_workpoints[] = 1;
        $_workpoints[] = 17;
        $workpoints = \App\WorkPoint::whereIn("id", $_workpoints)->get();
        $providers = \App\Provider::all();
        $_providers = array_column($providers->toArray(), "id");
        foreach($workpoints as $workpoint){
            $access = new AccessController($workpoint->dominio);
            $toInsert = collect($access->getAllWithdrawals())->map(function($row) use($workpoint, $_providers){
                $key = array_search($row["_provider"], $_providers);
                if($key === 0 || $key > 0){
                    return [
                        "code" => $row["code"],
                        "_cash" => $row["_cash"],
                        "description" => $row["description"],
                        "total" => $row["total"],
                        "_provider" => $row["_provider"],
                        "_workpoint" => $workpoint->id,
                        "created_at" => $row["created_at"]
                    ];
                }else{
                    return [
                        "code" => $row["code"],
                        "_cash" => $row["_cash"],
                        "description" => $row["description"],
                        "total" => $row["total"],
                        "_provider" => 404,
                        "_workpoint" => $workpoint->id,
                        "created_at" => $row["created_at"]
                    ];
                }
            })->toArray();
            DB::transaction(
                function() use($toInsert){
                    Wildrawals::insert($toInsert);
                }
            );
        }
        return response()->json(["sucessful" => true]);
    }

    public function getLatest(){
        $_workpoint = range(3,13);
        $_workpoint[] = 1;
        $workpoints = \App\WorkPoint::where("id", $_workpoints)->get();
        return response()->json($workpoints);
        $providers = \App\Provider::all();
        $_providers = array_column($providers->toArray(), "id");
        foreach($workpoints as $workpoint){
            $access = new AccessController($workpoint->dominio);
            $lastCode = Wildrawals::where('_workpoint', $workpoint->id)->max("code");
            $toInsert = $access->getLatestWithdrawals($lastCode)->map(function($row) use($workpoint, $_providers){
                $key = array_search($row["_provider"], $_providers);
                if($key === 0 || $key > 0){
                    return [
                        "code" => $row["code"],
                        "_cash" => $row["_cash"],
                        "description" => $row["description"],
                        "total" => $row["total"],
                        "_provider" => $row["_provider"],
                        "_workpoint" => $workpoint->id,
                        "created_at" => $row["created_at"]
                    ];
                }else{
                    return [
                        "code" => $row["code"],
                        "_cash" => $row["_cash"],
                        "description" => $row["description"],
                        "total" => $row["total"],
                        "_provider" => 404,
                        "_workpoint" => $workpoint->id,
                        "created_at" => $row["created_at"]
                    ];
                }
            });
            /* DB::transaction(
                function() use($toInsert){
                    Wildrawals::insert($toInsert);
                }
            ); *//*  */
            return response()->json(["sucessful" => $toInsert]);
        }
        return response()->json(["sucessful" => true]);
    }
}
