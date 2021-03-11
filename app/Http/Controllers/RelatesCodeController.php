<?php

namespace App\Http\Controllers;

use App\Product;
use App\ProductVariant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RelatesCodeController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        $this->account = Auth::payload()['workpoint'];
    }

    public function seeder(){
        try{
            /* $start = microtime(true);
            $client = curl_init();
            curl_setopt($client, CURLOPT_URL, "192.168.1.224:1618/access/public/product/related");
            curl_setopt($client, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
            $codes = collect(json_decode(curl_exec($client), true));
            curl_close($client); */
            $fac = new FactusolController();
            $codes = $fac->getRelatedCodes();
            if($codes){
                DB::transaction(function() use($codes){
                    foreach($codes as $code){
                        $product = Product::where('code', $code['ARTEAN'])->first();
                        if($product){
                            $variant = new ProductVariant();
                            $product->variants()->updateOrCreate(['barcode' => $code['EANEAN']], ['stock' => 0]);
                        }
                    }
                });
                return response()->json([
                    "success" => true,
                    "products" => count($codes),
                    "time" => microtime(true) - $start
                ]);
            }
            return response()->json(["message" => "No se obtuvo respuesta del servidor de factusol"]);
        }catch(Exeption $e){
            return response()->json(["message" => "No se ha podido poblar la base de datos"]);
        }
    }
}
