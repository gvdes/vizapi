<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class Requisition extends Model{
    
    protected $table = 'requisition';
    protected $fillable = ['name', 'notes', '_created_by', '_workpoint_from', '_workpoint_to', '_type', '_status', 'printed', 'time_life'];
    
    /*****************
     * Relationships *
     *****************/
    public function type(){
        return $this->belongsTo('App\Models\Requisition\Type', '_type');
    }

    public function status(){
        return $this->belongsTo('App\Models\Requisition\Process', '_status');
    }

    public function products(){
        return $this->belongsToMany('App\Product', 'product_required', '_requisition', '_product')
                    ->with('units', 'comments');
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
}