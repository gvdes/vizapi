<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class Client extends Model{
    
    protected $table = 'client';
    protected $fillable = ['name', "phone", "email", "rfc", "address", "_price_list"];
    
    /*****************
     * Relationships *
     *****************/
    public function sales(){
        return $this->hasMany('App\Sales', "_client");
    }
}