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
    public function __construct($ip_printer, $port){
        try{
            $connector = new NetworkPrintConnector($ip_printer, $port);
            $this->printer = new Printer($connector);
        }catch(\Exception $e){
            return $e;
        }
    }

    public function requisitionReceipt($requisition){
        try{
            $printer = $this->printer;
            if(!$printer){
                return false;
            }
            $summary = $requisition->products->reduce(function($summary, $product){
                if(intval($product->pivot->stock)>0){
                    $summary['models'] = $summary['models'] + 1;
                    $pieces = $product->_unit == 3 ? $product->pieces : 1;
                    $summary['articles'] = $summary['articles'] + $product->pivot->units * $pieces;
                }else{
                    $summary['soldOut'] = $summary['soldOut'] + 1;
                }
                return $summary;
            }, ["models" => 0, "articles" => 0, "soldOut" => 0]);
            $finished_at = $requisition->log->filter(function($log){
                return $log->pivot->_status = 1;
            });
            $finished_at = $finished_at[sizeof($finished_at) - 1];
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
            if($summary['soldOut']>0){
                $printer->setTextSize(1,1);
                $printer->text("Modelos agotados: ");
                $printer->setTextSize(2,1);
                $printer->text($summary['soldOut']."\n");
                $printer->setTextSize(1,1);
            }
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
        } catch(\Exception $e){
            return false;
        }
    }

    public function requisitionTicket(Requisition $requisition){
        try{
            $printer = $this->printer;
            if(!$printer){
                return false;
            }
            $summary = $requisition->products->reduce(function($summary, $product){
                $pieces = $product->_unit == 3 ? $product->pieces : 1;
                if($product->pivot->stock>0){
                    $summary['models'] = $summary['models'] + 1;
                    $summary['articles'] = $summary['articles'] + $product->pivot->units * $pieces;
                    $volumen = ($product->dimensions->length * $product->dimensions->height * $product->dimensions->width) / 1000000;
                    if($volumen<=0){
                        $summary['sinVolumen'] = $summary['sinVolumen'] + $product->pivot->units;
                    }
                    $summary['volumen'] = $summary['volumen'] + $volumen;
                }else{
                    $summary['modelsSouldOut'] = $summary['modelsSouldOut'] + 1;
                    $summary['articlesSouldOut'] = $summary['articlesSouldOut'] + $product->pivot->units * $pieces;
                }
                return $summary;
            }, ["models" => 0, "articles" => 0, "volumen" => 0, "sinVolumen" => 0, "modelsSouldOut" => 0, "articlesSouldOut" => 0]);
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
            $printer->text(" Pedido para ".$requisition->from->alias." \n");
            $printer->setReverseColors(false);
            if($requisition->notes){
                $printer->text(" $requisition->notes \n");
            }
            $printer->setTextSize(1,2);
            $printer->text("----------------------------------------\n");
            $printer->text(" Generado: ".$finished_at->pivot->created_at." Hrs ");
            $printer->setTextSize(2,2);
            $printer->setReverseColors(true);
            $printer->text("#".$requisition->id."\n");
            $printer->setReverseColors(false);
            $printer->setTextSize(1,2);
            $printer->text("----------------------------------------\n");
            $y = 1;
            $product = collect($requisition->products);
            $groupBy = $product->map(function($product){
                $product->locations->sortBy('id');
                return $product;
            })->groupBy(function($product){
                if(count($product->locations)>0){
                    return explode('-',$product->locations[0]->path)[0];
                }else{
                    return '';
                }
            })->sortKeys();
            /* return $groupBy; */
            $piso_num = 1;
            foreach($groupBy as $piso){
                if($piso_num>1){
                    $printer->setJustification(Printer::JUSTIFY_LEFT);
                    $printer->setTextSize(1,1);
                    $printer->text("----------------------------------------------\n");
                    $printer->text("----------------------------------------------\n");
                    $printer->setTextSize(2,1);
                    $printer->text("█ ".$requisition->to->alias." >>> ".$requisition->from->alias." █\n");
                    $printer->setTextSize(1,1);
                    $printer->text("Complemento █ ".$piso_num." █ ".$piso_num."/".count($groupBy)."\n");
                    $printer->feed(1);
                }
                foreach($piso as $product){
                    $pieces = 1;
                    if(intval($product->pivot->stock)>0){
                        $locations = $product->locations->reduce(function($res, $location){
                            return $res.$location->path.",";
                        }, '');
                        $printer->setJustification(Printer::JUSTIFY_LEFT);
                        $printer->setTextSize(2,1);
                        $printer->text($y."█ ".trim($locations)."█".$product->code." \n");
                        $printer->setTextSize(1,1);
                        $printer->text($product->description." \n");
                        if($product->units->id == 3){
                            $printer->text(" CAJAS SOLICITADAS: ");
                            $printer->setTextSize(2,1);
                            $printer->text($product->pivot->units."\r\n");
                            $printer->setJustification(Printer::JUSTIFY_RIGHT);
                            $pieces = $product->pieces;
                        }
                        $printer->setTextSize(1,1);
                        $printer->text("UF: ");
                        $printer->setTextSize(2,1);
                        $printer->text($product->pivot->units*$pieces);
                        $printer->setTextSize(1,1);
                        $printer->text(" - UD: ");
                        $printer->setTextSize(2,1);
                        $printer->text($product->pivot->stock."\n");
                        if($product->pivot->notes){
                            $printer->setTextSize(1,1);
                            $printer->text($product->pivot->notes."\n");
                        }
                        $printer->feed(1);
                        $y++;
                    }
                }
                $piso_num++;
            }
            /* foreach($requisition->products as $key => $product){
            } */
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
            if($summary['articlesSouldOut']>0){
                $printer->setJustification(Printer::JUSTIFY_LEFT);
                $printer->text("Modelos agotados: ");
                $printer->setTextSize(2,1);
                $printer->text($summary['modelsSouldOut']."\n");
                $printer->setTextSize(1,1);
                $printer->text("Piezas agotadas: ");
                $printer->setTextSize(2,1);
                $printer->text($summary['articlesSouldOut']."\n");
            }
            $printer->setTextSize(1,1);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
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
        }catch(\Exception $e){
            return false;
        }
    }

    public function test(Requisition $requisition){
        $product = collect($requisition->products);
        $groupBy = $product->map(function($product){
            $product->locations->sortBy('id');
            return $product;
        })->groupBy(function($product){
            if(count($product->locations)>0){
                return explode('-',$product->locations[0]->path)[0];
            }else{
                return '';
            }
        })->sortKeys();
        return $groupBy;
    }
}
