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
