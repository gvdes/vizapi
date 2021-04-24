<?php

namespace App\Http\Controllers;

class AccessController2 extends Controller{
  /**
   * Create a new controller instance.
   *
   * @return void
   */
  public function __construct(){
    try{
      $access = env('ACCESS_FILE');
      $db = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
      $this->con = $db;
    }catch(PDOException $e){
      return response()->json(["message" => "Algo salio mal con la conexión a la base de datos"]);
    }
  }

  public function F_AGE(){
    /* ELIMINAR AGENTES */
    $query = "DELETE * FROM F_AGE WHERE CODAGE > 1";
    $exec = $this->con->prepare($query);
    $res = $exec->execute();
    /* return response()->json(["res" => $res]); */
  }

  /* public function F_ALB($store = 3){
    $query_delete = "DELETE * FROM F_ALB WHERE NOT ALMALB = ? AND NOT ALMALB = ?";
    $almacenes = $this->almacenes($store);
    $exec_delete = $this->con->prepare($query_delete);
    $exec_delete->execute($almacenes);

    $query_select = "SELECT * FROM F_ALB";
    $exec_select = $this->con->prepare($query_select);
    $exec_select->execute();
    $headers = $exec_select->fetchAll(\PDO::FETCH_ASSOC);

    $codes = collect(array_column($headers, 'CODALB'))->sort()->values()->all();

    $query_body = "DELETE * FROM F_LAL WHERE NOT CODLAL = ?";
    for($i=0; $i<count($codes)-1; $i++){
      $query_body = $query_body. " AND NOT CODLAL = ?";
    }
    $exec_body = $this->con->prepare($query_body);
    $exec_body->execute($codes);

    $query_update_alb = "UPDATE F_ALB SET CODALB = ? WHERE CODALB = ?";
    $exec_update_alb = $this->con->prepare($query_update_alb);

    $query_update_lal = "UPDATE F_LAL SET CODLAL = ? WHERE CODLAL = ?";
    $exec_update_lal = $this->con->prepare($query_update_lal);

    foreach(range(1, count($codes)) as $next){
      if($next != $codes[$next-1]){
        $exec_update_alb->execute([$next, $codes[$next-1]]);
        $exec_update_lal->execute([$next, $codes[$next-1]]);
      }
    }
    return response()->json(["res" => true]);
  } */

  public function F_ALM(){
    /* ELIMINAR ALMACENES */
    $query = "DELETE * FROM F_ALM WHERE NOT CODALM = ? AND NOT CODALM = ?";
    $almacenes = $this->almacenes(3);
    $exec = $this->con->prepare($query);
    $res = $exec->execute($almacenes);
  }

  public function F_STO($store = 3){
    /* DELETE ALMACENES DE LAS TIENDAS */
    $query = "DELETE * FROM F_STO WHERE NOT ALMSTO = ? AND NOT ALMSTO = ?";
    $almacenes = $this->almacenes($store);
    $exec = $this->con->prepare($query);
    $res = $exec->execute($almacenes);
  }

  public function F_CIN($store = 3){
    /* DELETE ALMACENES QUE NO PERTENECEN A LAS TIENDAS */
    $query = "DELETE * FROM F_CIN WHERE NOT ALMCIN = ? AND NOT ALMCIN = ?";
    $almacenes = $this->almacenes($store);
    $exec = $this->con->prepare($query);
    $res = $exec->execute($almacenes);
  }

  public function F_ANT($store = 3){
    /* DELETE ALMACENES QUE NO PERTENECEN A LAS TIENDAS */
    $query = "DELETE * FROM F_ANT WHERE NOT CAJANT = ?";
    $terminales = $this->terminales($store);
    for($i=0; $i<count($terminales)-1; $i++){
      $query = $query. " AND NOT CAJANT = ?";
    }
    $exec = $this->con->prepare($query);
    /* return response()->json($exec); */
    $res = $exec->execute($terminales);
    $query_select = "SELECT * FROM F_ANT";
    $exec_select = $this->con->prepare($query_select);
    $exec_select->execute();
    $headers = $exec_select->fetchAll(\PDO::FETCH_ASSOC);

    $codes = collect(array_column($headers, 'CODANT'))->sort()->values()->all();
    if(count($codes)>0){
      $query_update_ant = "UPDATE F_ANT SET CODANT = ? WHERE CODANT = ?";
      $exec_update_ant = $this->con->prepare($query_update_ant);
      foreach(range(1, count($codes)) as $next){
        if($next != $codes[$next-1]){
          $exec_update_ant->execute([$next, $codes[$next-1]]);
        }
      }
    }
  }

  public function F_ENT($store = 3){
    /* ELIMINAR ALBARANES */
    $query_delete = "DELETE * FROM F_ENT WHERE NOT ALMENT = ? AND NOT ALMENT = ?";
    $almacenes = $this->almacenes($store);
    $exec_delete = $this->con->prepare($query_delete);
    $exec_delete->execute($almacenes);

    $query_select = "SELECT * FROM F_ENT";
    $exec_select = $this->con->prepare($query_select);
    $exec_select->execute();
    $headers = $exec_select->fetchAll(\PDO::FETCH_ASSOC);

    $codes = collect(array_column($headers, 'CODENT'))->sort()->values()->all();

    $query_body = "DELETE * FROM F_LEN WHERE NOT CODLEN = ?";
    for($i=0; $i<count($codes)-1; $i++){
      $query_body = $query_body. " AND NOT CODLEN = ?";
    }
    $exec_body = $this->con->prepare($query_body);
    $exec_body->execute($codes);

    $query_update_ent = "UPDATE F_ENT SET CODENT = ? WHERE CODENT = ?";
    $exec_update_ent = $this->con->prepare($query_update_ent);

    $query_update_len = "UPDATE F_LEN SET CODLEN = ? WHERE CODLEN = ?";
    $exec_update_len = $this->con->prepare($query_update_len);

    foreach(range(1, count($codes)) as $next){
      if($next != $codes[$next-1]){
        $exec_update_ent->execute([$next, $codes[$next-1]]);
        $exec_update_len->execute([$next, $codes[$next-1]]);
      }
    }
  }

  public function F_FAB($store = 3){
    /* ELIMINAR ALBARANES */
    $query_delete = "DELETE * FROM F_FAB WHERE NOT ALMFAB = ? AND NOT ALMFAB = ?";
    $almacenes = $this->almacenes($store);
    $exec_delete = $this->con->prepare($query_delete);
    $exec_delete->execute($almacenes);

    $query_select = "SELECT * FROM F_FAB";
    $exec_select = $this->con->prepare($query_select);
    $exec_select->execute();
    $headers = $exec_select->fetchAll(\PDO::FETCH_ASSOC);

    $codes = collect(array_column($headers, 'CODFAB'))->sort()->values()->all();

    if(count($codes)>0){
      $query_body = "DELETE * FROM F_LFB WHERE NOT CODLFB = ?";
      for($i=0; $i<count($codes)-1; $i++){
        $query_body = $query_body. " AND NOT CODLFB = ?";
      }
      $exec_body = $this->con->prepare($query_body);
      $exec_body->execute($codes);
  
      $query_update_fab = "UPDATE F_FAB SET CODFAB = ? WHERE CODFAB = ?";
      $exec_update_fab = $this->con->prepare($query_update_fab);
  
      $query_update_lfb = "UPDATE F_LFB SET CODLFB = ? WHERE CODLFB = ?";
      $exec_update_lfb = $this->con->prepare($query_update_lfb);
      foreach(range(1, count($codes)) as $next){
        if($next != $codes[$next-1]){
          $exec_update_fab->execute([$next, $codes[$next-1]]);
          $exec_update_lfa->execute([$next, $codes[$next-1]]);
        }
      }

    }else{
      $query_body = "DELETE * FROM F_LFB";
      $exec_body = $this->con->prepare($query_body);
      $exec_body->execute($codes);
    }
  }

  public function F_ALB($store = 3){
    /* ELIMINAR ALBARANES */

    $query_delete = "DELETE * FROM F_ALB WHERE NOT ALMALB = ? AND NOT ALMALB = ?";
    $almacenes = $this->almacenes($store);
    $exec_delete = $this->con->prepare($query_delete);
    $exec_delete->execute($almacenes);

    $query_select = "SELECT * FROM F_ALB";
    $exec_select = $this->con->prepare($query_select);
    $exec_select->execute();
    $headers = collect($exec_select->fetchAll(\PDO::FETCH_ASSOC))->groupBy('TIPALB');
    $series = array_keys($headers->toArray());
    $x = [];
    $query_delete_series_lac = "DELETE * FROM F_LAC WHERE";
    $query_delete_series_lal = "DELETE * FROM F_LAL WHERE";
    $y = [];
    foreach($series as $serie){
      $query_delete_series_lac = $query_delete_series_lac. " NOT TFALAC = ?";
      $query_delete_series_lal = $query_delete_series_lal. " NOT TIPLAL = ?";

      $codes = collect(array_column($headers[$serie]->toArray(), 'CODALB'))->sort()->values()->all();
      $y[] = $codes;
      if(count($codes)>0){
        $query_delete_lal = "DELETE * FROM F_LAL WHERE TIPLAL = ?";
        $query_delete_lac = "DELETE * FROM F_LAC WHERE TFALAC = ?";
        for($i=0; $i<count($codes); $i++){
          $query_delete_lal = $query_delete_lal. " AND NOT CODLAL = ?";
          $query_delete_lac = $query_delete_lac. " AND NOT CALLAC = ?";
        }
        $array_codes = array_merge([strval($serie)],$codes);

        $exec_delete_lal = $this->con->prepare($query_delete_lal);
        $res2 = $exec_delete_lal->execute($array_codes);
        $x[] = [$res2, $exec_delete_lal];
        $exec_delete_lac = $this->con->prepare($query_delete_lac);
        $res2 = $exec_delete_lac->execute($array_codes);
        $x[] = [$res2, $exec_delete_lac];
    
        $query_update_alb = "UPDATE F_ALB SET CODALB = ? WHERE CODALB = ? AND TIPALB = ?";
        $exec_update_alb = $this->con->prepare($query_update_alb);
    
        $query_update_lal= "UPDATE F_LAL SET CODLAL = ? WHERE CODLAL = ? AND TIPLAL = ?";
        $exec_update_lal = $this->con->prepare($query_update_lal);

        $query_update_lac = "UPDATE F_LAC SET CALLAC = ? WHERE CALLAC = ? AND TFALAC = ?";
        $exec_update_lac = $this->con->prepare($query_update_lac);

        foreach(range(1, count($codes)) as $next){
          if($next != $codes[$next-1]){
            $exec_update_alb->execute([$next, $codes[$next-1], $serie]);
            $exec_update_lal->execute([$next, $codes[$next-1], $serie]);
            $exec_update_lac->execute([$next, $codes[$next-1], $serie]);
          }
        }
      }else{
        $query_delete = "DELETE * FROM F_LAC";
        $exec_delete = $this->con->prepare($query_delete);
        $exec_delete->execute();
      }
    }
    $exec_delete_series_lac = $this->con->prepare($query_delete_series_lac);
    $exec_delete_series_lac->execute($series);
    $exec_delete_series_lal = $this->con->prepare($query_delete_series_lal);
    $exec_delete_series_lal->execute($series);
  }

  public function F_TRA($store = 3){
    /* ELIMINAR ALBARANES */
    $query_delete = "DELETE * FROM F_TRA WHERE (NOT AORTRA = ? AND NOT AORTRA = ?) AND (NOT ADETRA = ? AND NOT ADETRA = ?)";
    $almacenes = $this->almacenes($store);
    $exec_delete = $this->con->prepare($query_delete);
    $almacenes2 = array_merge($almacenes, $almacenes);
    $exec_delete->execute($almacenes2);

    $query_select = "SELECT * FROM F_TRA";
    $exec_select = $this->con->prepare($query_select);
    $exec_select->execute();
    $headers = $exec_select->fetchAll(\PDO::FETCH_ASSOC);

    $codes = collect(array_column($headers, 'DOCTRA'))->sort()->values()->all();

    if(count($codes)>0){
      $query_body = "DELETE * FROM F_LTR WHERE NOT DOCLTR = ?";
      for($i=0; $i<count($codes)-1; $i++){
        $query_body = $query_body. " AND NOT DOCLTR = ?";
      }
      $exec_body = $this->con->prepare($query_body);
      $exec_body->execute($codes);
  
      $query_update_tra = "UPDATE F_TRA SET DOCTRA = ? WHERE DOCTRA = ?";
      $exec_update_tra = $this->con->prepare($query_update_tra);
  
      $query_update_ltr= "UPDATE F_LTR SET DOCLTR = ? WHERE DOCLTR = ?";
      $exec_update_ltr = $this->con->prepare($query_update_ltr);
      foreach(range(1, count($codes)) as $next){
        if($next != $codes[$next-1]){
          $exec_update_tra->execute([$next, $codes[$next-1]]);
          $exec_update_ltr->execute([$next, $codes[$next-1]]);
        }
      }
    }else{
      $query_delete = "DELETE * FROM F_LTR";
      $exec_delete = $this->con->prepare($query_delete);
      $exec_delete->execute();
    }
  }

  /* public function F_FAC2($store = 3){
    $query_delete = "DELETE * FROM F_FAC WHERE NOT ALMFAC = ? AND NOT ALMFAC = ?";
    $almacenes = $this->almacenes($store);
    $exec_delete = $this->con->prepare($query_delete);
    $exec_delete->execute($almacenes);

    $query_select = "SELECT * FROM F_FAC";
    $exec_select = $this->con->prepare($query_select);
    $exec_select->execute();
    $headers = $exec_select->fetchAll(\PDO::FETCH_ASSOC);

    $codes = collect(array_column($headers, 'CODFAC'))->sort()->values()->all();

    if(count($codes)>0){
      $query_body = "DELETE * FROM F_LFA WHERE NOT CODLFA = ?";
      for($i=0; $i<count($codes)-1; $i++){
        $query_body = $query_body. " AND NOT CODLFA = ?";
      }
      $exec_body = $this->con->prepare($query_body);
      $exec_body->execute($codes);
  
      $query_update_fac = "UPDATE F_FAC SET CODFAC = ? WHERE CODFAC = ?";
      $exec_update_fac = $this->con->prepare($query_update_fac);
  
      $query_update_lfa = "UPDATE F_LFA SET CODLFA = ? WHERE CODLFA = ?";
      $exec_update_lfa = $this->con->prepare($query_update_lfa);
      foreach(range(1, count($codes)) as $next){
        if($next != $codes[$next-1]){
          $exec_update_fac->execute([$next, $codes[$next-1]]);
          $exec_update_lfa->execute([$next, $codes[$next-1]]);
        }
      }

    }else{
      $query_body = "DELETE * FROM F_LFA";
      $exec_body = $this->con->prepare($query_body);
      $exec_body->execute($codes);
    }
    
    return response()->json(["res" => true]);
  } */

  public function F_FAC($store = 3){
    /* ELIMINAR FACTURAS */

    $query_delete = "DELETE * FROM F_FAC WHERE NOT ALMFAC = ? AND NOT ALMFAC = ?";
    $almacenes = $this->almacenes($store);
    $exec_delete = $this->con->prepare($query_delete);
    $exec_delete->execute($almacenes);

    $query_select = "SELECT * FROM F_FAC";
    $exec_select = $this->con->prepare($query_select);
    $exec_select->execute();
    $headers = collect($exec_select->fetchAll(\PDO::FETCH_ASSOC))->groupBy('TIPFAC');
    $series = array_keys($headers->toArray());

    $query_delete_series_lfa = "DELETE * FROM F_LFA WHERE";
    $query_delete_series_lco = "DELETE * FROM F_LCO WHERE";

    foreach($series as $serie){
      /* MODIFICAR PARA CEDIS */
      $query_delete_series_lco = $query_delete_series_lco. " NOT TFALCO = ?";
      $query_delete_series_lfa = $query_delete_series_lfa. " NOT TIPLFA = ?";

      $codes = collect(array_column($headers[$serie]->toArray(), 'CODFAC'))->sort()->values()->all();
      if(count($codes)>0){
        $start = "0";
        foreach (array_chunk($codes, 50) as $insert) {
          $query_delete_lfa = "DELETE * FROM F_LFA WHERE TIPLFA = ?";
          $query_delete_lco = "DELETE * FROM F_LCO WHERE TFALCO = ?";
          for($i=0; $i<count($insert); $i++){
            $query_delete_lfa = $query_delete_lfa. " AND NOT CODLFA = ?";
            $query_delete_lco = $query_delete_lco. " AND NOT CFALCO = ?";
          }
          $query_delete_lfa = $query_delete_lfa. " AND CODLFA > ? AND CODLFA < ?";
          $query_delete_lco = $query_delete_lco. " AND CFALCO > ? AND CFALCO < ?";
          $array_codes = array_merge([strval($serie)],$insert, [$start, $insert[count($insert)-1]]);
          $exec_delete_lfa = $this->con->prepare($query_delete_lfa);
          $exec_delete_lfa->execute($array_codes);
          $exec_delete_lco = $this->con->prepare($query_delete_lco);
          $exec_delete_lco->execute($array_codes);
          $start = $insert[count($insert)-1];
        }
    
        $query_update_fac = "UPDATE F_FAC SET CODFAC = ? WHERE CODFAC = ? AND TIPFAC = ?";
        $exec_update_fac = $this->con->prepare($query_update_fac);
    
        $query_update_lfa= "UPDATE F_LFA SET CODLFA = ? WHERE CODLFA = ? AND TIPLFA = ?";
        $exec_update_lfa = $this->con->prepare($query_update_lfa);

        $query_update_lco = "UPDATE F_LCO SET CFALCO = ? WHERE CFALCO = ? AND TFALCO = ?";
        $exec_update_lco = $this->con->prepare($query_update_lco);

        foreach(range(1, count($codes)) as $next){
          if($next != $codes[$next-1]){
            $exec_update_fac->execute([$next, $codes[$next-1], $serie]);
            $exec_update_lfa->execute([$next, $codes[$next-1], $serie]);
            $exec_update_lco->execute([$next, $codes[$next-1], $serie]);
          }
        }
      }else{
        $query_delete = "DELETE * FROM F_LCO";
        $exec_delete = $this->con->prepare($query_delete);
        $exec_delete->execute();
      }
    }
    $exec_delete_series_lco = $this->con->prepare($query_delete_series_lco);
    $exec_delete_series_lco->execute($series);
    $exec_delete_series_lfa = $this->con->prepare($query_delete_series_lfa);
    $exec_delete_series_lfa->execute($series);
  }

  public function F_FRE($store = 3){
    /* ELIMINAR FACTURAS */

    $query_delete = "DELETE * FROM F_FRE WHERE NOT ALMFRE = ? AND NOT ALMFRE = ?";
    $almacenes = $this->almacenes($store);
    $exec_delete = $this->con->prepare($query_delete);
    $exec_delete->execute($almacenes);

    $query_select = "SELECT * FROM F_FRE";
    $exec_select = $this->con->prepare($query_select);
    $exec_select->execute();
    $headers = collect($exec_select->fetchAll(\PDO::FETCH_ASSOC))->groupBy('TIPFRE');
    $series = array_keys($headers->toArray());

    $query_delete_series_lfr = "DELETE * FROM F_LFR WHERE NOT TIPLFR = ?";
    $a = [];
    $i = 0;
    foreach($series as $serie){
      /* MODIFICAR PARA CEDIS */
      if($i>0){
        $query_delete_series_lfr = $query_delete_series_lfr. " AND NOT TIPLFR = ?";
      }
      $i++;
      $codes = collect(array_column($headers[$serie]->toArray(), 'CODFRE'))->sort()->values()->all();
      if(count($codes)>0){
        $start = "0";
        $b = [];
        $max = ceil(count($codes)/50);
        foreach (array_chunk($codes, 50) as $key => $insert) {
          $b[] = $key;
          
          $query_delete_lfr = "DELETE * FROM F_LFR WHERE TIPLFR = ?";
          for($i=0; $i<count($insert); $i++){
            $query_delete_lfr = $query_delete_lfr. " AND NOT CODLFR = ?";
          }
          $query_delete_lfr = $query_delete_lfr. " AND CODLFR > ? AND CODLFR < ?";
          $a[] = ($max-1)."---".strval($key);
          $a[] = $max == strval($key-1);
          $a[] = "---";
          if($max-1 == strval($key)){
            $array_codes = array_merge([strval($serie)],$insert, [$start, 1000000]);
          }else{
            $array_codes = array_merge([strval($serie)],$insert, [$start, $insert[count($insert)-1]]);
          }
          $exec_delete_lfr = $this->con->prepare($query_delete_lfr);
          $exec_delete_lfr->execute($array_codes);
          $start = $insert[count($insert)-1];
        }
    
        $query_update_fre = "UPDATE F_FRE SET CODFRE = ? WHERE CODFRE = ? AND TIPFRE = ?";
        $exec_update_fre = $this->con->prepare($query_update_fre);
    
        $query_update_lfr = "UPDATE F_LFR SET CODLFR = ? WHERE CODLFR = ? AND TIPLFR = ?";
        $exec_update_lfr = $this->con->prepare($query_update_lfr);

        foreach(range(1, count($codes)) as $next){
          if($next != $codes[$next-1]){
            $exec_update_fre->execute([$next, $codes[$next-1], $serie]);
            $exec_update_lfr->execute([$next, $codes[$next-1], $serie]);
          }
        }
      }else{
        $query_delete = "DELETE * FROM F_LFR";
        $exec_delete = $this->con->prepare($query_delete);
        $exec_delete->execute();
      }
    }
    $exec_delete_series_lfr = $this->con->prepare($query_delete_series_lfr);
    $a [] = $exec_delete_series_lfr;
    $a [] = $exec_delete_series_lfr->execute($series);
  }

  public function F_FRD($store = 3){
    /* ELIMINAR ALBARANES */
    $query_delete = "DELETE * FROM F_FRD WHERE NOT ALMFRD = ? AND NOT ALMFRD = ?";
    $almacenes = $this->almacenes($store);
    $exec_delete = $this->con->prepare($query_delete);
    $exec_delete->execute($almacenes);

    $query_select = "SELECT * FROM F_FRD";
    $exec_select = $this->con->prepare($query_select);
    $exec_select->execute();
    $headers = $exec_select->fetchAll(\PDO::FETCH_ASSOC);

    $codes = collect(array_column($headers, 'CODFRD'))->sort()->values()->all();

    if(count($codes)>0){
      $query_body = "DELETE * FROM F_LFD WHERE NOT CODLFD = ?";
      for($i=0; $i<count($codes)-1; $i++){
        $query_body = $query_body. " AND NOT CODLFD = ?";
      }
      $exec_body = $this->con->prepare($query_body);
      $exec_body->execute($codes);
  
      $query_update_frd = "UPDATE F_FRD SET CODFRD = ? WHERE CODFRD = ?";
      $exec_update_frd = $this->con->prepare($query_update_frd);
  
      $query_update_lfd = "UPDATE F_LFD SET CODLFD = ? WHERE CODLFD = ?";
      $exec_update_lfd = $this->con->prepare($query_update_lfd);
      foreach(range(1, count($codes)) as $next){
        if($next != $codes[$next-1]){
          $exec_update_fac->execute([$next, $codes[$next-1]]);
          $exec_update_lfa->execute([$next, $codes[$next-1]]);
        }
      }

    }else{
      $query_body = "DELETE * FROM F_LFD";
      $exec_body = $this->con->prepare($query_body);
      $exec_body->execute($codes);
    }
  }

  public function F_ING($store = 3){
    /* ELIMINAR TODO */
    $query = "DELETE * FROM F_ING";
    $exec = $this->con->prepare($query);
    $res = $exec->execute();
  }

  public function next(Request $request){
    $_workpoint = $request->_workpoint;
    $this->F_RET($_workpoint);
    $this->F_PRO($_workpoint);
    $this->T_TPV($_workpoint);
    $this->T_TER($_workpoint);
    $this->T_ATE($_workpoint);
    $this->F_PRE($_workpoint);
    $this->F_PRC($_workpoint);
    $this->F_PPR($_workpoint);
    $this->F_PDA($_workpoint);
    $this->F_OBR($_workpoint);
    $this->F_LPS($_workpoint);
    $this->F_CNP($_workpoint);
    $this->F_LPP($_workpoint);
    $this->F_LPF($_workpoint);
    $this->F_ING($_workpoint);
    $this->F_FRD($_workpoint);
    $this->F_FRE($_workpoint);
    $this->F_FAC($_workpoint);
    $this->F_TRA($_workpoint);
    $this->F_ALB($_workpoint);
    $this->F_FAB($_workpoint);
    $this->F_ENT($_workpoint);
    $this->F_ANT($_workpoint);
    $this->F_CIN($_workpoint);
    $this->F_STO($_workpoint);
    $this->F_ALM($_workpoint);
    $this->F_AGE($_workpoint);
    return response()->json(["result" => true]);
  }

  public function F_LPF($store = 3){
    /* ELIMINAR TODO */
    $res = true;
    if($store != 1){
      $query = "DELETE * FROM F_LPF";
      $exec = $this->con->prepare($query);
      $res = $exec->execute();
    }
  }


  public function F_LPP($store = 3){
    /* ELIMINAR TODO */
    $res = true;
    if($store != 1){
      $query = "DELETE * FROM F_LPP";
      $exec = $this->con->prepare($query);
      $res = $exec->execute();
    }
  }

  public function F_CNP($store = 3){
    /* ELIMINAR TODO */
    $res = true;
    if($store != 1){
      $query = "DELETE * FROM F_CNP";
      $exec = $this->con->prepare($query);
      $res = $exec->execute();
    }
  }

  public function F_LPS($store = 3){
    /* ELIMINAR TODO */
    $res = true;
    if($store != 1){
      $query = "DELETE * FROM F_LPS";
      $exec = $this->con->prepare($query);
      $res = $exec->execute();
    }
  }

  public function F_OBR($store = 3){
    /* ELIMINAR TODO */
    $res = true;
    if($store != 1){
      $query = "DELETE * FROM F_OBR";
      $exec = $this->con->prepare($query);
      $res = $exec->execute();
    }
  }

  public function F_PDA($store = 3){
    /* ELIMINAR TODO */
    $res = true;
    if($store != 1){
      $query = "DELETE * FROM F_PDA";
      $exec = $this->con->prepare($query);
      $res = $exec->execute();
    }
  }

  public function F_PPR($store = 3){
    /* ELIMINAR TODO */
    $res = true;
    if($store != 1){
      $query = "DELETE * FROM F_PPR";
      $exec = $this->con->prepare($query);
      $res = $exec->execute();
    }
  }

  public function F_PRC($store = 3){
    /* ELIMINAR TODO */
    $res = true;
    if($store != 1){
      $query = "DELETE * FROM F_PRC";
      $exec = $this->con->prepare($query);
      $res = $exec->execute();
    }
  }

  public function F_PRE($store = 3){
    /* ELIMINAR TODO */
    $res = true;
    if($store != 1){
      $query = "DELETE * FROM F_PRE";
      $exec = $this->con->prepare($query);
      $res = $exec->execute();
    }
  }

  public function T_ATE($store = 3){
    /* DELETE ALMACENES QUE NO PERTENECEN A LAS TIENDAS */
    $query = "DELETE * FROM T_ATE WHERE NOT TERATE = ?";
    $terminales = $this->terminales($store);
    for($i=0; $i<count($terminales)-1; $i++){
      $query = $query. " AND NOT TERATE = ?";
    }
    $exec = $this->con->prepare($query);
    $res = $exec->execute($terminales);
  }

  public function T_TER($store = 3){
    /* DELETE ALMACENES QUE NO PERTENECEN A LAS TIENDAS */
    $query = "DELETE * FROM T_TER WHERE NOT CODTER = ?";
    $terminales = $this->terminales($store);
    for($i=0; $i<count($terminales)-1; $i++){
      $query = $query. " AND NOT CODTER = ?";
    }
    $exec = $this->con->prepare($query);
    $res = $exec->execute($terminales);
  }

  public function T_TPV($store = 3){
    /* DELETE ALMACENES QUE NO PERTENECEN A LAS TIENDAS */
    $query = "DELETE * FROM T_TPV WHERE NOT ALMTPV = ? AND NOT ALMTPV = ?";
    $almacenes = $this->almacenes($store);
    $exec = $this->con->prepare($query);
    $res = $exec->execute($almacenes);
  }

  public function F_PRO($store = 3){
    /* DELETE ALMACENES QUE NO PERTENECEN A LAS TIENDAS */
    $query = "DELETE * FROM F_PRO WHERE NOT CODPRO = 5";
    $codes = array_merge(range(250, 261), range(800,850));
    for($i=0; $i<count($codes)-1; $i++){
      $query = $query. " AND NOT CODPRO = ?";
    }

    $exec = $this->con->prepare($query);
    $res = $exec->execute($codes);
  }
  
  public function F_RET($store = 3){
    /* DELETE ALMACENES QUE NO PERTENECEN A LAS TIENDAS */
    $query = "DELETE * FROM F_RET WHERE NOT CAJRET = ?";
    
    $terminales = $this->terminales($store);
    
    for($i=0; $i<count($terminales)-1; $i++){
      $query = $query. " AND NOT CAJRET = ?";
    }

    $exec = $this->con->prepare($query);
    $res = $exec->execute($terminales);

    $query_select = "SELECT * FROM F_RET";
    $exec_select = $this->con->prepare($query_select);
    $exec_select->execute();
    $headers = $exec_select->fetchAll(\PDO::FETCH_ASSOC);

    $codes = collect(array_column($headers, 'CODRET'))->sort()->values()->all();
    if(count($codes)>0){
      $query_update_ter = "UPDATE F_RET SET CODRET = ? WHERE CODRET = ?";
      $exec_update_ter = $this->con->prepare($query_update_ter);
      foreach(range(1, count($codes)) as $next){
        if($next != $codes[$next-1]){
          $exec_update_ter->execute([$next, $codes[$next-1]]);
        }
      }
    }
  }

  public function almacenes($_workpoint){
    switch($_workpoint){
      case 1: //CEDISSP
        $almacenes = ["GEN", ""];
      break;
      case 13: //SP3
        $almacenes = ["SP3", "011"];
      break;
      case 6: //CR2
        $almacenes = ["CR2", "009"];
      break;
      case 10: //RC2
        $almacenes = ["RA2", "013"];
      break;
      case 4: //SP2
        $almacenes = ["SP2", "005"];
      break;
      case 9: //RC1
        $almacenes = ["RA1", "006"];
      break;
      case 12: //BR2
        $almacenes = ["BR2", "002"];
      break;
      case 11: //BR1
        $almacenes = ["BR1", "001"];
      break;
      case 3: //SP1
        $almacenes = ["SP1", "010"];
      break;
      case 5: //CR1
        $almacenes = ["CR1", "008"];
      break;
      case 8: //AP2
        $almacenes = ["AP2", "004"];
      break;
      case 15: //SP4
        $almacenes = ["SP4", "012"];
      break;
      case 7: //AP1
        $almacenes = ["AP1", "003"];
      break;
      case 13: //BOL
        $almacenes = ["BOL", "007"];
      break;
    }
    return $almacenes;
  }

  public function getSerie($_workpoint){
    switch($_workpoint){
      case 1: //CEDISSP
        $serie = [1,2,3,4,5,6,7,8,9];
      break;
      case 13: //SP3
        $serie = [1];
      break;
      case 6: //CR2
        $serie = [4];
      break;
      case 10: //RC2
        $serie = [5];
      break;
      case 4: //SP2
        $serie = [1,2];
      break;
      case 9: //RC1
        $serie = [5];
      break;
      case 12: //BR2
        $serie = [7];
      break;
      case 11: //BR1
        $serie = [7];
      break;
      case 3: //SP1
        $serie = [1];
      break;
      case 5: //CR1
        $serie = [3];
      break;
      case 8: //AP2
        $serie = [6];
      break;
      case 15: //SP4
        $serie = [1];
      break;
      case 7: //AP1
        $serie = [6];
      break;
      case 13: //BOL
        $serie = [9];
      break;
    }
    return $almacenes;
  }

  public function terminales($_workpoint){
    switch($_workpoint){
      case 1: //CEDISSP
        $caja = [0,25];
      break;
      case 13: //SP3
        $caja = [13];
      break;
      case 6: //CR2
        $caja = [41];
      break;
      case 10: //RC2
        $caja = [53];
      break;
      case 4: //SP2
        $caja = [3];
      break;
      case 9: //RC1
        $caja = [18];
      break;
      case 12: //BR2
        $caja = [91];
      break;
      case 11: //BR1
        $caja = [10];
      break;
      case 3: //SP1
        $caja = [11,19,20,30];
      break;
      case 5: //CR1
        $caja = [12];
      break;
      case 8: //AP2
        $caja = [15];
      break;
      case 15: //SP4
        $caja = [14];
      break;
      case 7: //AP1
        $caja = [16];
      break;
      case 13: //BOL
        $caja = [17];
      break;
    }
    return $caja;
  }
}
