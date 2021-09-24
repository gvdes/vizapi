<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class CashRegisterStatus extends Model{
    
  protected $table = 'cash_status';
  protected $fillable = ['name'];
  public $timestamps = false;
  
  /*****************
   * Relationships *
   *****************/

  public function cashes(){
    return $this->hasMany('App\Cash', '_cash');
  }
}