<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductStatus extends Model{
    
    protected $table = 'product_status';
    protected $fillable = ['name'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/
    public function products(){
        return $this->hasMany('App\Product', '_status', 'id');
    }
}