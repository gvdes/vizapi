<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Http\Resources\User as UserResource;
use App\Http\Resources\Account as AccountResource;

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
        if (! $token = Auth::attempt($credentials)) {
            return response()->json(['message' => 'Usuario o contraseña incorrecta'], 200);
        }
        $payload = Auth::payload();
        $workpoints = Auth::user()->workpoints;
        $workpoint = $payload['workpoint']->id;
        if($payload['workpoint']->_status==4){
            return response()->json([
                'message' => 'La cuenta esta bloqueada',
                'token' => $token,
                'workpoints' => AccountResource::collection($workpoints)
            ]);
        }
        /**LOG 2 = INICIO DE SESIÓN */
        Auth::user()->log()->attach(4,[
            'details' => json_encode([
                '_accfrom' => $payload['workpoint']->_account
            ])
        ]);
        if($workpoint){
            $account = new AccountResource(\App\Account::with('status', 'rol', 'permissions', 'workpoint', 'user')->find($workpoint));
            $account->token = $token;
            return response()->json([
                'account' => $account,
                'workpoints' => AccountResource::collection($workpoints)
            ]);
        }else{
            return response()->json([
                'message' => 'No tiene acceso a esta tienda',
                'token' => $token,
                'workpoints' => AccountResource::collection($workpoints)
            ]);
        }
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