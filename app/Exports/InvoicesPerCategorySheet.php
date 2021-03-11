<?php 
namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

class InvoicesPerCategorySheet implements FromArray, WithTitle , ShouldAutoSize, WithHeadings, WithStyles, WithColumnFormatting{
  protected $invoices;
  protected $category;

  public function __construct(array $invoices, $category){
    $this->invoices = $invoices;
    $this->category = $category;
  }

  /**
   * @return string
   */
  public function title(): string{
    return $this->category;
  }

  public function headings(): array{
    return array_keys($this->invoices[0]);
  }

  public function array(): array{
    return $this->invoices;
  }

  public function styles(Worksheet $sheet){
    return [
      // Style the first row as bold text.
      1 => ['font' => ['bold' => true]],
      // Styling an entire column.
      'A' => ['font' => ['size' => 12]],
      // Styling an entire column.
      'B' => ['font' => ['size' => 12]],
      // Styling an entire column.
      'C' => ['font' => ['size' => 12]],
      // Styling an entire column.
      'D' => ['font' => ['size' => 12]],
      // Styling an entire column.
      'E' => ['font' => ['size' => 12]],
      // Styling an entire column.
      'F' => ['font' => ['size' => 12]],
      // Styling an entire column.
      'G' => ['font' => ['size' => 12]]
    ];
  }

  public function columnFormats(): array{
    if($this->category !="A"){
      return [
        'A' => NumberFormat::FORMAT_TEXT,
        'B' => NumberFormat::FORMAT_TEXT,
        'C' => NumberFormat::FORMAT_TEXT,
        'D' => NumberFormat::FORMAT_TEXT,
        'E' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
        'F' => NumberFormat::FORMAT_NUMBER,
        'G' => NumberFormat::FORMAT_NUMBER,
        'H' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
        'I' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE
      ];
    }else {
      return [
        'A' => NumberFormat::FORMAT_TEXT,
        'B' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
        'C' => NumberFormat::FORMAT_NUMBER,
      ];
    }
  }
}