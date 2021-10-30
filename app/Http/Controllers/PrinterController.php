<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Printer;

class PrinterController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        $this->account = Auth::payload()['workpoint'];
    }

    public function create(Request $request){
        /** VALIDACIONES */
        $printer = DB::transaction( function () use ($request){
            $name = isset($request->name) ? $request->name : "Mini-printer";
            $ip = isset($request->ip) ? $request->ip : "192.168.10.55";
            $_type = isset($request->_type) ? $request->_type : 1;
            $printer = Printer::create([
                'name' => $name,
                'ip' => $ip,
                '_type' => $_type,
                '_workpoint' => $this->account->_workpoint
            ]);
            return $printer;
        });
        if($printer){
            $printer->load('type');
            return response()->json([
                "success" => true,
                "server_status" => 200,
                "printer" => $printer,
                "msg" => "Ok"
            ]);
        }else{
            return response()->json([
                "success" => false,
                "server_status" => 500,
                "printer" => NULL,
                "msg" => "No se pudo crear la impresora"
            ]);
        }
        try{
        }catch(\Exception $e){
            return false;
        }
    }

    public function update(Request $request){
        $printer = Printer::find($request->id);
        if($printer){
            $printer->name = isset($request->name) ? $request->name : $printer->name;
            $printer->ip = isset($request->ip) ? $request->ip : $printer->ip;
            $printer->_type = isset($request->_type) ? $request->_type : $printer->_type;
            $success = $printer->save();
            $printer->refresh("type");
            return response()->json([
                "success" => $success,
                "server_status" => 200,
                "printer" => $printer,
                "msg" => "Ok"
            ]);
        }
        return response()->json([
            "success" => false,
            "server_status" => 404,
            "printer" => false,
            "msg" => "No existe la impresora"
        ]);
    }

    public function delete(Request $request){
        $printer = Printer::find($request->id);
        if($printer){
            $success = $printer->delete();
            return response()->json([
                "success" => $success,
                "server_status" => 200,
                "msg" => "Ok"
            ]);
        }
        return response()->json([
            "success" => false,
            "server_status" => 404,
            "msg" => "No existe la impresora"
        ]);
    }
}
