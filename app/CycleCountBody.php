<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class CycleCountBody extends Model{

    protected $table = 'cyclecount_body';
    protected $fillable = ['_cyclecount', '_product', 'stock', 'stock_acc', 'details'];
    public $timestamps = false;

    /*****************
     * Relationships *
     *****************/
    
}