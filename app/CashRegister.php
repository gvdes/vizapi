<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model{
    
  protected $table = 'cash_registers';
  protected $fillable = ['name', 'num_cash', '_status', '_workpoint'];
  public $timestamps = false;
  
  /*****************
   * Relationships *
   *****************/
  public function workpoint(){
    return $this->belongsTo('App\WorkPoint', '_workpoint');
  }

  public function sales(){
    return $this->hasMany('App\Sales', '_cash');
  }

  public function order_log(){
    return $this->morphMany('App\OrderLog', 'responsable', '_type', '_responsable', 'id');
  }

  public function status(){
    return $this->belongsTo('App\CashStatus', '_status');
  }

  public function cashier(){
    return $this->belongsTo('App\User', '_account');
  }
}