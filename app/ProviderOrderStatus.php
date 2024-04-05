<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class ProviderOrder extends Model{
    
    protected $table = 'provider_order_status';
    protected $fillable = ['name'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/
    public function orders(){
        return $this->hasMany('App\ProviderOrder', '_status', 'id');
    }
}