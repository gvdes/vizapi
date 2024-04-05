<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class Concept extends Model{
    
    protected $table = 'concept';
    protected $fillable = ['id', 'name', 'alias', 'concept'];
    public $timestamps = false;
}