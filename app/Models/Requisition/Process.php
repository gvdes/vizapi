<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class Process extends Model{
    
    protected $table = 'requisition_process';
    protected $fillable = ['name'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/
    public function requisitions(){
        return $this->hasMany('App\Models\Requisition\Requisition', '_status', 'id');
    }

    public function historic(){
        return $this->belongsToMany('App\Models\Requisition\Requisition', 'requisition_log', '_status', '_order')
                    ->withPivot('id', 'details')
                    ->withTimestamps();
    }
}