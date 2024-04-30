<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class RequisitionPartition extends Model{

    protected $table = 'requisition_partitions';
    public $timestamps = false;

    public function status(){
        return $this->belongsTo('App\RequisitionProcess', '_status');
    }
    public function products(){
        return $this->belongsToMany('App\Product', 'product_required', '_partition', '_product', 'id')
        ->withPivot('amount', '_supply_by', 'units', 'cost', 'total', 'comments', 'stock', 'toDelivered', 'toReceived', 'ipack', 'checkout','_suplier_id');
    }

    public function requisition(){
        return $this->hasOne('App\Requisition','id','_requisition');
    }

    public function log(){
        return $this->belongsToMany('App\RequisitionProcess', 'partition_logs', '_partition', '_status')
                    ->withPivot('id', 'details')
                    ->withTimestamps();
    }


}
