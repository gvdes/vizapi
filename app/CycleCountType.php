<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class CycleCountType extends Model{

    protected $table = 'cyclecount_type';
    protected $fillable = ['name'];
    public $timestamps = false;

    /*****************
     * Relationships *
     *****************/
    public function cyclecounts(){
        return $this->hasMany('App\CycleCount', '_type', 'id');
    }
}