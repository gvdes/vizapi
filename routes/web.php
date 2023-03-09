<?php

/** @var \Laravel\Lumen\Routing\Router $router */
use App\Product;
use Illuminate\Support\Facades\DB;
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
    // return $router->app->version();

    DB::enableQueryLog();

    $cedis = Product::selectRaw('products.*, getSection(products._category) AS section, getFamily(products._category) AS family, getCategory(products._category) AS categoryy')
                        ->with([
                            'category',
                            'stocks' => fn($q) => $q->where("_workpoint", 2),
                            'locations' => fn($q) => $q->whereHas('celler', fn($q) => $q->where('_workpoint', 2))
                        ])
                        ->whereHas('stocks', fn($q) => $q->where([["stock", ">", 0], ["_workpoint", 2]]))
                        ->where('_status', '!=', 4)
                        ->get();

    dd(DB::getQueryLog());
});

$router->group(['prefix' => 'account'], function () use ($router){ // Modulo de cuentas de usuario
    $router->post('/auth', 'AuthController@login'); // Autenticación
    $router->group(['middleware' => 'auth'], function() use($router){
        $router->get('/', 'AccountController@me'); // Función que retorna al usuario que ha pasado el TOKEN
        $router->get('/dataToCreate', 'AccountController@dataToCreateUser'); // Función que indica los datos necesarios para la vista de creación de usuarios (Puntos de trabajo, roles, modulos con sus permisos)
        $router->get('/all', 'AccountController@getAccounts'); // Función que retorna todas las cuentas de usuario que comparten el punto de trabajo del token
        $router->post('/users', 'AccountController@getUsers'); // Función que retorna todos los usuarios que comparte el punto de trabajo del TOKEN y ademas se puede realizar un filtro por rol
        $router->get('/general', 'AccountController@getAllUsers'); // Función que retorna todos los usuarios
        $router->get('/profile', 'AccountController@profile'); // Función que retorna tu perfil (Se necesita un TOKEN para lograrlo)
        $router->post('/', 'AccountController@create'); // Función para crear una cuenta (usuario)
        $router->post('/addPermissions', 'AccountController@addPermissions'); //Añade permisos a los usuarios de un punto de trabajo en especifico y/o rol tambien mediente los IDs
        $router->post('/updateRol', 'AccountController@changeRol'); // Se actualiza el rol en conjunto de los permisos de la cuenta
        $router->post('/addAcceso', 'AccountController@addAcceso'); // Función para darle acceso a un usuario (por ID)
        $router->post('/deletePermissions', 'AccountController@deletePermissions'); // Función para eliminar permisos con base a rol y workpoint
        $router->put('/status', 'AccountController@updateStatus'); // Función para cambiar de status una cuenta
        $router->put('/password', 'AccountController@updatePassword'); // Actualización de la contraseña
        $router->put('/profile/{id}', 'AccountController@updateProfile'); //Actualización de datos de un usuario (PUEDE SER CUALQUIER USUARIO)
        $router->put('/profile', 'AccountController@updateInfo'); // Actualización de los datos de un usuario (EL DEL TOKEN)
        $router->put('/{id}', 'AccountController@updateAccount');  // Actualización de los datos de una cuenta (ID)
    });
});

$router->group(['middleware' => 'auth'], function() use($router){ // Modulo de autenticación
    $router->group(['prefix' => 'workpoint'], function () use ($router){
        $router->post('/join', 'AuthController@joinWorkpoint'); // Cambio de sucursal
    });

    $router->group(['prefix' => 'location'], function () use ($router){
        $router->get('/cellers', 'LocationController@getCellers'); // Función para obtener todos los almacenes de VIZAPP
        $router->get('/sections', 'LocationController@getSections'); // Función para obtener todas las secciones en conjunto con los productos que estan ubicados en estas secciones
        $router->get('/allSections', 'LocationController@getAllSections'); // Función para obtener todas las secciones con sus respectivas sub-secciones
        $router->get('/product', 'LocationController@getProduct'); // Función para obtener productos para minimo y maximo antiguo
        $router->post('/report', 'LocationController@getReport'); // Función para obtener el reporte excel con base al index
        $router->post('/toggle', 'LocationController@setLocation'); // Función para poner o quitar una ubicación a un articulo
        $router->get('/index', 'LocationController@index'); // Función que indica los reportes del modulo de almacenes que pueden ser generados en EXCEL
        $router->post('/maximos', 'LocationController@setMax'); // Función para asignar un minimo y maximo de forma manual mediante el <id>
        $router->post('/massiveMaximos', 'LocationController@setMassiveMax'); // Función para asignar un minimo y maximo de forma masiva mediante el código principal
        $router->get('/pro/{id}', 'LocationController@getSectionsChildren'); // Función que nos retorna las secciones hija de una de nivel inferior
        $router->post('/celler', 'LocationController@createCeller'); // Función para crear un almacen
        $router->post('/section', 'LocationController@createSection'); // Función para crear una nueva sección en los almacenes
        $router->post('/updateCeller', 'LocationController@updateCeller'); // Función para actualizar los datos de un almacen
        $router->post('/updateSection', 'LocationController@updateSection'); // Función para actualizar los datos de una sección
        $router->post('/remove', 'LocationController@removeLocations'); // Función para remover ubicaciones por sección o una categoría completa de cierta sucursal
        $router->post('/deleteSection', 'LocationController@deleteSection'); // Función para eliminar una sección en conjunto con sus decendientes
        $router->post('/massiveLocations', 'LocationController@setMassiveLocations'); // Función para insertar ubicaciones de forma masiva, se tiene que modificar para cada caso
        $router->get('/sinMaximos', 'LocationController@sinMaximos'); // Función que retorna todos los productos que no tiene máximo y si stock
        $router->post('/getLocations', 'LocationController@getLocations'); // Función para obtener todas las ubicaciones de los productos
        $router->post('/saveHistoric', 'LocationController@saveStocks'); // Función para almacenar el cierre de stocks del día
    });

    $router->group([ 'prefix'=>'C' ], function() use($router){
        $router->post("salesstore", "LStoreController@index");
    });

    $router->group(['prefix' => 'inventory'], function () use ($router){
        $router->get('/', 'CycleCountController@index'); // Función que nos retorna las conteos ciclicos que hemos realizado en cierto periodo de tiempo, ademas retorna los datos necesarios para seguir creando conteos
        $router->get('/{id}', 'CycleCountController@find'); // Función para agregar un conteo ciclico en especifico con sus repectivos datos para trabajar con el
        $router->post('/', 'CycleCountController@create'); // Función para crear un conteo ciclico
        $router->post('/responsable', 'CycleCountController@addResponsable'); // Función para agregar personas al conteo ciclico que haran el conteo
        $router->post('/add', 'CycleCountController@addProducts'); // Función para agregar producto nuevos a un conteo ciclico activo
        $router->post('/remove', 'CycleCountController@removeProducts'); // Función para quitar producto nuevos a un conteo ciclico activo
        $router->post('/value', 'CycleCountController@saveValue'); // Función para poner el valor contado durante el conteo ciclico
        $router->post('/next', 'CycleCountController@nextStep'); // Función para cambiar el status de un conteo ciclico
    });

    $router->group(['prefix' => 'mail'], function () use ($router){
        $router->get('/', 'MailController@welcome'); // Función para realizar prueba de envio de correo
    });

    $router->group(['prefix' => 'product'], function () use ($router){ // Modulo de productos
        $router->get('/getStatus', 'ProductController@getStatus'); // Función para obtener todos los status de los productos (Catalogo de status)
        $router->get('/updateStocks', 'LocationController@updateStocks'); // Función para actualizar constantemente los stocks de todas las tiendas
        $router->post('/updateStatus', 'ProductController@updateStatus'); // Función para actualizar el status en la sucursal
        $router->post('/', 'ProductController@getProducts'); // Función autocomplete 2.0 la chida
        $router->get('/restore', 'ProductController@restoreProducts'); // Restablece el catalgo maestro de productos emparejando el catalogo en CEDIS SP (F_ART) con el de MySQL (products)
        $router->get('/previous', 'ProductController@addProductsLastYears'); // -- CASO ESPECIAL -- Esta función se hizo para poblar el catalogo maestro de productos con los creados desde 2016, para obtener el historico completo
        $router->get('/updateTable', 'ProductController@updateTable'); // Se actualiza la BD de mysql y los ACCESS de todas las sucursales con base a una fecha de actualización
        $router->get('/restorePrices', 'ProductController@restorePrices'); // Empatar BD de mysql con ACCESS CEDISSP (Solo precios)
        $router->get('/autocomplete', 'ProductController@autocomplete'); // Autocomplete ANTIGUO que estaba en la sección de minimos y maximos
        $router->post('/getMassive', 'ProductController@getMassiveProducts'); // Función para obtener los productos y obtener la lista de los que se encontraron y no
        $router->post('/catalog', 'ProductController@getProductByCategory'); /* Función paara traer los productos con sus atributos ya sea por su categoria o no */
        $router->post('/tree', 'ProductController@categoryTree'); /* Función para obtener los decendientes de una categoría o de todas las secciones */
        $router->post('/stocks', 'VentasController@getStocks'); // Modificar
        $router->post('/updateRelatedCodes', 'ProductController@updateRelatedCodes'); // Función para actualizar los códigos relacionados
        $router->get('/compareCatalog', 'ProductController@compareCatalog'); // Pasar al status eliminado (4) los productos que ya no se encuetran en Factusol en el catalogo de MySQL
        $router->get('/lessStock', 'ProductController@getProductsByCategory'); // No recuerdo que realiza, no la eliminare por si acaso
        $router->get('/ABC', 'ProductController@getABC'); // Función para obtener reporte de ABC por Valor de inventario, Venta ($$$), Unidades vendidas
        $router->get('/ABCStock', 'ProductController@getABCStock'); // Función para obtener reporte de ABC por valor de inventario por sucursal
    });

    $router->group(['prefix' => 'relatedCodes'], function () use ($router){ // Modulo de códigos relacionados
        $router->get('/seeder', 'RelatesCodeController@seeder'); // Función para actualizar los códigos relacionados desde 0 (Elimina y guarda los actuales)
    });

    $router->group(['prefix' => 'provider'], function () use ($router){ // Modulo de proveedores
        $router->get('/update', 'ProviderController@updateProviders'); // Función para actualizar el catalogo de proveedores
        $router->get('/orders', 'ProviderController@getAllOrders');
    });

    $router->group(['prefix' => 'cash'], function () use ($router){ // Modulo de cajas
        $router->post('/status', 'OrderController@changeCashRegisterStatus'); // Función para cambiar de status de las cajas de las tiendas para preventa
        $router->post('/assignCashier', 'OrderController@assignCashier'); // Función para asignar cajero para preventa
    });

    $router->group(['prefix' => 'invoices'], function () use ($router){ // Modulo de compras
        $router->get('/seeder', 'InvoicesReceivedController@getInvoices'); // Función para obtener todas las compras de CEDISSP
        $router->post('/update', 'InvoicesReceivedController@newOrders'); // Función para actualizar las ultimas compras de CEDIS
        $router->post('/restore', 'InvoicesReceivedController@restoreDay'); // Función eliminar las compras de los ultimos 7 días y posteriormente agregarlas de nuevo
    });

    $router->group(['prefix' => 'requisition'], function () use ($router){
        $router->get('/', 'RequisitionController@index'); // Función para traer todos los pedidos que ha levantado la sucursal
        $router->get('/dashboard', 'RequisitionController@dashboard'); // Función para trer todos los pedidos que le han solicitado a la sucursal
        $router->get('/{id}', 'RequisitionController@find'); // Función para buscar un pedido en especifico
        $router->post('/updateStocks', 'RequisitionController@updateStocks'); // Función para actualizar los stocks de un pedido de resurtido
        $router->post('/', 'RequisitionController@create'); // Función para trer todos los pedidos que le han solicitado a la sucursal
        $router->post('/add', 'RequisitionController@addProduct');
        $router->post('/addMassive', 'RequisitionController@addMassiveProduct');
        $router->post('/remove', 'RequisitionController@removeProduct');
        $router->post('/next', 'RequisitionController@nextStep');
        $router->post('/reimpresion', 'RequisitionController@reimpresion');
        $router->post('/toDelivered', 'RequisitionController@setDeliveryValue');
    });

    $router->group(['prefix' => 'order'], function () use ($router){
        $router->post('/test', 'OrderController@test');
        $router->post('/index', 'OrderController@index');
        $router->get('/config', 'OrderController@config');
        $router->post('/next', 'OrderController@nextStep');
        $router->get('/{id}', 'OrderController@find');
        $router->post('/', 'OrderController@create');
        $router->post('/add', 'OrderController@addProduct');
        $router->post('/addMassive', 'OrderController@addMassiveProducts');
        $router->post('/remove', 'OrderController@removeProduct');
        $router->post('/cancell', 'OrderController@cancelled');
        $router->post('/changeConfig', 'OrderController@changeConfig');
        $router->post('/reimpresion', 'OrderController@reimpresion');
        $router->post('/printTicket', 'OrderController@reimpresionClientTicket');
        $router->post('/printNotDelivered', 'OrderController@printNotDelivered');
        $router->post('/toDelivered', 'OrderController@setDeliveryValue');
        $router->post('/exportExcel', 'OrderController@exportExcel');
        $router->post('/edit', 'OrderController@editting');
        $router->post('/demo', 'OrderController@getNextStatus');
        $router->post('/excel', 'OrderController@excel');
    });

    $router->group(['prefix' => 'workpoints'], function () use ($router){
        $router->get('/', 'WorkpointController@index'); // Función que retorna los puntos de trabajo en conjunto con su tipo
    });

    $router->group(['prefix' => 'pdf'], function () use ($router){
        $router->get('/', 'PdfController@getPdfsToEtiquetas'); // Función para retornar los tipos de etiquetas disponibles
        $router->post('/etiquetas', 'PdfController@generatePdf'); // Función para devolver el formato de PDF que se desea obtener
    });

    $router->group(['prefix' => 'cash'], function () use ($router){
        $router->get('/{id}', 'CashRegisterController@find'); // Función para traer una caja en especifico en conjunto con sus datos de status, cajero y sucursal a la que pertenece
        $router->post('/changeStatus', 'CashRegisterController@changeStatus'); // Función para cambiar de status la caja
        $router->post('/changeCashier', 'CashRegisterController@changeCashier'); // Función para asignar un nuevo cajero a la caja
    });
});

        $router->group(['prefix' => 'client'], function () use ($router){
            $router->get('/update', 'ClientController@update'); // Función para actualizar el catalogo maestro de clientes en MySQL y los ACCESS de todas las sucursales
            $router->get('/search', 'ClientController@autocomplete'); // Función para buscar cliente por ID o coincidencia más acertada
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
            $router->get('/seederSellers', 'VentasController@seederSellers'); // Función que actualiza el catalogo activo de vendedores
            $router->get('/lastVentas', 'VentasController@getLastVentas'); // Función para actualizar las ventas (trae las ultimas ventas)
            $router->post('/restore', 'VentasController@restoreSales'); // Función para eliminar las ventas del día de operación
            $router->get('/insertVentas', 'VentasController@insertVentas');
            $router->get('/insertProductVentas', 'VentasController@insertProductVentas');
            $router->post('/tiendasXArticulos', 'VentasController@tiendasXArticulos');
            $router->post('/tiendasXArticulosFor', 'VentasController@tiendasXArticulosFor');
            $router->post('/provider', 'VentasController@exportExcelByProvider');
            $router->post('/compras', 'VentasController@getCompras');

        });

        $router->group(['prefix' => 'printer'], function () use ($router){
            $router->get('/demo', 'PrinterController@test'); // Función para crear una prueba de impresión
            $router->get('/all', 'PrinterController@getPrinters'); // Función para obtener todas las impresoras disponibles para el usuario
            $router->post('/create', 'PrinterController@create'); // Función para crear una nueva miniprinter (Impresora)
            $router->post('/update', 'PrinterController@update'); // Función para actualizar los datos de una miniprinter (Impresora)
            $router->post('/delete', 'PrinterController@delete'); // Función para eliminar una miniprinter (Impresora)
        });

        $router->group(['prefix' => 'salidas'], function () use ($router){
            $router->get('/', 'SalidasController@seederSalidas');
            $router->get('/new', 'SalidasController@LastSalidas');
        });

        $router->group(['prefix' => 'entradas'], function () use ($router){
            $router->get('/', 'SalidasController@seederEntradas');
            $router->get('/new', 'SalidasController@LastSalidas');
        });

        $router->group(['prefix' => 'withdrawals'], function () use ($router){
            $router->get('/', 'WithdrawalsController@seeder'); // Obtener las retiradas de todos los puntos de trabajo de tipo sucursal activas
            $router->get('/new', 'WithdrawalsController@getLatest'); // Obtener las ultimas retiradas de todos los puntos de trabajo de tipo sucursal activas
            $router->get('/restore', 'WithdrawalsController@restore'); // Función para eliminar las retiradas de los ultimos 7 días
        });

        $router->group(['prefix' => 'accounting'], function () use ($router){
            $router->get('/seederConcepts', 'AccountingController@updateConcepts'); // Función utilizada para actualizar los conceptos de gastos
            $router->get('/seederGastos', 'AccountingController@seederGastos'); // Función para traer todos los gastos (Se debe realizar cada cambio de año) para que las actualizaciones sean sobre las nuevas
            $router->post('/update', 'AccountingController@getNew'); // Función para traer los gastos apartir de la ultima fecha de actualización
            $router->post('/restore', 'AccountingController@restore'); // Se obtiene la fecha de la semana pasada
        });

        $router->group(['prefix' => 'L'], function() use($router){
            $router->group(['prefix' => 'restock'], function() use($router){
                $router->get('/', 'LRestockController@index');
                $router->get('/crypt', 'LRestockController@crypt');
                $router->get('/{oid}', 'LRestockController@order');
                $router->get('/{oid}/newinvoice', 'LRestockController@newinvoice');
                $router->get('/{oid}/newentry', 'LRestockController@newentry');
                $router->post('/changestate', 'LRestockController@changestate');
                $router->post('/setdelivery', 'LRestockController@setdelivery');
                $router->post('/setreceived', 'LRestockController@setreceived');
                $router->post('/checkin', 'LRestockController@checkin');
                $router->post('/checkininit', 'LRestockController@checkininit');
                $router->get('/report/{rep}', 'LRestockController@report');
                $router->post('/massaction', 'LRestockController@massaction');
                $router->post('/print/key', 'LRestockController@printkey');
                $router->post('/print/forsupply', 'LRestockController@printforsupply');
            });

            $router->group(['prefix' => 'faks'], function() use($router){
                $router->get('/', 'LFacsController@index');
                $router->get('/ticket', 'LFacsController@ticket');
            });

            $router->group(['prefix' => 'vfy'], function() use($router){
                $router->get('/index', 'LVerificatorController@index');
            });
        });
