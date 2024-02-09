<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductRequired extends JsonResource{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request){
        if($this->pivot->_supply_by == 3){
            if($this->pivot->toReceived){
                $pieces = $this->pivot->toReceived / $this->pivot->amount;
            }else if($this->pivot->toDelivered){
                $pieces = $this->pivot->toDelivered / $this->pivot->amount;
            }else{
                if($this->pivot->amount){
                    $pieces = $this->pivot->units / $this->pivot->amount;
                }else{
                    $pieces = $this->pieces;
                }
            }
        }else{
            $pieces = $this->pieces;
        }
        return [
            "id" => $this->id,
            "code" => $this->code,
            "name" => $this->name,
            "cost" => $this->cost,
            'barcode' => $this->barcode,
            'label' => $this->label,
            "description" => $this->description,
            "dimensions" => $this->dimensions,
            "section" => $this->section,
            "family" => $this->family,
            "category" => $this->category,
            "pieces" => $pieces,
            "ordered" => [
                "amount" => $this->pivot->amount,
                "_supply_by" => $this->pivot->_supply_by,
                "units" => $this->pivot->units,
                "cost" => $this->pivot->cost,
                "total" => $this->pivot->total,
                "comments" => $this->pivot->comments,
                "stock" => $this->pivot->stock,
                "toDelivered" => $this->pivot->toDelivered,
                "toReceived" => $this->pivot->toReceived
            ],
            "prices" => $this->prices->map(function($price){
                return [
                    "id" => $price->id,
                    "name" => $price->name,
                    "price" => $price->pivot->price,
                ];
            }),
            "units" => $this->units,
            'stocks' => $this->stocks->map(function($stock){
                return [
                    "_workpoint" => $stock->id,
                    "alias" => $stock->alias,
                    "name" => $stock->name,
                    "stock" => $stock->pivot->stock,
                    "gen" => $stock->pivot->gen,
                    "exh" => $stock->pivot->exh,
                    "min" => $stock->pivot->min,
                    "max" => $stock->pivot->max,
                ];
            })
        ];
    }
}