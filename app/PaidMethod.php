<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class PaidMethod extends Model{
    
  protected $table = 'paid_methods';
  protected $fillable = ['name', 'alias'];
  public $timestamps = false;
  
  /*****************
   * Relationships *
   *****************/
  public function sales(){
    return $this->hasMany('App\Sales', '_paid_by');
  }
}