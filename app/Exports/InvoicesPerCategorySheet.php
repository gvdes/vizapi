<?php 
namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromArray;

class InvoicesPerCategorySheet implements FromArray, WithTitle{
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
    return 'Category ' . $this->category;
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
        1   => ['font' => ['bold' => true]],
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
}