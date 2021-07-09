<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class Wildrawals extends Model{
    
    protected $table = 'wildrawls';
    protected $fillable = ['code', '_cash', 'description', 'total', 'provider', 'created_at'];
    
    /*****************
     * Relationships *
     *****************/
    public function workpoint(){
        return $this->belongsTo('App\WorkPoint', '_workpoint');
    }
}