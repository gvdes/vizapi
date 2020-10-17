<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class Product extends Model{
    
    protected $table = 'products';
    protected $fillable = ['code', 'name', 'description', 'stock', '_category', '_status', '_unit', '_provider', 'pieces', 'dimensions', 'weight'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/
    public function prices(){
        return $this->belongsToMany('App\PriceList', 'product_prices', '_product', '_type')
                    ->withPivot(['price']);
    }

    public function log(){
        return $this->belongsToMany('App\ProductAction', 'product_log', '_product', '_action')
                    ->withPivot(['details'])
                    ->withTimestamps();
    }

    public function kits(){
        return $this->belongsToMany('App\Kits', 'product_kits', '_product', '_kit')
                    ->withPivot(['price']);
    }

    public function status(){
        return $this->belongsTo('App\ProductStatus', '_status');
    }

    public function provider(){
        return $this->belongsTo('App\Provider', '_provider');
    }

    public function units(){
        return $this->belongsTo('App\ProductUnit', '_unit');
    }

    public function category(){
        return $this->belongsTo('App\ProductCategory', '_category');
    }

    public function variants(){
        return $this->hasMany('App\ProductVariant', '_product', 'id');
    }

    public function locations(){
        return $this->belongsToMany('App\CellerSection', 'product_location', '_product', '_location');
    }

    public function cyclecounts(){
        return $this->belongsToMany('App\CycleCount', 'cyclecount_body', '_product', '_cycle_count')
                    ->withPivot(['stock', 'stock_acc', 'details']);
    }

    public function attributes(){
        return $this->belongsToMany('App\CategoryAttribute', 'product_attributes', '_product', '_attribute')
                    ->withPivot('value');
    }

    /**
     * RELATIONSHIPS WITH REQUISITION'S MODELS
     */
    public function requisitions(){
        return $this->belongsToMany('App\Requisition', 'product_required', '_product', '_requisition')
                    ->withPivot('units', 'comments');
    }

    /**
     * RELATIONSHIPS WITH WORKPOINT'S MODELS
     */
    public function stock(){
        return $this->belongsToMany('App\WokrPoint', 'product_stock', '_product', '_workpoint')
                    ->withPivot('min', 'max', 'stock');
    }
}