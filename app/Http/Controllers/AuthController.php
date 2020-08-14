<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;

class AuthController extends Controller{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct(){
        $this->middleware('auth:api', ['except' => ['login']]);
    }

    /**
     * Join user to workpoint
     * @param object request
     * @param string request[].nick
     * @param string request[].password
     * @return object [token|string]
     */

    public function login(Request $request){
        // Validate incoming request
        $msg = ['required' => 'Campo necesario'];
        $this->validate($request, [
            'nick' => 'required|string',
            'password' => 'required|string'
        ], $msg);

        $credentials = $request->only(['nick', 'password']);
        if (! $token = auth()->attempt($credentials)) {
            return response()->json(['message' => 'Usuario o contraseña'], 200);
        }
        $user = Auth::user();
        $workpoints = $user->workpoints;
        if(count($workpoints) == 1){
            $token =  Auth::claims(['workpoint' => $workpoint[0]->pivot])->tokenById($user->id);
        }else{
            $token =  Auth::claims(['workpoint' => null])->tokenById($user->id);
        }
        return response()->json([
            'token' => $token,
        ]);
    }
    /**
     * Join user to workpoint
     * @param object request
     * @param string request[].workpoint
     * @return object [token|string, workpoint|int]
     */
    
    public function joinWorkpoint(Request $request){
        $user = Auth::user();
        $workpoint = $user->workpoints->filter( function($workpoint) use ($request){
            return $workpoint->id == $request->workpoint;
        })->values()->all();
        if($workpoint){
            $token =  Auth::claims(['workpoint' => $workpoint[0]->pivot])->tokenById($user->id);
            return response()->json([
                "token" => $token,
                "workpoint" => $workpoint
            ]);
        }
        return response()->json([
            "message" => "El usuario no tiene acceso a ese punto de trabajo",
        ]);
    }
}