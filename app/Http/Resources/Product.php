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
            }),
            'prices' => $this->whenLoaded('prices', function(){
                return $this->prices->map(function($price){
                    return [
                        "id" => $price->id,
                        "name" => $price->name,
                        "alias" => $price->alias,
                        "price" => $price->pivot->price
                    ];
                });
            }),
            'status' => $this->when($this->status, function(){
                return $this->status;
            }),
            'units' => $this->when($this->units, function(){
                return $this->units;
            })/* ,
            "created_at" => $this->created_at->format('Y-m-d H:i'),
            "updated_at" => $this->updated_at->format('Y-m-d H:i'), */
        ];
    }
}
