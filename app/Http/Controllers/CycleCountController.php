<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\CycleCount;
use Illuminate\Support\Facades\Auth;

class CycleCountController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
    }

    public function create(Request $request){
        try {
            $payload = Auth::payload();
            $counter = DB::transaction(function() use($payload, $request){
                $counter = CycleCount::create([
                    'workpoint' => $payload['workpoint']->_workpoint,
                    'created_by' => $payload['workpoint']->_account,
                    '_type' => $request->_type,
                    'status' => 1,
                    'details' => json_encode()
                ]);
                return $counter;
            });
        }catch(\Exception $e){
            return response()->json(["message"=> "No se ha podido crear el contador"]);
        }
    }

    public function saveDetails(Request $request){
        try {
            $counter = CycleCount::find($request->id);
            $counter->details = json_encode($request->details);
            $counter->save();
            return response()->json(["success" => true]);
        }catch(\Exception $e){
            return response()->json(["message" => "No se han podido actualizar los datos"]);
        }
    }

    public function get(Request $request){
        try {
            $counter = CycleCount::with('products', 'type')->find($request->id);
            switch($counter->_status){
                case '1':
                    break;
                case '2':
                    break;
                case '3':
                    break;
            }
        } catch(\Exception $e){
            return response()->json(["message" => "No se ha podido"]);
        }
    }
}
