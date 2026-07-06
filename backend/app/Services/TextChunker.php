<?php

namespace App\Services;

class TextChunker
{
    /**
     * Split text into overlapping chunks, breaking on whitespace boundaries
     * where possible so words aren't cut mid-string.
     *
     * @return string[] Ordered list of chunk contents (empty text → empty array)
     */
    public function chunk(string $text, int $chunkSize = 800, int $overlap = 100): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));

        if ($text === '') {
            return [];
        }

        if (mb_strlen($text) <= $chunkSize) {
            return [$text];
        }

        $chunks = [];
        $start = 0;
        $length = mb_strlen($text);

        while ($start < $length) {
            $end = min($start + $chunkSize, $length);

            if ($end < $length) {
                $lastSpace = mb_strrpos(mb_substr($text, $start, $end - $start), ' ');
                if ($lastSpace !== false) {
                    $end = $start + $lastSpace;
                }
            }

            $chunks[] = trim(mb_substr($text, $start, $end - $start));

            if ($end >= $length) {
                break;
            }

            // Retroceder por el overlap y luego avanzar hasta el siguiente
            // límite de palabra — sin este ajuste, el overlap puede dejar el
            // próximo chunk empezando a mitad de palabra.
            $nextStart = max($end - $overlap, 0);
            if ($nextStart > 0 && mb_substr($text, $nextStart - 1, 1) !== ' ') {
                $spacePos = mb_strpos($text, ' ', $nextStart);
                $nextStart = $spacePos !== false ? $spacePos + 1 : $length;
            }

            $start = max($nextStart, $start + 1);
        }

        return array_values(array_filter($chunks, fn ($c) => $c !== ''));
    }
}
