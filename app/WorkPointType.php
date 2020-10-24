<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class WorkPointType extends Model{
    
    protected $table = 'workpoints_types';
    protected $fillable = ['name'];
    public $timestamps = false;

    public function workpoints(){
        return $this->hasMany('App\WorkPoint', '_type', 'id');
    }
}