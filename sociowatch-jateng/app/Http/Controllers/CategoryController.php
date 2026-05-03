<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsActivity;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use LogsActivity;
    public function index()
    {
        $categories = Category::withCount('socialAccounts')->orderBy('name')->get();
        return view('categories.index', compact('categories'));
    }

    public function create()
    {
        return view('categories.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255|unique:categories',
            'color'       => 'required|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'description' => 'nullable|string',
        ]);
        $cat = Category::create($data);
        $this->logActivity('create', 'Category', $cat->id);
        return redirect()->route('categories.index')
            ->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function edit(Category $category)
    {
        return view('categories.edit', compact('category'));
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255|unique:categories,name,' . $category->id,
            'color'       => 'required|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'description' => 'nullable|string',
        ]);
        $category->update($data);
        $this->logActivity('update', 'Category', $category->id);
        return redirect()->route('categories.index')
            ->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category)
    {
        if ($category->socialAccounts()->exists()) {
            return back()->with('error', 'Kategori tidak dapat dihapus karena masih memiliki akun terdaftar.');
        }
        $id = $category->id;
        $category->delete();
        $this->logActivity('delete', 'Category', $id);
        return redirect()->route('categories.index')
            ->with('success', 'Kategori berhasil dihapus.');
    }
}
