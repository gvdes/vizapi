<?php

namespace App\Http\Resources;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

use Illuminate\Http\Resources\Json\JsonResource;

class Product extends JsonResource{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request){
        $_account = Auth::payload()['workpoint'];
        $stock = $this->whenLoaded('stocks', function() use($_account){
            return $this->stocks->filter(function($stock) use($_account){
                return $stock->id == $_account->_workpoint;
            })->values()->all();
        });
        return [
            'id' => $this->id,
            'code' => $this->code,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'barcode' => $this->barcode,
            'description' => $this->description,
            'label' => $this->label,
            'pieces' => $this->pieces,
            'dimensions' => $this->dimensions,
            'weight' => $this->weight,
            'large' => $this->large,
            'section' => $this->section,
            'family' => $this->family,
            'category' => $this->category,
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
                    if($this->_unit == 3 && $price->id == 3){
                        $name = "DOCENA";
                        $alias = "DOC";
                    }else{
                        $name = $price->name;
                        $alias = $price->alias;
                    }
                    return [
                        "id" => $price->id,
                        "name" => $name,
                        "alias" => $alias,
                        "price" => $price->pivot->price
                    ];
                });
            }),
            'status' => $this->whenLoaded('stocks', function() use($stock){
                if(count($stock)>0){
                    return \App\ProductStatus::find($stock[0]->pivot->_status);
                }else{
                    return $this->status;
                }
            }),
            /* 'status' => $this->whenLoaded('status', function(){
                return $this->status;
            }), */
            'variants' => $this->whenLoaded('variants', function(){
                return $this->variants;
            }),
            'units' => $this->whenLoaded('units', function(){
                return $this->units;
            }),
            'locations' => $this->whenLoaded('locations', function(){
                return $this->locations->map(function($location){
                    return [
                        "id" => $location->id,
                        "name" => $location->name,
                        "alias" => $location->alias,
                        "path" => $location->path
                    ];
                });
            }),
            'stocks' => $this->whenLoaded('stocks', function(){
                return $this->stocks->map(function($stock){
                    return [
                        "_workpoint" => $stock->id,
                        "alias" => $stock->alias,
                        "name" => $stock->name,
                        "stock" => $stock->pivot->stock,
                        "gen" => $stock->pivot->gen,
                        "exh" => $stock->pivot->exh,
                        "des" => $stock->pivot->des,
                        "fdt" => $stock->pivot->fdt,
                        "min" => $stock->pivot->min,
                        "max" => $stock->pivot->max,
                        "status" => \App\ProductStatus::find($stock->pivot->_status)
                    ];
                });
            })/* ,
            "created_at" => $this->created_at->format('Y-m-d H:i'),
            "updated_at" => $this->updated_at->format('Y-m-d H:i'), */
        ];
    }
}
