<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class Roles extends Model{

    protected $table = 'roles';
    protected $fillable = ['name'];
    public $timestamps = false;

    public function permissions_default(){
        return $this->belongsToMany('App\Permission', 'rol_permission_default', '_rol', '_permission');
    }

    public function accounts_principal(){
        return $this->hasMany('App\User', '_rol', 'id');
    }

    public function accounts(){
        return $this->hasMany('App\AccountWorkpoint', '_rol', 'id');
    }
}