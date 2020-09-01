<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\WorkTeam;

class WorkTeamController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
    }

    /**
     * Create team for support group
     * @param object request
     * @param string request[].name
     * @param string request[].icon - null
     * * @param array request[].members - null
     */
    public function create(Request $request){
        /** VALIDACIONES */
        $msg = [
            'required' => 'Campo necesario',
            'max' => 'El campo esta limitado hasta :max caracteres',
        ];

        $validateData = $this->validate($request, [
            'name' => 'required|max:35',
            'icon' => 'max:15',
        ], $msg);

        $team = DB::transaction( function () use ($request){
            try{
                $team = WorkTeam::create([
                    'name'=> $request->name,
                    'icon'=> $request->icon ? $request->icon : ''
                ]);
                return $team;
            }catch(\Exception $e){
                return false;
            }
        });
    }
}
