<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class Celler extends Model{
    
    protected $table = 'celler';
    protected $fillable = ['name', '_workpoint', '_type'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/

    public function type(){
        return $this->belongsTo('App\CellerType', '_type');
    }

    public function log(){
        return $this->hasMany('App\CellerLog', '_celler', 'id');
    }

    public function sections(){
        return $this->hasMany('App\CellerSection', '_celler', 'id');
    }
}