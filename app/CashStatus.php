<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class CashStatus extends Model{
    
    protected $table = 'cash_status';
    protected $fillable = ['name'];
    public $timestamps = false;
  
    /*****************
     * Relationships *
     *****************/
    public function cash(){
        return $this->hasMany('App\CashRegister', '_status');
    }
}