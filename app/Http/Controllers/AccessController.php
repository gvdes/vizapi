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

    public function getAllProducts($cols){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER').'/product/info');
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client,CURLOPT_TIMEOUT, 30);
        curl_setopt($client, CURLOPT_POST, 1);
        $data = http_build_query(["required" => $cols, "products" => true]);
        curl_setopt($client, CURLOPT_POSTFIELDS, $data);
        return json_decode(curl_exec($client), true);
    }

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

    public function getSalidas(){
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $this->url.env('ACCESS_SERVER').'/salidas/all');
        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client,CURLOPT_TIMEOUT, 30);
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

    public function getCategory($family){
        $categories = [
            "ACC"=>94, //Accesorios
            "BOC"=>113,
            "HOG"=>98,
            "HIG"=>97,
            "HER"=>96,
            "JUE"=>99,
            "PIL"=>114,
            "BEL"=>95,
            "BAR"=>94,
            "ROP"=>100,
            "TAZ"=>101,
            "PER"=>94,
            "TER"=>94,
            "RET"=>107,
            "AUD"=>112,
            "BEC"=>129,
            "CAL"=>102,//Calculadoras
            "CCI"=>104,
            "REL"=>102,
            "CAC"=>102,
            "MEM"=>106,
            "CRE"=>null,//Creditos
            "ELE"=>111,//Electronica
            "EQU"=>null,//Equipo de computo
            "MOU"=>null,
            "PAN"=>null,
            "TEC"=>null,
            "FIN"=>null,//Financieros
            "FLE"=>110,//Fletes
            "JUG"=>37,//Juguetes
            "DEP"=>44,
            "MON"=>38,
            "MOC"=>1,//Mochila
            "POR"=>1,
            "LON"=>4,
            "BOL"=>8,
            "PMO"=>13,
            "CAN"=>11,
            "CAR"=>12,
            "COS"=>16,
            "MRO"=>15,
            "LLA"=>1,
            "CRT"=>15,
            "MAR"=>9,
            "MAL"=>10,
            "LAP"=>5,
            "MPR"=>null,//Materias primas
            "NAV"=>null,//Navidad
            "20"=>131,
            "2"=>131,
            "19"=>141,
            "18"=>138,
            "17"=>138,
            "3"=>132,
            "15"=>160,
            "8"=>131,
            "14"=>130,
            "13"=>130,
            "12"=>null,
            "11"=>131,
            "10"=>134,
            "16"=>140,
            "4"=>132,
            "5"=>131,
            "PRO"=>null,
            "7"=>131,
            "9"=>134,
            "POF"=>130,
            "1"=>131,
            "6"=>131,
            "PAP"=>58,//Papeleria
            "PAR"=>66,//Paraguas
            "SAL"=>null,
            "IMP"=>89,
            "PEL"=>108,//Peluche
            "SAN"=>null//Sanitario
        ];
        $exist = array_key_exists(strtoupper($family), $categories);
        if($exist){
            return $categories[strtoupper($family)];
        }else{
            return 404;
        }
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
        curl_setopt($client,CURLOPT_TIMEOUT, 60);
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

    /************
     * Clientes *
     ************/
    public function getClients(){

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
}
