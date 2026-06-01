<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MistralAIService implements AIServiceInterface
{
    protected $apiKey;
    protected $model;

    public function __construct()
    {
        $this->apiKey = env('MISTRAL_API_KEY');
        $this->model = env('MISTRAL_MODEL', 'open-mistral-7b');
    }

    public function generateRecommendations(string $companyName, array $emissionsData, array $telemetryData): string
    {
        if (empty($this->apiKey)) {
            throw new \Exception("Mistral API Key is not configured.");
        }

        $prompt = $this->buildPrompt($companyName, $emissionsData, $telemetryData);

        Log::info("Sending recommendation request to Mistral AI...");

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json'
        ])->timeout(10)->post('https://api.mistral.ai/v1/chat/completions', [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Eres un consultor experto en sostenibilidad y eficiencia energética para el edificio ECONOVA de la Cámara de Comercio de Bogotá. Brindas recomendaciones directas, basadas en datos reales, en formato Markdown en español.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7
        ]);

        if ($response->successful()) {
            return $response->json()['choices'][0]['message']['content'] ?? 'No se recibieron sugerencias.';
        }

        Log::error("Mistral API call failed with status " . $response->status() . ": " . $response->body());
        throw new \Exception("Mistral API failed: " . $response->body());
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
