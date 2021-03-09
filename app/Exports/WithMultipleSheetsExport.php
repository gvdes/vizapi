<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class WithMultipleSheetsExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles{
    protected $invoices;

    public function __construct(array $invoices){
        $this->invoices = $invoices;
    }

    /* public function headings(): array{
        return array_keys($this->invoices[0]);
    } */

    public function array(): array{
        return $this->invoices;
    }

    public function sheets(): array{
        $sheets = [];
        foreach($this->invoces as $key => $category){
            $sheets[] = new InvoicesPerCategorySheet($category, $key);
        }

        return $sheets;
    }
}