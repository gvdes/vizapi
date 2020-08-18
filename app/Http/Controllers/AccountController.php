<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\User as UserResource;
use App\Http\Resources\Account as AccountResource;
use Illuminate\Support\Facades\Auth;
use App\User;
use App\Account;

class AccountController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
    }
    
    public function checkData($request){
        $msg = [
            'required' => 'Campo necesario',
            'nick.unique' => 'El nickname ya esta en uso',
            'max' => 'El campo esta limitado hasta :max caracteres',
        ];

        $validateData = $this->validate($request, [
            'nick' => 'required|unique:accounts|max:45',
            'names' => 'required|max:45',
            'surname_pat' => 'required|max:45',
            'surname_mat' => 'max:45',
            '_wp_principal' => 'required|exists:workpoints,id',
            '_rol' => 'required|exists:roles,id'
        ], $msg);
    }

    /**
     * Create user account
     * @param object request
     * @param string request[].nick
     * @param string request[].password - nick
     * @param file request[].picture - null
     * @param string request[].names
     * @param string request[].surname_pat
     * @param string request[].surname_mat - null
     * @param int request[]._wp_principal
     * @param int request[]._rol
     * @param object request[].workpoints[] - _wp_principal
     * @param int workpoint[]._workpoint
     * @param int workpoint[]._rol
     * @param int workpoint[].permissions - permissions_default ROL
     */

    public function create(Request $request){
        $this->checkData($request);
        $id = DB::transaction( function() use ($request){
            try{
                $user = User::create([
                    'nick'=> $request->nick,
                    'password'=> $request->password ? app('hash')->make($request->password) : app('hash')->make($request->nick),
                    'picture'=> $request->picture ? $request->picture : '',
                    'names'=> $request->names,
                    'surname_pat'=> $request->surname_pat,
                    'surname_mat'=> $request->surname_mat ? $request->surname_mat : '',
                    '_wp_principal'=> $request->_wp_principal,
                    '_rol'=> $request->_rol
                ]);
                if($user->_rol==1){
                    $workpoints = \App\WorkPoint::all();
                }else{
                    $workpoints = $request->workpoints ? (object)$request->workpoints : (object)['_workpoint' => $user->_wp_principal, '_rol' => $user->rol];
                }
                foreach($workpoints as $workpoint){
                    $account = \App\Account::create([
                        '_account' => $user->id,
                        '_workpoint' => $workpoint->id,
                        '_status' => 1,
                        '_rol' => $user->_rol == 1 ? 1 :$workpoint->_rol
                    ]);
                    $permissions = $workpoint->permissions ? (object)$workpoint->permissions : \App\Roles::with('permissions_default')->find($account->_rol);
                    //Asociar permisos con cuentas
                    $permissions_default = collect($permissions->permissions_default);
                    $insert = $permissions_default->map(function($permission){
                        return $permission->id;
                    })->toArray();
                    $account->permissions()->attach($insert);
                }
                return $user->id;
            }catch(\Exception $e){
                return false;
            }
        });
        return response()->json([
            'success' => $id
        ]);
    }

    public function me(){
        $payload = Auth::payload();
        return response()->json(new AccountResource(\App\Account::with('status', 'rol', 'permissions', 'workpoint', 'user')->find($payload['workpoint']->id)));
    }

    public function profile(){
        $user = Auth::user();
        return response()->json(new UserResource($user->fresh('rol','wp_principal','log', 'workpoints')));
    }

    public function updateStatus(Request $request){
        $payload = Auth::payload();
        $account = Account::find($payload['workpoint']->id);
        $account->_status = $request->_status;
        $success = $account->save();
        return response()->json([
            'success' => $success
        ]);
    }
}
