<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class WorkPoint extends Model{

    protected $table = 'workpoints';
    protected $fillable = ['fullname', 'alias', 'dominio', 'active', '_type', '_client'];
    // protected $hidden = ['_type'];//descomentalo si algo pasa en vizapi y en la de resurtido lo tienes que hacer manual por favor gracias
    protected $dateFormat = 'U';
    public $timestamps = false;

    public function type(){
        return $this->belongsTo('App\WorkPointType', '_type');
    }

    public function accounts_base(){
        return $this->hasMany('App\User', '_wp_principal', 'id');
    }

    public function accounts(){
        return $this->belongsToMany('App\User', 'account_workpoints', '_workpoint', '_account')
                    ->using('App\Account')
                    ->withPivot(['_status', '_rol', 'id']);
    }

    public function orders(){
        return $this->hasMany('App\Order', '_workpoint', 'id');
    }

    public function cyclecounts(){
        return $this->hasMany('App\CycleCount', '_workpoint', 'id');
    }

    /**
     * RELATIONSHIPS WITH REQUISITION'S MODELS
     */
    public function supplied(){
        return $this->hasMany('App\Requisition','_workpoint_to', 'id');
    }

    public function to_supply(){
        return $this->hasMany('App\Requisition','_workpoint_from', 'id');
    }

    /**
     * RELATIONSHIPS WITH CELLER'S MODELS
     */
    public function products(){
        return $this->belongsToMany('App\Product', 'product_stock', '_workpoint', '_product')
                    ->withPivot('min', 'max', 'stock', 'gen', 'exh', '_status');
    }

    public function printers(){
        return $this->hasMany('App\Printer', '_workpoint');
    }

    /**
     * RELATIONSHIPS WITH VENTAS MODELS
     */
    public function cash(){
        return $this->hasMany('App\CashRegister', '_workpoint');
    }

    public function wildrawals(){
        return $this->hasMany('App\Wildrawals', '_workpoint');
    }
}
