<?php

namespace App\Http\Controllers;

use App\Models\Keyword;
use App\Models\Post;
use App\Services\KeywordDetectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KeywordController extends Controller
{
    public function manage()
    {
        $keywords = Keyword::withCount('posts')
            ->orderBy('category')
            ->orderBy('word')
            ->get();

        return view('keywords.manage', compact('keywords'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'word'      => 'required|string|max:100|unique:keywords,word',
            'category'  => 'required|in:sensitif,negatif,netral,positif',
            'color'     => 'required|string|max:7',
            'is_active' => 'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        Keyword::create($data);

        return back()->with('success', "Keyword '{$data['word']}' berhasil ditambahkan.");
    }

    public function update(Request $request, Keyword $keyword)
    {
        $data = $request->validate([
            'word'      => 'required|string|max:100|unique:keywords,word,' . $keyword->id,
            'category'  => 'required|in:sensitif,negatif,netral,positif',
            'color'     => 'required|string|max:7',
            'is_active' => 'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $keyword->update($data);

        return back()->with('success', "Keyword '{$keyword->word}' berhasil diperbarui.");
    }

    public function destroy(Keyword $keyword)
    {
        $word = $keyword->word;
        $keyword->delete();
        return back()->with('success', "Keyword '{$word}' berhasil dihapus.");
    }

    public function toggle(Keyword $keyword)
    {
        $keyword->update(['is_active' => !$keyword->is_active]);
        return back()->with('success', "Keyword '{$keyword->word}' " . ($keyword->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    public function show(Keyword $keyword)
    {
        $keyword->loadCount('posts');

        $trend = DB::table('post_keyword_pivot')
            ->join('posts', 'posts.id', '=', 'post_keyword_pivot.post_id')
            ->where('post_keyword_pivot.keyword_id', $keyword->id)
            ->where('posts.posted_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(posts.posted_at) as date, SUM(post_keyword_pivot.occurrence_count) as total')
            ->groupBy(DB::raw('DATE(posts.posted_at)'))
            ->orderBy('date')
            ->get();

        $topAccounts = DB::table('post_keyword_pivot')
            ->join('posts', 'posts.id', '=', 'post_keyword_pivot.post_id')
            ->join('social_accounts', 'social_accounts.id', '=', 'posts.social_account_id')
            ->where('post_keyword_pivot.keyword_id', $keyword->id)
            ->selectRaw('social_accounts.id, social_accounts.display_name, social_accounts.platform,
                          SUM(post_keyword_pivot.occurrence_count) as total_occurrences, COUNT(posts.id) as post_count')
            ->groupBy('social_accounts.id', 'social_accounts.display_name', 'social_accounts.platform')
            ->orderByDesc('total_occurrences')
            ->take(10)
            ->get();

        $posts = $keyword->posts()
            ->with(['socialAccount.region', 'tags'])
            ->orderByDesc('posted_at')
            ->paginate(15);

        return view('keywords.show', compact('keyword', 'trend', 'topAccounts', 'posts'));
    }

    public function batchImport(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);
        $file    = $request->file('file');
        $rows    = array_map('str_getcsv', file($file->getPathname()));
        $header  = array_map('strtolower', array_shift($rows));
        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($rows as $i => $row) {
            $row = array_combine($header, $row);
            if (empty($row['word'])) { $skipped++; continue; }
            $category = $row['category'] ?? 'netral';
            if (!in_array($category, ['sensitif', 'negatif', 'netral', 'positif'])) {
                $errors[] = "Baris " . ($i + 2) . ": kategori '{$category}' tidak valid.";
                $skipped++;
                continue;
            }
            Keyword::firstOrCreate(
                ['word' => trim($row['word'])],
                [
                    'category'  => $category,
                    'color'     => $row['color'] ?? Keyword::categoryColor($category),
                    'is_active' => true,
                ]
            );
            $imported++;
        }

        $msg = "Import selesai: {$imported} keyword ditambahkan";
        if ($skipped) $msg .= ", {$skipped} dilewati.";

        return back()->with('success', $msg)->with('import_errors', $errors);
    }

    public function rescan()
    {
        $detector = new KeywordDetectionService();
        $count    = 0;

        Post::chunk(100, function ($posts) use ($detector, &$count) {
            foreach ($posts as $post) {
                $detected = $detector->detect($post->content);
                $post->keywords()->sync($detected);
                $count++;
            }
            $detector->flush();
        });

        return back()->with('success', "Scan ulang selesai: {$count} postingan diproses.");
    }
}
