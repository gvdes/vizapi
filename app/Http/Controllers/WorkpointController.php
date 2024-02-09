<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\WorkPoint;

class WorkpointController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
    }

    public function index(){ // Función que retorna los puntos de trabajo en conjunto con su tipo
        $workpoint = WorkPoint::with('type')->get();
        return response()->json($workpoint);
    }
}
