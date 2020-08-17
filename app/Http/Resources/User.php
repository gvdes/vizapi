<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class User extends JsonResource{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request){
        return [
            'id' => $this->id,
            'nick' => $this->nick,
            'pictures' => $this->pictures,
            'names' => $this->names,
            'surname_pat' => $this->surname_pat,
            'surname_mat' => $this->surname_mat,
            'change_password' => $this->change_password,
            'rol' => $this->whenLoaded('rol'),
            'wp_principal' => $this->whenLoaded('wp_principal'),
            'workpoints' => Account::collection($this->whenLoaded('workpoints')),
            'log' => Log::collection($this->whenLoaded('log'))
        ];
    }
}
