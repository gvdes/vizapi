<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class Sales extends Model{
    
    protected $table = 'sales';
    protected $fillable = ['num_ticket', 'name', 'created_at', '_cash', '_client', '_paid_by'];
    
    /*****************
     * Relationships *
     *****************/

    public function cash(){
      return $this->hasMany('App\CashRegister', '_cash');
    }

    public function products(){
      return $this->belongsToMany('App\Product', 'product_sold', '_sale', '_product')
                  ->withPivot('amount', 'costo', 'price', 'total');
    }
    
    public function paid_by(){
      return $this->belongsTo('App\PaidMethod', '_paid_by');
    }
}