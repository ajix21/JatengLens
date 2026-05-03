<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ApiTokenController extends Controller
{
    public function index()
    {
        $tokens = auth()->user()->tokens()->latest()->get();
        return view('settings.api-tokens', compact('tokens'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $token = $request->user()->createToken($request->name);

        return back()->with('new_token', $token->plainTextToken)
                     ->with('success', 'Token API berhasil dibuat. Simpan token ini — tidak akan ditampilkan lagi.');
    }

    public function destroy(int $id)
    {
        auth()->user()->tokens()->where('id', $id)->delete();
        return back()->with('success', 'Token API berhasil dihapus.');
    }
}
