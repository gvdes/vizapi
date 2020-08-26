<?php 
namespace App;

use Illuminate\Database\Eloquent\Relations\Pivot;

class Ticket extends Pivot{
    
    protected $table = 'ticket';
    protected $fillable = ['details', 'picture', '_report', '_status', '_responsable', '_created_by', 'created_at', 'updated_at'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/

    public function status(){
        return $this->belongsTo('App\ReportStatus', '_status');
    }

    public function createdBy(){
        return $this->belongsTo('App\User', '_created_by');
    }

    public function log(){
        return $this->hasMany('App\TicketLog', '_ticket', 'id');
    }
}