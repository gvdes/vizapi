<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithStyles;

class ArrayExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles{
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