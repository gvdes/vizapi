<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class Seasons extends Model{

  protected $table = 'seasons';
  public $timestamps = false;

      public function rules(){
        return $this->hasMany('App\SeasonsRules', '_season', 'id');
    }

}
