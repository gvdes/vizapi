<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class HistoryPrice extends Model{

    protected $table = 'history_prices';
    // protected $fillable = ['name', 'description'];
    // public $timestamps = false;

    /*****************
     * Relationships *
     *****************/
    // public function product(){
    //     return $this->belongsToMany('App\Product', 'product_log', '_action', '_product')
    //                 ->withPivot(['details'])
    //                 ->withTimestamps();
    // }
}
