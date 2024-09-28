<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model{

    protected $table = 'product_categories';
    protected $fillable = ['name', 'code', 'deep', 'root'];
    protected $hidden = ['attributes'];
    public $timestamps = false;

    /*****************
     * Relationships *
     *****************/
    public function products(){
        return $this->hasMany('App\Product', '_category', 'id');
    }

    public function attributes(){
        return $this->hasMany('App\CategoryAttribute', '_category', 'id');
    }


    public function category(){//se quitan si hay problema va
        return $this->belongsTo('\App\Models\ProductCategory');
    }

    public function familia()//se quitan si hay problema va
    {
        return $this->belongsTo(ProductCategory::class, 'root');
    }

    public function seccion()//se quitan si hay problema va
    {
        return $this->belongsTo(ProductCategory::class, 'root');
    }
}
