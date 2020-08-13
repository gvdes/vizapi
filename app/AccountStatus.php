<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class AccountStatus extends Model{
    
    protected $table = 'account_status';
    protected $fillable = ['name', 'description'];
    public $timestamps = false;

    public function accounts(){
        return $this->hasMany('App\Account', '_status', 'id');
    }
}