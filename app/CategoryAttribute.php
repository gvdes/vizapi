<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class CategoryAttribute extends Model{
    
    protected $table = 'category_attributes';
    protected $fillable = ['name', '_category', 'required'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/
    public function category(){
        return $this->belongsTo('App\ProductCategory', '_category');
    }

    public function products(){
        return $this->belongsToMany('App\Product', 'product_attributes', '_attribute', '_product')
                    ->withPivot(['value']);
    }
}