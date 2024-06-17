<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class Maker extends Model{

    protected $table = 'makers';
    protected $fillable = ['name'];
    public $timestamps = false;

    /*****************
     * Relationships *
     *****************/
    public function products(){
        return $this->hasMany('App\Product', '_maker', 'id');
    }
}
