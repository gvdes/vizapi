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
        $workpoints = \App\Account::with('status', 'rol', 'permissions', 'workpoint')->where('_account', Auth::user()->id)->get();
        /**LOG 4 = INICIO DE SESIÓN */
        $workpoint = $payload['workpoint'];
        Auth::user()->log()->attach(4,[
            'details' => json_encode([
                '_accfrom' => $payload['workpoint']->_account
            ])
        ]);
        $account = new AccountResource(\App\Account::with('workpoint', 'user')->find($workpoint->id));
        if($payload['workpoint']->_status==4){
            $account->token = null;
            return response()->json([
                'message' => 'Cuenta principal bloqueada',
                'server_status' => 500,
                'account' => $account,
                'workpoints' => AccountResource::collection($workpoints)
            ]);
        }else{
            $account->token = $token;
            return response()->json([
                'account' => $account,
                'server_status' => 200,
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