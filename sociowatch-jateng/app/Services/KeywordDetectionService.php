<?php

namespace App\Services;

use App\Models\Keyword;
use Illuminate\Support\Collection;

class KeywordDetectionService
{
    private ?Collection $keywords = null;

    /** Load active keywords once per instance */
    private function keywords(): Collection
    {
        if ($this->keywords === null) {
            $this->keywords = Keyword::where('is_active', true)->get();
        }
        return $this->keywords;
    }

    /**
     * Detect active keywords in the given content using whole-word boundary.
     * Prevents false positives where keywords match as substrings inside other words
     * (e.g. 'aksi' matching 'saksi' or 'demo' matching 'demokrasi').
     *
     * Returns array of [keyword_id => ['occurrence_count' => N]].
     */
    public function detect(string $content): array
    {
        if (trim($content) === '') {
            return [];
        }

        $results = [];

        foreach ($this->keywords() as $keyword) {
            $word = trim($keyword->word);
            if ($word === '') {
                continue;
            }

            $pattern = '/(?<![\p{L}\p{N}_])' . preg_quote($word, '/') . '(?![\p{L}\p{N}_])/iu';
            $count = preg_match_all($pattern, $content);

            if ($count > 0) {
                $results[$keyword->id] = ['occurrence_count' => $count];
            }
        }

        return $results;
    }

    /**
     * Parse boolean search query into LIKE clauses.
     * Supports: phrase, OR, - (exclude)
     * Returns ['must' => [...], 'should' => [...], 'must_not' => [...]]
     */
    public function parseBooleanQuery(string $query): array
    {
        $must     = [];
        $should   = [];
        $mustNot  = [];
        $parts    = preg_split('/\s+OR\s+/i', $query);

        foreach ($parts as $part) {
            $terms = preg_split('/\s+/', trim($part));
            foreach ($terms as $term) {
                $term = trim($term, '"');
                if ($term === '') {
                    continue;
                }
                if (str_starts_with($term, '-')) {
                    $mustNot[] = ltrim($term, '-"');
                } elseif (count($parts) > 1) {
                    $should[] = $term;
                } else {
                    $must[] = $term;
                }
            }
        }

        return compact('must', 'should', 'mustNot');
    }

    /** Flush the keyword cache (call after adding/removing keywords) */
    public function flush(): void
    {
        $this->keywords = null;
    }
}
