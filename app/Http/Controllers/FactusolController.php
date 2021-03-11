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
    $this->token = json_decode(curl_exec($client), true);
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
    curl_setopt($client,CURLOPT_TIMEOUT, 35);
    $post = json_encode(["ejercicio" => $year, "consulta" => $query]);
    curl_setopt($client, CURLOPT_POSTFIELDS, $post);
    curl_setopt($client, CURLOPT_FOLLOWLOCATION, 1);
    $res = $this->formattedData(json_decode(curl_exec($client), true));
    return $res;
  }

  public function productosActualizados($date){
    $date = is_null($date) ? date('Y-m-d', time()) : $date;
    $query = "SELECT F_ART.CODART, F_ART.CCOART, F_ART.DESART, F_ART.CP3ART, F_ART.FAMART, F_ART.NPUART, F_ART.PHAART, F_ART.DIMART, F_LTA.TARLTA, F_LTA.PRELTA FROM F_ART INNER JOIN F_LTA ON F_LTA.ARTLTA = F_ART.CODART WHERE F_ART.FUMART LIKE '$date'";
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
        "code" => $group[0]['CODART']/* mb_convert_encoding($group[0]['CODART'], "UTF-8", "Windows-1252") */,
        "name" => $group[0]['CCOART'],
        "description" => $group[0]['DESART']/* mb_convert_encoding($group[0]['DESART'], "UTF-8", "Windows-1252") */,
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

  public function getRelatedCodes(){
    $query = "SELECT ARTEAN, EANEAN FROM F_EAN INNER JOIN F_ART ON F_EAN.ARTEAN = F_ART.CODART WHERE F_ART.NPUART = 0";
    $rows = $this->lanzarConsulta($query);
    return $rows;
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

  public function getSales($num_ticket, $type = 1){
    //Obtener el ultimo folio del año
    //Ordenar por agente
    //Insertar
    //Realizarlo cada 2 minutos
    $query = "SELECT TIPFAC, CODFAC, FECFAC, CLIFAC, CNOFAC, FOPFAC, HORFAC, TOTFAC, ALMFAC, AGEFAC FROM F_FAC WHERE CODFAC > ".$num_ticket." AND TIPFAC = ".$type;
    $rows = $this->lanzarConsulta($query);
    if($rows){
      $min = $rows->min('CODFAC');
      $max = $rows->max('CODFAC');
      $query_body = "SELECT CODLFA, ARTLFA, CANLFA, PRELFA, TOTLFA, COSLFA FROM F_LFA WHERE TIPLFA = ".$type." AND CODLFA >= ".$min." AND CODLFA <=". $max;
      $data = $this->lanzarConsulta($query_body)->groupBy("CODLFA");
      $res = $rows->map(function($row) use($data){
        if(isset($data[$row["CODFAC"]])){
          $body = $data[$row["CODFAC"]]->map(function($row){
            return [
              "_product" => $row["ARTLFA"],
              "amount" => $row["CANLFA"],
              "price" => $row["PRELFA"],
              "total" => $row["TOTLFA"],
              "costo" => $row["COSLFA"]
            ];
          });
        }else{
          $body = [];
        }
        $hora = count(explode(" ", $row["HORFAC"]))>1 ? explode(" ", $row["HORFAC"])[1] : "00:00:00";
        $date = explode("T", $row["FECFAC"])[0]." ".$hora;
        $_paid_by = 1;
        switch($row["FOPFAC"]){
          case "EFE":
            $_paid_by = 1;
          break;
          case "TCD":
            $_paid_by = 2;
          break;
          case "DEP":
            $_paid_by = 3;
          break;
          case "TRA":
            $_paid_by = 4;
          break;
          case "C30":
            $_paid_by = 5;
          break;
          case "CHE":
            $_paid_by = 6;
          break;
          case "TBA":
            $_paid_by = 7;
          break;
          case "TDA":
            $_paid_by = 8;
          break;
          case "TDB":
            $_paid_by = 9;
          break;
          case "TDS":
            $_paid_by = 10;
          break;
          case "TSA":
            $_paid_by = 11;
          break;
          case "TSC":
            $_paid_by = 12;
          break;
        }
        $_workpoint = 0;
        switch($row["AGEFAC"]){
          case "1": //CEDISSP
            $_workpoint = 1;
          break;
          case "2": //SP3
            $_workpoint = 13;
          break;
          case "4": //CR2
            $_workpoint = 6;
          break;
          case "5": //RC2
            $_workpoint = 10;
          break;
          case "6": //SP2
            $_workpoint = 4;
          break;
          case "7": //RC1
            $_workpoint = 9;
          break;
          case "9": //BR2
            $_workpoint = 12;
          break;
          case "10": //BR1
            $_workpoint = 11;
          break;
          case "11": //SP1
            $_workpoint = 3;
          break;
          case "12": //CR1
            $_workpoint = 5;
          break;
          case "13": //AP2
            $_workpoint = 8;
          break;
          case "14": //SP4
            $_workpoint = 15;
          break;
          case "15": //AP1
            $_workpoint = 7;
          break;
          case "16": //BOL
            $_workpoint = 13;
          break;
          case "17": //EST
            $_workpoint = 0;
          break;
        }
        return [
          "_cash" => intval($row["TIPFAC"]),
          "_workpoint" => $_workpoint,
          "num_ticket" => intval($row["CODFAC"]),
          "created_at" => $date,
          "_client" => intval($row["CLIFAC"]),
          "total" => intval($row["TOTFAC"]),
          "name" => intval($row["CNOFAC"]),
          "_paid_by" => $_paid_by,
          "body" => $body
        ];
      })->filter(function($sale){
        $key = array_search($sale["_client"], [0, 1, 2, 551, 3, 4, 5, 248, 6, 73, 7, 122, 389, 60, 874]);
        if($key === 0 || $key >0 ){
          return false;
        }else{
          if($sale["_workpoint"] == 0){
            return false;
          }
          return true;
        }
      })->values()->all();
      return $res;
    }
    return false;
  }
}
