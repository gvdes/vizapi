<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Requisition;
use App\WorkPoint;
use App\Product;
use Carbon\CarbonImmutable;

class LFacsController extends Controller{

    public function index(){
        $stores = WorkPoint::all();

        return response()->json([
            "stores"=>$stores
        ]);
    }

    public function ticket(Request $req){
        $folio = $req->t;
        $domain = $req->d;
        // $domain = "192.168.12.88:1619";
        $port = $req->p;

        $ticket = $this->getTicketStore($domain,$folio);
        return response()->json([ "ticket"=>$ticket["done"] ]);
    }

    private function getTicketStore($domain,$folio){
        $data = json_encode([ "folio"=>$folio ]);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "http://$domain/access/public/Facturas/Facturas");
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));

        $exec = json_decode(curl_exec($curl));
        $info = curl_getinfo($curl);

        return curl_errno($curl) ? [ "error"=>curl_error($curl) ] : [ "error"=>false, "done"=>$exec, "info"=>$info ];

        curl_close($curl);
    }
}
