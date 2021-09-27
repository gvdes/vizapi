<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\CashRegister;

class CashRegisterController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
    }

    public function changeStatus(Request $request){
        $cashRegister = CashRegister::with('status')->find($request->_cash);
        if($cashRegister){
            $cashRegister->_status = $cashRegister->_status == 1 ? 2 : 1;
            $result = $cashRegister->save();
            $cashRegister->refresh();
            return response()->json(["msg" => "ok", "server_status" => 200, "success" => $result, "status" => $cashRegister->status]);
        }
        return response()->json(["msg" => "No se encontro la caja", "server_status" => 404, "success" => false]);
    }

    public function changeCashier(Request $request){
        $cashRegister = CashRegister::with('cashier')->find($request->_cash);
        if($cashRegister){
            $account = \App\Account::find($request->_account);
            if($account){
                $cashRegister->_account = $account->id;
                $result = $cashRegister->save();
                $cashRegister->refresh();
                return response()->json(["msg" => "ok", "server_status" => 200, "success" => $result, "cashier" => $cashRegister->cashier]);
            }
            return response()->json(["msg" => "No se encontro al usuario", "server_status" => 404, "success" => false]);
        }
        return response()->json(["msg" => "No se encontro la caja", "server_status" => 404, "success" => false]);
    }

    public function find($id){
        $cashRegister = CashRegister::with(['status', 'cashier', 'workpoint'])->find($id);
        return response()->json($cashRegister);
    }
}
