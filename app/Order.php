<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class Order extends Model{

    protected $table = 'orders';
    protected $fillable = ['num_ticket', 'name', 'printed', '_created_by', '_workpoint_from', 'time_life', '_status'];

    public function workpoint(){
        return $this->belongsTo('App\WorkPoint', '_workpoint_from');
    }

    public function history(){
        return $this->belongsToMany('App\OrderProcess', 'order_log', '_order', '_status')
        ->withPivot('id', 'details', 'created_at');
        /* ->withTimestamps(); */
    }
    
    public function status(){
        return $this->belongsTo('App\OrderProcess', '_status');
    }

    public function created_by(){
        return $this->belongsTo('App\User', '_created_by');
    }

    public function products(){
        return $this->belongsToMany('App\Product', 'product_ordered', '_order', '_product')
                    ->withPivot('kit', 'units', 'price', '_price_list', "comments", "total", "amount", '_supply_by');
    }
}