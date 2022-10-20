<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class Order extends Model{

    protected $table = 'orders';
    protected $fillable = ['num_ticket', 'name', 'printed', '_created_by', '_workpoint_from', 'time_life', '_status', '_client', '_price_list', '_order'];

    public function workpoint(){
        return $this->belongsTo('App\WorkPoint', '_workpoint_from');
    }

    /* public function history(){
        return $this->belongsToMany('App\OrderProcess', 'order_log', '_order', '_status')
        ->withPivot('id', 'details', 'created_at');
    } */

    public function history(){
        return $this->belongsToMany('App\OrderProcess', 'order_log', '_order', '_status')->using('App\OrderLog')->withPivot('_responsable', '_type', 'details', 'created_at');
    }

    public function status(){
        return $this->belongsTo('App\OrderProcess', '_status');
    }

    public function client(){
        return $this->belongsTo('App\Client', '_client');
    }

    public function price_list(){
        return $this->belongsTo('App\PriceList', '_price_list');
    }

    public function created_by(){
        return $this->belongsTo('App\User', '_created_by');
    }

    public function products(){
        return $this->belongsToMany('App\Product', 'product_ordered', '_order', '_product')
                    ->withPivot('kit', 'units', 'price', '_price_list', "comments", "total", "amount", '_supply_by', 'toDelivered', 'ipack');
    }
}
