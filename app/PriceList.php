<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class PriceList extends Model{
    
    protected $table = 'price_list';
    protected $fillable = ['name', 'short_name'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/
    public function products(){
        return $this->belongsToMany('App\Product', 'product_prices', '_type', '_product')
                    ->withPivot(['price']);
    }
}