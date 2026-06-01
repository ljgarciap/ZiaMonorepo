<?php

namespace App\Services\AI;

interface AIServiceInterface
{
    /**
     * Generate ecological recommendations based on company and telemetry data.
     *
     * @param string $companyName
     * @param array $emissionsData Consolidated historical emissions
     * @param array $telemetryData Recent telemetry readings (water/energy)
     * @return string Markdown formatted suggestions and analysis
     */
    public function generateRecommendations(string $companyName, array $emissionsData, array $telemetryData): string;
}
