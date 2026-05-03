<?php

namespace App\Http\Controllers;

use App\Imports\AccountsImport;
use App\Models\Category;
use App\Models\Region;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function index()
    {
        $categories = Category::orderBy('name')->get();
        $regions    = Region::orderBy('name')->get();
        return view('accounts.import', compact('categories', 'regions'));
    }

    public function downloadTemplate(): StreamedResponse
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template_import_akun.csv"',
        ];

        $columns = ['platform','username','display_name','profile_url','followers_count','following_count','post_count','bio','kategori','wilayah','catatan'];
        $example = ['instagram','contoh_akun','Contoh Akun','https://instagram.com/contoh_akun','10000','500','150','Bio akun contoh','Pemerintahan','Kota Semarang','Akun resmi'];

        return response()->stream(function () use ($columns, $example) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            fputcsv($out, $example);
            fclose($out);
        }, 200, $headers);
    }

    public function preview(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120']);

        $rows = Excel::toCollection(new \stdClass, $request->file('file'))->first();

        if (!$rows || $rows->isEmpty()) {
            return back()->with('error', 'File kosong atau tidak dapat dibaca.');
        }

        $header  = $rows->first()->keys()->map(fn($k) => strtolower(trim($k)))->values();
        $preview = $rows->skip(1)->take(10)->values();

        session(['import_file_path' => $request->file('file')->store('imports', 'local')]);

        return view('accounts.import-preview', compact('header', 'preview', 'rows'));
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120']);

        $import = new AccountsImport();
        Excel::import($import, $request->file('file'));

        $msg = "Import selesai: {$import->imported} akun berhasil diimpor";
        if ($import->skipped) {
            $msg .= ", {$import->skipped} dilewati.";
        }

        return redirect()->route('accounts.index')
            ->with('success', $msg)
            ->with('import_errors', $import->errors);
    }
}
