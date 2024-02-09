<?php
namespace App;
use Illuminate\Database\Eloquent\Relations\Pivot;

class OrderLog extends Pivot{
    protected $table = 'order_log';
    public $incrementing = true;
    public $timestamps = ["created_at"];
    const UPDATED_AT = null;
    protected $fillable = ["id", '_order', '_status', '_responsable', '_type', 'details', 'created_at'];

    public function responsable(){
        return $this->morphTo(__FUNCTION__, '_type', '_responsable');
    }
}