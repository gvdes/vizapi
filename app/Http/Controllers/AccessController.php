<?php

namespace App\Http\Controllers;

class AccessController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        $access = env('ACCESS_FILE');
        try{
            $db = new PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
            $this->con = $db;
        }catch(PDOException $e){
            die("Algo salio mal: ".$e->getMessage());
        }
    }

    public function getProducts(){
        $query = "SELECT CODART, CCOART, DLAART, CP3ART, FAMART, NPUART, PHAART FROM F_ART WHERE NPUART = 0";
        try{
            $exec = $this->con->prepare($query);
            $exec->execute();
            $rows = $exec->fetchAll(\PDO::FETCH_ASSOC);
            return $rows;
        }catch(\PDOException $e){
            die($e->getMessage());
        }
    }

    public function getKits(){
        $query = "SELECT CODCOM, ARTCOM, COSCOM FROM F_COM";
        try{
            $exec = $this->con->prepare($query);
            $exec->execute();
            $rows = $exec->fetchAll(\PDO::FETCH_ASSOC);
            return $rows;
        }catch(\PDOException $e){
            die($e->getMessage());
        }
    }

    public function getProviders(){
        $query = "SELECT CODPRO, NIFPRO, NOFPRO, NOCPRO, DOMPRO, PROPRO, TELPRO FROM F_PRO";
        try{
            $exec = $this->con->prepare($query);
            $exec->execute();
            $rows = $exec->fetchAll(\PDO::FETCH_ASSOC);
            return $rows;
        }catch(\PDOException $e){
            die($e->getMessage());
        }
    }
}
