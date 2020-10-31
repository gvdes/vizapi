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
            'type' => $this->whenLoaded('type'),
            '_type' => $this->when($this->type, function(){
                return $this->_type;
            }),
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
            'to' => $this->whenLoaded('to'),
            '_workpoint_to' => $this->when($this->to, function(){
                return $this->_workpoint_from;
            }),
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
                        "description" => $product->description,
                        "dimensions" => $product->dimensions,
                        "prices" => $product->prices->map(function($price){
                            return [
                                "id" => $price->id,
                                "name" => $price->name,
                                "price" => $price->pivot->price,
                            ];
                        }),
                        "pieces" => $product->pieces.' '.$product->units->alias,
                        "ordered" => [
                            "amount" => $product->pivot->units,
                            "comments" => $product->pivot->comments
                        ]
                    ];
                });
            })
        ];
    }
}