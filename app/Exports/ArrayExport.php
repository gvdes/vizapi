<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ArrayExport implements FromArray, WithHeadings{
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
}