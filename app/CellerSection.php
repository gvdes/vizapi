<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class CellerSection extends Model{
    
    protected $table = 'celler_section';
    protected $fillable = ['name', 'alias', 'path', 'root', 'deep', 'details'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/

    public function celler(){
        return $this->belongsTo('App\Celler', '_celler');
    }

    public function products(){
        return $this->belongsToMany('App\Product', 'product_location', '_location', '_product');
    }
}