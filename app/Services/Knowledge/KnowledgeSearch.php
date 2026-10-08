<?php

namespace App\Services\Knowledge;

use App\Models\KnowledgeChunk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class KnowledgeSearch
{
    private const STOPWORDS = [
        'apa', 'ada', 'yang', 'dan', 'atau', 'untuk', 'dengan', 'dalam', 'ini', 'itu', 'saya', 'kami', 'kita',
        'boleh', 'macam', 'mana', 'bila', 'siapa', 'kenapa', 'bagaimana', 'tak', 'tidak', 'nak', 'hendak',
        'the', 'and', 'for', 'with', 'what', 'when', 'where', 'who', 'why', 'how', 'can', 'are', 'was',
        'this', 'that', 'from', 'about', 'please', 'tolong', 'pada', 'dari', 'kepada', 'juga', 'sahaja',
    ];

    /**
     * @return list<string>
     */
    public function keywords(string $query): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', Str::lower($query), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_filter(
            $words,
            fn (string $w) => mb_strlen($w) >= 3 && ! in_array($w, self::STOPWORDS, true),
        )));
    }

    /**
     * @return Collection<int, KnowledgeChunk>
     */
    public function search(string $query, int $limit = 8): Collection
    {
        $keywords = $this->keywords($query);
        if ($keywords === []) {
            return collect();
        }

        $base = KnowledgeChunk::query()
            ->with('document.source')
            ->whereHas('document.source', fn (Builder $q) => $q->where('is_active', true));

        if (in_array($base->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $terms = implode(' ', $keywords);

            return $base
                ->whereFullText('content', $terms)
                ->orderByRaw('MATCH(content) AGAINST (?) DESC', [$terms])
                ->limit($limit)
                ->get();
        }

        return $base
            ->where(function (Builder $q) use ($keywords) {
                foreach ($keywords as $word) {
                    $q->orWhere('content', 'like', '%'.$word.'%');
                }
            })
            ->limit(500)
            ->get()
            ->map(function (KnowledgeChunk $chunk) use ($keywords) {
                $haystack = Str::lower($chunk->content.' '.$chunk->document->title);
                $chunk->setAttribute('score', collect($keywords)->sum(fn ($w) => substr_count($haystack, $w) > 0 ? 1 + min(substr_count($haystack, $w), 5) / 10 : 0));

                return $chunk;
            })
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }
}
