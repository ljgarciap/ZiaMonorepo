<?php

namespace Tests\Unit;

use App\Services\TextChunker;
use Tests\TestCase;

class TextChunkerTest extends TestCase
{
    private TextChunker $chunker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chunker = new TextChunker();
    }

    public function test_empty_text_returns_no_chunks()
    {
        $this->assertEquals([], $this->chunker->chunk(''));
        $this->assertEquals([], $this->chunker->chunk('   '));
    }

    public function test_text_shorter_than_chunk_size_returns_a_single_chunk()
    {
        $chunks = $this->chunker->chunk('Consumo de diésel en la flota vehicular.', 800, 100);

        $this->assertCount(1, $chunks);
        $this->assertEquals('Consumo de diésel en la flota vehicular.', $chunks[0]);
    }

    public function test_long_text_is_split_into_multiple_chunks()
    {
        $text = str_repeat('palabra ', 500); // ~4000 chars

        $chunks = $this->chunker->chunk($text, 800, 100);

        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(800, mb_strlen($chunk));
        }
    }

    public function test_chunks_do_not_cut_words_in_the_middle()
    {
        $text = str_repeat('palabra ', 500);

        $chunks = $this->chunker->chunk($text, 800, 100);

        foreach ($chunks as $chunk) {
            $this->assertStringStartsNotWith(' ', $chunk);
            // Cada chunk debe estar compuesto de palabras completas de "palabra"
            foreach (explode(' ', $chunk) as $word) {
                if ($word !== '') {
                    $this->assertEquals('palabra', $word);
                }
            }
        }
    }

    public function test_collapses_whitespace()
    {
        $chunks = $this->chunker->chunk("línea uno\n\n\tlínea   dos", 800, 100);

        $this->assertCount(1, $chunks);
        $this->assertEquals('línea uno línea dos', $chunks[0]);
    }
}
