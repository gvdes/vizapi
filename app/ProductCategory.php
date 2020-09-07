<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model{
    
    protected $table = 'product_categories';
    protected $fillable = ['name', 'code', 'deep', 'root'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/
    public function products(){
        return $this->hasMany('App\Product', '_category', 'id');
    }

    public function attributes(){
        return $this->hasMany('App\CategoryAttribute', '_category', 'id');
    }
}