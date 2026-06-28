<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CarbonEmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AISidecarController extends Controller
{
    /**
     * SSE proxy: forwards chat to zia-agent and streams the response back.
     * POST /api/ai/chat
     */
    public function chat(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'message'    => 'required|string|max:2000',
            'company_id' => 'required|integer|exists:companies,id',
            'period_id'  => 'nullable|integer',
            'history'    => 'nullable|array',
        ]);

        $agentUrl  = rtrim(config('services.zia_agent_url', 'http://zia-agent:8001'), '/');
        $authToken = $request->bearerToken();

        $payload = [
            'message'    => $validated['message'],
            'company_id' => $validated['company_id'],
            'period_id'  => $validated['period_id'] ?? null,
            'history'    => $validated['history'] ?? [],
            'auth_token' => $authToken,
        ];

        return new StreamedResponse(function () use ($agentUrl, $payload) {
            $ctx = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\nAccept: text/event-stream",
                    'content' => json_encode($payload),
                    'timeout' => 120,
                ],
            ]);

            $stream = @fopen("{$agentUrl}/chat", 'r', false, $ctx);

            if (!$stream) {
                $error = json_encode(['type' => 'error', 'message' => 'No se pudo conectar con el agente ZIA']);
                echo "data: {$error}\n\n";
                flush();
                return;
            }

            while (!feof($stream)) {
                $chunk = fread($stream, 1024);
                if ($chunk !== false && $chunk !== '') {
                    echo $chunk;
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            }

            fclose($stream);
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    /**
     * Get tailored ecological recommendations for the company context.
     */
    public function getRecommendations(Request $request)
    {
        $companyId = $request->header('X-Company-Context') ?? $request->input('company_id');

        if (!$companyId) {
            return response()->json(['error' => 'Company context header X-Company-Context is required'], 400);
        }

        $company = Company::findOrFail($companyId);

        $emissionsSummary = CarbonEmission::query()
            ->select('carbon_emissions.id', 'carbon_emissions.quantity', 'carbon_emissions.calculated_co2e', 'periods.year')
            ->join('periods', 'carbon_emissions.period_id', '=', 'periods.id')
            ->where('periods.company_id', $company->id)
            ->latest('carbon_emissions.created_at')
            ->limit(5)
            ->get()
            ->toArray();

        return response()->json([
            'company_name'    => $company->name,
            'recommendations' => [],
            'summary'         => $emissionsSummary,
            'timestamp'       => now()->toIso8601String(),
        ]);
    }
}
