<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AccountsExport implements WithMultipleSheets
{
    public function __construct(
        private $accounts,
        private string $filterLabel = 'Semua data'
    ) {}

    public function sheets(): array
    {
        return [
            new AccountsDataSheet($this->accounts),
            new AccountsSummarySheet($this->accounts, $this->filterLabel),
        ];
    }
}

class AccountsDataSheet implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private $accounts) {}

    public function title(): string { return 'Data Akun'; }

    public function headings(): array
    {
        return ['No','Platform','Username','Display Name','Kategori',
                'Kota/Kabupaten','Followers','Following','Admin Akun','Status','Tanggal Input'];
    }

    public function collection()
    {
        return $this->accounts->values()->map(fn ($acc, $i) => [
            $i + 1,
            strtoupper($acc->platform),
            $acc->username,
            $acc->display_name,
            $acc->category?->name ?? '-',
            $acc->region?->name ?? '-',
            (int) $acc->followers_count,
            (int) ($acc->following_count ?? 0),
            $acc->admins->pluck('full_name')->join(', '),
            $acc->is_active ? 'Aktif' : 'Nonaktif',
            $acc->created_at?->format('d/m/Y'),
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:K1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E2D4D']],
        ]);
        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}

class AccountsSummarySheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private $accounts, private string $filterLabel) {}

    public function title(): string { return 'Ringkasan'; }

    public function headings(): array { return ['Keterangan', 'Nilai']; }

    public function collection()
    {
        $rows = collect([
            ['Total Akun', $this->accounts->count()],
            ['Total Followers', $this->accounts->sum('followers_count')],
            ['Filter Digunakan', $this->filterLabel],
            ['Tanggal Export', now()->format('d/m/Y H:i')],
            ['', ''],
            ['Per Platform', ''],
        ]);

        foreach ($this->accounts->groupBy('platform') as $plat => $grp) {
            $rows->push([strtoupper($plat), $grp->count() . ' akun']);
        }

        return $rows;
    }
}
