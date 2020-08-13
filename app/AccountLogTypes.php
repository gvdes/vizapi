<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class AccountLogTypes extends Model{

    protected $table = 'account_log_types';
    protected $fillable = ['name'];
    public $timestamps = false;

    public function account_log(){
        return $this->belongsToMany('App\User', 'account_log', '_log_type', '_accto')->withTimestamps();
    }
}