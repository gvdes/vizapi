<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class PartitionLog extends Model{

    protected $table = 'partition_logs';
    public $timestamps = false;

    public function status(){
        return $this->belongsTo('App\RequisitionProcess', '_status');
    }


    // public function status(){
    //     return $this->belongsTo('App\RequisitionProcess', '_status');
    // }
    // public function products(){
    //     return $this->belongsToMany('App\Product', 'product_required', '_partition', '_product', 'id')
    //     ->withPivot('amount', '_supply_by', 'units', 'cost', 'total', 'comments', 'stock', 'toDelivered', 'toReceived', 'ipack', 'checkout','_suplier_id');
    // }

    // public function requisition(){
    //     return $this->hasOne('App\Requisition','id','_requisition');
    // }

}
