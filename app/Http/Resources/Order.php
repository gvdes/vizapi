<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class Order extends JsonResource{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request){

        return [
            'id' => $this->id,
            'num_ticket' => $this->num_ticket,
            'name' => $this->name,
            'printed' => $this->printed,
            'time_life' => $this->time_life,
            'created_at' => $this->created_at->format('Y-m-d H:i'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i'),
            'status' => $this->whenLoaded('status'),
            '_status' => $this->when($this->status, function(){
                return $this->_status;
            }),
            'created_by' => $this->whenLoaded('created_by'),
            '_created_by' => $this->when($this->created_by, function(){
                return $this->_created_by;
            }),
            'from' => $this->whenLoaded('from'),
            '_workpoint_from' => $this->when($this->from, function(){
                return $this->_workpoint_from;
            }),
            'log' => $this->whenLoaded('historic', function(){
                return $this->log->map(function($event){
                    return [
                        "id" => $event->id,
                        "name" => $event->name,
                        "details" => json_decode($event->pivot->details),
                        "created_at" => $event->pivot->created_at->format('Y-m-d H:i')
                    ];
                });
            }),
            'products' => $this->whenLoaded('products', function(){
                return $this->products->map(function($product){
                    return [
                        "id" => $product->id,
                        "code" => $product->code,
                        "name" => $product->name,
                        "description" => $product->description,
                        "dimensions" => $product->dimensions,
                        "prices" => $product->prices->map(function($price){
                            return [
                                "id" => $price->id,
                                "name" => $price->name,
                                "price" => $price->pivot->price,
                            ];
                        }),
                        "pieces" => $product->pieces,
                        "ordered" => [
                            "comments" => $product->pivot->comments,
                            "amount" => $product->pivot->amount,
                            "_supply_by" => $product->pivot->_supply_by,
                            "units" => $product->pivot->units,
                            "_price_list" => $product->pivot->_price_list,
                            "price" => $product->pivot->price,
                            "total" => $product->pivot->total,
                            "kit" => $product->pivot->kit,
                        ],
                        "units" => $product->units
                    ];
                });
            })
        ];
    }
}