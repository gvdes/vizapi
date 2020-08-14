<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Laravel\Lumen\Auth\Authorizable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Model implements AuthenticatableContract, AuthorizableContract, JWTSubject{
    use Authenticatable, Authorizable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'accounts';
    protected $fillable = [
        'nick', 'password', 'picture', 'names', 'surname_pat', 'surname_mat', '_wp_principal', '_rol', 'change_password'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password', '_rol', '_wp_principal', 'remember_token', 'created_at', 'updated_at'
    ];

    protected $attributes = [
        'change_password' => true
    ];

    public function log(){
        return $this->belongsToMany('App\AccountLogTypes', 'account_log', '_accto', '_log_type')
                    ->withPivot(['details'])
                    ->withTimestamps();
    }

    public function wp_principal(){
        return $this->belongsTo('App\WorkPoint', '_wp_principal');
    }

    public function rol(){
        return $this->belongsTo('App\Roles', '_rol');
    }

    public function workpoints(){
        return $this->belongsToMany('App\WorkPoint', 'account_workpoints', '_account', '_workpoint')
                    ->using('App\Account')
                    ->withPivot(['_status', '_rol', 'id']);
    }

    /** Mutators */
    public function setNamesAttribute($value){
        $this->attributes['names'] = ucfirst($value);
    }

    public function setSurnamePatAttribute($value){
        $this->attributes['surname_pat'] = ucfirst($value);
    }

    public function setSurnameMatAttribute($value){
        $this->attributes['surname_mat'] = ucfirst($value);
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier(){
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims(){
        return [];
    }
}
