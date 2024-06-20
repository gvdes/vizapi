<?php

namespace App\Http\Controllers;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use App\Requisition;
use App\Order;
use Carbon\Carbon;

class MiniPrinterController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public $ip = null;
    public $port = null;
    public $printer = null;
    public $barcode_width = 2;
    public $barcode_height = 50;

    public function __construct($ip_printer, $port, $time = 15){
        try{
            $mp = explode(":",$ip_printer);
            $_ip = $mp[0];
            $_port = sizeof($mp)==1 ? 9100 : $mp[1];
            $connector = new NetworkPrintConnector($_ip, $_port, $time);
            // $connector = new NetworkPrintConnector($ip_printer, $port, $time);
            $this->printer = new Printer($connector);
            $this->ip = $_ip;
            $this->port = $_port;
        }catch(\Exception $e){
            return $e;
        }
    }

    public function printKey($folio, $store, $key){
        try {
            $url = "http://192.168.10.189:2200/#/checkin/$folio?key=$key";
            // $url = "http://192.168.12.183:4550/#/checkin/$folio?key=$key";

            $printer = $this->printer;

            $printer->setEmphasis(true);
            $printer->setJustification(Printer::JUSTIFY_CENTER);

            $printer->qrCode($url, Printer::QR_ECLEVEL_L, 10, Printer::QR_MODEL_2);
            $printer->text("\n$folio - $store\n");

            $printer->feed(3);
            $printer->cut();
            $printer->close();

            return 200;
        } catch (\Error $e) { $printer->close(); return 500; }
    }

    public function notifyNewOrder(Requisition $requisition){
        try {
            $finished_at = $requisition->log->filter(function($log){ return $log->pivot->_status = 1; })[0];

            // $txt = json_decode($finished_at);
            $now = Carbon::now()->format("h:i a, Y/m/d");
            $printer = $this->printer;
            if(!$printer){ return false; }
            $printer->selectPrintMode(Printer::MODE_FONT_B);
            // $printer->setEmphasis(true);
            // $printer->text(" ".substr("0000".$requisition->id,-5,5)." \n");
            $printer->setTextSize(2,2);
            $printer->setReverseColors(true);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text(" *** Nuevo Pedido *** \n\n\n");
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->setReverseColors(false);
            // $printer->setEmphasis(false);

            $printer->text(" Folio:     ".$requisition->id."\n");
            $printer->text(" Sucursal:  ".$requisition->from->alias."\n\n");

            $printer->setTextSize(2,1);
            $printer->text(" ".$requisition->created_by->names."\n");
            $printer->text(" ".$finished_at->pivot->created_at."\n");

            $printer->setTextSize(1,1);
            $printer->selectPrintMode(Printer::MODE_FONT_A);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("\n\nGRUPO VIZCARRA\n");
            $printer->selectPrintMode(Printer::MODE_FONT_B);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("Impresion: ".$now."\n");
            $printer->feed(3);
            $printer->cut();
            $printer->close();
            return true;

        } catch (\Exception $e) { $printer->close(); return false; }
    }

    public function requisitionReceipt($requisition){
        try{
            $printer = $this->printer;

            $summary = $requisition->products->reduce(function($summary, $product){
                if(intval($product->pivot->stock)>0){
                    $summary['models'] = $summary['models'] + 1;
                    $summary['articles'] = $summary['articles'] + $product->pivot->units;
                }else{ $summary['soldOut'] = $summary['soldOut']+1; }
                return $summary;
            }, [ "models"=>0, "articles"=>0, "soldOut"=>0 ]);

            $finished_at = $requisition->log->filter(fn($log) => $log->pivot->_status=1);
            $finished_at = $finished_at[sizeof($finished_at) - 1];

            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setTextSize(2,2);
            $printer->setEmphasis(true);
            $printer->setReverseColors(true);
            $printer->text(" ".substr("0000".$requisition->id,-5,5)." \n");
            $printer->setReverseColors(false);
            $printer->setEmphasis(false);
            $printer->setTextSize(1,1);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("\n".$requisition->to->alias." ha recibido tu pedido!!\n");
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->setTextSize(1,1);
            $printer->text("------------------------------------------------\n");
            $printer->setTextSize(1,1);
            $printer->text(" AGENTE:     ".$requisition->created_by->names." ".$requisition->surname_pat."\n");
            $printer->text(" FECHA/HORA: ".$finished_at->pivot->created_at." Hrs \n");
            $printer->setTextSize(1,1);
            $printer->text("------------------------------------------------\n");
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
            }
            $printer->setTextSize(1,1);
            $printer->text("--------------------------------------------\n");
            $printer->setBarcodeHeight($this->barcode_height);
            $printer->setBarcodeWidth($this->barcode_width);
            $printer->barcode($requisition->id);
            $printer->feed(1);
            $printer->text("GRUPO VIZCARRA\n");
            $printer->feed(1);
            $printer->cut();
            $printer->close();
            return true;
        } catch(\Exception $e){
            $printer->close();
            return false;
        }
    }

    public function orderReceipt($order, $cash){
        $printer = $this->printer;
        if(!$printer){
            return false;
        }
        $summary = $order->products->reduce(function($summary, $product){
            $summary['models'] = $summary['models'] + 1;
            $summary['units'] = $summary['units'] + $product->pivot->units;
            return $summary;
        }, ["models" => 0, 'units' => 0]);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        if($order->_order){
            $printer->setTextSize(2,2);
            $printer->setEmphasis(true);
            $printer->setReverseColors(true);
            $printer->setTextSize(2,2);
            $printer->text("ANEXO ".$order->_order." \n");
            $printer->setEmphasis(false);
            $printer->setReverseColors(false);
        }
        $printer->setTextSize(1,2);
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text("Gracias por su pedido ".$order->name.", te esperamos en\n");
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(1,1);
        $printer->text("---------------------------\n");
        $printer->setTextSize(2,2);
        $printer->setEmphasis(true);
        $printer->text("--  ".$cash->pivot->responsable->name."  --\n");
        $printer->setEmphasis(false);
        $printer->setTextSize(1,1);
        $printer->text("---------------------------\n");
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->setTextSize(1,1);
        $printer->text(" Lo atendio: ".$order->created_by->names. " ".$order->created_by->surname_pat." \n");
        $printer->text(" Fecha/Hora: ".$cash->pivot->created_at." \n");
        $printer->setJustification(Printer::JUSTIFY_RIGHT);
        $printer->setTextSize(1,1);
        $printer->text("Modelos: ");
        $printer->setTextSize(2,1);
        $printer->text($summary['models']);
        $printer->setTextSize(1,1);
        $printer->text(" Piezas: ");
        $printer->setTextSize(2,1);
        $printer->text($summary['units']."\n");
        $printer->setTextSize(1,1);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("---------------------------\n");
        $printer->setBarcodeHeight($this->barcode_height);
        $printer->setBarcodeWidth($this->barcode_width);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->barcode($order->id);
        $printer->feed(1);
        $printer->text($order->id."\n");
        $printer->text($order->workpoint->name.", GRUPO VIZCARRA");
        $printer->feed(1);
        $printer->cut();
        $printer->close();
        return true;
        try{
        } catch(\Exception $e){
            return false;
        }
    }

    public function requisitionTicket(Requisition $requisition){
        $printer = $this->printer;
        if(!$printer){ return false; }

        $summary = $requisition->products->reduce(function($summary, $product){
            if($product->pivot->stock>0){
                $summary['models'] = $summary['models'] + 1;
                $summary['articles'] = $summary['articles'] + $product->pivot->units;
                $volumen = ($product->dimensions->length * $product->dimensions->height * $product->dimensions->width) / 1000000;
                if($volumen<=0){
                    $summary['sinVolumen'] = $summary['sinVolumen'] + $product->pivot->units;
                }
                $summary['volumen'] = $summary['volumen'] + $volumen;
            }else{
                $summary['modelsSouldOut'] = $summary['modelsSouldOut'] + 1;
                $summary['articlesSouldOut'] = $summary['articlesSouldOut'] + $product->pivot->units;
            }
            return $summary;
        }, ["models"=>0, "articles"=>0, "volumen"=>0, "sinVolumen"=>0, "modelsSouldOut"=>0, "articlesSouldOut"=>0]);

        $finished_at = $requisition->log->filter(fn($log) => $log->pivot->_status=1);
        $finished_at = $finished_at[sizeof($finished_at) - 1];

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setReverseColors(true);
        $printer->setEmphasis(true);

        if($requisition->printed>0){
            $printer->setTextSize(1,1);
            $printer->text(" *** REIMPRESION *** \n");
        }else{
            $printer->setTextSize(2,2);
            $printer->text(" *** Nuevo Pedido *** \n");
        }

        $printer->setReverseColors(false);
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->setEmphasis(false);
        $printer->setTextSize(1,1);
        $printer->text("------------------------------------------------\n");
        $printer->setTextSize(2,2);
        $printer->setReverseColors(true);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text(" ".$requisition->from->alias." - ".$requisition->id." \n");
        $printer->setReverseColors(false);
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->setTextSize(1,1);
        $printer->text("\n AGENTE:    ".$requisition->created_by->names."\n");
        $printer->text(" SOLICITUD: ".$finished_at->pivot->created_at."\n");

        if($requisition->notes){
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->selectPrintMode(Printer::MODE_FONT_B);
            $printer->setTextSize(2,1);
            $printer->setReverseColors(true);
            $printer->text("\n ¡¡ NOTAS !! \n");
            $printer->setReverseColors(false);
            $printer->text(" $requisition->notes \n\n");
            $printer->setTextSize(1,1);
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->selectPrintMode(Printer::MODE_FONT_A);
        }

        $printer->text("------------------------------------------------\n\n");
        $printer->setTextSize(1,2);
        $y = 1;
        $product = collect($requisition->products);
        $product2 = collect($requisition->products);
        $groupBy = $product->filter(function($product){
            return $product->pivot->stock>0;
        })->map(function($product){
            $product->locations->sortBy('path');
            return $product;
        })->groupBy(function($product){
            if(count($product->locations)>0){
                return explode('-',$product->locations[0]->path)[0];
            }else{ return ''; }
        })->sortKeys();
        $piso_num = 1;
        foreach($groupBy as $piso){
            $products = $piso->sortBy(function($product){
                if(count($product->locations)>0){
                    $location = $product->locations[0]->path;
                    $res = '';
                    $parts = explode('-', $location);
                    foreach($parts as $part){
                        $numbers = preg_replace('/[^0-9]/', '', $part);
                        $letters = preg_replace('/[^a-zA-Z]/', '', $part);
                        if(strlen($numbers)==1){
                            $numbers = '0'.$numbers;
                        }
                        $res = $res.$letters.$numbers.'-';
                    }
                    return $res;
                }
                return '';
            });
            if($piso_num>1){
                $printer->setJustification(Printer::JUSTIFY_LEFT);
                $printer->setTextSize(1,1);
                $printer->text("----------------------------------------------\n");
                $printer->text("----------------------------------------------\n");
                $printer->setTextSize(2,1);
                $printer->text("█ ".$requisition->id." ".$requisition->to->alias." >>> ".$requisition->from->alias." █\n");
                $printer->setTextSize(1,1);
                $printer->text("Complemento █ ".$piso_num." █ ".$piso_num."/".count($groupBy)."\n");
                $printer->feed(1);
            }
            foreach($products as $product){
                if(intval($product->pivot->stock)>0){
                    $locations = $product->locations->reduce(function($res, $location){
                        return $res.$location->path.",";
                    }, '');
                    $printer->setJustification(Printer::JUSTIFY_LEFT);
                    $printer->setTextSize(2,1);
                    $printer->text($y."█ ".trim($locations)."\n█ ".$product->code." █\n");
                    $printer->setTextSize(1,1);
                    $printer->text($product->description." \n");
                    $amount = '';
                    $multiple = "";
                    switch($product->pivot->_supply_by){
                        case 1:
                            $printer->text("UNIDADES SOLICITADAS: ");
                            break;
                        case 2:
                            $printer->text("DOCENAS SOLICITADAS: ");
                            $multiple = 'x12';
                            break;
                        case 3:
                            $printer->text("CAJAS SOLICITADAS: ");
                            $multiple = 'x'.$product->pieces;
                            break;
                        case 4:
                            $printer->text("MEDIAS CAJAS SOLICITADAS: ");
                            $multiple = "x".($product->pieces/2)."";
                            break;
                    }
                    $printer->setTextSize(2,1);
                    $printer->text($product->pivot->amount."".$multiple);
                    $printer->setTextSize(2,2);
                    $printer->text("[  ]");
                    $printer->setJustification(Printer::JUSTIFY_RIGHT);
                    $printer->setTextSize(2,2);
                    $printer->text("{  }\n");
                    $printer->setTextSize(1,1);
                    $printer->text("UF: ");
                    $printer->setTextSize(2,1);
                    $printer->text($product->pivot->units);
                    $printer->setTextSize(1,1);
                    $printer->text(" - UD: ");
                    $printer->setTextSize(2,1);
                    $printer->text($product->pivot->stock."\n");
                    if($product->pivot->comments){
                        $printer->setTextSize(1,1);
                        $printer->text("Notas: ".$product->pivot->comments."\n");
                    }
                    $printer->feed(1);
                    $y++;
                }
            }
            $piso_num++;
        }
        if($requisition->_type==3 || $requisition->_type==4 || $requisition->_type==1){
            $printer->setTextSize(1,1);
            $agotados = $product2->filter(function($product){
                return $product->pivot->stock<=0;
            })->map(function($product){
                $product->locations->sortBy('path');
                return $product;
            })->sortBy(function($product){
                if(count($product->locations)>0){
                    $location = $product->locations[0]->path;
                    $res = '';
                    $parts = explode('-', $location);
                    foreach($parts as $part){
                        $numbers = preg_replace('/[^0-9]/', '', $part);
                        $letters = preg_replace('/[^a-zA-Z]/', '', $part);
                        if(strlen($numbers)==1){
                            $numbers = '0'.$numbers;
                        }
                        $res = $res.$letters.$numbers.'-';
                    }
                    return $res;
                }
                return '';
            })->groupBy(function($product){
                if(count($product->locations)>0){
                    return explode('-',$product->locations[0]->path)[0];
                }else{
                    return '';
                }
            })->sortKeys();
            if(count($agotados)>0){
                $printer->setTextSize(2,1);
                $printer->setReverseColors(true);
                $printer->text("AGOTADOS \n");
                $printer->setReverseColors(false);
                $y = 1;
                $piso_num = 1;
                foreach($agotados as $piso){
                    $products = $piso->sortByDesc(function($product){
                        if(count($product->locations)>0){
                            return $product->locations[0]->path;
                        }
                        return '';
                    });
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
                    foreach($products as $product){
                        if(intval($product->pivot->stock)<=0){
                            $locations = $product->locations->reduce(function($res, $location){
                                return $res.$location->path.",";
                            }, '');
                            $printer->setJustification(Printer::JUSTIFY_LEFT);
                            $printer->setTextSize(2,1);
                            $printer->text($y."█ ".trim($locations)."\n█ ".$product->code." █\n");
                            $printer->setTextSize(1,1);
                            $printer->text($product->description." \n");
                            $amount = '';
                            $multiple = "";
                            switch($product->pivot->_supply_by){
                                case 1:
                                    $printer->text("UNIDADES SOLICITADAS: ");
                                    break;
                                case 2:
                                    $printer->text("DOCENAS SOLICITADAS: ");
                                    $multiple = 'x12';
                                    break;
                                case 3:
                                    $printer->text("CAJAS SOLICITADAS: ");
                                    $multiple = 'x'.$product->pieces;
                                    break;
                                case 4:
                                    $printer->text("MEDIAS CAJAS SOLICITADAS: ");
                                    $multiple = "x".($product->pieces/2)."";
                                    break;
                            }
                            $printer->setTextSize(2,1);
                            $printer->text($product->pivot->amount."".$multiple);
                            $printer->setTextSize(2,2);
                            $printer->text("[  ]");
                            $printer->setJustification(Printer::JUSTIFY_RIGHT);
                            $printer->setTextSize(2,2);
                            $printer->text("{  }\n");
                            $printer->setTextSize(1,1);
                            $printer->text("UF: ");
                            $printer->setTextSize(2,1);
                            $printer->text($product->pivot->units);
                            $printer->setTextSize(1,1);
                            $printer->text(" - UD: ");
                            $printer->setTextSize(2,1);
                            $printer->text($product->pivot->stock."\n");
                            if($product->pivot->comments){
                                $printer->setTextSize(1,1);
                                $printer->text("Notas: ".$product->pivot->comments."\n");
                            }
                            $printer->feed(1);
                            $y++;
                        }
                    }
                    $piso_num++;
                }
            }
        }
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(1,1);
        $printer->text("--------------------------------------------\n");
        $printer->text("Modelos: ");
        $printer->setTextSize(2,1);
        $printer->text($summary['models']);
        $printer->setTextSize(1,1);
        $printer->text(" Piezas: ");
        $printer->setTextSize(2,1);
        $printer->text(round($summary['articles'])."\n");
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
            $printer->text(round($summary['articlesSouldOut'])."\n");
        }
        $printer->setTextSize(1,1);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("--------------------------------------------\n");
        $printer->setBarcodeHeight($this->barcode_height);
        $printer->setBarcodeWidth($this->barcode_width);
        $printer->barcode($requisition->id);
        $printer->feed(1);
        $printer->text("GRUPO VIZCARRA\n");
        $printer->feed(1);
        $printer->cut();
        $printer->close();
        return true;
    }

    public function ticket(Order $order){
        try{
            $printer = $this->printer;
            if(!$printer){
                return false;
            }
            $summary = $order->products->reduce(function($summary, $product){
                $summary['models'] = $summary['models'] + 1;
                $summary['articles'] = $summary['articles'] + $product->pivot->units;
                return $summary;
            }, ["models" => 0, "articles" => 0]);
            if($order->printed>0){
                $finished_at = $order->history->filter(function($log){
                    return $log->pivot->_status = 2;
                });
                $finished_at = $finished_at[sizeof($finished_at) - 1]->pivot->created_at;
            }else{
                $finished_at = "now";
            }
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setTextSize(1,1);
            $printer->text("██████ ");
            $printer->setTextSize(2,1);
            $printer->text("Gracias $order->name ($order->num_ticket)");
            $printer->setTextSize(1,1);
            $printer->text(" ██████\n");
            $printer->setTextSize(2,1);
            $printer->setEmphasis(true);
            $printer->setUnderline(true);
            $printer->text("Pase a ");
            $printer->setReverseColors(true);
            $printer->setTextSize(2,2);
            $printer->text("caja 1");
            $printer->setTextSize(2,1);
            $printer->setReverseColors(false);
            $printer->text(" a recoger su mercancia\n");
            $printer->setUnderline(false);
            $printer->setEmphasis(false);
            $printer->setTextSize(1,1);
            $printer->text("--------------------------------------------\n");
            $printer->text(" Te atendio: ".$order->created_by->names."\n");
            $printer->text(" Fecha/Hora ".$finished_at." Hrs \n");
            $printer->setEmphasis(true);
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
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setBarcodeHeight($this->barcode_height);
            $printer->setBarcodeWidth($this->barcode_width);
            $printer->barcode($order->id);
            $printer->feed(1);
            $printer->setTextSize(2,1);
            $printer->text("GRUPO VIZCARRA\n");
            $printer->setTextSize(1,1);
            $printer->feed(1);
            $printer->cut();
            $printer->close();
            return true;
        } catch(\Exception $e){
            return false;
        }
    }

    public function orderTicket(Order $order, $cash, $in_coming = null){
        $printer = $this->printer;
        if(!$printer){
            return false;
        }
        $summary = $order->products->reduce(function($summary, $product){
            $summary['models'] = $summary['models'] + 1;
            $summary['articles'] = $summary['articles'] + $product->pivot->units;
            return $summary;
        }, ["models" => 0, "articles" => 0]);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        if($order->printed>0){
            $printer->setTextSize(2,1);
            $printer->setReverseColors(true);
            $printer->text("REIMPRESION \n");
            $printer->setReverseColors(false);
        }

        if($order->_order){
            $printer->text("ANEXO ");
            $printer->setReverseColors(true);
            $printer->setTextSize(2,2);
            $printer->text($order->_order." \n");
            $printer->setEmphasis(false);
            $printer->setReverseColors(false);
        }

        $printer->text("Pedido para ".$order->name." \n");
        $printer->setTextSize(2,2);
        $printer->text(" Vendedor: ".$order->created_by->names. " ".$order->created_by->surname_pat." \n");
        $printer->setTextSize(2,1);
        $printer->text("--  ".$cash->pivot->responsable->name."  --\n");
        $printer->setTextSize(1,1);
        $printer->text("----------------------------------------\n");
        $created_at = is_null($in_coming) ? date('d/m/Y H:i', time()) : $cash->pivot->created_at;
        $printer->text(" Fecha/Hora: ".$created_at." \n");
        $printer->text("Modelos: ");
        $printer->setTextSize(2,1);
        $printer->text($summary['models']);
        $printer->setTextSize(1,1);
        $printer->text(" Piezas: ");
        $printer->setTextSize(2,1);
        $printer->text(round($summary['articles'])."\n");
        $printer->setTextSize(1,1);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("----------------------------------------\n");
        $y = 1;
        $product = collect($order->products);
        $groupBy = $product->map(function($product){
            $product->locations->sortBy('path');
            return $product;
        })->groupBy(function($product){
            if(count($product->locations)>0){
                return explode('-',$product->locations[0]->path)[0];
            }else{
                return '';
            }
        })->sortKeys();
        $piso_num = 1;
        foreach($groupBy as $piso){
            $products = $piso->sortBy(function($product){
                if(count($product->locations)>0){
                    $location = $product->locations[0]->path;
                    $res = '';
                    $parts = explode('-', $location);
                    foreach($parts as $part){
                        $numbers = preg_replace('/[^0-9]/', '', $part);
                        $letters = preg_replace('/[^a-zA-Z]/', '', $part);
                        if(strlen($numbers)==1){
                            $numbers = '0'.$numbers;
                        }
                        $res = $res.$letters.$numbers.'-';
                    }
                    return $res;
                }
                return '';
            });
            if($piso_num>1){
                $printer->setJustification(Printer::JUSTIFY_LEFT);
                $printer->setTextSize(1,1);
                $printer->text("----------------------------------------------\n");
                $printer->text("----------------------------------------------\n");
                $printer->setTextSize(2,1);
                //$printer->text(" ".substr("0000".$order->num_ticket,-5,5)." \n");
                $printer->text("Pedido para ".$order->name.", ".substr("0000".$order->id,-5,5)." \n");
                $printer->setTextSize(1,1);
                $printer->text("Complemento █ ".$piso_num." █ ".$piso_num."/".count($groupBy)."\n");
                $printer->feed(1);
            }
            foreach($products as $product){
                $locations = $product->locations->reduce(function($res, $location){
                    return $res.$location->path.",";
                }, '');
                $printer->setJustification(Printer::JUSTIFY_LEFT);
                $printer->setTextSize(2,1);
                $printer->text($y."█ ".trim($locations)."\n█ ".$product->code." █\n");
                $printer->setTextSize(1,1);
                $printer->text($product->description." \n");
                $printer->setTextSize(1,1);
                $printer->text(" Piezas: ");
                $printer->setTextSize(2,1);
                $printer->text("[ ".$product->pivot->units." ]");
                $printer->setJustification(Printer::JUSTIFY_RIGHT);
                $printer->setTextSize(2,1);
                $printer->text("{   }\n");
                if($product->pivot->comments){
                    $printer->setTextSize(1,1);
                    $printer->setReverseColors(true);
                    $printer->text("Notas: ".$product->pivot->comments."\n");
                    $printer->setReverseColors(false);
                }
                $printer->feed(1);
                $y++;
            }
            $piso_num++;
        }
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setBarcodeHeight($this->barcode_height);
        $printer->setBarcodeWidth($this->barcode_width);
        $printer->barcode($order->id);
        $printer->feed(1);
        $printer->setTextSize(1,1);
        $printer->text($order->id."\n");
        $printer->text("GRUPO VIZCARRA\n");
        $printer->feed(1);
        $printer->cut();
        $printer->close();
        return true;
    }

    public function demo(){
        $printer = $this->printer;
        if(!$printer){ return false; }
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("--------------------------------------------\n");
        $printer->setTextSize(2,1);
        $printer->text("--PRUEBA DE CONEXIÓN--\n");
        $printer->setTextSize(1,1);
        $printer->text("--------------------------------------------\n");
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text("IP: ".$this->ip."\n");
        $printer->text("PORT: ".$this->port."\n");
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->feed(1);
        $printer->setBarcodeHeight($this->barcode_height);
        $printer->setBarcodeWidth($this->barcode_width);
        $printer->barcode("ID-15568");
        $printer->feed(1);
        $printer->text("GRUPO VIZCARRA\n");
        $printer->cut();
        $printer->close();
        return true;
    }

    public function validationTicket($series, $order){
        try{
            $printer = $this->printer;
            if(!$printer){
                return false;
            }
            $_series_type = array_column($series, "_type");
            $summary = $order->products->reduce(function($summary, $product){
                $summary['models'] = $summary['models'] + 1;
                $summary['articles'] = $summary['articles'] + $product->pivot->units;
                return $summary;
            }, ["models" => 0, "articles" => 0]);
            $printer->setJustification(Printer::JUSTIFY_CENTER);

            if($order->_order){
                $printer->setTextSize(2,2);
                $printer->setEmphasis(true);
                $printer->setReverseColors(true);
                $printer->setTextSize(2,2);
                $printer->text("ANEXO ".$order->_order." \n");
                $printer->setEmphasis(false);
                $printer->setReverseColors(false);
            }

            $products = $order->products->filter(function($product){
                if($product->pivot->toDelivered && $product->pivot->toDelivered > 0){
                    return true;
                }
                return false;
            })->groupBy(function($product){
                return $product->pivot->_supply_by;
            })->sortKeysDesc();
            $x = 0;
            foreach($products as $key => $el){
                $x++;
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->setReverseColors(true);
                $printer->setTextSize(2,1);
                switch($key){
                    case 1:
                        $printer->text(" PIEZAS - ".$x."/".count($products));
                        break;
                    case 2:
                        $printer->text(" DOCENAS - ".$x."/".count($products));
                        break;
                    case 3:
                        $printer->text(" CAJAS - ".$x."/".count($products));
                        break;
                    case 4:
                        $printer->text(" MEDIAS CAJAS - ".$x."/".count($products));
                        break;
                }
                $printer->setReverseColors(false);
                $printer->text(" ".$order->id."\n");
                $printer->setEmphasis(false);
                $printer->setFont(Printer::FONT_B);
                $printer->setTextSize(3,1);
                $printer->text(" ".$order->name."\n");
                $printer->setFont(Printer::FONT_A);
                $printer->setTextSize(1,1);
                $printer->setTextSize(4,3);
                $buscar_supply = array_search($key, $_series_type);
                $printer->text($series[$buscar_supply]["serie"]."-".$series[$buscar_supply]["ticket"]."\n");
                $printer->setTextSize(1,1);
                $printer->setEmphasis(true);
                $printer->setJustification(Printer::JUSTIFY_RIGHT);
                $printer->text(" Modelos: ".count($el));
                $cantidad = $el->sum(function($product){
                    return $product->pivot->amount;
                });
                switch($key){
                    case 1:
                        $printer->text(" Piezas: ".$cantidad."\n");
                        break;
                    case 2:
                        $printer->text(" Docenas: ".$cantidad."\n");
                        break;
                    case 3:
                        $printer->text(" Cajas: ".$cantidad."\n");
                        break;
                    case 4:
                        $printer->text(" Medias cajas: ".$cantidad."\n");
                        break;
                }
                $printer->setEmphasis(false);
                $printer->feed(1);
                $printer->text("--------------------------------------------\n");
                $printer->feed(1);
            }
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setTextSize(1,1);
            $printer->text("Modelos: ");
            $printer->setTextSize(2,1);
            $printer->text($summary['models']);
            $printer->setTextSize(1,1);
            $printer->text(" Piezas: ");
            $printer->setTextSize(2,1);
            $printer->text(round($summary['articles'])."\n");
            $printer->feed(1);
            $printer->setBarcodeHeight($this->barcode_height);
            $printer->setBarcodeWidth($this->barcode_width);
            $printer->barcode($order->id);
            $printer->feed(1);
            $printer->setTextSize(2,1);
            $printer->setFont(Printer::FONT_B);
            $printer->text("GRUPO VIZCARRA - ".$order->id."\n");
            $printer->cut();
            $printer->close();
            return true;
        }catch(\Exception $e){
            return false;
        }
    }

    public function validationTicketRequisition($series, $requisition){
        $printer = $this->printer;
        if(!$printer){
            return false;
        }

        $summary = $requisition->products->reduce(function($summary, $product){
            if($product->pivot->toDelivered && $product->pivot->toDelivered > 0){
                $summary['models'] = $summary['models'] + 1;
            }
            $summary['articles'] = $summary['articles'] + $product->pivot->toDelivered;
            return $summary;
        }, ["models" => 0, "articles" => 0]);

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(2,1);
        $printer->setReverseColors(true);
        $printer->text(" ".$requisition->id." \n");
        $printer->setReverseColors(false);
        $printer->setFont(Printer::FONT_B);
        $printer->setTextSize(3,1);
        $printer->text($requisition->notes."\n");
        $printer->setFont(Printer::FONT_A);
        $printer->setTextSize(1,1);
        $printer->text("Pedido de cliente para generar factura\n");
        $printer->setTextSize(4,3);
        $printer->text($series["serie"]."-".$series["ticket"]."\n");

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(1,1);
        $printer->text("Modelos: ");
        $printer->setTextSize(2,1);
        $printer->text($summary['models']);
        $printer->setTextSize(1,1);
        $printer->text(" Piezas: ");
        $printer->setTextSize(2,1);
        $printer->text(round($summary['articles'])."\n");
        $printer->feed(1);
        $printer->setBarcodeHeight($this->barcode_height);
        $printer->setBarcodeWidth($this->barcode_width);
        $printer->barcode($requisition->id);
        $printer->feed(1);
        $printer->setTextSize(2,1);
        $printer->setFont(Printer::FONT_B);
        $printer->text("GRUPO VIZCARRA - ".$requisition->id."\n");
        $printer->cut();
        $printer->close();
        return true;
        try{
        }catch(\Exception $e){
            return false;
        }
    }

    public function requisition_transfer($requisition){
        $printer = $this->printer;
        if(!$printer){
            return false;
        }

        $summary = $requisition->products->reduce(function($summary, $product){
            if($product->pivot->toDelivered && $product->pivot->toDelivered > 0){
                $summary['models'] = $summary['models'] + 1;
            }
            $summary['articles'] = $summary['articles'] + $product->pivot->toDelivered;
            return $summary;
        }, ["models" => 0, "articles" => 0]);

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(2,1);
        $printer->setReverseColors(true);
        $printer->text(" ".$requisition->id." \n");
        $printer->setReverseColors(false);
        $printer->setFont(Printer::FONT_B);
        $printer->setTextSize(3,1);
        $printer->text($requisition->notes."\n");
        $printer->setFont(Printer::FONT_A);
        $printer->setTextSize(1,1);
        $printer->text("Al entregar la mercancía en ".$requisition->from->alias." se hará el\n");
        $printer->setTextSize(2,2);
        $printer->setReverseColors(true);
        $printer->text(" traspaso \n");
        $printer->setReverseColors(false);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(1,1);
        $printer->text("Modelos: ");
        $printer->setTextSize(2,1);
        $printer->text($summary['models']);
        $printer->setTextSize(1,1);
        $printer->text(" Piezas: ");
        $printer->setTextSize(2,1);
        $printer->text(round($summary['articles'])."\n");
        $printer->feed(1);
        $printer->setBarcodeHeight($this->barcode_height);
        $printer->setBarcodeWidth($this->barcode_width);
        $printer->barcode($requisition->id);
        $printer->feed(1);
        $printer->setTextSize(2,1);
        $printer->setFont(Printer::FONT_B);
        $printer->text("GRUPO VIZCARRA - ".$requisition->id."\n");
        $printer->cut();
        $printer->close();
        return true;
        try{
        }catch(\Exception $e){
            return false;
        }
    }

    public function orderTicket2(Order $order, $cash, $in_coming = null){
        $printer = $this->printer;
        if(!$printer){
            return false;
        }
        $summary = $order->products->reduce(function($summary, $product){
            $summary['models'] = $summary['models'] + 1;
            $summary['articles'] = $summary['articles'] + $product->pivot->units;
            return $summary;
        }, ["models" => 0, "articles" => 0]);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        if($order->printed>0){
            $printer->setTextSize(2,1);
            $printer->setReverseColors(true);
            $printer->text("REIMPRESION \n");
            $printer->setReverseColors(false);
        }

        if($order->_order){

            $printer->text("ANEXO ");
            $printer->setReverseColors(true);
            $printer->setTextSize(2,2);
            $printer->text($order->_order." \n");
            $printer->setEmphasis(false);
            $printer->setReverseColors(false);
        }

        $printer->setTextSize(1,2);
        $printer->text("Pedido para: \n");
        $printer->setTextSize(2,2);
        $printer->text($order->name." \n");
        // $printer->text("Pedido para:".$order->name." \n");
        $printer->setTextSize(1,1);
        $printer->text(" Vendedor: ".$order->created_by->names. " ".$order->created_by->surname_pat." \n");
        $printer->setTextSize(2,2);
        $printer->text("--  ".$cash->pivot->responsable->name."  --\n");
        $printer->setTextSize(1,1);
        $printer->text("----------------------------------------\n");
        $created_at = is_null($in_coming) ? date('d/m/Y H:i', time()) : $cash->pivot->created_at;
        $printer->text(" Fecha/Hora: ".$created_at." \n");
        $printer->text("Modelos: ");
        $printer->setTextSize(2,1);
        $printer->text($summary['models']);
        $printer->setTextSize(1,1);
        $printer->text(" Piezas: ");
        $printer->setTextSize(2,1);
        $printer->text(round($summary['articles'])."\n");
        $printer->setTextSize(1,1);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("----------------------------------------\n");
        $y = 1;
        $products = $order->products->map(function($product){
            $product->locations->sortBy('path');
            return $product;
        })->sortBy(function($product){
            if(count($product->locations)>0){
                $location = $product->locations[0]->path;
                $res = '';
                $parts = explode('-', $location);
                foreach($parts as $part){
                    $numbers = preg_replace('/[^0-9]/', '', $part);
                    $letters = preg_replace('/[^a-zA-Z]/', '', $part);
                    if(strlen($numbers)==1){
                        $numbers = '0'.$numbers;
                    }
                    $res = $res.$letters.$numbers.'-';
                }
                return $res;
            }
            return '';
        })->groupBy(function($product){
            return $product->pivot->_supply_by;
        })->sortKeysDesc();
        $x = 1;
        foreach($products as $key => $el){
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setReverseColors(true);
            $printer->setTextSize(2,1);
            switch($key){
                case 1:
                    $printer->text(" Piezas - ".$x."/".count($products));
                    break;
                case 2:
                    $printer->text(" Docenas - ".$x."/".count($products));
                    break;
                case 3:
                    $printer->text(" Cajas - ".$x."/".count($products));
                    break;
                case 4:
                    $printer->text(" Medias cajas - ".$x."/".count($products));
                    break;
            }
            $printer->setReverseColors(false);
            $printer->text(" ".$order->id."\n");
            foreach($el as $key => $product){
                $this->printBodyTicket($printer, $product, $key+1);
            }
            $printer->setTextSize(1,1);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("----------------------------------------\n");
            $printer->text("----------------------------------------\n");
            $printer->feed(1);
            $x++;
        }
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setBarcodeHeight($this->barcode_height);
        $printer->setBarcodeWidth($this->barcode_width);
        $printer->barcode($order->id);
        $printer->feed(1);
        $printer->setTextSize(1,1);
        $printer->text($order->id."\n");
        $printer->text("GRUPO VIZCARRA\n");
        $printer->feed(1);
        $printer->cut();
        $printer->close();
        return true;
    }

    public function orderTicketToDelivered(Order $order, $cash, $in_coming = null){
        $printer = $this->printer;
        if(!$printer){
            return false;
        }
        $summary = $order->products->reduce(function($summary, $product){
            if(!$product->pivot->toDelivered){
                $summary['models'] = $summary['models'] + 1;
                $summary['articles'] = $summary['articles'] + $product->pivot->units;
            }
            return $summary;
        }, ["models" => 0, "articles" => 0]);

        if($order->_order){
            $printer->text("ANEXO ");
            $printer->setReverseColors(true);
            $printer->setTextSize(2,2);
            $printer->text($order->_order." \n");
            $printer->setEmphasis(false);
            $printer->setReverseColors(false);
        }
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(2,1);
        $printer->setReverseColors(true);
        $printer->text(" Productos faltantes \n");
        $printer->setReverseColors(false);
        $printer->setTextSize(2,2);
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text(" Pedido para: ");
        $printer->setEmphasis(true);
        $printer->text($order->name." \n");
        $printer->setEmphasis(false);
        $printer->setTextSize(1,1);
        $printer->text(" Vendedor: ");
        $printer->setEmphasis(true);
        $printer->text($order->created_by->names. " ".$order->created_by->surname_pat." \n");
        $printer->setEmphasis(false);
        $printer->setTextSize(2,1);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("--  ".$cash->pivot->responsable->name."  --\n");
        $printer->setTextSize(1,1);
        $printer->text("----------------------------------------\n");
        $created_at = is_null($in_coming) ? date('d/m/Y H:i', time()) : $cash->pivot->created_at;
        $printer->text(" Fecha/Hora: ".$created_at." \n");
        $printer->text("Modelos: ");
        $printer->setTextSize(2,1);
        $printer->text($summary['models']);
        $printer->setTextSize(1,1);
        $printer->text(" Piezas: ");
        $printer->setTextSize(2,1);
        $printer->text(round($summary['articles'])."\n");
        $printer->setTextSize(1,1);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("----------------------------------------\n");
        $y = 1;
        $products = $order->products->filter(function($product){
            return !$product->pivot->toDelivered;
        })->values()->map(function($product){
            $product->locations->sortBy('path');
            return $product;
        })->sortBy(function($product){
            if(count($product->locations)>0){
                $location = $product->locations[0]->path;
                $res = '';
                $parts = explode('-', $location);
                foreach($parts as $part){
                    $numbers = preg_replace('/[^0-9]/', '', $part);
                    $letters = preg_replace('/[^a-zA-Z]/', '', $part);
                    if(strlen($numbers)==1){
                        $numbers = '0'.$numbers;
                    }
                    $res = $res.$letters.$numbers.'-';
                }
                return $res;
            }
            return '';
        })->groupBy(function($product){
            return $product->pivot->_supply_by;
        })->sortKeysDesc();
        $x = 1;
        foreach($products as $key => $el){
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setReverseColors(true);
            $printer->setTextSize(2,1);
            switch($key){
                case 1:
                    $printer->text(" Piezas - ".$x."/".count($products));
                    break;
                case 2:
                    $printer->text(" Docenas - ".$x."/".count($products));
                    break;
                case 3:
                    $printer->text(" Cajas - ".$x."/".count($products));
                    break;
                case 4:
                    $printer->text(" Medias cajas - ".$x."/".count($products));
                    break;
            }
            $printer->setReverseColors(false);
            $printer->text(" ".$order->id."\n");
            foreach($el as $key => $product){
                $this->printBodyTicket($printer, $product, $key+1);
            }
            $printer->setTextSize(1,1);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("----------------------------------------\n");
            $printer->text("----------------------------------------\n");
            $printer->feed(1);
            $x++;
        }
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setBarcodeHeight($this->barcode_height);
        $printer->setBarcodeWidth($this->barcode_width);
        $printer->barcode($order->id);
        $printer->feed(1);
        $printer->setTextSize(1,1);
        $printer->text($order->id."\n");
        $printer->text("GRUPO VIZCARRA\n");
        $printer->feed(1);
        $printer->cut();
        $printer->close();
        return true;
    }

    public function printBodyTicket($printer, $product, $y){
        $locations = $product->locations->reduce(function($res, $location){
            return $res.$location->path.",";
        }, '');
        $stock =$product->stocks;
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->setFont(Printer::FONT_B);
        $printer->setTextSize(3,1);
        $printer->text($y."█ ".trim($locations)."\n█ ");
        $printer->text($product->code." █ \n");
        $printer->setEmphasis(true);
        $printer->setTextSize(1,1);
        $printer->text($product->description."\n");
        $printer->setFont(Printer::FONT_A);
        switch($product->pivot->_supply_by){
            case 1:
                $printer->text("UNIDADES SOLICITADAS: ");
                $printer->setTextSize(2,1);
                break;
            case 2:
                $printer->text("DOCENAS SOLICITADAS: ");
                $printer->setTextSize(2,1);
                $printer->text($product->pivot->amount.'x12= ');
                break;
            case 3:
                $printer->text("CAJAS SOLICITADAS: ");
                $units = $product->pivot->units / $product->pivot->amount;
                $printer->setTextSize(2,1);
                $printer->text($product->pivot->amount."x".$units."= ");
                break;
            case 4:
                $printer->text("MEDIAS CAJAS SOLICITADAS: ");
                $units = ($product->pivot->units / $product->pivot->amount)/2;
                $printer->setTextSize(2,1);
                $printer->text($product->pivot->amount."x".$units."= ");
                break;
        }
        $printer->setReverseColors(true);
        $printer->text(" ".$product->pivot->units."pz");
        $printer->setReverseColors(false);
        $printer->text(" D->".$stock[0]->pivot->gen." \n");
        if($product->pivot->comments){
            $printer->setTextSize(1,1);
            $printer->setReverseColors(true);
            $printer->text("Notas: ".$product->pivot->comments."\n");
            $printer->setReverseColors(false);
        }
        $printer->setEmphasis(false);
        $printer->feed(1);
    }
}
