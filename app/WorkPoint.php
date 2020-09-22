<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class WorkPoint extends Model{
    
    protected $table = 'workpoints';
    protected $fillable = ['fullname', 'alias', '_type'];
    protected $hidden = ['_type'];
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
        return $this->hasMany('App\CycleCount', '_workpount', 'id');
    }
}