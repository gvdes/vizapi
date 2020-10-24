<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductPicture extends Model{
    
    protected $table = 'product_picture';
    protected $fillable = ['picture', '_product'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/
    public function product(){
        return $this->belongsTo('App\ProductVariant', '_product');
    }
}