<?php

namespace App\Http\Controllers;
use App\Product;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
    }

    public function getAllFromAccess(){
        $access = new \App\Access();
        $items_from_access = collect($access->getProducts());
        $products = $items_from_access->map(function($product){
            return [
                "code" => mb_convert_encoding((string)$product['CODART'], "UTF-8", "Windows-1252"),
                "name" => (string)$product['CCOART'],
                "description" => mb_convert_encoding($product['DLAART'], "UTF-8", "Windows-1252"),
                "pieces" => explode(" ", $product['CP3ART'])[0] ? intval(explode(" ", $product['CP3ART'])[0]) : 0,
                "_category" => getCategory($product['FAMART']),
                "_status" => $product['NPUART'],
                "_provider" => ($product['PHAART']<139 || $product['PHAART'] == 1000) ? $product['PHAART'] : 404,
                "_unit" => 1
            ];
        })->toArray();
        $success = DB::table('products')->insert($products);

        return response()->json([
            "sucess" => $success,
            "products" => count($products)
        ]);
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
}
