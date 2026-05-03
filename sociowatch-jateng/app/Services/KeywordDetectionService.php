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
     * Detect active keywords in the given content.
     * Returns array of [keyword_id => ['occurrence_count' => N]].
     */
    public function detect(string $content): array
    {
        $lower   = mb_strtolower($content);
        $results = [];

        foreach ($this->keywords() as $keyword) {
            $word  = mb_strtolower($keyword->word);
            $count = substr_count($lower, $word);
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
