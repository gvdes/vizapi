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
    public function __construct(){
    }

    public function getProducts(){
        $access = "C:\\Users\Carlo\\Desktop\\VPA2020.mdb";
        $query_products = "SELECT CODART, CCOART, DLAART, CP3ART, FAMART, NPUART, PHAART FROM F_ART";
        $query_prices = "SELECT TARLTA, ARTLTA, PRELTA FROM F_LTA INNER JOIN F_ART ON F_LTA.ARTLTA = F_ART.CODART";
        /* $query_kits = "SELECT CODCOM, ARTCOM, COSCOM FROM F_COM INNER JOIN F_ART ON F_COM.CODCOM = F_ART.CODART"; */
        try{
            $db = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
            $start = microtime(true);
            //obtener productos
            $exec = $db->prepare($query_products);
            $exec->execute();
            $rows_products = $exec->fetchAll(\PDO::FETCH_ASSOC);
            $products = collect($rows_products);
            $inserts = $products->map(function($product){
                $category = $this->getCategory($product['FAMART']);
                return [
                    "code" => mb_convert_encoding((string)$product['CODART'], "UTF-8", "Windows-1252"),
                    "name" => $product['CCOART'],
                    "description" => mb_convert_encoding($product['DLAART'], "UTF-8", "Windows-1252"),
                    "pieces" => explode(" ", $product['CP3ART'])[0] ? intval(explode(" ", $product['CP3ART'])[0]) : 0,
                    "_category" =>  $category ? $category : 404,
                    "_status" => 1,
                    "_provider" => ($product['PHAART']<139 || $product['PHAART'] == 1000) ? $product['PHAART'] : 404,
                    "_provider" => 404,
                    "_unit" => 1
                ];
            })->toArray();
            $id = [];
            foreach ($inserts as $key => $value) {
                $id[$value['code']] = Product::insertGetId($value);
            }

            //obtener precios
            $exec = $db->prepare($query_prices);
            $exec->execute();
            $rows_prices = $exec->fetchAll(\PDO::FETCH_ASSOC);
            $prices = collect($rows_prices);
            //SET PRICES
            $prices_insert = $prices->map(function($price) use($id){
                $code = mb_convert_encoding((string)$price['ARTLTA'], "UTF-8", "Windows-1252");
                $type = $price['TARLTA']>3 ? $price['TARLTA']+1 : $price['TARLTA'];
                return [
                    'price' => $price['PRELTA'],
                    '_type' => $type,
                    '_product' => $id[$code] ? $id[$code] : null
                ];
            })->filter(function($price){
                return $price!=null;
            })->toArray();

            foreach (array_chunk($prices_insert, 1000) as $insert) {
                $success = DB::table('product_prices')->insert($insert);
            }
            return response()->json([
                "products" => count($id),
                "pricesc" => count($rows_prices),
                "time" => microtime(true) - $start,
            ]);
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
        $access = "C:\\Users\Carlo\\Desktop\\VPA2020.mdb";
        $query = "SELECT ARTEAN, EANEAN FROM F_EAN";
        $db = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
        try{
            $exec = $db->prepare($query);
            $exec->execute();
            $rows = $exec->fetchAll(\PDO::FETCH_ASSOC);
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
            "20"=>17,
            "2"=>19,
            "19"=>35,
            "18"=>34,
            "17"=>33,
            "3"=>20,
            "15"=>31,
            "8"=>25,
            "14"=>30,
            "13"=>29,
            "12"=>null,
            "11"=>28,
            "10"=>27,
            "16"=>32,
            "4"=>21,
            "5"=>19,
            "PRO"=>null,
            "7"=>24,
            "9"=>26,
            "POF"=>36,
            "1"=>18,
            "6"=>23,
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

    public static function getMinMax($code){
        $access = "C:\\Users\Carlo\\Desktop\\VPA2020.mdb";
        $query = "SELECT ACTSTO, MINSTO, MAXSTO FROM F_STO WHERE ARTSTO = '$code' AND ALMSTO = 'GEN'";
        $db = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
        try{
            $exec = $db->prepare($query);
            $exec->execute();
            $rows = $exec->fetchAll(\PDO::FETCH_ASSOC);
            return $rows[0];
        }catch(\PDOException $e){
            die($e->getMessage());
        }
    }

    public static function getStock($code){
        $access = "C:\\Users\Carlo\\Desktop\\VPA2020.mdb";
        $query = "SELECT ACTSTO FROM F_STO WHERE ARTSTO = '$code'";
        $db = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
        try{
            $exec = $db->prepare($query);
            $exec->execute();
            $rows = $exec->fetchAll(\PDO::FETCH_ASSOC);
            $stock = array_reduce($rows,function($res, $row){
                return $res + $row['ACTSTO'];
            },0);
            return $stock;
        }catch(\PDOException $e){
            die($e->getMessage());
        }
    }

    public static function getProductWithStock(){
        $access = "C:\\Users\Carlo\\Desktop\\VPA2020.mdb";
        $query = "SELECT ARTSTO AS code, ACTSTO AS stock FROM F_STO WHERE ACTSTO > 0 AND ALMSTO = 'GEN'";
        $db = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
        try{
            $exec = $db->prepare($query);
            $exec->execute();
            $rows = $exec->fetchAll(\PDO::FETCH_ASSOC);
            return $rows;
        }catch(\PDOException $e){
            die($e->getMessage());
        }
    }

    public static function getProductWithoutStock(){
        $access = "C:\\Users\Carlo\\Desktop\\VPA2020.mdb";
        $query = "SELECT ARTSTO AS code, ACTSTO AS stock FROM F_STO WHERE ACTSTO < 1 AND ALMSTO = 'GEN'";
        $db = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
        try{
            $exec = $db->prepare($query);
            $exec->execute();
            $rows = $exec->fetchAll(\PDO::FETCH_ASSOC);
            return $rows;
        }catch(\PDOException $e){
            die($e->getMessage());
        }
    }
}
