<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class CellerType extends Model{
    
    protected $table = 'celler_type';
    protected $fillable = ['name', 'shortname'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/

    public function cellers(){
        return $this->hasMany('App\Celler', '_type', 'id');
    }
}