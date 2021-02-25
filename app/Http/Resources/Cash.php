<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class Cash extends JsonResource{
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
            'num_cash' => $this->num_cash,
            'sales' => Sale::collection($this->whenLoaded('sales'))
        ];
    }
}
