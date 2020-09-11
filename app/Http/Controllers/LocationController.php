<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
    }

    /**
     * Create celler
     * @param object request
     * @param string request[].name
     * @param string request[]._workpoint
     * @param string request[]._type
     */
    public function createCeller(Request $request){
        $celler = \App\Celler::create([
            'name' => $request->name,
            '_workpoint' => $request->_workpoint,
            '_type' => $request->_type
        ]);
        return response()->json([
            'success' => true,
            'celler' => $celler
        ]);
    }

    /**
     * Create section
     * @param object request
     * @param string request[].name
     * @param string request[].alias
     * @param string request[].path
     * @param int request[].root
     * @param int request[].deep
     * @param json request[].details
     * @param int request[].celler
     */
    public function createSection(Request $request){
        $section = \App\CellerSection::create([
            'name' => $request->name,
            'alias' => $request->alias,
            'path' => $request->path,
            'root' => $request->root,
            'deep' => $request->deep,
            'details' => $request->details,
            '_celler' => $request->_celler
        ]);

        return response()->json([
            'success' => true,
            'celler' => $section
        ]);
    }

    /**
     * Get section in celler or children's sections
     * @param object request
     * @param int request[].celler | null
     * @param int request[].section | null
     */
    public function getSections(Request $request){
        $celler = $request->_celler ? $request->_celler : null;
        $section = $request->section ? \App\CellerSection::find($request->section) : null;
        if($celler && !$section){
            $sections = \App\CellerSection::where('_celler', $celler)->get();
            $res = $sections->map(function($section){
                $section->sections = \App\CellerSection::where('root', $section->id)->get();
                return $section;
            });
        }else{
            $section->sections = \App\CellerSection::where('root', $section->id)->get();
            $res = $section;
        }
        return response()->json($res);
    }

    /**
     * Get cellers in workpoint
     */
    public function getCellers(){
        $workpoint = env('ID_ENV');
        if($workpoint){
            $cellers = \App\Celler::where('_workpoint', $workpoint)->get();
            
            $res = $cellers->map(function($celler){
                $celler->sections = \App\CellerSection::where([
                    ['_celler', '=',$celler->id],
                    ['deep', '=', 0],
                ])->get();
                return $celler;
            });
            return response()->json([
                'cellers' => $res,
            ]);
        }else{
            return response()->json([
                'msg' => 'No ha indicado ningun punto de trabajo'
            ]);
        }
    }

    /**
     * Get section in celler or children's sections
     * @param object request
     * @param int request[].code
     */
    public function getproduct(Request $request){
        $code = $request->code;
        $product = \App\Product::with('locations', 'category', 'status')->where('code', $code)->orWhere('name', $code)->first();
        return response()->json($product);
    }

    /**
     * Set locations to multiples products
     * @param object request
     * @param array request[].products
     * @param int product._product
     * @param int product._section
     */
    public function setLocations(Request $request){
        $products = collect($request->products);
        $res = DB::transaction( function () use ($products){
            try{
                foreach($products as $item){
                    $product = \App\Product::find($item->$_product);
                    $product->locations()->attach($item->$_section);
                }
                return true;
            }catch(\Exception $e){
                return false;
            }
        });
        return response()->json([
            'success' => $res
        ]);
    }
    
    /**
     * Delete locations to product
     * @param object request
     * @param int request[]._products
     * @param int product._location
     */
    public function deleteLocations(Request $request){
        $product = \App\Product::find($request->$_product);
        $section = $request->$_location;
        if($product){
            $success = $product->locations()->detach($section);
        }else{
            return response()->json([
                'msg' => "El producto no existe"
            ]);
        }
        return response()->json([
            'success' => $success
        ]);
    }
}
