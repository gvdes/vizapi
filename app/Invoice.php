<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model{
    
    protected $table = 'invoices_received';
    protected $fillable = ['serie', 'code', 'ref', '_provider', 'description', 'total', 'created_at', '_order'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/

    public function products(){
        return $this->belongsToMany('App\Product', 'product_received', '_order', '_product')
                    ->withPivot('amount', 'price', 'total');
    }
}