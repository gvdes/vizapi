<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderProcessConfig extends Model{

    protected $table = 'order_process_config';
    protected $fillable = ['_process', '_workpoint', 'active', 'details'];
    public $timestamps = false;

    public function process(){
        return $this->belongsTo('App\OrderProcess', '_process');
    }
}