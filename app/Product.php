<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class Product extends Model{

    protected $table = 'products';
    protected $fillable = ['code', 'name', 'description', 'stock', '_category', '_status', '_unit', '_provider', 'pieces', 'dimensions', 'weight', 'cost', 'large', 'label'];
    /* public $timestamps = false; */

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
    public function maker(){
        return $this->belongsTo('App\Maker', '_maker');
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
        return $this->belongsToMany('App\CycleCount', 'cyclecount_body', '_product', '_cyclecount')
                    ->withPivot(['stock', 'stock_acc', 'details']);
    }

    public function sales(){
        return $this->belongsToMany('App\Sales', 'product_sold', '_product', '_sale')
                    ->withPivot('amount', 'costo', 'price', 'total');
    }

    /**
     * RELATIONSHIPS WITH PROVIDER ORDER'S MODELS
     */
    public function providerOrders(){
        return $this->belongsToMany('App\ProviderOrder', 'product_in_coming', '_product', '_order')
                    ->withPivot('amount', 'price', 'total');
    }

    /**
     * RELATIONSHIPS WITH PROVIDER ORDER'S MODELS
     */
    public function invoiceOrders(){
        return $this->belongsToMany('App\Invoice', 'product_received', '_product', '_order')
                    ->withPivot('amount', 'price', 'total');
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
                    ->withPivot('units', 'comments', 'stock');
    }

    /**
     * RELATIONSHIPS WITH WORKPOINT'S MODELS
     */
    public function stocks(){
        return $this->belongsToMany('App\WorkPoint', 'product_stock', '_product', '_workpoint')
                    ->withPivot('min', 'max', 'stock', 'gen', 'exh', 'des', 'fdt', 'V23', 'LRY', 'STC', '_status');
    }

    /**
     * MUTATORS
     */
    public function getDimensionsAttribute($value){
        $values = is_null($value) ? $value : json_decode($value);
        $values->length = is_null($values) ? floatval(0) : floatval($values->length);
        $values->height = is_null($values) ? floatval(0) : floatval($values->height);
        $values->width = is_null($values) ? floatval(0) : floatval($values->width);
        return $values;
    }
}
