<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderSupply extends Model{
    
    protected $table = 'order_supply';
    protected $fillable = ['serie', 'num_ticket', 'name', '_workpoint_from', '_workpoint_to', 'created_at', '_requisition', 'ref', 'total'];
    
    /*****************
     * Relationships *
     *****************/

    public function products(){
        return $this->belongsToMany('App\Product', 'product_supply', '_order', '_product')
                    ->withPivot('amount', 'price', 'costo', 'total');
    }

    public function to(){
        return $this->belongsTo('App\WorkPoint', '_workpoint_to');
    }

    public function from(){
        return $this->belongsTo('App\WorkPoint', '_workpoint_from');
    }
}