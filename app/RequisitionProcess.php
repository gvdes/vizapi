<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class RequisitionProcess extends Model{
    
    protected $table = 'requisition_process';
    protected $fillable = ['name', 'active', 'allow'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/
    public function requisitions(){
        return $this->hasMany('App\Requisition', '_status', 'id');
    }

    public function historic(){
        return $this->belongsToMany('App\Requisition', 'requisition_log', '_status', '_order')
                    ->withPivot('id', 'details')
                    ->withTimestamps();
    }
}