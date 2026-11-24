<?php

namespace App\Services;

use App\Models\Keyword;
use App\Models\Post;
use Illuminate\Support\Collection;

class SentimentAnalysisService
{
    private const NEGATIONS = [
        'tidak', 'tak', 'bukan', 'belum', 'jangan', 'tanpa',
        'bebas', 'ora', 'mboten', 'enggak', 'gak', 'tiada',
    ];

    private const SENSITIF_LEXICON = [
        'ricuh', 'bentrok', 'unjuk rasa', 'demo', 'kerusuhan', 'mogok',
        'boikot', 'darurat', 'korupsi', 'suap', 'pungli', 'intoleransi',
        'radikal', 'teror', 'hoax', 'fitnah', 'krisis',
    ];

    private const NEGATIF_LEXICON = [
        'keluhan', 'mengeluhkan', 'protes', 'macet', 'rusak', 'banjir',
        'longsor', 'mahal', 'langka', 'phk', 'sampah', 'polusi',
        'lambat', 'kecewa', 'meresahkan', 'ancaman', 'gagal',
    ];

    private const POSITIF_LEXICON = [
        'apresiasi', 'kondusif', 'aman', 'tertib', 'prestasi', 'penghargaan',
        'bantuan', 'solusi', 'sukses', 'lancar', 'inovasi', 'sinergi',
        'sejahtera', 'tuntas', 'terkendali',
    ];

    private ?Collection $keywords = null;

    /** Load active keywords once per instance */
    private function activeKeywords(): Collection
    {
        if ($this->keywords === null) {
            $this->keywords = Keyword::where('is_active', true)->get();
        }

        return $this->keywords;
    }

    /**
     * Classify content sentiment as 'sensitif', 'negatif', 'positif', or 'netral'.
     */
    public function analyze(string $content, ?iterable $matchedKeywords = null): string
    {
        $lower = mb_strtolower($content);
        if (trim($lower) === '') {
            return 'netral';
        }

        $scores = [
            'sensitif' => 0.0,
            'negatif'  => 0.0,
            'positif'  => 0.0,
            'netral'   => 0.0,
        ];

        $keywords = $matchedKeywords ?? $this->activeKeywords();
        $matchedWords = [];

        foreach ($keywords as $kw) {
            $word = mb_strtolower((string) $kw->word);
            if ($word === '') {
                continue;
            }

            $pattern = '/(?<![\p{L}\p{N}_])' . preg_quote($word, '/') . '(?![\p{L}\p{N}_])/iu';
            $total   = preg_match_all($pattern, $lower);
            if ($total === 0) {
                continue;
            }

            $matchedWords[$word] = true;
            $negated = $this->countNegatedOccurrences($lower, $word);
            $nonNegated = max(0, $total - $negated);

            if ($nonNegated > 0) {
                match ($kw->category) {
                    'sensitif' => $scores['sensitif'] += 4.0 * $nonNegated,
                    'negatif'  => $scores['negatif'] += 2.0 * $nonNegated,
                    'positif'  => $scores['positif'] += 2.0 * $nonNegated,
                    default    => $scores['netral'] += 1.0 * $nonNegated,
                };
            }

            if ($negated > 0) {
                if ($kw->category === 'positif') {
                    $scores['negatif'] += 2.5 * $negated;
                } elseif ($kw->category === 'sensitif' || $kw->category === 'negatif') {
                    $scores['positif'] += 1.0 * $negated;
                }
            }
        }

        $lexiconGroups = [
            'sensitif' => [self::SENSITIF_LEXICON, 3.0],
            'negatif'  => [self::NEGATIF_LEXICON, 1.5],
            'positif'  => [self::POSITIF_LEXICON, 1.5],
        ];

        foreach ($lexiconGroups as $category => [$terms, $weight]) {
            foreach ($terms as $term) {
                if (isset($matchedWords[$term])) {
                    continue;
                }

                $pattern = '/(?<![\p{L}\p{N}_])' . preg_quote($term, '/') . '(?![\p{L}\p{N}_])/iu';
                $total   = preg_match_all($pattern, $lower);
                if ($total === 0) {
                    continue;
                }

                $negated = $this->countNegatedOccurrences($lower, $term);
                $nonNegated = max(0, $total - $negated);

                if ($nonNegated > 0) {
                    $scores[$category] += $weight * $nonNegated;
                }

                if ($negated > 0) {
                    if ($category === 'positif') {
                        $scores['negatif'] += 2.0 * $negated;
                    } else {
                        $scores['positif'] += 0.5 * $negated;
                    }
                }
            }
        }

        if ($scores['sensitif'] >= 3.0) {
            return 'sensitif';
        }

        if ($scores['negatif'] > $scores['positif'] && $scores['negatif'] >= 1.5) {
            return 'negatif';
        }

        if ($scores['positif'] > $scores['negatif'] && $scores['positif'] >= 1.5) {
            return 'positif';
        }

        return 'netral';
    }

    /**
     * Analyze and assign sentiment to a Post, respecting manual overrides by default.
     */
    public function assignToPost(Post $post, bool $respectManual = true): string
    {
        if ($respectManual && $post->sentiment_manual) {
            return $post->sentiment ?: 'netral';
        }

        $sentiment = $this->analyze((string) $post->content);

        if ($post->exists) {
            $post->update(['sentiment' => $sentiment]);
        } else {
            $post->sentiment = $sentiment;
        }

        return $sentiment;
    }

    /** Clear cached active keywords */
    public function flush(): void
    {
        $this->keywords = null;
    }

    private function isNegated(string $lowerText, string $term): bool
    {
        return $this->countNegatedOccurrences($lowerText, $term) > 0;
    }

    private function countNegatedOccurrences(string $lowerText, string $term): int
    {
        $pattern = '/(?<![\p{L}\p{N}_])(?:' . implode('|', self::NEGATIONS) . ')\s+(?:[\p{L}\p{N}_]+\s+){0,2}' . preg_quote($term, '/') . '(?![\p{L}\p{N}_])/iu';
        $matches = preg_match_all($pattern, $lowerText);

        return $matches === false ? 0 : $matches;
    }
}
