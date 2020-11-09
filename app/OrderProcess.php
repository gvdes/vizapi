<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderProcess extends Model{

    protected $table = 'order_process';
    protected $guarded = [];

    public function orders(){
        return $this->belongsToMany('App\Order', 'order_log', '_status', '_order')
        ->withPivot('id', 'details')
        ->withTimestamps();
    }
}