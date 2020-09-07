<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class Provider extends Model{
    
    protected $table = 'providers';
    protected $fillable = ['rfc', 'name', 'alias', 'description', 'address', 'phone', 'email'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/
    public function products(){
        return $this->hasMany('App\Product', '_provider', 'id');
    }
}