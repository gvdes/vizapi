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
    $router->post('/auth', 'AuthController@login');
    $router->group(['middleware' => 'auth'], function() use($router){
        $router->get('/', 'AccountController@me');
        $router->get('/dataToCreate', 'AccountController@dataToCreateUser');
        $router->get('/all', 'AccountController@getAccounts');
        $router->post('/users', 'AccountController@getUsers');
        $router->get('/general', 'AccountController@getAllUsers');
        $router->get('/profile', 'AccountController@profile');
        $router->post('/', 'AccountController@create');
        $router->post('/addPermissions', 'AccountController@addPermissions');
        $router->post('/addAcceso', 'AccountController@addAcceso');
        $router->post('/deletePermissions', 'AccountController@deletePermissions');
        $router->put('/status', 'AccountController@updateStatus');
        $router->put('/password', 'AccountController@updatePassword');
        $router->put('/profile/{id}', 'AccountController@updateProfile');
        $router->put('/profile', 'AccountController@updateInfo');
        $router->put('/{id}', 'AccountController@updateAccount');
    });
});


$router->group(['middleware' => 'auth'], function() use($router){
    $router->group(['prefix' => 'workpoint'], function () use ($router){
        $router->post('/join', 'AuthController@joinWorkpoint');
    });
    
    
    $router->group(['prefix' => 'access'], function () use ($router){
        $router->get('/products', 'AccessController@getProducts');
        $router->get('/providers', 'AccessController@getProviders');
        $router->get('/related', 'AccessController@getRelatedCodes');
    });

    $router->group(['prefix' => 'location'], function () use ($router){
        $router->get('/', 'RequisitionController@test');
        $router->get('/cellers', 'LocationController@getCellers');
        $router->get('/sections', 'LocationController@getSections');
        $router->get('/allSections', 'LocationController@getAllSections');
        $router->get('/product', 'LocationController@getProduct');
        $router->post('/report', 'LocationController@getReport');
        $router->post('/toggle', 'LocationController@setLocation');
        $router->get('/index', 'LocationController@index');
        $router->post('/maximos', 'LocationController@setMax');
        /* $router->post('/setMassive', 'LocationController@setMasiveLocation'); */
        $router->get('/pro/{id}', 'LocationController@getSectionsChildren');
        $router->post('/stocks', 'LocationController@getStocks');
        $router->post('/stocksFromStores', 'LocationController@getStocksFromStores');
        $router->post('/celler', 'LocationController@createCeller');
        $router->post('/section', 'LocationController@createSection');
        $router->post('/updateCeller', 'LocationController@updateCeller');
        $router->post('/updateSection', 'LocationController@updateSection');
        $router->post('/remove', 'LocationController@removeLocations');
        $router->post('/deleteSection', 'LocationController@deleteSection');
        $router->get('/sinMaximos', 'LocationController@sinMaximos');
        $router->post('/setMassive', 'LocationController@setMassiveLocation');
    });

    $router->group(['prefix' => 'inventory'], function () use ($router){
        $router->get('/', 'CycleCountController@index');
        $router->get('/{id}', 'CycleCountController@find');
        $router->post('/', 'CycleCountController@create');
        $router->post('/responsable', 'CycleCountController@addResponsable');
        $router->post('/add', 'CycleCountController@addProducts');
        $router->post('/remove', 'CycleCountController@removeProducts');
        $router->post('/value', 'CycleCountController@saveValue');
        $router->post('/next', 'CycleCountController@nextStep');
    });

    $router->group(['prefix' => 'mail'], function () use ($router){
        $router->get('/', 'MailController@welcome');
    });

    $router->group(['prefix' => 'product'], function () use ($router){
        $router->get('/getStatus', 'ProductController@getStatus');
        $router->get('/updateStocks', 'LocationController@updateStocks2');
        $router->post('/updateStatus', 'ProductController@updateStatus');
        $router->post('/', 'ProductController@getProducts');
        $router->get('/seeder', 'ProductController@seeder');
        $router->get('/updateTable', 'ProductController@updateTable');
        $router->get('/updatePrices', 'ProductController@updatePrices');
        $router->get('/autocomplete', 'ProductController@autocomplete');
        $router->post('/getMassive', 'ProductController@getMassiveProducts');
        $router->post('/catalog', 'ProductController@getProductByCategory');
        $router->post('/tree', 'ProductController@categoryTree');
        $router->get('/seederMax', 'ProductController@getMaximum');
        $router->post('/updateDesc', 'ProductController@addAtributes');
        $router->post('/getCategories', 'ProductController@getCategory');
    });

    $router->group(['prefix' => 'relatedCodes'], function () use ($router){
        $router->get('/seeder', 'RelatesCodeController@seeder');
        $router->get('/products', 'ProductController@getProductsWithCodes');
    });

    $router->group(['prefix' => 'provider'], function () use ($router){
        $router->get('/seeder', 'ProviderController@seeder');
    });
    
    $router->group(['prefix' => 'requisition'], function () use ($router){
        $router->get('/', 'RequisitionController@index');
        $router->get('/dashboard', 'RequisitionController@dashboard');
        $router->get('/{id}', 'RequisitionController@find');
        $router->post('/', 'RequisitionController@create');
        $router->post('/add', 'RequisitionController@addProduct');
        $router->post('/addMassive', 'RequisitionController@addMassiveProduct');
        $router->post('/remove', 'RequisitionController@removeProduct');
        $router->post('/next', 'RequisitionController@nextStep');
        $router->post('/reimpresion', 'RequisitionController@reimpresion');
    });

    $router->group(['prefix' => 'order'], function () use ($router){
        $router->get('/', 'OrderController@index');
        $router->get('/{id}', 'OrderController@find');
        $router->post('/', 'OrderController@create');
        $router->post('/add', 'OrderController@addProduct');
        $router->post('/reimpresion', 'OrderController@reimpresion');
    });

    $router->group(['prefix' => 'workpoints'], function () use ($router){
        $router->get('/', 'WorkpointController@index');
    });

    $router->group(['prefix' => 'pdf'], function () use ($router){
        $router->get('/', 'PdfController@getPdfsToEtiquetas');
        $router->post('/etiquetas', 'PdfController@generatePdf');
    });

});
        $router->group(['prefix' => 'reports'], function () use ($router){
            /* $router->get('/', 'MiniPrinterController@requisitionTicket'); */
            $router->get('/stocks', 'ReportsController@chechStocks');
            $router->post('/ventas', 'ReportsController@ventas');
            $router->get('/test', 'RequisitionController@test');
            $router->get('/sinUbicacion', 'ReportsController@sinUbicaciones');
            $router->get('/sinMaximos', 'ReportsController@sinMaximos');
        });

        $router->group(['prefix' => 'clients'], function () use ($router){
            /* $router->get('/seeder', 'ClientController@Seeder'); */
            $router->get('/{id}', 'CycleCountController@generateReport');
        });

        $router->group(['prefix' => 'ventas'], function () use ($router){
            $router->post('/', 'VentasController@index');
            $router->post('/tienda', 'VentasController@tienda');
            $router->post('/folio', 'VentasController@venta');
            $router->post('/articulos', 'VentasController@VentasxArticulos');
            $router->get('/seeder', 'VentasController@getVentas');
            $router->get('/seeder2', 'VentasController@getVentas2019');
            $router->get('/lastVentas', 'VentasController@getLastVentas');
            $router->get('/insertVentas', 'VentasController@insertVentas');
            $router->get('/insertProductVentas', 'VentasController@insertProductVentas');
        });

        $router->group(['prefix' => 'test'], function () use ($router){
            $router->get('/', 'FactusolController@getSales');
        });