<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class ReportStatus extends Model{
    
    protected $table = 'report_status';
    protected $fillable = ['name'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/
    public function tickets(){
        return $this->hasMany('App\Ticket', '_status', 'id');
    }
}