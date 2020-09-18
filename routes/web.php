<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->group(['prefix' => 'account'], function () use ($router){
    $router->get('/', 'AccountController@me');
    $router->get('/dataToCreate', 'AccountController@dataToCreateUser');
    $router->get('/all', 'AccountController@getAccounts');
    $router->get('/general', 'AccountController@getAllUsers');
    $router->get('/profile', 'AccountController@profile');
    $router->post('/', 'AccountController@create');
    $router->post('/auth', 'AuthController@login');
    $router->put('/status', 'AccountController@updateStatus');
    $router->put('/password', 'AccountController@updatePassword');
    $router->put('/profile/{id}', 'AccountController@updateProfile');
    $router->put('/profile', 'AccountController@updateInfo');
});

$router->group(['prefix' => 'workpoint'], function () use ($router){
    $router->post('/join', 'AuthController@joinWorkpoint');
});

$router->group(['prefix' => 'products'], function () use ($router){
});

$router->group(['prefix' => 'access'], function () use ($router){
    $router->get('/products', 'AccessController@getProducts');
    $router->get('/providers', 'AccessController@getProviders');
    $router->get('/related', 'AccessController@getRelatedCodes');
});

$router->group(['prefix' => 'location'], function () use ($router){
    $router->get('/cellers', 'LocationController@getCellers');
    $router->get('/sections', 'LocationController@getSections');
    $router->get('/product', 'LocationController@getProduct');
    $router->get('/report', 'LocationController@getReport');
    $router->post('/toggle', 'LocationController@setLocation');
    $router->get('/index', 'LocationController@index');
    $router->get('/index2', 'LocationController@index2');
});