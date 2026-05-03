<?php

namespace App\Http\Controllers;

use App\Imports\PostsImport;
use App\Models\Category;
use App\Models\Keyword;
use App\Models\Post;
use App\Models\PostTag;
use App\Models\Region;
use App\Models\SocialAccount;
use App\Services\KeywordDetectionService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $query = Post::with(['socialAccount.category', 'socialAccount.region', 'tags', 'keywords'])
            ->orderByDesc('posted_at');

        if ($request->filled('account_id')) {
            $query->where('social_account_id', $request->account_id);
        }
        if ($request->filled('platform')) {
            $query->where('platform', $request->platform);
        }
        if ($request->filled('category_id')) {
            $query->whereHas('socialAccount', fn($q) => $q->where('category_id', $request->category_id));
        }
        if ($request->filled('region_id')) {
            $query->whereHas('socialAccount', fn($q) => $q->where('region_id', $request->region_id));
        }
        if ($request->filled('media_type')) {
            $query->where('media_type', $request->media_type);
        }
        if ($request->filled('date_from')) {
            $query->where('posted_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('posted_at', '<=', $request->date_to . ' 23:59:59');
        }
        if ($request->filled('flagged')) {
            $query->where('is_flagged', true);
        }
        if ($request->filled('tag_id')) {
            $query->whereHas('tags', fn($q) => $q->where('post_tags.id', $request->tag_id));
        }
        if ($request->filled('q')) {
            $term = $request->q;
            $query->where('content', 'LIKE', "%{$term}%");
        }
        if ($request->filled('sort')) {
            $query->reorder();
            match($request->sort) {
                'likes'    => $query->orderByDesc('likes_count'),
                'comments' => $query->orderByDesc('comments_count'),
                default    => $query->orderByDesc('posted_at'),
            };
        }

        $posts      = $query->paginate(20)->withQueryString();
        $accounts   = SocialAccount::orderBy('display_name')->get(['id', 'display_name', 'platform', 'username']);
        $categories = Category::orderBy('name')->get();
        $regions    = Region::orderBy('name')->get();
        $allTags    = PostTag::orderBy('name')->get();

        return view('posts.index', compact('posts', 'accounts', 'categories', 'regions', 'allTags'));
    }

    public function create()
    {
        $accounts = SocialAccount::where('is_active', true)->with(['category', 'region'])->orderBy('display_name')->get();
        $allTags  = PostTag::orderBy('name')->get();
        return view('posts.create', compact('accounts', 'allTags'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'social_account_id' => 'required|exists:social_accounts,id',
            'post_url'          => 'nullable|url|max:500',
            'content'           => 'required|string',
            'media_type'        => 'nullable|in:text,image,video,reel,story',
            'likes_count'       => 'nullable|integer|min:0',
            'comments_count'    => 'nullable|integer|min:0',
            'shares_count'      => 'nullable|integer|min:0',
            'views_count'       => 'nullable|integer|min:0',
            'posted_at'         => 'required|date',
            'is_flagged'        => 'boolean',
            'flag_reason'       => 'nullable|string|max:500',
            'tags'              => 'nullable|array',
            'tags.*'            => 'string|max:50',
        ]);

        $account = SocialAccount::findOrFail($data['social_account_id']);
        $data['platform']  = $account->platform;
        $data['is_flagged'] = $request->boolean('is_flagged');

        $post = Post::create($data);

        // Tags
        $tagIds = [];
        foreach ($request->input('tags', []) as $tagName) {
            $tag = PostTag::firstOrCreate(
                ['name' => $tagName],
                ['color' => '#' . substr(md5($tagName), 0, 6)]
            );
            $tagIds[] = $tag->id;
        }
        $post->tags()->sync($tagIds);

        // Keyword detection
        $detector = new KeywordDetectionService();
        $detected = $detector->detect($post->content);
        $post->keywords()->sync($detected);

        return redirect()->route('posts.show', $post)->with('success', 'Postingan berhasil ditambahkan.');
    }

    public function show(Post $post)
    {
        $post->load(['socialAccount.category', 'socialAccount.region', 'tags', 'keywords']);
        return view('posts.show', compact('post'));
    }

    public function edit(Post $post)
    {
        $post->load(['tags', 'keywords']);
        $accounts = SocialAccount::where('is_active', true)->with('category')->orderBy('display_name')->get();
        $allTags  = PostTag::orderBy('name')->get();
        return view('posts.edit', compact('post', 'accounts', 'allTags'));
    }

    public function update(Request $request, Post $post)
    {
        $data = $request->validate([
            'social_account_id' => 'required|exists:social_accounts,id',
            'post_url'          => 'nullable|url|max:500',
            'content'           => 'required|string',
            'media_type'        => 'nullable|in:text,image,video,reel,story',
            'likes_count'       => 'nullable|integer|min:0',
            'comments_count'    => 'nullable|integer|min:0',
            'shares_count'      => 'nullable|integer|min:0',
            'views_count'       => 'nullable|integer|min:0',
            'posted_at'         => 'required|date',
            'is_flagged'        => 'boolean',
            'flag_reason'       => 'nullable|string|max:500',
            'tags'              => 'nullable|array',
            'tags.*'            => 'string|max:50',
        ]);

        $account = SocialAccount::findOrFail($data['social_account_id']);
        $data['platform']   = $account->platform;
        $data['is_flagged'] = $request->boolean('is_flagged');

        $post->update($data);

        $tagIds = [];
        foreach ($request->input('tags', []) as $tagName) {
            $tag = PostTag::firstOrCreate(
                ['name' => $tagName],
                ['color' => '#' . substr(md5($tagName), 0, 6)]
            );
            $tagIds[] = $tag->id;
        }
        $post->tags()->sync($tagIds);

        $detector = new KeywordDetectionService();
        $detected = $detector->detect($post->content);
        $post->keywords()->sync($detected);

        return redirect()->route('posts.show', $post)->with('success', 'Postingan berhasil diperbarui.');
    }

    public function destroy(Post $post)
    {
        $post->delete();
        return redirect()->route('posts.index')->with('success', 'Postingan berhasil dihapus.');
    }

    public function toggleFlag(Request $request, Post $post)
    {
        $post->update([
            'is_flagged'  => !$post->is_flagged,
            'flag_reason' => $post->is_flagged ? null : $request->input('flag_reason', 'Ditandai manual'),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['is_flagged' => $post->is_flagged]);
        }
        return back()->with('success', $post->is_flagged ? 'Postingan berhasil diflag.' : 'Flag berhasil dihapus.');
    }

    public function detectKeywords(Request $request)
    {
        $request->validate(['content' => 'required|string']);
        $detector = new KeywordDetectionService();
        $detected = $detector->detect($request->content);
        $keywords = Keyword::whereIn('id', array_keys($detected))
            ->get()
            ->map(fn($k) => [
                'id'         => $k->id,
                'word'       => $k->word,
                'category'   => $k->category,
                'color'      => $k->color,
                'occurrence' => $detected[$k->id]['occurrence_count'],
            ]);
        return response()->json(['keywords' => $keywords]);
    }

    public function flagged(Request $request)
    {
        $query = Post::with(['socialAccount.category', 'socialAccount.region', 'tags', 'keywords'])
            ->where('is_flagged', true)
            ->orderByDesc('posted_at');

        if ($request->filled('category_id')) {
            $query->whereHas('socialAccount', fn($q) => $q->where('category_id', $request->category_id));
        }
        if ($request->filled('region_id')) {
            $query->whereHas('socialAccount', fn($q) => $q->where('region_id', $request->region_id));
        }
        if ($request->filled('date_from')) {
            $query->where('posted_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('posted_at', '<=', $request->date_to . ' 23:59:59');
        }

        $posts      = $query->paginate(20)->withQueryString();
        $categories = Category::orderBy('name')->get();
        $regions    = Region::orderBy('name')->get();

        return view('posts.flagged', compact('posts', 'categories', 'regions'));
    }

    public function flaggedPdf(Request $request)
    {
        $query = Post::with(['socialAccount.category', 'socialAccount.region', 'keywords'])
            ->where('is_flagged', true)
            ->orderByDesc('posted_at');

        if ($request->filled('category_id')) {
            $query->whereHas('socialAccount', fn($q) => $q->where('category_id', $request->category_id));
        }
        if ($request->filled('region_id')) {
            $query->whereHas('socialAccount', fn($q) => $q->where('region_id', $request->region_id));
        }

        $posts = $query->get();
        $pdf   = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.flagged-posts-pdf', compact('posts'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('laporan-postingan-terpantau-' . now()->format('Y-m-d') . '.pdf');
    }

    public function importIndex()
    {
        return view('posts.import');
    }

    public function downloadImportTemplate(): StreamedResponse
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template_import_postingan.csv"',
        ];
        $columns = ['platform', 'username', 'post_url', 'content', 'media_type',
                    'likes_count', 'comments_count', 'shares_count', 'views_count', 'posted_at'];
        $example = ['instagram', 'bem_undip_official', 'https://instagram.com/p/abc123',
                    'Contoh isi postingan tentang kebijakan upah minimum.', 'image',
                    '500', '30', '10', '2500', now()->format('Y-m-d H:i:s')];

        return response()->stream(function () use ($columns, $example) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            fputcsv($out, $example);
            fclose($out);
        }, 200, $headers);
    }

    public function importProcess(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240']);

        $import = new PostsImport();
        Excel::import($import, $request->file('file'));

        $msg = "Import selesai: {$import->imported} postingan berhasil diimpor";
        if ($import->skipped) {
            $msg .= ", {$import->skipped} dilewati.";
        }

        return redirect()->route('posts.index')
            ->with('success', $msg)
            ->with('import_errors', $import->errors);
    }

    public function search(Request $request)
    {
        $posts     = collect();
        $highlight = '';

        if ($request->filled('q')) {
            $raw      = $request->q;
            $highlight = $raw;
            $detector = new \App\Services\KeywordDetectionService();
            $parsed   = $detector->parseBooleanQuery($raw);

            $query = Post::with(['socialAccount.category', 'socialAccount.region', 'tags', 'keywords'])
                ->orderByDesc('posted_at');

            foreach ($parsed['must'] as $term) {
                $query->where('content', 'LIKE', "%{$term}%");
            }
            if (!empty($parsed['should'])) {
                $query->where(function ($q) use ($parsed) {
                    foreach ($parsed['should'] as $term) {
                        $q->orWhere('content', 'LIKE', "%{$term}%");
                    }
                });
            }
            foreach ($parsed['mustNot'] as $term) {
                $query->where('content', 'NOT LIKE', "%{$term}%");
            }

            // Platform filter
            if ($request->filled('platform')) {
                $query->where('platform', $request->platform);
            }
            if ($request->boolean('flagged_only')) {
                $query->where('is_flagged', true);
            }
            if ($request->filled('days')) {
                $query->where('posted_at', '>=', now()->subDays((int) $request->days));
            }
            if ($request->boolean('sensitive_only')) {
                $query->whereHas('keywords', fn($q) => $q->where('category', 'sensitif'));
            }

            // Sort
            match($request->get('sort', 'latest')) {
                'engagement' => $query->orderByRaw('(likes_count + comments_count + shares_count) DESC'),
                'latest'     => $query->reorder()->orderByDesc('posted_at'),
                default      => null,
            };

            $posts = $query->paginate(15)->withQueryString();
        }

        return view('posts.search', compact('posts', 'highlight'));
    }
}
