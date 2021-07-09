<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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
        $_workpoint = range(3,13);
        $_workpoint[] = 1;
        $workpoints = \App\WorkPoint::where("id", $_workpoints)->get();
        foreach($workpoints as $workpoint){
            $access = new AccessController($workpoint->dominio);
            $toInsert = $access->getAllWithdrawals()->map(function($row) use($workpoint){
                return [
                    "code" => $row["code"],
                    "_cash" => $row["_cash"],
                    "description" => $row["description"],
                    "total" => $row["total"],
                    "_provider" => $row["_provider"],
                    "_workpoint" => $workpoint->id,
                    "created_at" => $row["created_at"]
                ];
            });
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
        foreach($workpoints as $workpoint){
            $access = new AccessController($workpoint->dominio);
            $lastCode = Wildrawals::where('_workpoint', $workpoint->id)->max("code");
            $toInsert = $access->getLatestWithdrawals($lastCode)->map(function($row) use($workpoint){
                return [
                    "code" => $row["code"],
                    "_cash" => $row["_cash"],
                    "description" => $row["description"],
                    "total" => $row["total"],
                    "_provider" => $row["_provider"],
                    "_workpoint" => $workpoint->id,
                    "created_at" => $row["created_at"]
                ];
            });
            DB::transaction(
                function() use($toInsert){
                    Wildrawals::insert($toInsert);
                }
            );
        }
        return response()->json(["sucessful" => true]);
    }
}
