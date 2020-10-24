<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class WorkTeam extends Model{
    
    protected $table = 'work_team';
    protected $fillable = ['name', 'icon'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/
    public function reports(){
        return $this->hasMany('App\CatalogReport', '_work_team', 'id');
    }

    public function members(){
        return $this->belongsToMany('App\User', 'group_member', '_work_team', '_account')
                    ->using('App\GroupMember')
                    ->withPivot(['id','_rol']);
    }
}