<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use App\Product;

class AccessController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public function __construct($url){
        $this->url = $url;
    }
    
    public function getStocks($_workpoint){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER').'/warehouse/stocks');
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client, CURLOPT_POST, 1);
        curl_setopt($client,CURLOPT_TIMEOUT, 30);
        curl_setopt($client, CURLOPT_POST, 1);
        $data = json_encode(["_workpoint" => $_workpoint]);
        curl_setopt($client, CURLOPT_POSTFIELDS, $data);
        curl_setopt($client, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        return json_decode(curl_exec($client), true);
    }
    /* 

    public function todosLosProducts($cols){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER').'/product/all');
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client,CURLOPT_TIMEOUT, 30);
        curl_setopt($client, CURLOPT_POST, 1);
        $data = http_build_query(["required" => $cols, "products" => true]);
        curl_setopt($client, CURLOPT_POSTFIELDS, $data);
        return json_decode(curl_exec($client), true);
    } */

    public function getDifferencesBetweenCatalog($products){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER').'/product/validate');
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client,CURLOPT_TIMEOUT, 30);
        curl_setopt($client, CURLOPT_POST, 1);
        $data = json_encode(["clouster" => $products]);
        curl_setopt($client, CURLOPT_POSTFIELDS, $data);
        curl_setopt($client, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        return json_decode(curl_exec($client), true);
    }

    /***************
     *** SALIDAS ***
     ***************/

    public function getSalidas(){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER').'/salidas/all');
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client,CURLOPT_TIMEOUT, 30);
        return json_decode(curl_exec($client), true);
    }

    public function getLastSalidas($caja_x_ticket){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER').'/salidas/new');
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client, CURLOPT_TIMEOUT, 20);
        curl_setopt($client, CURLOPT_POST, 1);
        $data = json_encode(["cash" => $caja_x_ticket]);
        curl_setopt($client, CURLOPT_POSTFIELDS, $data);
        curl_setopt($client, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        return json_decode(curl_exec($client), true);
    }

    public function getRelatedCodes(){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER').'/product/related');
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client,CURLOPT_TIMEOUT, 30);
        return json_decode(curl_exec($client), true);
    }

    /***************
     * PROVEEDORES *
     ***************/

    public function getProviders($date){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER').'/provider');
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client, CURLOPT_TIMEOUT, 20);
        curl_setopt($client, CURLOPT_POST, 1);
        $data = json_encode(["date" => $date]);
        curl_setopt($client, CURLOPT_POSTFIELDS, $data);
        curl_setopt($client, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        return json_decode(curl_exec($client), true);
    }

    public function getRawProviders($date){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER').'/provider/raw');
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client, CURLOPT_TIMEOUT, 20);
        curl_setopt($client, CURLOPT_POST, 1);
        $data = json_encode(["date" => $date]);
        curl_setopt($client, CURLOPT_POSTFIELDS, $data);
        curl_setopt($client, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        return json_decode(curl_exec($client), true);
    }

    public function syncProviders($providers){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER').'/provider/sync');
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client,CURLOPT_TIMEOUT, 50);
        curl_setopt($client, CURLOPT_POST, 1);
        $data = json_encode(["providers" => $providers]);
        curl_setopt($client, CURLOPT_POSTFIELDS, $data);
        curl_setopt($client, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        return json_decode(curl_exec($client), true);
    }

    public function getAllProviderOrders(){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER').'/providerOrder/all');
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client,CURLOPT_TIMEOUT, 50);
        return json_decode(curl_exec($client), true);
    }

    /**********************
     * FACTURAS RECIBIDAS *
     **********************/
    public function getAllInvoicesReceived(){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER').'/invoicesReceived/all');
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client,CURLOPT_TIMEOUT, 50);
        return json_decode(curl_exec($client), true);
    }

    /*************
     * PRODUCTOS *
     *************/
    public function getProducts(){
        
    }

    public function getRawProducts($date, $prices, $products){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER').'/product/info');
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client,CURLOPT_TIMEOUT, 60);
        curl_setopt($client, CURLOPT_POST, 1);
        $data = json_encode(["date" => $date, "prices" => $prices, "products" => $products]);
        curl_setopt($client, CURLOPT_POSTFIELDS, $data);
        curl_setopt($client, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        return json_decode(curl_exec($client), true);
    }

    public function syncProducts($prices, $products){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER').'/product/sync');
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client, CURLOPT_TIMEOUT, 500);
        curl_setopt($client, CURLOPT_POST, 1);
        $data = json_encode(["prices" => $prices, "products" => $products]);
        curl_setopt($client, CURLOPT_POSTFIELDS, $data);
        curl_setopt($client, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        return json_decode(curl_exec($client), true);
    }

    public function getUpdatedProducts($date){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER').'/product/update');
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client,CURLOPT_TIMEOUT, 30);
        curl_setopt($client, CURLOPT_POST, 1);
        $data = json_encode(["date" => $date]);
        curl_setopt($client, CURLOPT_POSTFIELDS, $data);
        curl_setopt($client, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        return json_decode(curl_exec($client), true);
    }

    public function getAllProducts(){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER').'/product/all');
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client,CURLOPT_TIMEOUT, 30);
        curl_setopt($client, CURLOPT_POST, 1);
        return json_decode(curl_exec($client), true);
    }

    /************
     * Clientes *
     ************/
    public function getClients(){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER').'/client/all');
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        return json_decode(curl_exec($client), true);
    }

    public function getRawClients(){

    }

    public function syncClients(){

    }

    /***********
     * Agentes *
     ***********/
    public function getSeller(){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER').'/user');
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client,CURLOPT_TIMEOUT, 10);
        curl_setopt($client, CURLOPT_POST, 1);
        $data = json_encode([]);
        curl_setopt($client, CURLOPT_POSTFIELDS, $data);
        curl_setopt($client, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        return json_decode(curl_exec($client), true);
    }

    public function getRawSeller(){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER').'/user/raw');
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client,CURLOPT_TIMEOUT, 10);
        curl_setopt($client, CURLOPT_POST, 1);
        $data = json_encode([]);
        curl_setopt($client, CURLOPT_POSTFIELDS, $data);
        curl_setopt($client, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        return json_decode(curl_exec($client), true);
    }

    public function syncSeller($users){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER').'/user/sync');
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client,CURLOPT_TIMEOUT, 10);
        curl_setopt($client, CURLOPT_POST, 1);
        $data = json_encode(["users" => $users]);
        curl_setopt($client, CURLOPT_POSTFIELDS, $data);
        curl_setopt($client, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        return json_decode(curl_exec($client), true);
    }

    /**********
     * VENTAS *
     **********/

    public function getSaleStore($folio, $caja){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER')."/sale/folio");
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client, CURLOPT_POST, 1);
        curl_setopt($client,CURLOPT_TIMEOUT, 10);
        $data = json_encode(["folio" => $folio, "caja" => $caja]);
        curl_setopt($client, CURLOPT_POSTFIELDS, $data);
        curl_setopt($client, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        return json_decode(curl_exec($client), true);
    }

    public function getLastSales($caja_x_ticket){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER')."/sale/new");
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client, CURLOPT_POST, 1);
        curl_setopt($client,CURLOPT_TIMEOUT, 40);
        $data = json_encode(["cash" => $caja_x_ticket]);
        curl_setopt($client, CURLOPT_POSTFIELDS, $data);
        curl_setopt($client, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        return json_decode(curl_exec($client), true);
    }

    /************
     * PREVENTA *
     ************/

    public function getOrderStore($folio){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER')."/preventa/folio");
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client, CURLOPT_POST, 1);
        curl_setopt($client,CURLOPT_TIMEOUT, 10);
        $data = json_encode(["folio" => $folio]);
        curl_setopt($client, CURLOPT_POSTFIELDS, $data);
        curl_setopt($client, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        return json_decode(curl_exec($client), true);
    }

    /************
     * RETIRADAS *
     ************/

    public function getAllWithdrawals(){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER')."/withdrawals/all");
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client,CURLOPT_TIMEOUT, 20);
        return json_decode(curl_exec($client), true);
    }

    public function getLatestWithdrawals($lastCode){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER')."/withdrawals/latest");
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client, CURLOPT_POST, 1);
        curl_setopt($client,CURLOPT_TIMEOUT, 10);
        $data = json_encode(["code" => $lastCode]);
        curl_setopt($client, CURLOPT_POSTFIELDS, $data);
        curl_setopt($client, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        return json_decode(curl_exec($client), true);
    }

    /************
     * RETIRADAS *
     ************/

    public function getAllGastos(){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER')."/accounting/all");
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client,CURLOPT_TIMEOUT, 20);
        return json_decode(curl_exec($client), true);
    }

    public function getConcepts(){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER')."/accounting/concept");
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client,CURLOPT_TIMEOUT, 20);
        return json_decode(curl_exec($client), true);
    }

    public function getLatestGastos($lastCode){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER')."/accounting/updated");
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client, CURLOPT_POST, 1);
        curl_setopt($client,CURLOPT_TIMEOUT, 10);
        $data = json_encode(["code" => $lastCode]);
        curl_setopt($client, CURLOPT_POSTFIELDS, $data);
        curl_setopt($client, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        return json_decode(curl_exec($client), true);
    }
}
