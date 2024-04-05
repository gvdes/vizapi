<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

class ArrayExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithColumnFormatting, WithStrictNullComparison{
    protected $invoices;

    public function __construct(array $invoices){
        $this->invoices = $invoices;
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
        return [
            'B' => NumberFormat::FORMAT_NUMBER,
            'C' => NumberFormat::FORMAT_NUMBER,
            'D' => NumberFormat::FORMAT_NUMBER,
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_TEXT,
            'G' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_TEXT,
            'I' => NumberFormat::FORMAT_NUMBER,
            'J' => NumberFormat::FORMAT_TEXT,
            'K' => NumberFormat::FORMAT_TEXT,
            'L' => NumberFormat::FORMAT_NUMBER,
            'M' => NumberFormat::FORMAT_NUMBER,
            'N' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
            'O' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
            'P' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
            'Q' => NumberFormat::FORMAT_TEXT,
        ];
      }
}