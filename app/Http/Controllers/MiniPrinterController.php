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
    public function __construct(){
        $ip_printer = env('PRINTER');
        $connector = new NetworkPrintConnector("192.168.1.36",9100);
        $this->printer = new Printer($connector);
    }

    public function requisitionReceipt(Requisition $requisition){
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
        $printer->text(" Pedido generado ".$requisition->log[0]->updated_at." Hrs \n");
        $printer->setTextSize(1,1);
        $printer->text("--------------------------------------------\n");
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(1,1);
        $printer->text("Modelos: ");
        $printer->setTextSize(2,1);
        $printer->text($requisition->summary->models);
        $printer->setTextSize(1,1);
        $printer->text(" Piezas: ");
        $printer->setTextSize(2,1);
        $printer->text($requisition->summary->articles."\n");
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
        return response()->json(['result'=> 'ticket impreso']);
    }

    public function requisitionTicket(){
        $ip_printer = env('PRINTER');
        $connector = new NetworkPrintConnector("192.168.1.36",9100);
        $printer = new Printer($connector);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(1,2);
        $printer->setEmphasis(true);
        $printer->setReverseColors(true);
        $printer->text(" Pedido para SP2 \n");
        $printer->setReverseColors(false);
        $printer->setTextSize(1,2);
        $printer->text("----------------------------------------\n");
        $printer->text(" Generado: 21-10-2020 15:30 Hrs ");
        $printer->setTextSize(2,2);
        $printer->setReverseColors(true);
        $printer->text("#0002\n");
        $printer->setReverseColors(false);
        $printer->setTextSize(1,2);
        $printer->text("----------------------------------------\n");

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
        $printer->text(" 240\r\n");
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        
        $printer->setTextSize(1,1);
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
        $printer->text(" 240\r\n");
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(1,1);
        $printer->text("--------------------------------------------\n");
        $printer->text("Modelos: ");
        $printer->setTextSize(2,1);
        $printer->text("4");
        $printer->setTextSize(1,1);
        $printer->text(" Piezas: ");
        $printer->setTextSize(2,1);
        $printer->text("10\n");
        $printer->setTextSize(1,1);
        $printer->text("Volumen 10 m^3\n");
        $printer->text("5 cajas sin contabilizar\n");
        $printer->text("--------------------------------------------\n");
        $printer->setBarcodeHeight(80);
        $printer->setBarcodeWidth(4);
        $printer->barcode("0002");
        $printer->feed(1);
        $printer->text("GRUPO VIZCARRA\n");
        $printer->feed(1);
        $printer->cut();
        /* $printer->close(); */
        return response()->json(['result'=> 'ticket impreso']);
    }
}
