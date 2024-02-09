<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderStatus extends JsonResource{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request){

        return [
            'id' => $this->id,
            'name' => $this->name,
            'allow' => $this->allow,
            'active' => $this->whenLoaded('config', function(){
                return $this->config[0]->pivot->active;
            }),
            'orders' => Order::collection($this->whenLoaded('orders'))
        ];
    }
}