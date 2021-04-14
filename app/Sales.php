<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class Sales extends Model{
    
    protected $table = 'sales';
    protected $fillable = ['num_ticket', 'name', 'total', 'created_at', '_cash', '_client', '_paid_by', 'serie', '_seller'];
    
    /*****************
     * Relationships *
     *****************/

    public function cash(){
      return $this->belongsTo('App\CashRegister', '_cash');
    }

    public function products(){
      return $this->belongsToMany('App\Product', 'product_sold', '_sale', '_product')
                  ->withPivot('amount', 'costo', 'price', 'total');
    }
    
    public function paid_by(){
      return $this->belongsTo('App\PaidMethod', '_paid_by');
    }

    public function client(){
      return $this->belongsTo('App\Client', '_client');
    }

    public function seller(){
      return $this->belongsTo('App\Seller', '_seller');
    }
}