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
        $this->client = curl_init();
    }
    
    public function getStocks(){
        curl_setopt($this->client, CURLOPT_URL, $url.env('ACCESS_SERVER').'/warehouse/stocks');
        curl_setopt($this->client, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($this->client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->client, CURLOPT_POST, 1);
        curl_setopt($this->client,CURLOPT_TIMEOUT, 10);
        return json_decode(curl_exec($client), true);
    }

    public function getProducts(){
        $query_products = "SELECT CODART, CCOART, DESART, CP3ART, FAMART, PCOART, NPUART, PHAART, DIMART FROM F_ART";
        try{
            $start = microtime(true);
            //obtener productos
            $exec = $this->db->prepare($query_products);
            $exec->execute();
            $rows_products = $exec->fetchAll(\PDO::FETCH_ASSOC);
            $products = collect($rows_products);
            $result = $products->map(function($product){
                $category = $this->getCategory($product['FAMART']);
                $dimensions = explode('*', $group[0]['DIMART']);
                return [
                    "code" => mb_convert_encoding((string)$product['CODART'], "UTF-8", "Windows-1252"),
                    "name" => $product['CCOART'],
                    "description" => mb_convert_encoding($product['DESART'], "UTF-8", "Windows-1252"),
                    "cost" => $group[0]['x|'],/* me quede aqui */
                    "dimensions" =>json_encode([
                        "length" => count($dimensions)>0 ? $dimensions[0] : '',
                        "height" => count($dimensions)>1 ? $dimensions[1] : '',
                        "width" => count($dimensions)>2 ? $dimensions[2] : ''
                    ]),
                    "pieces" => explode(" ", $product['CP3ART'])[0] ? intval(explode(" ", $product['CP3ART'])[0]) : 0,
                    "_category" =>  $category ? $category : 404,
                    "_status" => 1,
                    "_provider" => (($group[0]['PHAART'] > 0 && $group[0]['PHAART']<139) || $group[0]['PHAART'] == 160 || $group[0]['PHAART'] == 200 || $group[0]['PHAART'] == 1000) ? $group[0]['PHAART'] : 404,
                    "_unit" => 1
                ];
            })->toArray();
            return $result;
        }catch(\PDOException $e){
            die($e->getMessage());
        }
    }

    public function getProviders(){
        $access = "C:\\Users\Carlo\\Desktop\\VPA2020.mdb";
        $query = "SELECT CODPRO, NIFPRO, NOFPRO, NOCPRO, DOMPRO, PROPRO, TELPRO FROM F_PRO";
        $db = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
        try{
            $exec = $db->prepare($query);
            $exec->execute();
            $rows = $exec->fetchAll(\PDO::FETCH_ASSOC);
            $items_from_access = collect($rows);
            $providers = $items_from_access->map(function($provider){
                return [
                    "id" => (string)$provider['CODPRO'],
                    "rfc" => (string)$provider['NIFPRO'],
                    "name" => mb_convert_encoding((string)$provider['NOFPRO'], "UTF-8", "Windows-1252"),
                    "alias" => mb_convert_encoding((string)$provider['NOCPRO'], "UTF-8", "Windows-1252"),
                    "description" => '',
                    "adress" => json_encode([
                        'calle' => mb_convert_encoding((string)$provider['DOMPRO'], "UTF-8", "Windows-1252"),
                        'municipio' => mb_convert_encoding((string)$provider['PROPRO'], "UTF-8", "Windows-1252")
                    ]),
                    "phone" => (string)$provider['TELPRO'],
                ];
            })->toArray();
            $success = DB::table('providers')->insert($providers);
            return $rows;
        }catch(\PDOException $e){
            die($e->getMessage());
        }
    }

    public function getRelatedCodes(){
        /* $access = "C:\\Users\Carlo\\Desktop\\VPA2020.mdb";
        $query = "SELECT ARTEAN, EANEAN FROM F_EAN";
        $db = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;"); */
        try{
            /* $exec = $db->prepare($query);
            $exec->execute();
            $rows = $exec->fetchAll(\PDO::FETCH_ASSOC); */
            $fac = new FactusolController();
            $rows = $fac->getRelatedCodes();
            DB::transaction( function() use($rows){
                $items_from_access = collect($rows);
                foreach($items_from_access as $relatedCode){
                    $product = \App\Product::where('code', $relatedCode['ARTEAN'])->first();
                    if($product){
                        $variant = new \App\ProductVariant(['barcode'=> $relatedCode['EANEAN'], "stock" => 0]);
                        $product->variants()->save($variant);
                    }
                }
            });
        }catch(\PDOException $e){
            die($e->getMessage());
        }
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
}
