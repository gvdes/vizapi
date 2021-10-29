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
                        "active" => $event->active,
                        "allow" => $event->allow,
                        "details" => json_decode($event->pivot->details),
                        "created_at" => $event->pivot->created_at->format('Y-m-d H:i'),
                        "updated_at" => $event->pivot->updated_at->format('Y-m-d H:i')
                    ];
                });
            }),
            'products' => $this->whenLoaded('products', function(){
                return $this->products->map(function($product){
                    if($product->pivot->_supply_by == 3){
                        if($product->pivot->toReceived){
                            $pieces = $product->pivot->toReceived / $product->pivot->amount;
                        }else if($product->pivot->toDelivered){
                            $pieces = $product->pivot->toDelivered / $product->pivot->amount;
                        }else{
                            if($product->pivot->amount){
                                $pieces = $product->pivot->units / $product->pivot->amount;
                            }else{
                                $pieces = $product->pieces;
                            }
                        }
                    }else{
                        $pieces = $product->pieces;
                    }
                    return [
                        "id" => $product->id,
                        "code" => $product->code,
                        "name" => $product->name,
                        "cost" => $product->cost,
                        'barcode' => $product->barcode,
                        'label' => $product->label,
                        "description" => $product->description,
                        "dimensions" => $product->dimensions,
                        "section" => $product->section,
                        "family" => $product->family,
                        "category" => $product->category,
                        "pieces" => $pieces,
                        "ordered" => [
                            "amount" => $product->pivot->amount,
                            "_supply_by" => $product->pivot->_supply_by,
                            "units" => $product->pivot->units,
                            "cost" => $product->pivot->cost,
                            "total" => $product->pivot->total,
                            "comments" => $product->pivot->comments,
                            "stock" => $product->pivot->stock,
                            "toDelivered" => $product->pivot->toDelivered,
                            "toReceived" => $product->pivot->toReceived
                        ],
                        "prices" => $product->prices->map(function($price){
                            return [
                                "id" => $price->id,
                                "name" => $price->name,
                                "price" => $price->pivot->price,
                            ];
                        }),
                        "units" => $product->units,
                        'stocks' => $product->stocks->map(function($stock){
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
                        })/* ->filter(function($stock){
                            return $this->_workpoint_from == $stock['_workpoint'];
                        }) */
                    ];
                });
            })
        ];
    }
}