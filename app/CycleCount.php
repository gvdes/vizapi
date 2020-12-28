<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class CycleCount extends Model{

    protected $table = 'cyclecount';
    protected $fillable = ['_workpoint', '_created_by', '_type', '_status'];

    /*****************
     * Relationships *
     *****************/
    public function type(){
        return $this->belongsTo('App\CycleCountType', '_type');
    }

    public function created_by(){
        return $this->belongsTo('App\User', '_created_by');
    }

    public function workpoint(){
        return $this->belongsTo('App\Workpoint', '_workpoint');
    }

    public function status(){
        return $this->belongsTo('App\CycleCountStatus', '_status');
    }

    public function products(){
        return $this->belongsToMany('App\Product', 'cyclecount_body', '_cyclecount', '_product')
                    ->withPivot(['stock', 'stock_acc', 'details']);
    }
    
}