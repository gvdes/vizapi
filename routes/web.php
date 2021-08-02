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
        $router->get('/cellers', 'LocationController@getCellers');
        $router->get('/sections', 'LocationController@getSections');
        $router->get('/allSections', 'LocationController@getAllSections');
        $router->get('/product', 'LocationController@getProduct');
        $router->post('/report', 'LocationController@getReport');
        $router->post('/toggle', 'LocationController@setLocation');
        $router->get('/index', 'LocationController@index');
        $router->post('/maximos', 'LocationController@setMax');
        $router->post('/massiveMaximos', 'LocationController@setMassiveMax');
        $router->get('/pro/{id}', 'LocationController@getSectionsChildren');
        $router->post('/celler', 'LocationController@createCeller');
        $router->post('/section', 'LocationController@createSection');
        $router->post('/updateCeller', 'LocationController@updateCeller');
        $router->post('/updateSection', 'LocationController@updateSection');
        $router->post('/remove', 'LocationController@removeLocations');
        $router->post('/deleteSection', 'LocationController@deleteSection');
        $router->post('/massiveLocations', 'LocationController@setMassiveLocations');
        $router->get('/sinMaximos', 'LocationController@sinMaximos');
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
        $router->get('/restore', 'ProductController@restoreProducts');
        $router->get('/previous', 'ProductController@addProductsLastYears');
        $router->get('/updateTable', 'ProductController@updateTable');
        $router->get('/restorePrices', 'ProductController@restorePrices');
        $router->get('/autocomplete', 'ProductController@autocomplete');
        $router->post('/getMassive', 'ProductController@getMassiveProducts');
        $router->post('/saveStocks', 'ProductController@saveStocks');
        $router->post('/catalog', 'ProductController@getProductByCategory');
        $router->post('/tree', 'ProductController@categoryTree');
        $router->get('/seederMax', 'ProductController@getMaximum');
        $router->post('/updateDesc', 'ProductController@addAtributes');
        $router->post('/getCategories', 'ProductController@getCategory');
        $router->post('/stocks', 'VentasController@getStocks');
        /* $router->get('/demo', 'ProductController@getDiferenceBetweenStores'); */
        $router->get('/lessStock', 'ProductController@getProductsByCategory');
    });

    $router->group(['prefix' => 'relatedCodes'], function () use ($router){
        $router->get('/seeder', 'RelatesCodeController@seeder');
    });

    $router->group(['prefix' => 'provider'], function () use ($router){
        $router->get('/update', 'ProviderController@updateProviders');
        $router->get('/orders', 'ProviderController@getAllOrders');
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
        $router->post('/index', 'OrderController@index');
        $router->get('/config', 'OrderController@config');
        $router->post('/next', 'OrderController@nextStep');
        $router->get('/{id}', 'OrderController@find');
        $router->post('/', 'OrderController@create');
        $router->post('/add', 'OrderController@addProduct');
        $router->post('/remove', 'OrderController@removeProduct');
        $router->post('/cancell', 'OrderController@cancelled');
        $router->post('/reimpresion', 'OrderController@reimpresion');
    });

    $router->group(['prefix' => 'workpoints'], function () use ($router){
        $router->get('/', 'WorkpointController@index');
    });

    $router->group(['prefix' => 'pdf'], function () use ($router){
        $router->get('/', 'PdfController@getPdfsToEtiquetas');
        $router->post('/etiquetas', 'PdfController@generatePdf');
    });

    $router->group(['prefix' => 'test'], function () use ($router){
        $router->get('/celler/structure', 'LocationController@getStructureCellers');
        $router->get('/productABC', 'ProductController@getABC');
        $router->get('/productABCStock', 'ProductController@getABCStock');
        $router->get('/delete', 'AccessController2@next');
        $router->get('/demo', 'OrderController@getCash');
    });
});

        $router->group(['prefix' => 'client'], function () use ($router){
            $router->get('/update', 'ClientController@update');
            $router->get('/updateStore', 'ClientController@getStoreClients');
            $router->get('/search', 'ClientController@autocomplete');
        });

        $router->group(['prefix' => 'ventas'], function () use ($router){
            $router->post('/', 'VentasController@index');
            $router->post('/tienda', 'VentasController@tienda');
            $router->post('/folio', 'VentasController@venta');
            $router->post('/articulos', 'VentasController@VentasxArticulos');
            $router->get('/tiendaAgente', 'VentasController@tiendaXSeller');
            $router->get('/seeder', 'VentasController@getVentas');
            $router->get('/seeder2', 'VentasController@getVentas2019');
            $router->get('/seeder3', 'VentasController@getVentasX');
            $router->get('/seederSellers', 'VentasController@seederSellers');
            $router->get('/lastVentas', 'VentasController@getLastVentas');
            $router->get('/insertVentas', 'VentasController@insertVentas');
            $router->get('/insertProductVentas', 'VentasController@insertProductVentas');
            $router->post('/tiendasXArticulos', 'VentasController@tiendasXArticulos');
            $router->post('/tiendasXArticulosFor', 'VentasController@tiendasXArticulosFor');
        });
        $router->group(['prefix' => 'printer'], function () use ($router){
            $router->get('/demo', 'RequisitionController@demoImpresion');
        });

        $router->group(['prefix' => 'sdelsol'], function () use ($router){
            $router->get('/salidas', 'FactusolController@getSalidas');
            $router->get('/depure', 'ProductController@depure');
        });

        $router->group(['prefix' => 'salidas'], function () use ($router){
            $router->get('/', 'SalidasController@seederSalidas');
            $router->get('/new', 'SalidasController@LastSalidas');
        });

        $router->group(['prefix' => 'withdrawals'], function () use ($router){
            $router->get('/', 'WithdrawalsController@seeder');
            $router->get('/new', 'WithdrawalsController@getLatest');
        });