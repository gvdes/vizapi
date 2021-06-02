<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\User as UserResource;
use App\Http\Resources\Account as AccountResource;
use App\Http\Resources\Module as ModuleCollection;
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
        $this->account = Auth::payload()['workpoint'];
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
                $workpoints = $request->workpoints ? (object)$request->workpoints : [['id' => $user->_wp_principal, '_rol' => $user->_rol]];
            }
            foreach($workpoints as $workpoint){
                if($workpoint['id']!=404){
                    $account = \App\Account::create([
                        '_account' => $user->id,
                        '_workpoint' => $workpoint['id'],
                        '_rol' => $user->_rol == 1 ? 1 :$workpoint['_rol'],
                        '_status' => 1,
                    ]);
                    $permissions = isset($workpoint['permissions']) ? (object)$workpoint['permissions'] : \App\Roles::with('permissions_default')->find($account->_rol);
                    //Asociar permisos con cuentas
                    $permissions_default = collect($permissions->permissions_default);
                    $insert = $permissions_default->map(function($permission){
                        return $permission->id;
                    })->toArray();
                    $account->permissions()->attach($insert);
                }
            }
            /**LOG 1 = CREACIÓN DE CUENTA */
            /* $payload = Auth::payload();
            $user->log()->attach(1,[
                'details' => json_encode([
                    '_accfrom' => $payload['workpoint']->_account,
                    'data' => $request
                ])
            ]); */
            return $user->id;
            /* try{
            }catch(\Exception $e){
                return false;
            } */
        });
        return response()->json([
            'success' => $id
        ]);
    }

    /*********************
     ***** CONSULTAS *****
     *********************/

    public function dataToCreateUser(){
        $workpoints = \App\WorkPoint::all();
        $modules = new ModuleCollection(\App\Module::all());
        $roles = \App\Roles::all();
        return response()->json([
            'workpoints' => $workpoints,
            'roles' => $roles,
            'modules' => $modules
        ]);
    }

    public function me(){
        $payload = Auth::payload();
        return response()->json(new AccountResource(\App\Account::with('status', 'rol', 'permissions', 'workpoint', 'user')->find($payload['workpoint']->id)));
    }

    public function getAccounts(){
        $payload = Auth::payload();
        $session = $payload['workpoint'];
        $accounts = Account::with('rol', 'status', 'workpoint', 'user')->where('_workpoint',$session->_workpoint)->get();
        return response()->json(AccountResource::collection($accounts));
    }

    public function getAllUsers(){
        $users = User::with('rol', 'wp_principal')->get();
        return response()->json(UserResource::collection($users));
    }

    public function profile(){
        $user = Auth::user();
        return response()->json(new UserResource($user->fresh('rol','wp_principal','log', 'workpoints')));
    }

    /*********************
     ** ACTUALICACIONES **
     *********************/
    /**
     * Create user account
     * @param object request
     * @param string request[].id //id De la cuenta
     *  @param string request[]._status //Nuevo status a poner en la sucursal
     */

    public function updateStatus(Request $request){
        $payload = Auth::payload();
        $account = Account::find($request->id);
        $account->_status = $request->_status;
        $success = $account->save();
        /**LOG 6 = CAMBIO DE STATUS */
        $payload = Auth::payload();
        $user->log()->attach(6,[
            'details' => json_encode([
                '_accfrom' => $payload['workpoint']->_account,
                'status' => $request->_status,
                'workpoint' => $account->_workpoint
            ])
        ]);
        return response()->json([
            'success' => $success
        ]);
    }
    /**
     * Create user account
     * @param object request
     * @param string request[].password //Contraseña actual
     * @param string request[].new_password //Contraseña que se desea poner
     */
    public function updatePassword(Request $request){
        try{
            $user = Auth::user();
            if(app('hash')->check($request->password, $user->password)){
                if(app('hash')->check($request->new_password, $user->password)){
                    return response()->json(['message' => 'La nueva contraseña es igual a la antigua']);
                }else{
                    $user->password = app('hash')->make($request->new_password);
                    $user->change_password = false;
                    $save = $user->save();
                    /**LOG 3 = CAMBIO DE CONTRASEÑA */
                    $payload = Auth::payload();
                    $user->log()->attach(3,[
                        'details' => json_encode([
                            '_accfrom' => $payload['workpoint']->_account,
                        ])
                    ]);
                    return response()->json(["success" => $save]);
                }
            }else{
                return response()->json(['message' => 'La contraseña actual no es correcta']);    
            }
        }catch(\Exception $e){
            return response()->json(['message' => 'No se ha podido cambiar la contraseña']);
        }
    }

    /**
     * Create user account
     * @param object request
     * @param string request[].nick - null
     * @param file request[].picture - null
     * @param string request[].names - null
     * @param string request[].surname_pat - null
     * @param string request[].surname_mat - null
     * @param int request[]._wp_principal - null
     * @param int request[]._rol - null
     */

    public function updateInfo(Request $request){
        try{
            $user = Auth::user();
            $user->picture = $request->picture ? $request->picture : $user->picture;
            $user->names = $request->names ? $request->names : $user->names;
            $user->surname_pat = $request->surname_pat ? $request->surname_pat : $user->surname_pat;
            $user->surname_mat = $request->surname_mat ? $request->surname_mat : $user->surname_mat;
            $user->_wp_principal = $request->_wp_principal ? $request->_wp_principal : $user->_wp_principal;
            $user->_rol = $request->_rol ? $request->_rol : $user->_rol;
            $save = $user->save();
            /**LOG 2 = ACTUALIZACIÓN DE DATOS */
            $payload = Auth::payload();
            $user->log()->attach(2,[
                'details' => json_encode([
                    '_accfrom' => $payload['workpoint']->_account,
                    'data' => $request
                ])
            ]);
            return response()->json(["sucess" => $save]);
        }catch(\Exception $e){
            return response()->json(['message' => 'No se ha podido actualizar la información de la cuenta']);
        }
    }

    /**
     * Create user account
     * @param object request
     * @param string request[].nick - null
     * @param file request[].picture - null
     * @param string request[].names - null
     * @param string request[].surname_pat - null
     * @param string request[].surname_mat - null
     * @param int request[]._wp_principal - null
     * @param int request[]._rol - null
     */

    public function updateProfile(Request $request, $id){
        try{
            $user = User::find($id);
            $user->picture = $request->picture ? $request->picture : $user->picture;
            $user->names = $request->names ? $request->names : $user->names;
            $user->surname_pat = $request->surname_pat ? $request->surname_pat : $user->surname_pat;
            $user->surname_mat = $request->surname_mat ? $request->surname_mat : $user->surname_mat;
            $user->_wp_principal = $request->_wp_principal ? $request->_wp_principal : $user->_wp_principal;
            $user->_rol = $request->_rol ? $request->_rol : $user->_rol;
            $save = $user->save();
            /**LOG 2 = ACTUALIZACIÓN DE DATOS */
            $payload = Auth::payload();
            $user->log()->attach(2,[
                'details' => json_encode([
                    '_accfrom' => $payload['workpoint']->_account,
                    'data' => $request
                ])
            ]);
            return response()->json(["sucess" => $save]);
        }catch(\Exception $e){
            return response()->json(['message' => 'No se ha podido actualizar la información de la cuenta']);
        }
    }

    /**
     * Create user account
     * @param object request
     * @param int request[]._status - null
     * @param int request[]._rol - null
     * @param object request[].permissions[] - null
     */
    public function updateAccount(Request $request, $id){
        try{
            $account = Account::find($id);
            $account->_status = $request->_status ? $request->_status : $account->_status;
            $account->_rol = $request->_rol ? $request->_rol : $account->_rol;
            $permissions = $request->permissions ? $request->permissions : $account->permissions;
            $account->permissions()->sync($permissions);
            $save = $account->save();
            /**LOG 2 = ACTUALIZACIÓN DE DATOS */
            $payload = Auth::payload();
            /* $user->log()->attach(2,[
                'details' => json_encode([
                    '_accfrom' => $payload['workpoint']->_account,
                    'data' => $request
                ])
            ]); */
            return response()->json(['sucess' => $save]);
        }catch(\Exception $e){
            return response()->json(['message' => 'No se ha podido actualizar la información de la cuenta']);
        }
    }

    public function deletePermissions(Request $request){
        $accounts = Account::where([['_rol', $request->_rol], ['_workpoint', $request->_workpoint]])->get();
        $total = 0;
        foreach($accounts as $account){
            $account->permissions()->detach($request->permissions);
            $total++;
        }
        return response()->json(["changed" => $total]);
    }

    public function addPermissions(Request $request){
        if(isset($request->_workpoint)){
            $accounts = Account::where([['_rol', $request->_rol], ['_workpoint', $request->_workpoint]])->get();
        }else{
            $accounts = Account::where('_rol', $request->_rol)->get();
        }
        $total = 0;
        foreach($accounts as $account){
            $account->permissions()->syncWithoutDetaching($request->permissions);
            $total++;
        }
        return response()->json(["changed" => $total]);
    }

    public function addAcceso(Request $request){
        $users = User::where('_rol', 1)->get();
        $total = 0;
        foreach($users as $user){
            $account = \App\Account::where([
                ["_account", $user->id],
                ["_workpoint", $request->_workpoint]
            ])->first();
            if(!$account){
                $account = \App\Account::create([
                    '_account' => $user->id,
                    '_workpoint' => $request->_workpoint,
                    '_rol' => $request->rol,
                    '_status' => 1,
                ]);
                $permissions = \App\Roles::with('permissions_default')->find($account->_rol);
                //Asociar permisos con cuentas
                $permissions_default = collect($permissions->permissions_default);
                $insert = $permissions_default->map(function($permission){
                    return $permission->id;
                })->toArray();
                $account->permissions()->attach($insert);
                $total++;
            }
        }
        return response()->json(["add" => $total]);
    }

    public function getUsers(Request $request){
        if(isset($request->_rol)){
            $users = User::with('rol')->whereIn("_rol", $request->_rol)->whereHas('workpoints', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            })->orderBy('names', 'asc')->get();
        }else{
            $users = User::with('rol')->orderBy('names', 'asc')->get();
        }
        return response()->json($users);
    }
}
