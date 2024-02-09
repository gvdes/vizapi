<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class ProviderOrder extends Model{
    
    protected $table = 'provider_order';
    protected $fillable = ['serie', 'code', 'ref', '_provider', '_status', 'description', 'total', 'created_at', 'received_at'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/

    public function products(){
        return $this->belongsToMany('App\Product', 'product_in_coming', '_order', '_product')
                    ->withPivot('amount', 'price', 'total');
    }
}