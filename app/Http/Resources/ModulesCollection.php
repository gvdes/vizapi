<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ModulesCollection extends ResourceCollection{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request){
        $permissions = collect(parent::toArray($request));
        $groupBy = $permissions->groupBy('_module');
        $modules = $groupBy->map( function($group, $index){
            $module = \App\Module::find($index);
            $module->permissions = Permission::collection($group);
            return $module;
        });
        $allModules = $this->getRoots($modules);
        return $this->getBranches(collect($allModules), 0, 0);
    }

    public function getBranches( $nodes, $deep, $root=0){
        $branchesNotUse = $nodes->filter( function ($node) use ($deep, $root){
            return $node["deep"]!= $deep || $node["root"] != $root;
        });

        $branches = $nodes->filter( function( $node) use( $deep, $root){
            return $node["deep"] == $deep && $node["root"] == $root;
        })->map( function ($branch) use( $branchesNotUse, $deep){
            $branch["submodules"] = $this->getBranches( $branchesNotUse, $deep+1, $branch["id"]);
            return $branch;
        })->values()->all();

        return $branches;
    }
    
    public function getRoots( $modules){
        $modules_arr = $modules->toArray();
        $roots = $modules->map( function($root){
            if($root->deep>0){
                $module = \App\Module::find($root->root);
                $module->permissions = [];
                return $module;
            }
        })->filter(function($module){
            return !is_null($module);
        })->unique()->filter(function($module) use ($modules_arr){
            return array_search(array_column((array)$module,'id'),$modules_arr);
        })->toArray();
        /* return $roots; */
        
        return array_merge( $modules_arr, $roots);
    }
}