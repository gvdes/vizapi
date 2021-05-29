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
        /* $printer->setTextSize(2,2);
        $printer->setReverseColors(true);
        $printer->text("#".$requisition->id."\n");
        $printer->setReverseColors(false); */
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
        })/* ->sortBy(function($product){
            if(count($product->locations)>0){
                return $product->locations[0]->path;
            }
            return '';
        }) */->sortKeys();
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
                $printer->text("█ ".$requisition->to->alias." >>> ".$requisition->from->alias." █\n");
                $printer->setTextSize(1,1);
                $printer->text("Complemento █ ".$piso_num." █ ".$piso_num."/".count($groupBy)."\n");
                $printer->feed(1);
            }
            foreach($products as $product){
                $pieces = 1;
                if(intval($product->pivot->stock)>0){
                    $locations = $product->locations->reduce(function($res, $location){
                        return $res.$location->path.",";
                    }, '');
                    $printer->setJustification(Printer::JUSTIFY_LEFT);
                    $printer->setTextSize(2,1);
                    $printer->text($y."█ ".trim($locations)."\n█ ".$product->code." █\n");
                    $printer->setTextSize(1,1);
                    $printer->text($product->description." \n");
                    if($product->units->id == 3){
                        $printer->text("CAJAS SOLICITADAS: ");
                        $printer->setTextSize(2,1);
                        if($requisition->_type == 4){
                            $printer->text($product->pivot->units);
                        }else{
                            $printer->text(round($product->pivot->units));
                        }
                        $printer->setTextSize(1,1);
                        $printer->text(" x: ");
                        $printer->setTextSize(2,2);
                        $printer->text("[   ]");
                        $pieces = $product->pieces;
                    }else{
                        $printer->text("CAJAS SOLICITADAS: ");
                        $printer->setTextSize(2,1);
                        $pieces = $product->pieces == 0 ? 1 : $product->pieces;
                        $printer->text(rount($product->pivot->units/$pieces));
                        $printer->setTextSize(1,1);
                        $printer->text(" x: ");
                        $printer->setTextSize(2,2);
                        $printer->text("[   ]");
                    }
                    $printer->setJustification(Printer::JUSTIFY_RIGHT);
                    $printer->setTextSize(2,2);
                    $printer->text("{   }\n");
                    $printer->setTextSize(1,1);
                    $printer->text("UF: ");
                    $printer->setTextSize(2,1);
                    $printer->text(round($product->pivot->units*$pieces));
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
                            $printer->text(round($product->pivot->units)."\r\n");
                            $printer->setJustification(Printer::JUSTIFY_RIGHT);
                            $pieces = $product->pieces;
                        }
                        $printer->setTextSize(1,1);
                        $printer->text("UF: ");
                        $printer->setTextSize(2,1);
                        $printer->text(round($product->pivot->units*$pieces));
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
        $printer->setBarcodeHeight(80);
        $printer->setBarcodeWidth(4);
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
            $printer->setBarcodeHeight(100);
            $printer->setBarcodeWidth(4);
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

    public function orderTicket(Order $order){
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
        if($order->printed>0){
            $printer->setTextSize(1,1);
            $printer->text("████████ ");
            $printer->setTextSize(2,2);
            $printer->text("REIMPRESION ");
            $printer->setTextSize(1,1);
            $printer->text("████████ \n");
        }
        $printer->setTextSize(1,1);
        $printer->text("--- Pedido para ");
        $printer->setTextSize(2,1);
        $printer->setEmphasis(true);
        $printer->text("$order->name ($order->num_ticket) ");
        $printer->setEmphasis(false);
        $printer->setTextSize(1,1);
        $printer->text("--- \n");
        $printer->text("----------------------------------------\n");
        $printer->text("Generado: ".$finished_at." Hrs por: ".$order->created_by->names."\n");
        $printer->setTextSize(1,2);
        $printer->text("----------------------------------------\n");
        $y = 1;
        $products = collect($order->products);
        $groupBy = $products->map(function($product){
            $product->locations = $product->locations->sortBy(function($location){
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
            });
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
        });
        foreach($groupBy as $product){
            $pieces = 1;
            $locations = $product->locations->reduce(function($res, $location){
                return $res.$location->path.",";
            }, '');
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->setTextSize(2,1);
            $printer->text($y."█ ".trim($locations)." █ ".$product->code." █\n");
            $printer->setTextSize(1,1);
            $printer->text($product->description." \n");
            /* if($product->units->id == 3){
                $printer->text("CAJAS SOLICITADAS: ");
                $printer->setTextSize(2,1);
                if($requisition->_type == 4){
                    $printer->text($product->pivot->units);
                }else{
                    $printer->text(round($product->pivot->units));
                }
                $printer->setTextSize(1,1);
                $printer->text(" x: ");
                $printer->setTextSize(2,2);
                $printer->text("[   ]\n");
                $printer->setJustification(Printer::JUSTIFY_RIGHT);
                $pieces = $product->pieces;
            } */
            $printer->setTextSize(1,1);
            $printer->text("UF: ");
            $printer->setTextSize(2,1);
            $printer->text(round($product->pivot->units));
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
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("--------------------------------------------\n");
        $printer->setBarcodeHeight(80);
        $printer->setBarcodeWidth(4);
        $printer->barcode($order->num_ticket);
        $printer->feed(1);
        $printer->text("GRUPO VIZCARRA ");
        $printer->setEmphasis(true);
        $printer->text($order->workpoint->name."\n");
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
        $printer->text("PRUEBA DE CONEXIÓN");
        $printer->setTextSize(1,1);
        $printer->text("--------------------------------------------\n");
        $printer->setTextSize(2,1);
        $printer->feed(1);
        $printer->cut();
        $printer->close();
        return true;
        /* try{
        }catch(\Exception $e){
            return false;
        } */
    }
}
