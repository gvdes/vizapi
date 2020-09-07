<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model{
    
    protected $table = 'product_variant';
    protected $fillable = ['barcode', 'stock', 'stocking_time', '_product'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/
    public function product(){
        return $this->belongsTo('App\Product', '_product');
    }

    public function pictures(){
        return $this->hasMany('App\ProductPicture', '_product', 'id');
    }
}