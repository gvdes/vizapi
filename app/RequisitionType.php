<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class RequisitionType extends Model{
    
    protected $table = 'type_requisition';
    protected $fillable = ['name', 'shortname'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/
    public function requisitions(){
        return $this->hasMany('App\Requisition', '_type', 'id');
    }
}