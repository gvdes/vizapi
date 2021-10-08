<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class Requisition extends Model{
    
    protected $table = 'requisition';
    protected $fillable = ['name', 'num_ticket', 'num_ticket_store', 'notes', '_created_by', '_workpoint_from', '_workpoint_to', '_type', '_status', 'printed', 'time_life'];
    
    /*****************
     * Relationships *
     *****************/
    public function type(){
        return $this->belongsTo('App\RequisitionType', '_type');
    }

    public function status(){
        return $this->belongsTo('App\RequisitionProcess', '_status');
    }

    public function products(){
        return $this->belongsToMany('App\Product', 'product_required', '_requisition', '_product')
                    ->withPivot('amount', '_supply_by', 'units', 'cost', 'total', 'comments', 'stock');
    }

    public function to(){
        return $this->belongsTo('App\WorkPoint', '_workpoint_to');
    }

    public function from(){
        return $this->belongsTo('App\WorkPoint', '_workpoint_from');
    }

    public function created_by(){
        return $this->belongsTo('App\User', '_created_by');
    }

    public function log(){
        return $this->belongsToMany('App\RequisitionProcess', 'requisition_log', '_order', '_status')
                    ->withPivot('id', 'details')
                    ->withTimestamps();
    }
}