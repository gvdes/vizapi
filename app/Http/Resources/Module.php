<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class Module extends ResourceCollection{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request){
        $modules = collect(parent::toArray($request));
        /* return $modules; */
        return $this->getBranches($modules, 0, 0);
    }

    public function getBranches( $nodes, $deep, $root=0){
        $branchesNotUse = $nodes->filter( function ($node) use ($deep, $root){
            return $node['deep']!= $deep || $node['root'] != $root;
        });

        $branches = $nodes->filter( function( $node) use( $deep, $root){
        return $node['deep'] == $deep && $node['root'] == $root;
        })->map( function ($branch) use( $branchesNotUse, $deep){
            $branch['submodules'] = $this->getBranches( $branchesNotUse, $deep+1, $branch['id']);
            $branch['permissions'] = \App\Permission::where('_module',$branch['id'])->get();
            return $branch;
        })->values()->all();

        return $branches;
    }
}