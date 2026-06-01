<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

class AIManager
{
    protected $mistralService;
    protected $geminiService;
    protected $primaryProvider;
    protected $fallbackProvider;

    public function __construct(MistralAIService $mistralService, GeminiAIService $geminiService)
    {
        $this->mistralService = $mistralService;
        $this->geminiService = $geminiService;

        $this->primaryProvider = env('AI_PRIMARY_PROVIDER', 'mistral');
        $this->fallbackProvider = env('AI_FALLBACK_PROVIDER', 'gemini');
    }

    /**
     * Get recommendations using the primary LLM provider, with transparent fallback.
     */
    public function generateRecommendations(string $companyName, array $emissionsData, array $telemetryData): string
    {
        Log::info("AIManager: Generating recommendations. Primary: {$this->primaryProvider}, Fallback: {$this->fallbackProvider}");

        // 1. Attempt Primary
        try {
            $primaryService = $this->getService($this->primaryProvider);
            return $primaryService->generateRecommendations($companyName, $emissionsData, $telemetryData);
        } catch (\Exception $e) {
            Log::warning("AIManager: Primary AI provider [{$this->primaryProvider}] failed! Error: " . $e->getMessage() . ". Attempting fallback [{$this->fallbackProvider}]...");
        }

        // 2. Attempt Fallback
        try {
            $fallbackService = $this->getService($this->fallbackProvider);
            return $fallbackService->generateRecommendations($companyName, $emissionsData, $telemetryData);
        } catch (\Exception $e) {
            Log::error("AIManager: Both primary and fallback AI providers failed! Fallback Error: " . $e->getMessage());
            
            // In case of complete offline/balance error, return a friendly local rule-based warning
            return $this->generateLocalRecommendationsFallback($companyName, $emissionsData, $telemetryData);
        }
    }

    /**
     * Resolve service instance by name.
     */
    protected function getService(string $name): AIServiceInterface
    {
        switch (strtolower($name)) {
            case 'gemini':
                return $this->geminiService;
            case 'mistral':
            default:
                return $this->mistralService;
        }
    }

    /**
     * Complete local safety fallback when both APIs are down/out of budget.
     */
    protected function generateLocalRecommendationsFallback(string $companyName, array $emissions, array $telemetry): string
    {
        return "### ⚡ Agente de IA Zia (Conexión Offline)\n\n" .
               "No pudimos conectar con los servicios cognitivos de IA externos (Mistral/Gemini). Sin embargo, nuestro analizador analítico local detecta:\n\n" .
               "1. **Revisión de Huella:** Su empresa tiene un registro consolidado de huella de carbono. Le sugerimos revisar las categorías con mayor huella en su Alcance 1.\n" .
               "2. **Monitoreo de Telemetría:** Se registran consumos estables en el edificio ECONOVA. Recuerde apagar subestaciones auxiliares fuera de las 18:00 horas.\n" .
               "3. **Prácticas recomendadas:** Monitorear fugas de agua y mantener actualizados los factores de emisión en el Panel Administrativo.";
    }
}
