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

    public function create(Request $request){ // Función para crear una nueva miniprinter (Impresora)
        /** VALIDACIONES */
        /*
            Datos necesarios:
                ip -> dirección local
                name -> nombre de la impresora
                _type -> tipo de impresora
        */
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

    public function update(Request $request){ // Función para actualizar los datos de una miniprinter (Impresora)
        /*
            Datos necesarios:
                ip -> dirección local
                name -> nombre de la impresora
                _type -> tipo de impresora
        */
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

    public function delete(Request $request){ // Función para eliminar una miniprinter (Impresora)
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

    public function getPrinters(){ // Función para obtener todas las impresoras disponibles para el usuario
        if($this->account->_rol == 1){
            // Se válida si el usuario es root, de ser el caso se le dará acceso a todas las impresoras
            $workpoints = \App\WorkPoint::whereHas('printers')->get();
        }else{
            // Si no es root solo se le dará acceso a las impresoras de su sucursal principal
            $workpoints = \App\WorkPoint::where('id', $this->account->_workpoint)->get();
        }
        // Se da el formato para el frontend, (Las impresoras se agrupan por sucursal antes de enviarse)
        $result = $workpoints->map(function($workpoint){
            $printers = \App\PrinterType::with(['printers' => function($query) use($workpoint){
                $query->where('_workpoint', $workpoint->id);
            }])->orderBy('id')->get();
            $workpoint->printers = $printers;
        });
        return response()->json($workpoints);
    }

    public function test(Request $request){ // Función para realizar una prueba de impresión
        $printer = \App\Printer::find($request->_printer); // Se busca la impresora a la cual se quiere hacer la prueba
        $cellerPrinter = new MiniPrinterController($printer->ip, $printer->_port); // Se hace la conexión con la impresora  le pones 9100
        $res = $cellerPrinter->demo(); // Se ejecuta la prueba de impresión
        return response()->json(["success" => $res]);
    }
}
