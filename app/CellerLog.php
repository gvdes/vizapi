<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class CellerLog extends Model{
    
    protected $table = 'celler_log';
    protected $fillable = ['details', '_celler'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/

    public function celler(){
        return $this->belongsTo('App\Celler', '_celler');
    }
}