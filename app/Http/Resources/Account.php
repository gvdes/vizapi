<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class Account extends JsonResource{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request){
        /* return parent::toArray($request); */
        /* return $this->whenPivotLoaded('account_workpoints', function () {
            return [
                'id' => $this->pivot->id,
                '_account' => $this->pivot->_account,
                '_workpoint' => $this->pivot->_workpoint,
                '_status' => $this->pivot->_status,
                '_rol' => $this->pivot->_rol
            ];
        }); */

        return [
            'id' => $this->when(!$this->workpoint, function(){
                return $this->pivot->id;
            }),
            '_account' => $this->when(!$this->workpoint, function(){
                return $this->pivot->_account;
            }),
            'me' => $this->whenLoaded('user', new User($this->user)),
            'status' => $this->whenLoaded('status'),
            '_status' => $this->when(!$this->workpoint, function(){
                return $this->pivot->_status;
            }),
            'rol' => $this->whenLoaded('rol'),
            '_rol' => $this->when(!$this->workpoint, function(){
                return $this->pivot->_rol;
            }),
            '_workpoint' => $this->when(!$this->workpoint, function(){
                return $this->pivot->_workpoint;
            }),
            'workpoint' => $this->whenLoaded('workpoint'),
            'modules' => $this->whenLoaded('permissions'),
            'token' => $this->when($this->token, function(){
                return $this->token;
            })
        ];
    }
}