<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class Sale extends JsonResource{
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
            'num_ticket' => $this->num_ticket,
            'created_at' => $this->created_at->format('Y-m-d H:i'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i'),
            '_paid_by' => $this->_paid_by,
            'client' => $this->whenLoaded('client', function(){
              return [
                "id" => $this->client->id,
                "name" => $this->client->name,
                "phone" => $this->client->phone,
                "email" => $this->client->email
              ];
            }),
            'products' => $this->whenLoaded('products', function(){
              return  $this->products->map(function($product){
                return [
                  "id" => $product->id,
                  "code" => $product->code,
                  "name" => $product->name,
                  "description" => $product->description,
                  "sold" => [
                    "amount" => $product->pivot->amount,
                    "costo" => $product->pivot->costo,
                    "price" => $product->pivot->price,
                    "total" => $product->pivot->total
                  ]
                ];
              });
            }),
            "total" => $this->total
        ];
    }
}
