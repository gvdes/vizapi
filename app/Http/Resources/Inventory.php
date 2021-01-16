<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class Inventory extends JsonResource{
  /**
   * Transform the resource collection into an array.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return array
   */
  public function toArray($request){

    return [
      'id' => $this->id,
      'notes' => $this->notes,
      'created_at' => $this->created_at->format('Y-m-d H:i'),
      'updated_at' => $this->updated_at->format('Y-m-d H:i'),
      'type' => $this->whenLoaded('type'),
      '_type' => $this->when(!$this->type, function(){
        return $this->_type;
      }),
      'status' => $this->whenLoaded('status'),
      '_status' => $this->when(!$this->status, function(){
        return $this->_status;
      }),
      'created_by' => $this->whenLoaded('created_by'),
      '_created_by' => $this->when(!$this->created_by, function(){
        return $this->_created_by;
      }),
      'workpoint' => $this->whenLoaded('workpoint'),
      '_workpoint' => $this->when(!$this->workpoint, function(){
        return $this->_workpoint;
      }),
      'responsables' => $this->whenLoaded('responsables'),
      'settings' => json_decode($this->settings),
      'log' => $this->whenLoaded('log', function(){
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
            "pieces" => $product->pieces,
            "ordered" => [
              "stocks" => $product->pivot->stock,
              "stocks_acc" => $product->pivot->stock_acc,
              "details" => json_decode($product->pivot->details)
            ],
            "units" => $product->units,
            'locations' => $product->locations->map(function($location){
              return [
                "id" => $location->id,
                "name" => $location->name,
                "alias" => $location->alias,
                "path" => $location->path
              ];
            })
          ];
        });
      })
    ];
  }
}