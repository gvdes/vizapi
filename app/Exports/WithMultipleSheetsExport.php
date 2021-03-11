<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class WithMultipleSheetsExport implements WithMultipleSheets{
    protected $invoices;

    public function __construct(array $invoices){
        $this->invoices = $invoices;
    }

    public function sheets(): array{
        $sheets = [];
        foreach($this->invoices as $key => $category){
            $sheets[] = new InvoicesPerCategorySheet($category, $key);
        }

        return $sheets;
    }
}