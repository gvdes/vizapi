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

    public function create(Request $request){ // Función para crear una cuenta de usuario
        $this->checkData($request); // Validación de los datos 
        $id = DB::transaction( function() use ($request){ // Se realiza una trasacción para asegurar que todos los cambios sean realizado
            $user = User::create([
                'nick'=> $request->nick,
                'password'=> $request->password ? app('hash')->make($request->password) : app('hash')->make($request->nick),
                'picture'=> $request->picture ? $request->picture : '',
                'names'=> $request->names,
                'surname_pat'=> $request->surname_pat,
                'surname_mat'=> $request->surname_mat ? $request->surname_mat : '',
                '_wp_principal'=> $request->_wp_principal,
                '_rol'=> $request->_rol
            ]); // Se crea el usuario con todos los datos necesarios

            if($user->_rol==1 || $user->_rol==8){
                // Si el usuario sera root se obtiene la info de todas las tiendas para darle el acceso
                $workpoints = \App\WorkPoint::all();
            }else{
                // Solo se le dara acceso a la tienda que nos indican en el parametro workpoints
                $workpoints = $request->workpoints ? (object)$request->workpoints : [['id' => $user->_wp_principal, '_rol' => $user->_rol]];
            }

            foreach($workpoints as $workpoint){ // Se crea el acceso para cada una de las sucursales a las que se le dara acceso
                if($workpoint['id']!=404){
		    $_rool = ($user->_rol==1||$user->_rol==8) ? $user->_rol : $workpoint['_rol'];
                    $account = \App\Account::create([
                        '_account' => $user->id,
                        '_workpoint' => $workpoint['id'],
                        // '_rol' => $user->_rol == 1 ? 1 :$workpoint['_rol'],
                        '_rol' => $_rool,
                        '_status' => 1,
                    ]);
                    //Se buscan los permisos por default para el rol que tendra la cuenta
                    $permissions = isset($workpoint['permissions']) ? (object)$workpoint['permissions'] : \App\Roles::with('permissions_default')->find($account->_rol);
                    //Asociar permisos con cuentas
                    $permissions_default = collect($permissions->permissions_default);
                    $insert = $permissions_default->map(function($permission){
                        return $permission->id;
                    })->toArray(); // Se obtienen los IDs de los permisos
                    $account->permissions()->attach($insert); // Se le otorgan los permisos a la cuenta
                }
            }
            return $user->id; // Guardamos el ID principal
        });
        return response()->json([
            'success' => $id // Retorna el ID de la cuenta que se ha creado
        ]);
    }

    /*********************
     ***** CONSULTAS *****
     *********************/

    public function dataToCreateUser(){ // Función que indica los datos necesarios para la vista de creación de usuarios (Puntos de trabajo, roles, modulos con sus permisos)
        $workpoints = \App\WorkPoint::all();
        $modules = new ModuleCollection(\App\Module::all());
        $roles = \App\Roles::all();
        return response()->json([
            'workpoints' => $workpoints,
            'roles' => $roles,
            'modules' => $modules
        ]);
    }

    public function me(){ // Función para retornar los datos del usuario que paso el TOKEN
        $payload = Auth::payload(); //En esta variable nos indica los datos del usuario conectado
        return response()->json(
            new AccountResource(
                \App\Account::with('status', 'rol', 'permissions', 'workpoint', 'user')->find($payload['workpoint']->id // Cuenta que buscare en conjunto con todos sus datos
            ) // Decorador para presentar en la vista a un usuario
        ));
    }

    public function getAccounts(){ // Función que retorna todas las cuentas de usuario que comparten el punto de trabajo del token
        $payload = Auth::payload();
        $session = $payload['workpoint']; //token se obtiene el punto de trabajo
        $accounts = Account::with('rol', 'status', 'workpoint', 'user')->where('_workpoint',$session->_workpoint)->get();
        return response()->json(AccountResource::collection($accounts));
    }

    public function getAllUsers(){ //Función que retorna todos los usuarios
        $users = User::with('rol', 'wp_principal')->get(); //Trae todos los usuarios con su rol y punto de trabajo principal
        return response()->json(UserResource::collection($users));
    }

    public function profile(){ // Función que retorna tu perfil (Se necesita un TOKEN para lograrlo)
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

    public function updateStatus(Request $request){// Cambio de status de una cuenta
        $payload = Auth::payload();
        $account = Account::find($request->id); // Se busca la cuenta
        $account->_status = $request->_status; // Se establece el nuevo estatus
        $success = $account->save(); // Se guarda el cambio
        /**LOG 6 = CAMBIO DE STATUS */
        $payload = Auth::payload(); //Se guarda el hsitorico
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

    public function updateInfo(Request $request){ //Función para actualizar mi cuenta de usuario
        try{
            $user = Auth::user(); // Se busca el usuario al que se le aplicaran los datos
            // Datos que cambiaran
            $user->picture = $request->picture ? $request->picture : $user->picture;
            $user->names = $request->names ? $request->names : $user->names;
            $user->surname_pat = $request->surname_pat ? $request->surname_pat : $user->surname_pat;
            $user->surname_mat = $request->surname_mat ? $request->surname_mat : $user->surname_mat;
            $user->_wp_principal = $request->_wp_principal ? $request->_wp_principal : $user->_wp_principal;
            $user->_rol = $request->_rol ? $request->_rol : $user->_rol;
            $save = $user->save(); // Se guardan los datos
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

    public function updateProfile(Request $request, $id){ //Actualización de datos de un usuario
        try{
            $user = User::find($id); // Se busca al usuario que se le aplicaran los cambios
            // Datos que cambiaran
            $user->picture = $request->picture ? $request->picture : $user->picture;
            $user->names = $request->names ? $request->names : $user->names;
            $user->surname_pat = $request->surname_pat ? $request->surname_pat : $user->surname_pat;
            $user->surname_mat = $request->surname_mat ? $request->surname_mat : $user->surname_mat;
            $user->_wp_principal = $request->_wp_principal ? $request->_wp_principal : $user->_wp_principal;
            $user->_rol = $request->_rol ? $request->_rol : $user->_rol;
            $save = $user->save(); //Se guardan los cambios
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
    public function updateAccount(Request $request, $id){ //Función para actualizar los datos de una cuenta
        try{
            $account = Account::find($id); //Busqueda de la cuenta a la que se le aplicaran los cambios
            // Datos que seran modificados
            $account->_status = $request->_status ? $request->_status : $account->_status;
            $account->_rol = $request->_rol ? $request->_rol : $account->_rol;
            $permissions = $request->permissions ? $request->permissions : $account->permissions; // Cambio de permisos en dado caso que se manden
            $account->permissions()->sync($permissions); // Se puenden actualizar los permisos
            $save = $account->save(); //Se guardan los datos de las cuentas
            return response()->json(['sucess' => $save]);
        }catch(\Exception $e){
            return response()->json(['message' => 'No se ha podido actualizar la información de la cuenta']);
        }
    }

    public function changeRol(Request $request){
        $account = \App\User::find($request->_account);
        $users = Account::with('permissions')->where('_account', $request->_account)->get();
        $rol = \App\Roles::with('permissions_default')->find($request->_rol);
        $_permissions = array_column($rol->permissions_default->toArray(), "id");
        DB::transaction(function() use($account, $users, $request, $_permissions){
            $account->_rol = $request->_rol;
            $account->save();
            foreach($users as $user){
                $user->permissions()->sync($_permissions);
            }
        });
        return response()->json(["account" => $account, "user" => $users]);
    }

    public function deletePermissions(Request $request){ // Función para eliminar permisos a un de usuarios de un punto de trabajo en especifico
        // Se buscan todas las cuentas que tengan el _rol y _workpoint establecido
        $accounts = Account::where([['_rol', $request->_rol], ['_workpoint', $request->_workpoint]])->get();
        $total = 0;
        foreach($accounts as $account){
            //Se eliminan los permisos que se mandanron en forma de <array> id int
            $account->permissions()->detach($request->permissions);
            $total++;
        }
        return response()->json(["changed" => $total]);
    }

    public function addPermissions(Request $request){ //Añade permisos a los usuarios de un punto de trabajo en especifico y/o rol tambien mediente los IDs
        // Es necesario siempre mandar el rol al que se le aplicaran los cambios
        if(isset($request->_workpoint)){ // Si viene la sucursal se añade a todos los de esta
            $accounts = Account::where([['_rol', $request->_rol], ['_workpoint', $request->_workpoint]])->get();
        }else{
            $accounts = Account::where('_rol', $request->_rol)->get();
        }
        $total = 0;
        foreach($accounts as $account){ // Se le añaden los permisos que se indican a todas las cuentas
            $account->permissions()->syncWithoutDetaching($request->permissions);
            $total++;
        }
        return response()->json(["changed" => $total]); // Retornamos la cantidad de usuarios a los que se les añadio los permisos
    }

    public function addAcceso(Request $request){
        if(isset($request->_account)){
            //Se busca un usuario en especifico
            $users = User::where('id', $request->_account)->get();
        }
        $total = 0;
        foreach($users as $user){
            $account = \App\Account::where([
                ["_account", $user->id],
                ["_workpoint", $request->_workpoint]
            ])->first(); //Se valida si el usuario tiene cuenta
            if(!$account){ // Si no tiene cuenta se le creara una y se le daran los permisos correspondientes al rol
                $account = \App\Account::create([
                    '_account' => $user->id,
                    '_workpoint' => $request->_workpoint,
                    '_rol' => $request->_rol,
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
        return response()->json(["add" => $total]); //Responde con la cantidad de usuarios a los que se le dio acceso
    }

    public function getUsers(Request $request){ //Función que retorna todos los usuarios que comparte el punto de trabajo del TOKEN y ademas se puede realizar un filtro por rol
        if(isset($request->_rol)){ //Si se manda el rol se aplica el filtro de rol
            $users = User::with('rol')->whereIn("_rol", $request->_rol)->whereHas('workpoints', function($query){
                $query->where('_workpoint', $this->account->_workpoint);
            })->orderBy('names', 'asc')->get();
        }else{
            $users = User::with('rol')->orderBy('names', 'asc')->get();
        }
        return response()->json($users);
    }
}
