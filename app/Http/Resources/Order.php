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
            'client' => $this->whenLoaded('client', function(){
                return [
                    "id" => $this->client->id,
                    "name" => $this->client->name,
                    "phone" => $this->client->phone,
                    "_price_list" => $this->client->_price_list
                ];
            }),
            'price_list' => $this->whenLoaded('price_list'),
            'status' => $this->whenLoaded('status'),
            '_status' => $this->when(!$this->status, function(){
                return $this->_status;
            }),
            'created_by' => $this->whenLoaded('created_by'),
            '_created_by' => $this->when(!$this->created_by, function(){
                return $this->_created_by;
            }),
            'from' => $this->whenLoaded('workpoint'),
            '_workpoint_from' => $this->when(!$this->workpoint, function(){
                return $this->_workpoint_from;
            }),
            'log' => $this->whenLoaded('history', function(){
                return $this->history->map(function($event){
                    return [
                        "id" => $event->id,
                        "name" => $event->name,
                        "details" => json_decode($event->pivot->details),
                        "created_at" => $event->pivot->created_at->format('Y-m-d H:i'),
                        "responsable" => $event->pivot->responsable
                    ];
                });
            }),
            'products' => $this->whenLoaded('products', function(){
                return $this->products->map(function($product){
                    if($product->pivot->_supply_by == 3){
                        if($product->pivot->toDelivered){
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
                        "description" => $product->description,
                        "dimensions" => $product->dimensions,
                        "barcode" => $product->barcode,
                        "cost" => $product->cost,
                        "section" => $product->section,
                        "family" => $product->family,
                        "category" => $product->category,
                        "prices" => $product->prices->map(function($price){
                            return [
                                "id" => $price->id,
                                "name" => $price->name,
                                "price" => $price->pivot->price,
                            ];
                        }),
                        "pieces" => $pieces,
                        "ordered" => [
                            "comments" => $product->pivot->comments,
                            "amount" => $product->pivot->amount,
                            "units" => $product->pivot->units,
                            "toDelivered" => $product->pivot->toDelivered,
                            "_supply_by" => $product->pivot->_supply_by,
                            "_price_list" => $product->pivot->_price_list,
                            "price" => $product->pivot->price,
                            "total" => $product->pivot->total,
                            "kit" => $product->pivot->kit,
                            "ripack" => $product->pivot->ipack
                        ],
                        "units" => $product->units,
                        'stocks' => $product->stocks->map(function($stock){
                            return [
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
                });
            }),
            '_order' => $this->_order,
            'parent' => (isset($this->parent) && isset($this->parent->id)) ? new Order($this->parent) : [],
            'children' => isset($this->children) ? Order::collection($this->children) : []
        ];
    }
}
