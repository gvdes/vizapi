<?php

namespace App\Http\Controllers;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use App\Requisition;

class MiniPrinterController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public $printer = null;
    public function __construct($ip_printer){
        /* $ip_printer = env('PRINTER'); */
        $connector = new NetworkPrintConnector($ip_printer, 9100);
        $this->printer = new Printer($connector);
    }

    public function requisitionReceipt($requisition){
        $summary = $requisition->products->reduce(function($summary, $product){
            $summary['models'] = $summary['models'] + 1;
            $summary['articles'] = $summary['articles'] + $product->pivot->units;
            return $summary;
        }, ["models" => 0, "articles" => 0]);
        $finished_at = $requisition->log->filter(function($log){
            return $log->pivot->_status = 1;
        });
        $finished_at = $finished_at[sizeof($finished_at) - 1];
        $printer = $this->printer;
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(1,2);
        $printer->setEmphasis(true);
        $printer->setReverseColors(true);
        $printer->text(" Solicitud de mercancia a ".$requisition->to->alias." #".$requisition->id."\n");
        $printer->setReverseColors(false);
        $printer->setTextSize(1,1);
        $printer->text("--------------------------------------------\n");
        $printer->setTextSize(1,2);
        $printer->text(" Pedido generado ".$finished_at->pivot->created_at." Hrs \n");
        $printer->setTextSize(1,1);
        $printer->text("--------------------------------------------\n");
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(1,1);
        $printer->text("Modelos: ");
        $printer->setTextSize(2,1);
        $printer->text($summary['models']);
        $printer->setTextSize(1,1);
        $printer->text(" Piezas: ");
        $printer->setTextSize(2,1);
        $printer->text($summary['articles']."\n");
        $printer->setTextSize(1,1);
        $printer->text("--------------------------------------------\n");
        $printer->setBarcodeHeight(80);
        $printer->setBarcodeWidth(4);
        $printer->barcode($requisition->id);
        $printer->feed(1);
        $printer->text("GRUPO VIZCARRA\n");
        $printer->feed(1);
        $printer->cut();
        $printer->close();
        return true;
        try {
        } catch(\Exception $e){
            return false;
        }
    }

    public function requisitionTicket(Requisition $requisition){
        $printer = $this->printer;
        $summary = $requisition->products->reduce(function($summary, $product){
            $summary['models'] = $summary['models'] + 1;
            $summary['articles'] = $summary['articles'] + $product->pivot->units;
            $volumen = ($product->dimensions->length * $product->dimensions->height * $product->dimensions->width) / 1000000;
            if($volumen<=0){
                $summary['sinVolumen'] = $summary['sinVolumen'] + $product->pivot->units;
            }
            $summary['volumen'] = $summary['volumen'] + $volumen;
            return $summary;
        }, ["models" => 0, "articles" => 0, "volumen" => 0, "sinVolumen" => 0]);
        $finished_at = $requisition->log->filter(function($log){
            return $log->pivot->_status = 1;
        });
        $finished_at = $finished_at[sizeof($finished_at) - 1];
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        if($requisition->printed>0){
            $printer->setTextSize(2,2);
            $printer->setReverseColors(true);
            $printer->text(" REIMPRESION \n");
            $printer->feed(1);
            $printer->setReverseColors(false);
        }
        $printer->setTextSize(1,2);
        $printer->setEmphasis(true);
        $printer->setReverseColors(true);
        $printer->text(" Pedido para ".$requisition->to->alias." \n");
        $printer->setReverseColors(false);
        $printer->setTextSize(1,2);
        $printer->text("----------------------------------------\n");
        $printer->text(" Generado: ".$finished_at->pivot->created_at." Hrs ");
        $printer->setTextSize(2,2);
        $printer->setReverseColors(true);
        $printer->text("#".$requisition->id."\n");
        $printer->setReverseColors(false);
        $printer->setTextSize(1,2);
        $printer->text("----------------------------------------\n");
        foreach($requisition->products as $key => $product){
            $locations = $product->locations->reduce(function($res, $location){
                return $res.$location->path."";
            }, '');
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->setTextSize(2,1);
            $printer->text(($key+1)."█ ".trim($locations)." █".$product->code." \n");
            $printer->setTextSize(1,1);
            $printer->text($product->description." \n");
            $printer->text(" CAJAS SOLICITADAS: ");
            $printer->setTextSize(2,1);
            $printer->text($product->pivot->units."\r\n");
            $printer->setJustification(Printer::JUSTIFY_RIGHT);
            $printer->setTextSize(1,1);
            $printer->text("UF: ");
            $printer->setTextSize(2,1);
            $printer->text($product->pivot->units);
            $printer->setTextSize(1,1);
            $printer->text(" - UD: ");
            $printer->setTextSize(2,1);
            $printer->text($product->pivot->units."\r\n");
            $printer->feed(1);
        }
        
        /* $printer->setTextSize(1,1);
        $printer->text("--------------------------------------------\n");
        $printer->text("--------------------------------------------\n");
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text(" COMPLEMENTO █ 0002 █ 2/2");
        $printer->setTextSize(2,1);
        $printer->text(" SP2\n");
        $printer->setTextSize(1,1);
        $printer->text(" Generado: 21-10-2020 15:30 Hrs \n");
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->setTextSize(2,1);
        $printer->text("1█ P1-P1-T2,T3 █ 5658 \n");
        $printer->setTextSize(1,1);
        $printer->text("Carreola de juguete doll \n");
        $printer->text("   CAJAS SOLICITADAS: ");
        $printer->setTextSize(2,1);
        $printer->text("2\r\n");
        $printer->setJustification(Printer::JUSTIFY_RIGHT);
        $printer->setTextSize(1,1);
        $printer->text("UF: ");
        $printer->setTextSize(2,1);
        $printer->text("16");
        $printer->setTextSize(1,1);
        $printer->text(" - UD: ");
        $printer->setTextSize(2,1);
        $printer->text(" 287\r\n");
        $printer->feed(1);
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text("2█ P1-P1-T4,T5 █ JL7229 \n");
        $printer->setTextSize(1,1);
        $printer->text("Carreola de juguete doll \n");
        $printer->text("   CAJAS SOLICITADAS: ");
        $printer->setTextSize(2,1);
        $printer->text("1\r\n");
        $printer->setJustification(Printer::JUSTIFY_RIGHT);
        $printer->setTextSize(1,1);
        $printer->text("UF: ");
        $printer->setTextSize(2,1);
        $printer->text("24");
        $printer->setTextSize(1,1);
        $printer->text(" - UD: ");
        $printer->setTextSize(2,1);
        $printer->text(" 240\r\n"); */
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(1,1);
        $printer->text("--------------------------------------------\n");
        $printer->text("Modelos: ");
        $printer->setTextSize(2,1);
        $printer->text($summary['models']);
        $printer->setTextSize(1,1);
        $printer->text(" Piezas: ");
        $printer->setTextSize(2,1);
        $printer->text($summary['articles']."\n");
        $printer->setTextSize(1,1);
        $printer->text("Volumen ".$summary['volumen']." m^3\n");
        $printer->text($summary['sinVolumen']." cajas sin contabilizar\n");
        $printer->text("--------------------------------------------\n");
        $printer->setBarcodeHeight(80);
        $printer->setBarcodeWidth(4);
        $printer->barcode("0002");
        $printer->feed(1);
        $printer->text("GRUPO VIZCARRA\n");
        $printer->feed(1);
        $printer->cut();
        $printer->close();
        return response()->json(['result'=> 'ticket impreso']);
    }
}
