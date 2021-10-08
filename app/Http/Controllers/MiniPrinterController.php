<?php

namespace App\Http\Controllers;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use App\Requisition;
use App\Order;

class MiniPrinterController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public $printer = null;
    public $barcode_width = 2;
    public $barcode_height = 50;

    public function __construct($ip_printer, $port, $time = 15){
        try{
            $connector = new NetworkPrintConnector($ip_printer, $port, $time);
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
                    $summary['acticles'] = $product->pivot->units;
                    /* $pieces = $product->_unit == 3 ? $product->pieces : 1;
                    $summary['articles'] = $summary['articles'] + $product->pivot->units * $pieces; */
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
            $printer->text(" ".substr("0000".$requisition->id,-5,5)." \n");
            $printer->setReverseColors(false);
            $printer->setEmphasis(false);
            $printer->setTextSize(1,2);
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text(" Solicitud de mercancia a ".$requisition->to->alias." #".$requisition->id."\n");
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setTextSize(1,1);
            $printer->text("--------------------------------------------\n");
            $printer->setTextSize(1,2);
            $printer->text(" Generado por ".$requisition->created_by->names." ".$requisition->surname_pat."\n");
            $printer->text(" A las ".$finished_at->pivot->created_at." Hrs \n");
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
        $printer->setTextSize(2,2);
        $printer->setEmphasis(true);
        $printer->setReverseColors(true);
        //$printer->text(" ".substr("0000".$order->num_ticket,-5,5)." \n");
        $printer->setEmphasis(false);
        $printer->setReverseColors(false);
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
        $printer->text("--------------------------------------------\n");
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
            $printer->setTextSize(2,1);
            $printer->setReverseColors(true);
            $printer->text("REIMPRESION \n");
            $printer->setReverseColors(false);
        }
        $printer->setTextSize(2,2);
        $printer->setEmphasis(true);
        $printer->setReverseColors(true);
        $printer->text("Pedido para ".$requisition->from->alias." #".$requisition->id." \n");
        $printer->setReverseColors(false);
        if($requisition->notes){
            $printer->setTextSize(2,1);
            $printer->text("$requisition->notes \n");
        }
        $printer->setTextSize(1,1);
        $printer->text("----------------------------------------\n");
        $printer->text("Generado: ".$finished_at->pivot->created_at." Hrs por: ".$requisition->created_by->names."\n");
        $printer->setTextSize(1,2);
        $printer->text("----------------------------------------\n");
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
                    switch($product->pivot->_supply_by){
                        case 2:
                            $printer->text("DOCENAS SOLICITADAS: ");
                            $amount = '12';
                            break;
                        case 3:
                            $printer->text("CAJAS SOLICITADAS: ");
                            break;
                        case 4:
                            $printer->text("MEDIAS CAJAS SOLICITADAS: ");
                            break;
                    }
                    $printer->setTextSize(2,1);
                    $printer->text($product->pivot->amount);
                    $printer->setTextSize(1,1);
                    $printer->text(" x: ");
                    $printer->setTextSize(2,2);
                    $printer->text("[ ".$amount." ]");
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
                $printer->text("----------------------------------------------\n");
                $printer->text("---------------AGOTADOS---------------\n");
                $printer->text("----------------------------------------------\n");
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
                        $pieces = 1;
                        $locations = $product->locations->reduce(function($res, $location){
                            return $res.$location->path.",";
                        }, '');
                        $printer->setJustification(Printer::JUSTIFY_LEFT);
                        $printer->setTextSize(2,1);
                        $printer->text($y."█ ".trim($locations)."█ ".$product->code." \n");
                        $printer->setTextSize(1,1);
                        $printer->text($product->description." \n");
                        if($product->units->id == 3){
                            $printer->text("CAJAS SOLICITADAS: ");
                            $printer->setTextSize(2,1);
                            $printer->text($product->pivot->units);
                            $printer->setTextSize(1,1);
                            $printer->text(" x: ");
                            $printer->setTextSize(2,2);
                            $printer->text("[  ]");
                            $pieces = $product->pieces == 0 ? 1 : $product->pieces;
                            $amount = $pieces * $product->pivot->units;
                        }else{
                            $pieces = $product->pieces == 0 ? 1 : $product->pieces;
                            $printer->text("CAJAS SOLICITADAS: ");
                            $printer->setTextSize(2,1);
                            $printer->text(round($product->pivot->units/$pieces));
                            $printer->setTextSize(1,1);
                            $printer->text(" x: ");
                            $printer->setTextSize(2,2);
                            $printer->text("[  ]");
                            $amount = $product->pivot->units;
                        }
                        $printer->setJustification(Printer::JUSTIFY_RIGHT);
                        $printer->setTextSize(2,2);
                        $printer->text("{  }\n");
                        $printer->setTextSize(1,1);
                        $printer->text("UF: ");
                        $printer->setTextSize(2,1);
                        $printer->text($amount);
                        $printer->setTextSize(1,1);
                        $printer->text("UF: ");
                        $printer->setTextSize(2,1);
                        $printer->text($pieces);
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
        /* try{
        }catch(\Exception $e){
            return false;
        } */
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
        $printer->setTextSize(2,2);
        $printer->setEmphasis(true);
        $printer->setReverseColors(true);
        // $printer->text(" ".substr("0000".$order->num_ticket,-5,5)." \n");
        $printer->setReverseColors(false);
        $printer->text("Pedido para ".$order->name." \n");
        $printer->setTextSize(1,1);
        $printer->text(" Vendedor: ".$order->created_by->names. " ".$order->created_by->surname_pat." \n");
        $printer->setTextSize(2,1);
        $printer->text("--  ".$cash->pivot->responsable->name."  --\n");
        $printer->setTextSize(1,1);
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
                $printer->text("Pedido para ".$order->name." \n");
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
        /* try{
        }catch(\Exception $e){
            return false;
        } */
    }

    public function demo(){
        $printer = $this->printer;
        if(!$printer){
            return false;
        }
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(1,1);
        $printer->text("--------------------------------------------\n");
        $printer->setTextSize(2,1);
        $printer->text("--PRUEBA DE CONEXIÓN--");
        $printer->setTextSize(1,1);
        $printer->text("--------------------------------------------\n");
        $printer->setTextSize(2,1);
        $printer->feed(1);
        $printer->setBarcodeHeight($this->barcode_height);
        $printer->setBarcodeWidth($this->barcode_width);
        $printer->barcode("ID-15568");
        $printer->feed(1);
        $printer->setTextSize(2,1);
        $printer->text("GRUPO VIZCARRA\n");
        $printer->cut();
        $printer->close();
        return true;
        /* try{
        }catch(\Exception $e){
            return false;
        } */
    }

    public function validationTicket($serie, $ticket, $id, $name){
        try{
            $printer = $this->printer;
            if(!$printer){
                return false;
            }
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setTextSize(1,2);
            $printer->setEmphasis(true);
            /* $printer->setReverseColors(true); */
            $printer->text($name);
            $printer->setTextSize(2,2);
            $printer->text(" #".$id."\n");
            $printer->setEmphasis(false);
            /* $printer->setReverseColors(false); */
            $printer->setTextSize(1,1);
            $printer->text("--------------------------------------------\n");
            $printer->setTextSize(3,3);
            $printer->text($serie."-".$ticket."\n");
            $printer->setTextSize(1,1);
            $printer->text("--------------------------------------------\n");
            $printer->setJustification(Printer::JUSTIFY_RIGHT);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setTextSize(2,1);
            $printer->feed(1);
            $printer->setBarcodeHeight($this->barcode_height);
            $printer->setBarcodeWidth($this->barcode_width);
            $printer->barcode("ID-".$id);
            $printer->feed(1);
            $printer->setTextSize(2,1);
            $printer->text("GRUPO VIZCARRA\n");
            $printer->cut();
            $printer->close();
            return true;
        }catch(\Exception $e){
            return false;
        }
    }
}
