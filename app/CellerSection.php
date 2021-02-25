<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CellerSection extends Model{
    use SoftDeletes;
    protected $table = 'celler_section';
    protected $fillable = ['name', 'alias', 'path', 'root', 'deep', 'details', '_celler'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/

    public function celler(){
        return $this->belongsTo('App\Celler', '_celler');
    }

    public function products(){
        return $this->belongsToMany('App\Product', 'product_location', '_location', '_product');
    }

    /**
     * MUTTATORS
     */
    public function setNameAttribute($value){
        $this->attributes['name'] = strtoupper($value);
    }
    public function setAliasAttribute($value){
        $this->attributes['alias'] = strtoupper($value);
    }
}