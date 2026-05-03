<?php

namespace App\Http\Controllers;

use App\Exports\AccountsExport;
use App\Exports\RegionsExport;
use App\Models\Region;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    // ── Shared: build account query from request filters ─────────────────────
    private function accountQuery(Request $request)
    {
        return SocialAccount::with([
            'category:id,name,color',
            'region:id,name',
            'admins:id,social_account_id,full_name',
        ])
        ->when($request->category_id, fn ($q) => $q->whereIn('category_id', (array) $request->category_id))
        ->when($request->platform,    fn ($q) => $q->whereIn('platform', (array) $request->platform))
        ->when($request->region_id,   fn ($q) => $q->where('region_id', $request->region_id))
        ->when($request->is_active !== null && $request->is_active !== '',
               fn ($q) => $q->where('is_active', $request->boolean('is_active')))
        ->orderByDesc('followers_count');
    }

    // ── Map query (uses same filter params as /map/markers) ──────────────────
    private function mapQuery(Request $request)
    {
        return SocialAccount::with([
            'category:id,name,color',
            'region:id,name',
            'admins:id,social_account_id,full_name',
        ])
        ->where('is_active', true)
        ->when($request->categories, fn ($q) => $q->whereIn('category_id', (array) $request->categories))
        ->when($request->platforms,  fn ($q) => $q->whereIn('platform', (array) $request->platforms))
        ->when($request->region_id,  fn ($q) => $q->where('region_id', $request->region_id))
        ->when($request->min_followers, fn ($q) => $q->where('followers_count', '>=', (int) $request->min_followers))
        ->when($request->max_followers, fn ($q) => $q->where('followers_count', '<=', (int) $request->max_followers))
        ->orderByDesc('followers_count');
    }

    // ── GET /export/accounts → Excel ─────────────────────────────────────────
    public function accountsExcel(Request $request)
    {
        $accounts = $this->accountQuery($request)->get();
        $label    = $this->buildFilterLabel($request);
        $filename = 'laporan-akun-' . now()->format('Ymd-His') . '.xlsx';
        return Excel::download(new AccountsExport($accounts, $label), $filename);
    }

    // ── GET /export/accounts/pdf → PDF ───────────────────────────────────────
    public function accountsPdf(Request $request)
    {
        $accounts = $this->accountQuery($request)->get();
        $filters  = $this->buildFilterLabel($request);

        $pdf = app('dompdf.wrapper')
            ->setPaper('a4', 'landscape')
            ->loadView('exports.accounts-pdf', compact('accounts', 'filters'));

        return $pdf->download('laporan-akun-' . now()->format('Ymd') . '.pdf');
    }

    // ── GET /export/regions → Excel ──────────────────────────────────────────
    public function regionsExcel(Request $request)
    {
        $regions  = Region::with(['socialAccounts.category'])->orderBy('name')->get();
        $filename = 'rekapitulasi-wilayah-' . now()->format('Ymd-His') . '.xlsx';
        return Excel::download(new RegionsExport($regions), $filename);
    }

    // ── GET /export/regions/pdf → PDF ────────────────────────────────────────
    public function regionsPdf(Request $request)
    {
        $regions = Region::with(['socialAccounts.category'])->orderBy('name')->get();

        $pdf = app('dompdf.wrapper')
            ->setPaper('a4', 'landscape')
            ->loadView('exports.regions-pdf', compact('regions'));

        return $pdf->download('rekapitulasi-wilayah-' . now()->format('Ymd') . '.pdf');
    }

    // ── GET /export/map → Excel (filtered map data) ───────────────────────────
    public function mapExcel(Request $request)
    {
        $accounts = $this->mapQuery($request)->get();
        $label    = 'Data Peta · ' . $this->buildFilterLabel($request);
        $filename = 'export-peta-' . now()->format('Ymd-His') . '.xlsx';
        return Excel::download(new AccountsExport($accounts, $label), $filename);
    }

    // ── Build human-readable filter label ────────────────────────────────────
    private function buildFilterLabel(Request $request): string
    {
        $parts = [];
        if ($request->region_id)   $parts[] = 'Wilayah terpilih';
        if ($request->platform)    $parts[] = 'Platform: ' . implode(', ', (array) $request->platform);
        if ($request->platforms)   $parts[] = 'Platform: ' . implode(', ', (array) $request->platforms);
        if ($request->category_id) $parts[] = 'Kategori terpilih';
        if ($request->categories)  $parts[] = 'Kategori terpilih';
        return $parts ? implode(' · ', $parts) : 'Semua data';
    }
}
