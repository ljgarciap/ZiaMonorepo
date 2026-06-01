<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiAIService implements AIServiceInterface
{
    protected $apiKey;
    protected $model;

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY');
        $this->model = env('GEMINI_MODEL', 'gemini-1.5-flash');
    }

    public function generateRecommendations(string $companyName, array $emissionsData, array $telemetryData): string
    {
        if (empty($this->apiKey)) {
            throw new \Exception("Gemini API Key is not configured.");
        }

        $prompt = $this->buildPrompt($companyName, $emissionsData, $telemetryData);

        Log::info("Sending recommendation request to Gemini AI (Google AI Studio)...");

        $response = Http::timeout(10)->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => "Eres un consultor experto en sostenibilidad y eficiencia energética para el edificio ECONOVA de la Cámara de Comercio de Bogotá. Brindas recomendaciones directas, basadas en datos reales, en formato Markdown en español.\n\n" . $prompt
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7
            ]
        ]);

        if ($response->successful()) {
            return $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? 'No se recibieron sugerencias.';
        }

        Log::error("Gemini API call failed with status " . $response->status() . ": " . $response->body());
        throw new \Exception("Gemini API failed: " . $response->body());
    }

    protected function buildPrompt(string $companyName, array $emissions, array $telemetry): string
    {
        $emissionsStr = json_encode($emissions, JSON_PRETTY_PRINT);
        $telemetryStr = json_encode($telemetry, JSON_PRETTY_PRINT);

        return <<<EOT
Analiza el estado de emisiones y lecturas de telemetría IoT del aliado **{$companyName}** en el edificio ECONOVA y genera un reporte breve en Markdown con 3 sugerencias accionables de ahorro y optimización:

### Datos de Huella de Carbono del Aliado:
{$emissionsStr}

### Lecturas de Telemetría IoT Recientes (Agua / Energía):
{$telemetryStr}

Por favor, sé muy directo, profesional y estructurado en español.
EOT;
    }
}
