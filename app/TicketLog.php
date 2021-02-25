<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class TicketLog extends Model{
    
    protected $table = 'ticket_log';
    protected $fillable = ['details', 'created_at', 'updated_at'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/
    public function ticket(){
        return $this->belongsTo('App\Ticket', '_ticket');
    }
}