<?php

namespace App\Services\Knowledge;

class TextChunker
{
    public function __construct(
        private readonly int $size = 1500,
        private readonly int $overlap = 200,
    ) {}

    /**
     * @return list<string>
     */
    public function chunk(string $text): array
    {
        $text = $this->normalize($text);
        if ($text === '') {
            return [];
        }

        $chunks = [];
        $current = '';

        foreach (preg_split("/\n{2,}/", $text) as $paragraph) {
            foreach ($this->splitLong($paragraph) as $piece) {
                if ($current !== '' && mb_strlen($current) + mb_strlen($piece) + 2 > $this->size) {
                    $chunks[] = $current;
                    $current = $this->tail($current);
                }
                $current = $current === '' ? $piece : $current."\n\n".$piece;
            }
        }

        if (trim($current) !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text) ?? $text;
        $text = preg_replace("/ *\n */", "\n", $text) ?? $text;

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }

    /**
     * @return list<string>
     */
    private function splitLong(string $paragraph): array
    {
        $limit = $this->size - $this->overlap;
        if (mb_strlen($paragraph) <= $limit) {
            return [$paragraph];
        }

        $pieces = [];
        $buffer = '';
        foreach (preg_split('/(?<=[.!?])\s+/u', $paragraph) as $sentence) {
            while (mb_strlen($sentence) > $limit) {
                if ($buffer !== '') {
                    $pieces[] = $buffer;
                    $buffer = '';
                }
                $pieces[] = mb_substr($sentence, 0, $limit);
                $sentence = mb_substr($sentence, $limit);
            }
            if ($buffer !== '' && mb_strlen($buffer) + mb_strlen($sentence) + 1 > $limit) {
                $pieces[] = $buffer;
                $buffer = '';
            }
            $buffer = $buffer === '' ? $sentence : $buffer.' '.$sentence;
        }
        if ($buffer !== '') {
            $pieces[] = $buffer;
        }

        return $pieces;
    }

    private function tail(string $chunk): string
    {
        if ($this->overlap <= 0) {
            return '';
        }

        $tail = mb_substr($chunk, -$this->overlap);
        $space = mb_strpos($tail, ' ');

        return $space === false ? $tail : mb_substr($tail, $space + 1);
    }
}
