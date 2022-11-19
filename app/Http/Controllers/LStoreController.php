<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Requisition;
use App\WorkPoint;
use App\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;


class LStoreController extends Controller{

    public $account = null;

    public function __construct(){
        $this->account = Auth::payload()['workpoint'];
    }

    public function index(){
        try {
            $wkp = $this->account->_workpoint;
            $now = CarbonImmutable::now();
            $from = $now->startOf("day")->format("Y-m-d H:i");
            $to = $now->endOf("day")->format("Y-m-d H:i");

            $query = "  SELECT
                            P.id,
                            P.name,
                            P.code,
                            P.description,
                            GETSECTION(PC.id) AS SECCION,
                            GETFAMILY(PC.id) AS FAMILIA,
                            GETCATEGORY(PC.id) AS CATEGORIA,
                            VENT.VENTA AS VEN,
                            STO.STOCK AS STO,
                            STO.MIN as MIN,
                            STO.MAX as MAX
                        FROM products P
                            INNER JOIN product_categories PC ON PC.id = P._category
                            LEFT JOIN (SELECT _product AS CODIGO, min AS MIN, max as MAX, SUM(stock) AS STOCK FROM product_stock WHERE _workpoint = $wkp GROUP BY _product,min,max) AS STO ON STO.CODIGO = P.id
                            LEFT JOIN (SELECT PS._product AS CODIGO, SUM(PS.amount) AS VENTA FROM product_sold PS INNER JOIN sales S ON S.id = PS._sale INNER JOIN cash_registers CS ON CS.id = S._cash WHERE  _workpoint = $wkp AND S.created_at BETWEEN '$from' AND '$to' GROUP BY PS._product) AS VENT ON VENT.CODIGO = P.id
                        WHERE P._status!=4";

            $rows = DB::select($query);

            return response()->json([
                "user"=>$this->account,
                "rows"=>$rows
            ]);
        } catch (\Throwable $th) { return response()->json($th,500); }
    }
}
