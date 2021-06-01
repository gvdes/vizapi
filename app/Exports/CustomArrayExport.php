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

class CustomArrayExport implements FromArray, WithTitle , ShouldAutoSize, WithHeadings, WithStyles, WithColumnFormatting{
    protected $invoices;
    protected $sheet;

    public function __construct(array $invoices, $sheet, $format){
        $this->invoices = $invoices;
        $this->sheet = $sheet;
        $this->format = $format;
    }

    /**
     * @return string
     */
    public function title(): string{
        return $this->sheet;
    }

    public function headings(): array{
        return count($this->invoices)>0 ? array_keys($this->invoices[0]) : ["Sin información"];
    }

    public function array(): array{
        return $this->invoices;
    }

    public function styles(Worksheet $sheet){
        return [
        // Style the first row as bold text.
        1 => ['font' => ['bold' => true]]
        ];
    }

    public function columnFormats(): array{       
        $properties = ["NUMBER" => NumberFormat::FORMAT_NUMBER, "TEXT" => NumberFormat::FORMAT_TEXT];
        $format = [];
        foreach($this->format as $key => $col){
            $format[$key] = $properties[$col];
        }
        return $format;
    }
}