<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RegionsExport implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private $regions) {}

    public function title(): string { return 'Rekapitulasi Wilayah'; }

    public function headings(): array
    {
        return ['No','Nama Wilayah','Tipe','Jumlah Akun','Total Followers','Kategori Dominan','Breakdown Kategori'];
    }

    public function collection()
    {
        return $this->regions->values()->map(function ($region, $i) {
            $accounts   = $region->socialAccounts;
            $byCategory = $accounts->groupBy('category_id');
            $dominant   = $byCategory->sortByDesc(fn ($g) => $g->count())->first();
            $breakdown  = $byCategory->map(fn ($g) => ($g->first()->category?->name ?? '-') . ':' . $g->count())->join(', ');

            return [
                $i + 1,
                $region->name,
                ucfirst($region->type),
                $accounts->count(),
                (int) $accounts->sum('followers_count'),
                $dominant?->first()?->category?->name ?? '-',
                $breakdown ?: '-',
            ];
        });
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E2D4D']],
        ]);
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
