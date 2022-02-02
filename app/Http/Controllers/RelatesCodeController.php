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

    public function seeder(){ // Función para actualizar los códigos relacionados desde 0 (Elimina y guarda los actuales)
        try{
            $start = microtime(true);
            $workpoint = \App\WorkPoint::find(1); // Se busca la sucursal de CEDIS
            $access = new AccessController($workpoint->dominio); // Se hace la conexión a la sucursal de CEDIS para obtener sus datos de ACCESS
            $codes = $access->getRelatedCodes(); // Se obtienen los códigos relacionados
            if($codes){ // Se válida que los códigos relacionados llegaron
                DB::transaction(function() use($codes){ // Trasacción para validar que se eliminan y despues se vuelven a almacenar
                    DB::table('product_variants')->delete(); // Eliminar los códigos de barra
                    foreach($codes as $code){
                        $product = Product::where('code', trim($code['ARTEAN']))->first(); // Se busca el producto con el que esta relacionado el código
                        if($product){ // Si existe el producto
                            $variant = new ProductVariant(); // Se crea el código relacionado
                            $product->variants()->updateOrCreate(['barcode' => $code['EANEAN']], ['stock' => 0]); // Se almacena el nuevo código de barras
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
