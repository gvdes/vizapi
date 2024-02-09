<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class PrinterType extends Model{
    
    protected $table = 'printer_types';
    protected $fillable = ['name'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/
    public function printers(){
        return $this->hasMany('App\Printer', '_type');
    }
}