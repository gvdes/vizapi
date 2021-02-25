<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class Kits extends Model{
    
    protected $table = 'kits';
    protected $fillable = ['code'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/
    public function products(){
        return $this->belongsToMany('App\Product', 'product_kits', '_kit', '_product')
                    ->withPivot(['price']);
    }
}