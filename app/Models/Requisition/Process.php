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
        return $this->hasMany('App\Models\Requisitions\Requisition', '_status', 'id');
    }
}