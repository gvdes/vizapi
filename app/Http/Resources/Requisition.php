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
            'created_at' => $this->created_at->format('Y-m-d H:00'),
            'updated_at' => $this->updated_at->format('Y-m-d H:00'),
            '_type' => $this->when(!$this->type, function(){
                return $this->_type;
            }),
            'type' => $this->whenLoaded('type'),
            '_status' => $this->when(!$this->status, function(){
                return $this->_status;
            }),
            'status' => $this->whenLoaded('status'),
            '_created_by' => $this->when(!$this->created_by, function(){
                return $this->_created_by;
            }),
            'created_by' => $this->whenLoaded('created_by'),
            '_workpoint_from' => $this->when(!$this->from, function(){
                return $this->_workpoint_from;
            }),
            'from' => $this->whenLoaded('from'),
            '_workpoint_to' => $this->when(!$this->to, function(){
                return $this->_workpoint_from;
            }),
            'to' => $this->whenLoaded('to'),
            'log' => $this->whenLoaded('log', function(){
                return $this->log->map(function($event){
                    return [
                        "id" => $event->id,
                        "name" => $event->name,
                        "details" => json_decode($event->pivot->details),
                        "created_at" => $event->pivot->created_at->format('Y-m-d H:00'),
                        "updated_at" => $event->pivot->updated_at->format('Y-m-d H:00')
                    ];
                });
            }),
            'products' => $this->whenLoaded('products', function(){
                return $this->products;
            })
        ];
    }
}