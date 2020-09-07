<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductUnit extends Model{
    
    protected $table = 'product_units';
    protected $fillable = ['name', 'alias', 'equivalence'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/
    public function products(){
        return $this->hasMany('App\Product', '_unit', 'id');
    }
}