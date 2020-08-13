<?php

namespace App;

use Illuminate\Database\Eloquent\Relations\Pivot;

class Account extends Pivot{
    public $incrementing = true;
    protected $table = 'account_workpoints';
    public $timestamps = false;
    protected $fillable = ['_account', '_workpoint', '_status', '_rol'];

    public function status(){
        return $this->belongsTo('App\Status', '_status');
    }

    public function rol(){
        return $this->belongsTo('App\Rol', '_rol');
    }

    public function permissions(){
        return $this->belongsToMany('App\Permission', 'account_permissions', '_account', '_permission');
    }
}