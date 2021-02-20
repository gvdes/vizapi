<?php

namespace App\Http\Controllers;

class FactusolController extends Controller{
  /**
   * Create a new controller instance.
   *
   * @return void
   */
  public $token = null;
  public function __construct(){
    $client = curl_init();
    curl_setopt($client, CURLOPT_URL, env("DELSOL_API")."/login/Autenticar");
    curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($client, CURLOPT_POST, 1);
    curl_setopt($client,CURLOPT_TIMEOUT, 2);
    $data = http_build_query(["codigoFabricante" => env('DELSOL_FABRICANTE'), "codigoCliente" => env('DELSOL_CLIENTE'), "baseDatosCliente" => env('DELSOL_BD'), "password" => base64_encode(env('DELSOL_PASSWORD'))]);
    curl_setopt($client, CURLOPT_POSTFIELDS, $data);
    $this->token =json_decode(curl_exec($client), true);
  }

  public function formattedData($data){
    if(isset($data["resultado"])){
      if(gettype($data["resultado"]) == "array"){
        $data = collect($data["resultado"]);
        $res = $data->map(function($row){
          $res = [];
          foreach($row as $field){
            $res[$field["columna"]] = $field["dato"];
          }
          return $res;
        });
        return $res;
      }
    }
    return false;
  }

  public function lanzarConsulta($query){
    $year = date("Y");
    $client = curl_init();
    $authorization = "Authorization: Bearer ".$this->token["resultado"];
    curl_setopt($client, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization ));
    curl_setopt($client, CURLOPT_URL, env("DELSOL_API")."/admin/LanzarConsulta");
    curl_setopt($client, CURLOPT_SSL_VERIFYPEER, TRUE);
    curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($client, CURLOPT_POST, 1);
    curl_setopt($client,CURLOPT_TIMEOUT, 15);
    $post = json_encode(["ejercicio" => $year, "consulta" => $query]);
    curl_setopt($client, CURLOPT_POSTFIELDS, $post);
    curl_setopt($client, CURLOPT_FOLLOWLOCATION, 1);
    $res = $this->formattedData(json_decode(curl_exec($client), true));
    return $res;
  }

  public function productosActualizados(){
    $date = isset($request->date) ? $request->date : date('Y-m-d', time());
    $query = "SELECT F_ART.CODART, F_ART.CCOART, F_ART.DLAART, F_ART.CP3ART, F_ART.FAMART, F_ART.NPUART, F_ART.PHAART, F_ART.DIMART, F_LTA.TARLTA, F_LTA.PRELTA FROM F_ART INNER JOIN F_LTA ON F_LTA.ARTLTA = F_ART.CODART WHERE F_ART.FUMART LIKE '$date'";
    $rows = $this->lanzarConsulta($query);
    $products = $rows->groupBy('CODART')->map(function($group){
      $category = $this->getCategory($group[0]['FAMART']);
      $prices = $group->map(function($el){
        return [
          "_type" => $el['TARLTA'],
          "price" => $el['PRELTA']
        ];
      });
      $dimensions = explode('*', $group[0]['DIMART']);
      return [
        "code" => mb_convert_encoding($group[0]['CODART'], "UTF-8", "Windows-1252"),
        "name" => $group[0]['CCOART'],
        "description" => mb_convert_encoding($group[0]['DLAART'], "UTF-8", "Windows-1252"),
        "dimensions" =>json_encode([
          "length" => count($dimensions)>0 ? $dimensions[0] : '',
          "height" => count($dimensions)>1 ? $dimensions[1] : '',
          "width" => count($dimensions)>2 ? $dimensions[2] : ''
        ]),
        "pieces" => explode(" ", $group[0]['CP3ART'])[0] ? intval(explode(" ", $group[0]['CP3ART'])[0]) : 0,
        "_category" =>  $category ? $category : 404,
        "_status" => $group[0]['NPUART'],
        "_provider" => (($group[0]['PHAART'] > 0 && $group[0]['PHAART']<139) || $group[0]['PHAART'] == 160 || $group[0]['PHAART'] == 200 || $group[0]['PHAART'] == 1000) ? $group[0]['PHAART'] : 404,
        "_unit" => 1,
        "prices" => $prices
      ];
    })->values()->all();
    return $products;
  }

  public function getStocks($_workpoint){
    $almacenes = $this->getAlmacenes($_workpoint);
    if($almacenes){
      $gen = $almacenes["GEN"];
      $exh = $almacenes["EXH"];
      $query = "SELECT ACTSTO, ARTSTO, ALMSTO, MINSTO, MAXSTO FROM F_STO WHERE ALMSTO = '$gen' OR ALMSTO = '$exh'";
      $rows = $this->lanzarConsulta($query);
      if($rows){
        $res = $rows->groupBy('ARTSTO')->map(function($product) use($almacenes){
          $min = 0;
          $max = 0;
          $gen = 0;
          $exh = 0;
          foreach($product as $stock){
            if($stock["ALMSTO"] == $almacenes["GEN"]){
              $gen = intval($stock["ACTSTO"]);
              $min = intval($stock["MINSTO"]);
              $max= intval($stock["MAXSTO"]);
            }else{
              $exh = intval($stock["ACTSTO"]);
            }
          }
          return [
            "code" => mb_convert_encoding((string)$product[0]['ARTSTO'], "UTF-8", "Windows-1252"),
            "gen" => $gen,
            "exh" => $exh,
            "stock" => $gen+$exh,
            "min" => $min,
            "max" => $max
          ];
        })->values()->all();
        return $res;
      }else{
        return false;
      }
    }else{
      return false;
    }
  }

  public function getAlmacenes($id){
    switch($id){
      case 1: //CEDIS
        return ["GEN" => "GEN", "EXH" => ""];
      case 2: //PANTACO
        return ["GEN" => "", "EXH" => ""];
      case 3: //SP1
        return ["GEN" => "SP1", "EXH" => "010"];
      case 4: //SP2
        return ["GEN" => "SP2", "EXH" => "005"];
      case 5: //CR1
        return ["GEN" => "CR1", "EXH" => "008"];
      case 6: //CR2
        return ["GEN" => "CR2", "EXH" => "009"];
      case 7: //AP1
        return ["GEN" => "AP1", "EXH" => "003"];
      case 8: //AP2
        return ["GEN" => "AP2", "EXH" => "004"];
      case 9: //RC1
        return ["GEN" => "RA1", "EXH" => "006"];
      case 10: //RC2
        return ["GEN" => "RA2", "EXH" => "013"];
      case 11: //BRA1
        return ["GEN" => "BR1", "EXH" => "001"];
      case 12: //BRA2
        return ["GEN" => "BR2", "EXH" => "002"];
      case 13: //CEDISBOL
        return ["GEN" => "BOL", "EXH" => "007"];
      case 14: //SP3
        return ["GEN" => "SP3", "EXH" => "011"];
      case 15: //SP4
        return ["GEN" => "SP4", "EXH" => "012"];
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
