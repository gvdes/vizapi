<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class CatalogReport extends Model{
    
    protected $table = 'catalog_report';
    protected $fillable = ['name', 'description', 'data', '_work_team'];
    public $timestamps = false;
    
    /*****************
     * Relationships *
     *****************/
    public function workteam(){
        return $this->belongsTo('App\WorkTeam', '_work_team');
    }

    public function tickets(){
        return $this->belongsToMany('App\User', 'ticket', '_report', '_responsable')
                    ->using('App\Ticket')
                    ->withPivot(['id','details','pictue','_status','_created_by']);
    }
}