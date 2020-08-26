<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class RolSupport extends Model{
    
    protected $table = 'rol_support';
    protected $fillable = ['name'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/

    public function members(){
        return $this->hasMany('App\GroupMember', '_rol', 'id');
    }
}