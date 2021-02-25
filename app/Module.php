<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class Module extends Model{
    
    protected $table = 'modules_app';
    protected $fillable = ['name', 'root', 'deep', 'path'];
    public $timestamps = false;

    public function permissions(){
        return $this->hasMany('App\Permission', '_module', 'id');
    }
}