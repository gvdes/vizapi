<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class Printer extends Model{
    
    protected $table = 'printer';
    protected $fillable = ['name', 'ip', 'preferences', '_workpoint', '_type'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/
    public function workpoint(){
        return $this->belongsTo('App\WorkPoint', '_workpoint');
    }

    public function type(){
        return $this->belongsTo('App\PrinterType', '_type');
    }
}