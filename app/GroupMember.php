<?php 
namespace App;

use Illuminate\Database\Eloquent\Relations\Pivot;

class GroupMember extends Pivot{
    
    protected $table = 'group_member';
    protected $fillable = ['_work_team', '_rol', '_account'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/

    public function rol(){
        return $this->belongsTo('App\RolSupport', '_rol');
    }
}