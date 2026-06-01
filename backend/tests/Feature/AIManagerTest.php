<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\AI\AIManager;
use App\Services\AI\MistralAIService;
use App\Services\AI\GeminiAIService;
use Mockery;

class AIManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that AIManager uses primary provider (Mistral) when it succeeds.
     */
    public function test_uses_primary_provider_on_success()
    {
        $mistralMock = Mockery::mock(MistralAIService::class);
        $geminiMock = Mockery::mock(GeminiAIService::class);

        $mistralMock->shouldReceive('generateRecommendations')
            ->once()
            ->with('ECONOVA Ally', [], [])
            ->andReturn('Mistral suggestions output');

        $geminiMock->shouldReceive('generateRecommendations')
            ->never();

        $manager = new AIManager($mistralMock, $geminiMock);
        $result = $manager->generateRecommendations('ECONOVA Ally', [], []);

        $this->assertEquals('Mistral suggestions output', $result);
    }

    /**
     * Test that AIManager falls back to Gemini when primary (Mistral) fails.
     */
    public function test_falls_back_to_gemini_on_primary_failure()
    {
        $mistralMock = Mockery::mock(MistralAIService::class);
        $geminiMock = Mockery::mock(GeminiAIService::class);

        $mistralMock->shouldReceive('generateRecommendations')
            ->once()
            ->with('ECONOVA Ally', [], [])
            ->andThrow(new \Exception('API Balance empty'));

        $geminiMock->shouldReceive('generateRecommendations')
            ->once()
            ->with('ECONOVA Ally', [], [])
            ->andReturn('Gemini backup output');

        $manager = new AIManager($mistralMock, $geminiMock);
        $result = $manager->generateRecommendations('ECONOVA Ally', [], []);

        $this->assertEquals('Gemini backup output', $result);
    }

    /**
     * Test that AIManager returns local offline recommendations when both LLM providers fail.
     */
    public function test_returns_local_fallback_on_total_failure()
    {
        $mistralMock = Mockery::mock(MistralAIService::class);
        $geminiMock = Mockery::mock(GeminiAIService::class);

        $mistralMock->shouldReceive('generateRecommendations')
            ->once()
            ->with('ECONOVA Ally', [], [])
            ->andThrow(new \Exception('Network Timeout'));

        $geminiMock->shouldReceive('generateRecommendations')
            ->once()
            ->with('ECONOVA Ally', [], [])
            ->andThrow(new \Exception('Quota Exceeded'));

        $manager = new AIManager($mistralMock, $geminiMock);
        $result = $manager->generateRecommendations('ECONOVA Ally', [], []);

        $this->assertStringContainsString('Agente de IA Zia (Conexión Offline)', $result);
        $this->assertStringContainsString('Revisión de Huella', $result);
    }
}
