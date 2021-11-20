<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderSupplied extends Model{
    
    protected $table = 'order_supplied';
    protected $fillable = ['serie', 'num_ticket', 'reference', 'name', '_workpoint', '_workpoint_from', 'created_at', 'total', 'folio_fac', 'serie_fac'];
    
    /*****************
     * Relationships *
     *****************/

    public function products(){
        return $this->belongsToMany('App\Product', 'product_supplied', '_order', '_product')
                    ->withPivot('amount', 'price', 'total');
    }

    /* public function to(){
        return $this->belongsTo('App\WorkPoint', '_workpoint');
    }

    public function from(){
        return $this->belongsTo('App\WorkPoint', '_workpoint_from');
    } */
}