<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Account;
use Illuminate\Support\Facades\Auth;
use PDF;

class PdfController extends Controller{
  /**
   * Create a new controller instance.
   *
   * @return void
   */
  public function __construct(){
    $this->account = Auth::payload()['workpoint'];
  }

  public function getPdfsToEtiquetas(Request $request){
    $types = [
      ["id" => 1, "name" => "Estrella x1"],
      ["id" => 2, "name" => "Estrella x2"],
      ["id" => 3, "name" => "Estrella x3"],
      ["id" => 4, "name" => "Estrella x4"],
      ["id" => 5, "name" => "Bodega"],
      ["id" => 6, "name" => "Mochila"],
      ["id" => 7, "name" => "Lonchera"],
      ["id" => 8, "name" => "Lapicera"],
      ["id" => 9, "name" => "Lonchera_16"],
      ["id" => 10, "name" => "Lapicera_20"],
      ["id" => 11, "name" => "Mochila_16"]
    ];
    $priceList = \App\PriceList::all();
    return response()->json(["types" => $types, "price_list" => $priceList]);
  }

  public function generatePdf(Request $request){
    switch($request->_pdf){
      case 1:
        return $this->pdf_big_star($request->products, $request->isInnerPack);
        break;
      case 2:
        return $this->pdf_star_2($request->products, $request->isInnerPack);
        break;
      case 3:
        return $this->pdf_star_3($request->products, $request->isInnerPack);
        break;
      case 4:
        return $this->pdf_star_4($request->products, $request->isInnerPack);
        break;
      case 5:
        return $this->pdf_bodega($request->products);
        break;
      case 6:
        return $this->pdf_mochila($request->products, $request->isInnerPack);
        break;
      case 7:
        return $this->pdf_lonchera($request->products, $request->isInnerPack);
        break;
      case 8:
        return $this->pdf_lapicera($request->products, $request->isInnerPack);
        break;
      case 9:
        return $this->pdf_lonchera_16($request->products, $request->isInnerPack);
        break;
      case 10:
        return $this->pdf_lapicera_20($request->products, $request->isInnerPack);
        break;
      case 11:
        return $this->pdf_mochila_16($request->products, $request->isInnerPack);
        break;
    }
  }

  public function getStdProducts($products){
    $products = collect($products);
    return $products->filter(function($product){
      return $product['type'] == 'std' || $product['type'] == 'may';
    })->values()->all();
  }

  public function getOffProducts($products){
    $products = collect($products);
    return $products->filter(function($product){
      return $product['type'] == 'off';
    })->values()->all();
  }

  public function customPrices($prices, $br, $ex = false){
    $prices = collect($prices);
    if(count($prices)>1){
      $text = '';
      foreach($prices as $key => $price){
        if($ex){
          $row = '<span>'.$price['alias'].' <span style="font-size:1.2em;"> $'.$price['price'].'</span></span><span style="font-size:'.$br.'em;"><br><br></span>';
          $text = $text.$row;
        }elseif($key==(count($prices)-1)){
          $row = '<span>'.$price['alias'].' <span style="font-size:1.2em;"> $'.$price['price'].'</span></span>';
          $text = $text.$row;
        }else{
          $row = '<span>'.$price['alias'].' <span style="font-size:1.2em;"> $'.$price['price'].'</span></span><span style="font-size:'.$br.'em;"><br></span>';
          $text = $text.$row;
        }
      }
      return $text;
    }
    $text = $prices->reduce( function( $result, $price){
      $row = '<span style="font-size:.5em;">¡¡¡'.$price['alias'].'!!!</span><span style="font-size:.1px;"><br></span><span style="font-size:1.15em;"> $'.$price['price'].'</span>';
      return $result.$row;
    });
    return $text;
  }

  public function setImageBackground($image, $content, $width, $height, $cols, $rows, $top_space, $sides_space, $position){
    $bucle = floor(($position)/($rows*$cols));
    $position = $position-($bucle*$cols*$rows);
    $x = 5+(($position%$cols)*($width))+$sides_space;
    $y = 10+(intval(($position/$cols))*$height)+$top_space;
    if($image){
        $star = PDF::Image($image, 0, 0, 0, '', '', '', '', false, 700, '', true);
        PDF::Image($image, $x, $y, $width, $height, '', '', '', false, 300, '', false, $star);
    }
    PDF::MultiCell($width, $height, $content, $border=0, $align="center", $fill=0, $ln=0, $x, $y+2, $reseth=true, $stretch=0, $ishtml=true, $autopadding=false, $maxh=0);
  }

  public function setImageBackground_bordered($image, $content, $width, $height, $cols, $rows, $top_space, $sides_space, $position){
    $bucle = floor(($position)/($rows*$cols));
    $position = $position-($bucle*$cols*$rows);
    $x = 5+(($position%$cols)*($width))+$sides_space;
    $y = 10+(intval(($position/$cols))*$height)+$top_space;
    if($image){
      $star = PDF::Image($image, 0, 0, 0, '', '', '', '', false, 700, '', true);
      PDF::Image($image, $x, $y, $width, $height, '', '', '', false, 300, '', false, $star);
    }
    PDF::MultiCell($width, $height, $content, $border=1, $align="center", $fill=0, $ln=0, $x, $y+2, $reseth=true, $stretch=0, $ishtml=true, $autopadding=false, $maxh=0);
  }

  public function setImageBackground_area($image, $content, $width, $height, $cols, $rows, $top_space, $sides_space, $position, $top_margin, $sides_margin){
    $x_store = 0;
    $y_store = 6;
    $bucle = floor(($position)/($rows*$cols));
    $position = $position-($bucle*$cols*$rows);
    $x = (($position%$cols)*($width))+$sides_space+($sides_margin*(0+($position%$cols)))+$x_store;

    if(floor($position/$cols)==0){
        $y = 4+(intval(($position/$cols))*$height)+$top_space+2+($top_margin*(1+(floor($position/$cols))))+$y_store+5;
    }else{
        $y = 5+(intval(($position/$cols))*$height)+$top_space+3+($top_margin*(2+(floor($position/$cols))));
    }
    if($image){
        //$star = PDF::Image($image, 0, 0, 0, '', '', '', '', false, 700, '', true);
        PDF::Image($image, $x, $y-$top_margin-6, $width, $height+$top_margin, '', '', '', false, 300, '', false);
    }
    PDF::MultiCell($width, $height, $content, $border=0, $align="center", $fill=0, $ln=0, $x, $y+1, $reseth=true, $stretch=0, $ishtml=true, $autopadding=false, $maxh=0);
}

  public function setImageBackground_area_2($image, $content, $width, $height, $cols, $rows, $top_space, $sides_space, $position, $top_margin, $sides_margin){
    $x_store = 0;
    $y_store = 6;
    $bucle = floor(($position)/($rows*$cols));
    $position = $position-($bucle*$cols*$rows);
    $x = (($position%$cols)*($width))+$sides_space+($sides_margin*(0+($position%$cols)))+$x_store;

    if(floor($position/$cols)==0){
        $y = 3+(intval(($position/$cols))*$height)+$top_space+($top_margin*(2+(floor($position/$cols))));
    }else{
        $y = 3+(intval(($position/$cols))*$height)+$top_space+($top_margin*(2+(floor($position/$cols))));
    }
    PDF::MultiCell($width, $height, $content, $border=0, $align="center", $fill=0, $ln=0, $x, $y+1, $reseth=true, $stretch=0, $ishtml=true, $autopadding=false, $maxh=0);
}

  public function pdf_big_star($products, $isInnerPack){
    PDF::SetTitle('Pdf estrella gigante');
    $off = $this->getOffProducts($products);
    $std = $this->getStdProducts($products);
    $account = Account::with('user')->find($this->account->id);
    $person = $account->user->names.' '.$account->user->surname_pat.' '.$account->user->surname_mat;
    $counter = 0;
    //etiquetas por hoja
    $pzHoja = 2;
    foreach($std as $key => $product){
      for($i=0; $i<$product['copies']; $i++){
        if($i>0){
          $counter +=1; 
        }
        if(($key+$counter)%$pzHoja==0){
          PDF::AddPage();
          PDF::MultiCell($w=240, $h=10, '<span style="font-size:1.5em;">Hoja #'.(intval(($key+$counter)/$pzHoja)+1).' verde. Creada por: '.$person.'</span>', $border=0, $align='center', $fill=0, $ln=0, $x=0, $y=0, $reseth=true, $stretch=0, $ishtml=true, $autopadding=false, $maxh=0);
        }
        $pz = '';
        if($isInnerPack){
          $pz = $product['pieces'];
        }
        $number_of_prices = count($product['prices']);
        $font_size_prices = 2;
        $br = .3;
        switch($number_of_prices){
          case 1:
            $font_size_prices = 6.5;
            $br_prices = 1;
          break;
          case 2:
            $font_size_prices = 5.2;
            $br_prices = .8;
          break;
          case 3:
            $font_size_prices = 3.85;
            $br_prices = .1;
          break;
          case 4:
            $font_size_prices = 2.9;
            $br_prices = .1;
            $br = 0;
          break;
        }
        $pz = '';
        $space = '<span style="font-size:1em"><br/></span>';
        if($isInnerPack){
          $pz = $product['pieces'].' pz';
          $space = '<span style="font-size:.7em"><br/></span>';
          $br= .4;
        }
        $tool = '';
        /* if($product['tool']){
            $tool = '+'.$product['tool'];
        } */
        $content =  '<div style="text-align: center;">'.$space.'
                        <span style="font-size:6em; font-weight: bold;">'.$product['name'].$i.'</span><span style="font-size:.1em"><br/></span>
                        <span style="font-size:5em; font-weight: bold;">'.$product['code'].$tool.'</span><span style="font-size:.1em"><br/></span>
                        <span style="font-size:'.$font_size_prices.'em; font-weight: bolder;">'.$this->customPrices($product['prices'], $br).'</span><span style="font-size:'.$br_prices.'em"><br/><br/></span>
                        <span style="font-size:3em; font-weight: bold;">'.$pz.'</span>
                    </div>';
        $this->setImageBackground(realpath(dirname(__FILE__).'/../../..').'/files/resources/img/STAR12.png', $content, 200, 125, 1, 2, 2, 0, $key+$counter);
      }
    }
    $counter = 0;
    foreach($off as $key => $product){
      for($i=0; $i<$product['copies']; $i++){
        if($i>0){
          $counter +=1;
        }
        if(($key+$counter)%$pzHoja==0){
          PDF::AddPage();
          PDF::MultiCell($w=240, $h=10, '<span style="font-size:2em;">Hoja #'.(intval(($key+$counter)/$pzHoja)+1).' naranja. Creada por: '.$person.'</span>', $border=0, $align='center', $fill=0, $ln=0, $x=0, $y=0, $reseth=true, $stretch=0, $ishtml=true, $autopadding=false, $maxh=0);
        }
        $pz = '';
        $br = 1;
        $space = '<span style="font-size:1.5em"><br/></span>';
        if($isInnerPack){
          $pz = $product['pieces'].' pz';
          $space = '<span style="font-size:1em"><br/></span>';
          $br= 0;
        }
        $tool = '';
        /* if($product['tool']){
          $tool = '+'.$product['tool'];
        } */
        $content = '<span style="text-align: center;">'.$space.'
                      <span style="font-size:6.5em; font-weight: bold;">'.$product['name'].'</span><span style="font-size:.1em"><br/></span>
                      <span style="font-size:5em; font-weight: bold;">'.$product['code'].$tool.'</span><span style="font-size:.1em"><br/></span>
                      <span style="font-size:6.5em; font-weight: bold;">'.$this->customPrices($product['prices'], $br).'</span><span style="font-size:.1em"><br/></span>
                      <span style="font-size:4.3em; font-weight: bold;">'.$pz.'</span>
                    </span>';
        $this->setImageBackground(realpath(dirname(__FILE__).'/../../..').'/files/resources/img/STAR12.png', $content, 200, 125, 1, 2, 5, 0, $key+$counter);
      }
    }
    
    $nameFile = time().'.pdf';
    PDF::Output(realpath(dirname(__FILE__).'/../../..').'/files/'.$nameFile, 'F');
    $std = collect($std);
    $off = collect($off);
    $totalOff = $off->reduce(function($total, $product){
      return $total = $total + $product['copies'];
    });
    $totalStd = $std->reduce(function($total, $product){
      return $total = $total + $product['copies'];
    });
    return response()->json([
        'pages_off' => ceil($totalOff/$pzHoja),
        'pages_std' => ceil($totalStd/$pzHoja),
        'total' => ceil($totalStd/$pzHoja) + ceil($totalOff/$pzHoja),
        'file' => $nameFile,
    ]);
  }

  public function pdf_star_2($products, $isInnerPack){
    PDF::SetTitle('Pdf estrella x2');
    $off = $this->getOffProducts($products);
    $std = $this->getStdProducts($products);
    $counter = 0;
    //etiquetas por hoja
    $pzHoja = 8;
    $account = Account::with('user')->find($this->account->id);
    $person = $account->user->names.' '.$account->user->surname_pat.' '.$account->user->surname_mat;
    foreach($std as $key => $product){
      for($i=0; $i<$product['copies']; $i++){
        if($i>0){
          $counter +=1; 
        }
        if(($key+$counter)%$pzHoja==0){
          PDF::AddPage();
          PDF::MultiCell($w=240, $h=10, '<span style="font-size:2em;">Hoja #'.(intval(($key+$counter)/$pzHoja)+1).' verde. Creada por: '.$person.'</span>', $border=0, $align='center', $fill=0, $ln=0, $x=0, $y=0, $reseth=true, $stretch=0, $ishtml=true, $autopadding=false, $maxh=0);
        }
        $pz = '';
        if($isInnerPack){
          $pz = $product['pieces'];
        }
        $number_of_prices = count($product['prices']);
        $font_size_prices = 2;
        $br = 1;
        switch($number_of_prices){
          case 1:
            $font_size_prices = 3.2;
            $br_prices = .5;
          break;
          case 2:
            $font_size_prices = 2.4;
            $br_prices = .3;
          break;
          case 3:
            $font_size_prices = 1.9;
            $br_prices = .1;
          break;
          case 4:
            $font_size_prices = 1.45;
            $br_prices = .1;
            $br = 0;
          break;
        }
        $pz = '';
        $space = '<span style="font-size:.2em"><br/></span>';
        if($isInnerPack){
          $pz = $product['pieces'].' pz';
          $space = '<span style="font-size:.2em"><br/></span>';
          $br= .4;
        }
        $tool = '';
        /* if($product['tool']){
          $tool = '+'.$product['tool'];
        } */
        $content =  '<div style="text-align: center;">'.$space.'
                        <span style="font-size:3.2em; font-weight: bold;">'.$product['name'].'</span><span style="font-size:.1em"><br/></span>
                        <span style="font-size:2em; font-weight: bold;">'.$product['code'].$tool.'</span><span style="font-size:.1em"><br/></span>
                        <span style="font-size:'.$font_size_prices.'em; font-weight: bold;">'.$this->customPrices($product['prices'], $br).'</span><span style="font-size:'.$br_prices.'em"><br/><br/></span>
                        <span style="font-size:1.5em; font-weight: bold;">'.$pz.'</span>
                    </div>';
        $this->setImageBackground(realpath(dirname(__FILE__).'/../../..').'/files/resources/img/STAR12.png', $content, 100, 62.5, 2, 4, 5, 0, $key+$counter);
      }
    }
    $counter = 0;
    foreach($off as $key => $product){
        for($i=0; $i<$product['copies']; $i++){
            if($i>0){
              $counter +=1;
            }
            if(($key+$counter)%$pzHoja==0){
              PDF::AddPage();
              PDF::MultiCell($w=240, $h=10, '<span style="font-size:2em;">Hoja #'.(intval(($key+$counter)/$pzHoja)+1).' naranja. Creada por: '.$person.'</span>', $border=0, $align='center', $fill=0, $ln=0, $x=0, $y=0, $reseth=true, $stretch=0, $ishtml=true, $autopadding=false, $maxh=0);
            }
            $pz = '';
            $br = 1;
            $space = '<span style="font-size:1em"><br/></span>';
            if($isInnerPack){
              $pz = $product['pieces'].' pz';
              $space = '<span style="font-size:.2em"><br/></span>';
              $br= 0;
            }
            $tool = '';
            /* if($product['tool']){
                $tool = '+'.$product['tool'];
            } */
            $content = '<span style="text-align: center;">'.$space.'
                            <span style="font-size:3.2em; font-weight: bold;">'.$product['name'].'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:2.2em; font-weight: bold;">'.$product['code'].$tool.'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:3.2em; font-weight: bold;">'.$this->customPrices($product['prices'], $br).'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:1.5em; font-weight: bold;">'.$pz.'</span>
                        </span>';
            /* $this->setImageBackground(__dir__.'./resources/img/STAR12.png', $content, 100, 62.5, 2, 4, 0, 0, $key); */
            $this->setImageBackground(realpath(dirname(__FILE__).'/../../..').'/files/resources/img/STAR12.png', $content, 100, 62.5, 2, 4, 5, 0, $key+$counter);
        }
    }
    
    $nameFile = time().'.pdf';
    PDF::Output(realpath(dirname(__FILE__).'/../../..').'/files/'.$nameFile, 'F');
    //return response(PDF::Output($nameFile, 'D'));
    
    $std = collect($std);
    $off = collect($off);
    $totalOff = $off->reduce( function($total, $product){
      return $total = $total + $product['copies'];
    });
    $totalStd = $std->reduce( function($total, $product){
      return $total = $total + $product['copies'];
    });
    return response()->json([
      'pages_off' => ceil($totalOff/$pzHoja),
      'pages_std' => ceil($totalStd/$pzHoja),
      'total' => ceil($totalStd/$pzHoja) + ceil($totalOff/$pzHoja),
      'file' => $nameFile,
    ]);
  }

  public function pdf_star_3($products, $isInnerPack){
    PDF::SetTitle('Pdf estrella x3');
    $off = $this->getOffProducts($products);
    $std = $this->getStdProducts($products);
    $counter = 0;
    $account = Account::with('user')->find($this->account->id);
    $person = $account->user->names.' '.$account->user->surname_pat.' '.$account->user->surname_mat;
    //etiquetas por hoja
    $pzHoja = 18;
    foreach($std as $key => $product){
      for($i=0; $i<$product['copies']; $i++){
        if($i>0){
          $counter +=1; 
        }
        if(($key+$counter)%$pzHoja==0){
          PDF::AddPage();
          PDF::MultiCell($w=240, $h=10, '<span style="font-size:2em;">Hoja #'.(intval(($key+$counter)/$pzHoja)+1).' verde. Creada por: '.$person.'</span>', $border=0, $align='center', $fill=0, $ln=0, $x=0, $y=0, $reseth=true, $stretch=0, $ishtml=true, $autopadding=false, $maxh=0);
        }
        $pz = '';
        if($isInnerPack){
          $pz = $product['pieces'];
        }
        $number_of_prices = count($product['prices']);
        $font_size_prices = 2;
        $br = 0;
        switch($number_of_prices){
          case 1:
            $font_size_prices = 2.3;
          break;
          case 2:
            $font_size_prices = 1.6;
          break;
          case 3:
            $font_size_prices = 1.2;
          break;
          case 4:
            $font_size_prices = 1;
            $br = 0;
          break;
        }
        $pz = '';
        $space = '';
        if($isInnerPack){
          $pz = $product['pieces'].' pz';
          $space = '';
          $br= 0;
        }
        $tool = '';
        /* if($product['tool']){
          $tool = '+'.$product['tool'];
        } */
        $content = '<span style="text-align: center;">'.$space.'
                      <span style="font-size:2.1em; font-weight: bold;">'.$product['name'].'</span><span style="font-size:.1em"><br/></span>
                      <span style="font-size:1.5em; font-weight: bold;">'.$product['code'].$tool.'</span><span style="font-size:.1em"><br/></span>
                      <span style="font-size:'.$font_size_prices.'em; font-weight: bold;">'.$this->customPrices($product['prices'], $br).'</span><span style="font-size:.1em"><br/></span>
                      <span style="font-size:1.1em; font-weight: bold;">'.$pz.'</span>
                    </span>';
        $this->setImageBackground(realpath(dirname(__FILE__).'/../../..').'/files/resources/img/STAR12.png', $content, 66.6, 41.6, 3, 6, 5, 0, $key+$counter);
      }
    }
    $counter = 0;
    foreach($off as $key => $product){
      for($i=0; $i<$product['copies']; $i++){
        if($i>0){
          $counter +=1;
        }
        if(($key+$counter)%$pzHoja==0){
          PDF::AddPage();
          PDF::MultiCell($w=240, $h=10, '<span style="font-size:2em;">Hoja #'.(intval(($key+$counter)/$pzHoja)+1).' naranja. Creada por: '.$person.'</span>', $border=0, $align='center', $fill=0, $ln=0, $x=0, $y=0, $reseth=true, $stretch=0, $ishtml=true, $autopadding=false, $maxh=0);
        }
        $pz = '';
        $br = 1;
        if($isInnerPack){
          $pz = $product['pieces'].' pz';
          $br= 0;
        }
        $tool = '';
        /* if($product['tool']){
          $tool = '+'.$product['tool'];
        } */
        $content = '<span style="text-align: center;">
                        <span style="font-size:2.1em; font-weight: bold;">'.$product['name'].'</span><span style="font-size:.1em"><br/></span>
                        <span style="font-size:1.5em; font-weight: bold;">'.$product['code'].$tool.'</span><span style="font-size:.1em"><br/></span>
                        <span style="font-size:2.3em; font-weight: bold;">'.$this->customPrices($product['prices'], $br).'</span><span style="font-size:.1em"><br/></span>
                        <span style="font-size:1.2em; font-weight: bold;">'.$pz.'</span>
                    </span>';

        $this->setImageBackground(realpath(dirname(__FILE__).'/../../..').'/files/resources/img/STAR12.png', $content, 66.6, 41.6, 3, 6, 5, 0, $key+$counter);
      }
    }
    
    $nameFile = time().'.pdf';
    PDF::Output(realpath(dirname(__FILE__).'/../../..').'/files/'.$nameFile, 'F');
    $std = collect($std);
    $off = collect($off);
    $totalOff = $off->reduce( function($total, $product){
      return $total = $total +$product['copies'];
    });
    $totalStd = $std->reduce( function($total, $product){
      return $total = $total +$product['copies'];
    });
    return response()->json([
      'pages_off' => ceil($totalOff/$pzHoja),
      'pages_std' => ceil($totalStd/$pzHoja),
      'total' => ceil($totalStd/$pzHoja) + ceil($totalOff/$pzHoja),
      'file' => $nameFile,
    ]);
  }

  public function pdf_star_4($products, $isInnerPack){
    PDF::SetTitle('Pdf estrella x4');
    $off = $this->getOffProducts($products);
    $std = $this->getStdProducts($products);
    $counter = 0;
    $account = Account::with('user')->find($this->account->id);
    $person = $account->user->names.' '.$account->user->surname_pat.' '.$account->user->surname_mat;
    //etiquetas por hoja
    $pzHoja = 24;
    foreach($std as $key => $product){
      for($i=0; $i<$product['copies']; $i++){
        if($i>0){
          $counter +=1;
        }
        if(($key+$counter)%$pzHoja==0){
          PDF::AddPage();
          PDF::MultiCell($w=240, $h=10, '<span style="font-size:2em;">Hoja #'.(intval(($key+$counter)/$pzHoja)+1).' verde. Creada por: '.$person.'</span>', $border=0, $align='center', $fill=0, $ln=0, $x=0, $y=0, $reseth=true, $stretch=0, $ishtml=true, $autopadding=false, $maxh=0);
        }
        $pz = '';
        if($isInnerPack){
          $pz = $product['pieces'];
        }
        $number_of_prices = count($product['prices']);
        $font_size_prices = 2;
        $br = 0;
        switch($number_of_prices){
          case 1:
            $font_size_prices = 2.3;
          break;
          case 2:
            $font_size_prices = 1.6;
          break;
          case 3:
            $font_size_prices = 1.2;
          break;
          case 4:
            $font_size_prices = 1;
            $br = 0;
          break;
        }
        $pz = '';
        if($isInnerPack){
          $pz = $product['pieces'].' pz';
          $br= 0;
        }
        $tool = '';
        /* if($product['tool']){
          $tool = '+'.$product['tool'];
        } */
        $content = '<span style="text-align: center;">
                      <span style="font-size:2.1em; font-weight: bold;">'.$product['name'].'</span><span style="font-size:.1em"><br/></span>
                      <span style="font-size:1.5em; font-weight: bold;">'.$product['code'].$tool.'</span><span style="font-size:.1em"><br/></span>
                      <span style="font-size:'.$font_size_prices.'em; font-weight: bold;">'.$this->customPrices($product['prices'], $br).'</span><span style="font-size:.1em"><br/></span>
                      <span style="font-size:1em; font-weight: bold;">'.$pz.'</span>
                    </span>';
        $this->setImageBackground(realpath(dirname(__FILE__).'/../../..').'/files/resources/img/STAR12.png', $content, 50, 41.6, 4, 6, 5, 0, $key+$counter);
      }
    }
    $counter = 0;
    foreach($off as $key => $product){
      for($i=0; $i<$product['copies']; $i++){
        if($i>0){
          $counter +=1;
        }
        if(($key+$counter)%$pzHoja==0){
          PDF::AddPage();
          PDF::MultiCell($w=240, $h=10, '<span style="font-size:2em;">Hoja #'.(intval(($key+$counter)/$pzHoja)+1).' naranja. Creada por: '.$person.'</span>', $border=0, $align='center', $fill=0, $ln=0, $x=0, $y=0, $reseth=true, $stretch=0, $ishtml=true, $autopadding=false, $maxh=0);
        }
        $pz = '';
        $br = 1;
        if($isInnerPack){
          $pz = $product['pieces'].' pz';
          $br= 0;
        }
        $tool = '';
        /* if($product['tool']){
          $tool = '+'.$product['tool'];
        } */
        $content = '<span style="text-align: center;">
                      <span style="font-size:2.1em; font-weight: bold;">'.$product['name'].'</span><span style="font-size:.1em"><br/></span>
                      <span style="font-size:1.5em; font-weight: bold;">'.$product['code'].$tool.'</span><span style="font-size:.1em"><br/></span>
                      <span style="font-size:2.3em; font-weight: bold;">'.$this->customPrices($product['prices'], $br).'</span><span style="font-size:.1em"><br/></span>
                      <span style="font-size:1.1em; font-weight: bold;">'.$pz.'</span>
                    </span>';
        $this->setImageBackground(realpath(dirname(__FILE__).'/../../..').'/files/resources/img/STAR12.png', $content, 50, 41.6, 4, 6, 5, 0, $key+$counter);
      }
    }
    
    $nameFile = time().'.pdf';
    PDF::Output(realpath(dirname(__FILE__).'/../../..').'/files/'.$nameFile, 'F');
    $std = collect($std);
    $off = collect($off);
    $totalOff = $off->reduce( function($total, $product){
      return $total = $total +$product['copies'];
    });
    $totalStd = $std->reduce( function($total, $product){
      return $total = $total +$product['copies'];
    });
    return response()->json([
      'pages_off' => ceil($totalOff/$pzHoja),
      'pages_std' => ceil($totalStd/$pzHoja),
      'total' => ceil($totalStd/$pzHoja) + ceil($totalOff/$pzHoja),
      'file' => $nameFile,
    ]);
  }

  public function pdf_bodega($products){
    $account = Account::with('user')->find($this->account->id);
    $person = $account->user->names.' '.$account->user->surname_pat.' '.$account->user->surname_mat;
    PDF::SetTitle('Pdf bodega');
    $counter = 0;
    //etiquetas por hoja
    $pzHoja = 18;
    foreach($products as $key => $product){
      for($i=0; $i<$product['copies']; $i++){
        if($i>0){
          $counter +=1;
        }
        if(($key+$counter)%$pzHoja==0){
          PDF::AddPage();
          PDF::SetMargins(0, 0, 0);
          PDF::SetAutoPageBreak(FALSE, 0);
          PDF::setCellPaddings(0,0,0,0);
          PDF::MultiCell($w=240, $h=10, '<span style="font-size:2em;">Hoja #'.(intval(($key+$counter)/$pzHoja)+1).' naranja. Creada por: '.$person.'</span>', $border=0, $align='center', $fill=0, $ln=0, $x=0, $y=0, $reseth=true, $stretch=0, $ishtml=true, $autopadding=false, $maxh=0);
        }
        $tool = '';
        /* if($product['tool']){
          $tool = '+'.$product['tool'];
        } */
        $font_size_code = 3.8;
        if(strlen($product['code'])>13){
          $font_size_code = 2.4;
        }
        else if(strlen($product['code'])>12){
          $font_size_code = 2.6;
        }
        else if(strlen($product['code'])>11){
          $font_size_code = 3.1;
        }else{
          $font_size_code = 3.4;
        }
        $content = '<span style="text-align: center;">
                      <span style="font-size:'.$font_size_code.'em; font-weight: bold;">'.$product['code'].'</span><span style="font-size:.1em"><br/></span>
                      <span style="font-size:2.7em; font-weight: bold;">'.$product['name'].$tool.'</span>
                    </span>';

        $this->setImageBackground_bordered(null, $content, 103, 30, 2, 9, 1, -4, $key+$counter);
      }
    }
    
    $nameFile = time().'.pdf';
    PDF::Output(realpath(dirname(__FILE__).'/../../..').'/files/'.$nameFile, 'F');
    $products = collect($products);
    $total = $products->reduce( function($total, $product){
      return $total = $total +$product['copies'];
    });
    return response()->json([
      'total' => ceil($total/$pzHoja),
      'file' => $nameFile,
    ]);
  }

  public function pdf_mochila($products, $isInnerPack){
    PDF::SetTitle('Pdf Mochila x6');
    $off = $this->getOffProducts($products);
    $std = $this->getStdProducts($products);
    $account = Account::with('user')->find($this->account->id);
    $person = $account->user->names.' '.$account->user->surname_pat.' '.$account->user->surname_mat;
    $counter = 0;
    //etiquetas por hoja
    $pzHoja = 6;
    foreach($std as $key => $product){
        for($i=0; $i<$product['copies']; $i++){
            if($i>0){
                $counter +=1; 
            }
            if(($key+$counter)%$pzHoja==0){
                PDF::AddPage('L');
                PDF::SetMargins(0, 0, 0);
                PDF::SetAutoPageBreak(FALSE, 0);
                PDF::setCellPaddings(0,0,0,0);
                PDF::MultiCell($w=240, $h=10, '<span style="font-size:2em;">Hoja #'.(intval(($key+$counter)/$pzHoja)+1).' azul o rosa. Creada por: '.$person.'</span>', $border=0, $align='center', $fill=0, $ln=0, $x=0, $y=0, $reseth=true, $stretch=0, $ishtml=true, $autopadding=false, $maxh=0);
            }
            $pz = '';
            if($isInnerPack){
                $pz = $product['pieces'];
            }
            $number_of_prices = count($product['prices']);
            $font_size_prices = 2;
            $br = 1;
            switch($number_of_prices){
                case 1:
                    $font_size_prices = 3.1;
                break;
                case 2:
                    $font_size_prices = 1.4;
                break;
                case 3:
                    $font_size_prices = 1.4;
                break;
                case 4:
                    $font_size_prices = 1.3;
                    $br = 0;
                break;
            }
            $pz = '';
            $space = '<span style="font-size:.1em"><br/></span>';
            if($isInnerPack){
                $pz = $product['pieces'].' pz';
                $space = '<span style="font-size:.1em"><br/></span>';
                $br= .4;
            }
            $tool = '';
            /* if($product['tool']){
                $tool = '+'.$product['tool'];
            } */
            $content =  '<div style="text-align: center;">'.$space.'
                        <span style="font-size:3.2em; font-weight: bold;">'.$product['name'].'</span><span style="font-size:.1em"><br/></span>
                        <span style="font-size:1.4em; font-weight: bold;">'.$product['code'].$tool.'</span><span style="font-size:.1em"><br/></span>
                        <span style="font-size:'.$font_size_prices.'em; font-weight: bold;">'.$this->customPrices($product['prices'], $br).'</span><span style="font-size:.1em"><br/></span>
                        <span style="font-size:1.3em; font-weight: bold;">'.$pz.'</span>
                    </div>';
            $this->setImageBackground_area(__DIR__.'./resources/img/STAR12.png', $content, $width=47, $height=53, $cols=3, $rows=2, $top_space=-5.2, $sides_space= 29, $key+$counter, $top_margin=30, $sides_margin=40);
        }
    }
    $counter = 0;
    foreach($off as $key => $product){
        for($i=0; $i<$product['copies']; $i++){
            if($i>0){
                $counter +=1; 
            }
            if(($key+$counter)%$pzHoja==0){
                PDF::AddPage('L');
                PDF::SetMargins(0, 0, 0);
                PDF::SetAutoPageBreak(FALSE, 0);
                PDF::setCellPaddings(0,0,0,0);
                PDF::MultiCell($w=240, $h=10, '<span style="font-size:2em;">Hoja #'.(intval(($key+$counter)/$pzHoja)+1).'. Creada por: '.$person.'</span>', $border=0, $align='center', $fill=0, $ln=0, $x=0, $y=0, $reseth=true, $stretch=0, $ishtml=true, $autopadding=false, $maxh=0);
            }
            $pz = '';
            /* if($isInnerPack){
                $pz = $product['pieces'];
            } */
            $font_size_prices = 2;
            $br = 1;
            $pz = '';
            $space = '<span style="font-size:.1em"><br/></span>';
            if($isInnerPack){
                $pz = $product['pieces'].' pz';
                $space = '<span style="font-size:.1em"><br/></span>';
                $br= .4;
            }
            $tool = '';
            /* if($product['tool']){
                $tool = '+'.$product['tool'];
            } */
            $content =  '<div style="text-align: center;">'.$space.'
                <span style="font-size:3.2em; font-weight: bold;">'.$product['name'].'</span><span style="font-size:.1em"><br/></span>
                <span style="font-size:1.4em; font-weight: bold;">'.$product['code'].$tool.'</span><span style="font-size:.1em"><br/></span>
                <span style="font-size:3.1em; font-weight: bold;">'.$this->customPrices($product['prices'], $br).'</span><span style="font-size:.1em"><br/></span>
                <span style="font-size:1.3em; font-weight: bold;">'.$pz.'</span>
            </div>';
            $this->setImageBackground_area(__DIR__.'./resources/img/STAR12.png', $content, $width=47, $height=53, $cols=3, $rows=2, $top_space=-4, $sides_space= 29, $key+$counter, $top_margin=31.5, $sides_margin=40);
        }
    }
    
    $nameFile = time().'.pdf';
    PDF::Output(realpath(dirname(__FILE__).'/../../..').'/files/'.$nameFile, 'F');
    
    $std = collect($std);
    $off = collect($off);
    $totalOff = $off->reduce( function($total, $product){
        return $total = $total +$product['copies'];
    });
    $totalStd = $std->reduce( function($total, $product){
        return $total = $total +$product['copies'];
    });
    return response()->json([
        'pages_off' => ceil($totalOff/$pzHoja),
        'pages_std' => ceil($totalStd/$pzHoja),
        'total' => ceil($totalStd/$pzHoja) + ceil($totalOff/$pzHoja),
        'file' => $nameFile,
    ]);
  }

  public function pdf_lonchera($products, $isInnerPack){
      PDF::SetTitle('Pdf lonchera');
      $counter = 0;
      //etiquetas por hoja
      $pzHoja = 9;
      $account = Account::with('user')->find($this->account->id);
      $person = $account->user->names.' '.$account->user->surname_pat.' '.$account->user->surname_mat;
      foreach($products as $key => $product){
          for($i=0; $i<$product['copies']; $i++){
              if($i>0){
                  $counter +=1; 
              }
              if(($key+$counter)%$pzHoja==0){
                  PDF::AddPage();
                  PDF::SetMargins(0, 0, 0);
                  PDF::SetAutoPageBreak(FALSE, 0);
                  PDF::setCellPaddings(0,0,0,0);
                  PDF::MultiCell($w=240, $h=10, '<span style="font-size:2em;">Hoja #'.(intval(($key+$counter)/$pzHoja)+1).'. Creada por: '.$person.'</span>', $border=0, $align='center', $fill=0, $ln=0, $x=0, $y=0, $reseth=true, $stretch=0, $ishtml=true, $autopadding=false, $maxh=0);
              }
              $pz = '';
              if($isInnerPack){
                  $pz = $product['pieces'];
              }
              $number_of_prices = count($product['prices']);
              $font_size_prices = 2;
              $br = 1;
              switch($number_of_prices){
                  case 1:
                      $font_size_prices = 2.7;
                  break;
                  case 2:
                      $font_size_prices = 1.7;
                  break;
                  case 3:
                      $font_size_prices = 1.6;
                  break;
                  case 4:
                      $font_size_prices = 1.4;
                      $br = 0;
                  break;
              }
              $pz = '';
              $space = '<span style="font-size:1em"><br/></span>';
              if($isInnerPack){
                  $pz = $product['pieces'].' pz';
                  $space = '<span style="font-size:.4em"><br/></span>';
                  $br= .4;
              }
              $tool = '';
              /* if($product['tool']){
                  $tool = '+'.$product['tool'];
              } */
              $content = '';
              if($product['type']=='off'){
                      $content =  '<div style="text-align: center;">'.$space.'
                              <span style="font-size:.9em"><br/></span>
                              <span style="font-size:2.8em; font-weight: bold;">'.$product['name'].'</span><span style="font-size:.1em"><br/></span>
                              <span style="font-size:1.2em; font-weight: bold;">'.$product['code'].$tool.'</span><span style="font-size:.1em"><br/></span>
                              <span style="font-size:'.$font_size_prices.'em; font-weight: bold;">'.$this->customPrices($product['prices'], $br).'</span><span style="font-size:.1em"><br/></span>
                              <span style="font-size:1.2em; font-weight: bold;">'.$pz.'</span>
                          </div>';
              }else{
                  $content =  '<div style="text-align: center;">'.$space.'
                              <span style="font-size:2.8em; font-weight: bold;">'.$product['name'].'</span><span style="font-size:.1em"><br/></span>
                              <span style="font-size:1.2em; font-weight: bold;">'.$product['code'].$tool.'</span><span style="font-size:.1em"><br/></span>
                              <span style="font-size:'.$font_size_prices.'em; font-weight: bold;">'.$this->customPrices($product['prices'], $br).'</span><span style="font-size:.1em"><br/></span>
                              <span style="font-size:1.2em; font-weight: bold;">'.$pz.'</span>
                          </div>';
              }
              
              $this->setImageBackground_area_2(null, $content, $width=52, $height=58, $cols=3, $rows=3, $top_space=-43, $sides_space=14.7, $position=$key+$counter, $top_margin=30, $sides_margin=14);
          }
      }
      
      $nameFile = time().'.pdf';
      PDF::Output(realpath(dirname(__FILE__).'/../../..').'/files/'.$nameFile, 'F');
      //return response(PDF::Output($nameFile, 'D'));
      $products = collect($products);
      $totalProducts = $products->reduce( function($total, $product){
          return $total = $total +$product['copies'];
      });
      return response()->json([
          'total' => ceil($totalProducts/$pzHoja),
          'file' => $nameFile,
      ]);
  }

  public function pdf_lapicera($products, $isInnerPack){
    PDF::SetTitle('Pdf lapicera');
    $counter = 0;
    //etiquetas por hoja
    $pzHoja = 12;
    $account = Account::with('user')->find($this->account->id);
    $person = $account->user->names.' '.$account->user->surname_pat.' '.$account->user->surname_mat;
    foreach($products as $key => $product){
        for($i=0; $i<$product['copies']; $i++){
            if($i>0){
                $counter +=1; 
            }
            if(($key+$counter)%$pzHoja==0){
                PDF::AddPage();
                PDF::SetMargins(0, 0, 0);
                PDF::SetAutoPageBreak(FALSE, 0);
                PDF::setCellPaddings(0,0,0,0);
                PDF::MultiCell($w=240, $h=10, '<span style="font-size:2em;">Hoja #'.(intval(($key+$counter)/$pzHoja)+1).'. Creada por: '.$person.'</span>', $border=0, $align='center', $fill=0, $ln=0, $x=0, $y=0, $reseth=true, $stretch=0, $ishtml=true, $autopadding=false, $maxh=0);
            }
            $pz = '';
            if($isInnerPack){
                $pz = $product['pieces'];
            }
            $number_of_prices = count($product['prices']);
            $font_size_prices = 2;
            $br = 1;
            switch($number_of_prices){
                case 1:
                    $font_size_prices = 2.3;
                break;
                case 2:
                    $font_size_prices = 1.4;
                break;
                case 3:
                    $font_size_prices = 1.3;
                break;
                case 4:
                    $font_size_prices = 1.1;
                    $br = 0;
                break;
            }
            $pz = '';
            $space = '<span style="font-size:1em"><br/></span>';
            if($isInnerPack){
                $pz = $product['pieces'].' pz';
                $space = '<span style="font-size:.4em"><br/></span>';
                $br= .4;
            }
            $tool = '';
            /* if($product['tool']){
                $tool = '+'.$product['tool'];
            } */
            $content = '';
            if($product['type']=='off'){
                $content =  '<div style="text-align: center;">'.$space.'
                            <span style="font-size:.1em"><br/></span>
                            <span style="font-size:2.1em; font-weight: bold;">'.$product['name'].'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:1em; font-weight: bold;">'.$product['code'].$tool.'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:'.$font_size_prices.'em; font-weight: bold;">'.$this->customPrices($product['prices'], $br).'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:1em; font-weight: bold;">'.$pz.'</span>
                        </div>';
            }else{
                $content =  '<div style="text-align: center;">'.$space.'
                            <span style="font-size:2.1em; font-weight: bold;">'.$product['name'].'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:1em; font-weight: bold;">'.$product['code'].$tool.'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:'.$font_size_prices.'em; font-weight: bold;">'.$this->customPrices($product['prices'], $br).'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:1em; font-weight: bold;">'.$pz.'</span>
                        </div>';
            }
            
            $this->setImageBackground_area_2(null, $content, $width=40, $height=48, $cols=4, $rows=3, $top_space=-62, $sides_space=15, $position=$key+$counter, $top_margin=42, $sides_margin=7.5);
        }
    }
    
    $nameFile = time().'.pdf';
    PDF::Output(realpath(dirname(__FILE__).'/../../..').'/files/'.$nameFile, 'F');
    //return response(PDF::Output($nameFile, 'D'));
    $products = collect($products);
    $totalProducts = $products->reduce( function($total, $product){
        return $total = $total +$product['copies'];
    });
    return response()->json([
        'total' => ceil($totalProducts/$pzHoja),
        'file' => $nameFile,
    ]);    
  }

  public function pdf_mochila_16($products, $isInnerPack){
    PDF::SetTitle('Pdf mochila x16');
    $account = Account::with('user')->find($this->account->id);
    $person = $account->user->names.' '.$account->user->surname_pat.' '.$account->user->surname_mat;
    $counter = 0;
    //etiquetas por hoja
    $pzHoja = 16;
    foreach($products as $key => $product){
        for($i=0; $i<$product['copies']; $i++){
            if($i>0){
              $counter +=1; 
            }
            if(($key+$counter)%$pzHoja==0){
                PDF::AddPage();
                PDF::SetMargins(0, 0, 0);
                PDF::SetAutoPageBreak(FALSE, 0);
                PDF::setCellPaddings(0,0,0,0);
                PDF::MultiCell($w=240, $h=10, '<span style="font-size:2em;">Hoja #'.(intval(($key+$counter)/$pzHoja)+1).'. Creada por: '.$person.'</span>', $border=0, $align='center', $fill=0, $ln=0, $x=0, $y=0, $reseth=true, $stretch=0, $ishtml=true, $autopadding=false, $maxh=0);
            }
            $pz = '';
            if($isInnerPack){
                $pz = $product['pieces'];
            }
            $number_of_prices = count($product['prices']);
            $font_size_prices = 2;
            $br = 1;
            switch($number_of_prices){
                case 1:
                    $font_size_prices = 2.7;
                break;
                case 2:
                    $font_size_prices = 1.5;
                break;
                case 3:
                    $font_size_prices = 1.5;
                break;
                case 4:
                    $font_size_prices = 1.4;
                    $br = 0;
                break;
            }
            $pz = '';
            $space = '<span style="font-size:1em"><br/></span>';
            if($isInnerPack){
                $pz = $product['pieces'].' pz';
                $space = '<span style="font-size:.4em"><br/></span>';
                $br= .4;
            }
            $tool = '';
            /* if($product['tool']){
                $tool = '+'.$product['tool'];
            } */
            $content = '';
            if($product['type']=='off'){
                    $content =  '<div style="text-align: center;">'.$space.'
                            <span style="font-size:1em"><br/></span>
                            <span style="font-size:2.8em; font-weight: bold;">'.$product['name'].'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:1.2em; font-weight: bold;">'.$product['code'].$tool.'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:'.$font_size_prices.'em; font-weight: bold;">'.$this->customPrices($product['prices'], $br).'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:1.2em; font-weight: bold;">'.$pz.'</span>
                        </div>';
            }else{
                $content =  '<div style="text-align: center;">'.$space.'
                            <span style="font-size:1em"><br/></span>
                            <span style="font-size:2.8em; font-weight: bold;">'.$product['name'].'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:1.2em; font-weight: bold;">'.$product['code'].$tool.'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:'.$font_size_prices.'em; font-weight: bold;">'.$this->customPrices($product['prices'], $br).'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:1.2em; font-weight: bold;">'.$pz.'</span>
                        </div>';
            }
            
            $this->setImageBackground_area_2(null, $content, $width=44, $height=49, $cols=4, $rows=4, $top_space=-19.8, $sides_space=6.5, $position=$key+$counter, $top_margin=16, $sides_margin=7.5);
        }
    }
    
    $nameFile = time().'.pdf';
    PDF::Output(realpath(dirname(__FILE__).'/../../..').'/files/'.$nameFile, 'F');
    //return response(PDF::Output($nameFile, 'D'));
    $products = collect($products);
    $totalProducts = $products->reduce( function($total, $product){
        return $total = $total +$product['copies'];
    });
    return response()->json([
        'total' => ceil($totalProducts/$pzHoja),
        'file' => $nameFile,
    ]);
  }

  public function pdf_lonchera_16($products, $isInnerPack){
    PDF::SetTitle('Pdf lochera x16');
    $counter = 0;
    //etiquetas por hoja
    $account = Account::with('user')->find($this->account->id);
    $person = $account->user->names.' '.$account->user->surname_pat.' '.$account->user->surname_mat;
    $pzHoja = 16;
    foreach($products as $key => $product){
        for($i=0; $i<$product['copies']; $i++){
            if($i>0){
                $counter +=1; 
            }
            if(($key+$counter)%$pzHoja==0){
                PDF::AddPage();
                PDF::SetMargins(0, 0, 0);
                PDF::SetAutoPageBreak(FALSE, 0);
                PDF::setCellPaddings(0,0,0,0);
                PDF::MultiCell($w=240, $h=10, '<span style="font-size:2em;">Hoja #'.(intval(($key+$counter)/$pzHoja)+1).'. Creada por: '.$person.'</span>', $border=0, $align='center', $fill=0, $ln=0, $x=0, $y=0, $reseth=true, $stretch=0, $ishtml=true, $autopadding=false, $maxh=0);
            }
            $pz = '';
            if($isInnerPack){
                $pz = $product['pieces'];
            }
            $number_of_prices = count($product['prices']);
            $font_size_prices = 2;
            $br = 1;
            switch($number_of_prices){
                case 1:
                    $font_size_prices = 2.7;
                break;
                case 2:
                    $font_size_prices = 1.7;
                break;
                case 3:
                    $font_size_prices = 1.6;
                break;
                case 4:
                    $font_size_prices = 1.4;
                    $br = 0;
                break;
            }
            $pz = '';
            $space = '<span style="font-size:1em"><br/></span>';
            if($isInnerPack){
              $pz = $product['pieces'];
              $space = '<span style="font-size:.4em"><br/></span>';
              $br= .4;
            }
            $tool = '';
            /* if($product['tool']){
                $tool = '+'.$product['tool'];
            } */
            $content = '';
            if($product['type']=='off'){
                    $content =  '<div style="text-align: center;">'.$space.'
                            <span style="font-size:1em"><br/></span>
                            <span style="font-size:2.8em; font-weight: bold;">'.$product['name'].'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:1.2em; font-weight: bold;">'.$product['code'].$tool.'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:'.$font_size_prices.'em; font-weight: bold;">'.$this->customPrices($product['prices'], $br).'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:1.2em; font-weight: bold;">'.$pz.'</span>
                        </div>';
            }else{
                $content =  '<div style="text-align: center;">'.$space.'
                            <span style="font-size:1em"><br/></span>
                            <span style="font-size:2.8em; font-weight: bold;">'.$product['name'].'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:1.2em; font-weight: bold;">'.$product['code'].$tool.'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:'.$font_size_prices.'em; font-weight: bold;">'.$this->customPrices($product['prices'], $br).'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:1.2em; font-weight: bold;">'.$pz.'</span>
                        </div>';
            }
            
            $this->setImageBackground_area_2(null, $content, $width=44, $height=49, $cols=4, $rows=4, $top_space=-24, $sides_space=7.5, $position=$key+$counter, $top_margin=14.6, $sides_margin=7.5);
        }
    }
    
    $nameFile = time().'.pdf';
    PDF::Output(realpath(dirname(__FILE__).'/../../..').'/files/'.$nameFile, 'F');
    //return response(PDF::Output($nameFile, 'D'));
    $products = collect($products);
    $totalProducts = $products->reduce( function($total, $product){
        return $total = $total + $product['copies'];
    });
    return response()->json([
        'total' => ceil($totalProducts/$pzHoja),
        'file' => $nameFile,
    ]);
  }

  public function pdf_lapicera_20($products, $isInnerPack){
    PDF::SetTitle('Pdf lapicera x20');
    $counter = 0;
    //etiquetas por hoja
    $account = Account::with('user')->find($this->account->id);
    $person = $account->user->names.' '.$account->user->surname_pat.' '.$account->user->surname_mat;
    $pzHoja = 20;
    foreach($products as $key => $product){
        for($i=0; $i<$product['copies']; $i++){
            if($i>0){
                $counter +=1; 
            }
            if(($key+$counter)%$pzHoja==0){
                PDF::AddPage();
                PDF::SetMargins(0, 0, 0);
                PDF::SetAutoPageBreak(FALSE, 0);
                PDF::setCellPaddings(0,0,0,0);
                PDF::MultiCell($w=240, $h=10, '<span style="font-size:1em;">Hoja #'.(intval(($key+$counter)/$pzHoja)+1).'. Creada por: '.$person.'</span>', $border=0, $align='center', $fill=0, $ln=0, $x=5, $y=5, $reseth=true, $stretch=0, $ishtml=true, $autopadding=false, $maxh=0);
            }
            $pz = '';
            if($isInnerPack){
                $pz = $product['pieces'];
            }
            $number_of_prices = count($product['prices']);
            $font_size_prices = 2;
            $br = 1;
            switch($number_of_prices){
                case 1:
                    $font_size_prices = 2.3;
                break;
                case 2:
                    $font_size_prices = 1.4;
                break;
                case 3:
                    $font_size_prices = 1.3;
                break;
                case 4:
                    $font_size_prices = 1.1;
                    $br = 0;
                break;
            }
            $pz = '';
            $space = '<span style="font-size:1em"><br/></span>';
            if($isInnerPack){
                $pz = $product['pieces'].' pz';
                /* $space = '<span style="font-size:.4em"><br/></span>'; */
                $br= .4;
            }
            $tool = '';
            /* if($product['tool']){
                $tool = '+'.$product['tool'];
            } */
            $content = '';
            if($product['type']=='off'){
                $content =  '<div style="text-align: center;">'.$space.'
                            <span style="font-size:.1em"><br/></span>
                            <span style="font-size:2.1em; font-weight: bold;">'.$product['name'].'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:1em; font-weight: bold;">'.$product['code'].$tool.'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:'.$font_size_prices.'em; font-weight: bold;">'.$this->customPrices($product['prices'], $br).'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:1em; font-weight: bold;">'.$pz.'</span>
                        </div>';
            }else{
                $content =  '<div style="text-align: center;">'.$space.'
                            <span style="font-size:2.1em; font-weight: bold;">'.$product['name'].'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:1em; font-weight: bold;">'.$product['code'].$tool.'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:'.$font_size_prices.'em; font-weight: bold;">'.$this->customPrices($product['prices'], $br).'</span><span style="font-size:.1em"><br/></span>
                            <span style="font-size:1em; font-weight: bold;">'.$pz.'</span>
                        </div>';
            }
            
            $this->setImageBackground_area_2(null, $content, $width=37, $height=40, $cols=4, $rows=5, $top_space=-21, $sides_space=11.8, $position=$key+$counter, $top_margin=12.7, $sides_margin=11.6);
        }
    }
    
    $nameFile = time().'.pdf';
    PDF::Output(realpath(dirname(__FILE__).'/../../..').'/files/'.$nameFile, 'F');
    $products = collect($products);
    $totalProducts = $products->reduce( function($total, $product){
        return $total = $total +$product['copies'];
    });
    return response()->json([
        'total' => ceil($totalProducts/$pzHoja),
        'file' => $nameFile,
    ]);
  }
  
}
