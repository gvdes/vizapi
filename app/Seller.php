<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class Seller extends Model{
    
  protected $table = 'sellers';
  protected $fillable = ['id', 'name', 'created_at'];
  public $timestamps = false;
  
  /*****************
   * Relationships *
    *****************/
  public function sales(){
    return $this->hasMany('App\Sales', "_seller");
  }
}