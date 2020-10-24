<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class Product extends JsonResource{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request){
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'stock' => $this->stock,
            'pieces' => $this->pieces,
            'dimensions' => $this->dimensions,
            'weight' => $this->weight,
            'attributes' => $this->whenLoaded('attributes', function(){
                return $this->attributes->map(function($attribute){
                    return [
                        "id" => $attribute->id,
                        "name" => $attribute->name,
                        "value" => $attribute->pivot->value
                    ];
                });
            })
        ];
    }
}
