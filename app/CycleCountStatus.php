<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class CycleCountStatus extends Model{

  protected $table = 'cyclecount_status';
  protected $fillable = ['name'];

  /*****************
   * Relationships *
   *****************/
  public function cyclecounts(){
    return $this->hasMany('App\CycleCount', '_status', 'id');
  }
}