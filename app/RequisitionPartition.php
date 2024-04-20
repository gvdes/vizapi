<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class RequisitionPartition extends Model{

    protected $table = 'requisition_partitions';
    public $timestamps = false;

    public function status(){
        return $this->belongsTo('App\RequisitionProcess', '_status');
    }

}
