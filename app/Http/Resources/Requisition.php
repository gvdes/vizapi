<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class Requisition extends JsonResource{
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
            'num_ticket_store' => $this->num_ticket_store,
            'notes' => $this->notes,
            'printed' => $this->printed,
            'time_life' => $this->time_life,
            'created_at' => $this->created_at->format('Y-m-d H:i'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i'),
            "models" => $this->products_count,
            'type' => $this->whenLoaded('type'),
            'status' => $this->whenLoaded('status'),
            'created_by' => $this->whenLoaded('created_by'),
            'from' => $this->whenLoaded('from'),
            'to' => $this->whenLoaded('to'),
            'log' => $this->whenLoaded('log', function(){
                return $this->log->map(function($event){
                    return [
                        "id" => $event->id,
                        "name" => $event->name,
                        "details" => json_decode($event->pivot->details),
                        "created_at" => $event->pivot->created_at->format('Y-m-d H:i'),
                        "updated_at" => $event->pivot->updated_at->format('Y-m-d H:i')
                    ];
                });
            }),
            'products' => $this->whenLoaded('products', function(){
                return $this->products->map(function($product){
                    return [
                        "id" => $product->id,
                        "code" => $product->code,
                        "name" => $product->name,
                        "cost" => $product->cost,
                        "description" => $product->description,
                        "dimensions" => $product->dimensions,
                        /* "prices" => $product->prices->map(function($price){
                            return [
                                "id" => $price->id,
                                "name" => $price->name,
                                "price" => $price->pivot->price,
                            ];
                        }), */
                        "pieces" => $product->pieces,
                        "ordered" => [
                            "amount" => $product->pivot->amount,
                            "_supply_by" => $product->pivot->_supply_by,
                            "units" => $product->pivot->units,
                            "cost" => $product->pivot->cost,
                            "total" => $product->pivot->total,
                            "comments" => $product->pivot->comments,
                            "stock" => $product->pivot->stock
                        ],
                        "units" => $product->units
                    ];
                });
            })
        ];
    }
}