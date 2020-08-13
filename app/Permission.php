<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model{

    protected $table = 'permissions';
    protected $fillable = ['name', '_module'];
    public $timestamps = false;

    public function module(){
        return $this->belongsTo('App\Module', '_module');
    }

    public function roles(){
        return $this->belongsToMany('App\Rol', 'rol_permission_default', '_permission', '_rol');
    }

    public function accounts(){
        return $this->belongsToMany('App\Account', 'account_permissions', '_permission', '_account');
    }
}