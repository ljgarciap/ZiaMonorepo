<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Huella de Carbono - Zia</title>
    <style>
        @page { margin: 40px 40px; }
        body { font-family: 'Helvetica', sans-serif; color: #333; line-height: 1.5; padding: 0; }
        .header { text-align: center; border-bottom: 2px solid #1a237e; padding-bottom: 10px; margin-bottom: 30px; }
        .header h1 { color: #1a237e; margin: 0; font-size: 24px; }
        .header p { color: #666; font-size: 14px; margin: 5px 0 0 0; }
        .company-info { margin-bottom: 30px; }
        .company-info div { margin-bottom: 5px; }
        .label { font-weight: bold; color: #1a237e; }
        .kpi-table { width: 100%; margin-bottom: 30px; border: none; }
        .kpi-table td { border: none; padding: 0 10px 0 0; width: 25%; vertical-align: top; }
        .kpi-table td:last-child { padding-right: 0; }
        .kpi-card { padding: 15px 10px; border: 1px solid #ddd; border-radius: 8px; text-align: center; background: #f9f9f9; }
        .kpi-title { font-size: 10px; text-transform: uppercase; color: #666; margin-bottom: 5px; }
        .kpi-value { font-size: 18px; font-weight: bold; color: #1a237e; }
        .kpi-unit { font-size: 10px; color: #666; }
        .clear { clear: both; }
        .section-title { font-size: 16px; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 15px; color: #1a237e; margin-top: 30px;}
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 11px; }
        th { background-color: #f5f5f5; color: #1a237e; text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
        td { padding: 8px; border-bottom: 1px solid #eee; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 10px; }
        .scope-badge { padding: 2px 5px; border-radius: 3px; font-size: 9px; color: #fff; }
        .scope-1 { background: #1a237e; }
        .scope-2 { background: #00897b; }
        .scope-3 { background: #f59e0b; }
    </style>
</head>
<body>
    <div class="header">
        <h1>REPORTE EJECUTIVO DE HUELLA DE CARBONO</h1>
        <p>Generado por Zia Carbon Control • {{ now()->format('d/m/Y') }}</p>
    </div>

    <div class="company-info">
        <div><span class="label">Empresa:</span> {{ $period->company->name }}</div>
        <div><span class="label">NIT:</span> {{ $period->company->nit }}</div>
        <div><span class="label">Periodo:</span> {{ $period->year }}</div>
        <div><span class="label">Estado:</span> {{ ucfirst($period->status) }}</div>
    </div>

    <table class="kpi-table">
        <tr>
            <td class="kpi-cell">
                <div class="kpi-card">
                    <div class="kpi-title">Huella Total</div>
                    <div class="kpi-value">{{ number_format($summary['huella_total'], 2) }}</div>
                    <div class="kpi-unit">tCO2e</div>
                </div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-card">
                    <div class="kpi-title">Alcance 1</div>
                    <div class="kpi-value">{{ number_format($summary['alcances']['scope_1']['total'], 2) }}</div>
                    <div class="kpi-unit">tCO2e</div>
                </div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-card">
                    <div class="kpi-title">Alcance 2</div>
                    <div class="kpi-value">{{ number_format($summary['alcances']['scope_2']['total'], 2) }}</div>
                    <div class="kpi-unit">tCO2e</div>
                </div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-card">
                    <div class="kpi-title">Alcance 3</div>
                    <div class="kpi-value">{{ number_format($summary['alcances']['scope_3']['total'], 2) }}</div>
                    <div class="kpi-unit">tCO2e</div>
                </div>
            </td>
        </tr>
    </table>
    <div class="clear"></div>

    <div class="section-title">Distribución por Fuente de Emisión</div>
    <table>
        <thead>
            <tr>
                <th>Alcance</th>
                <th>Fuente</th>
                <th>Cantidad</th>
                <th>Total (tCO2e)</th>
                <th>Incertidumbre (%)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($period->emissions as $emission)
            <tr>
                <td>{{ $emission->factor->category->scope->name ?? 'Alcance ' . ($emission->factor->category->scope_id ?? 'N/A') }}</td>
                <td>{{ $emission->factor->name }}</td>
                <td>{{ number_format($emission->quantity, 2) }} {{ $emission->factor->unit->symbol ?? $emission->factor->unit->name ?? 'u' }}</td>
                <td><strong>{{ number_format($emission->calculated_co2e, 4) }}</strong></td>
                <td>{{ number_format($emission->uncertainty_result, 3) }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title">Equivalencias de Impacto Ambiental</div>
    <p>La huella de carbono de este periodo equivale aproximadamente a:</p>
    <ul>
        <li><strong>{{ number_format($summary['equivalency']['value'], 1) }}</strong> {{ $summary['equivalency']['label'] }}.</li>
    </ul>

    <div class="section-title">Declaración de Indicadores Estándar (GRI & SASB)</div>
    <table style="margin-top: 10px;">
        <thead>
            <tr>
                <th style="width: 25%;">Estándar / Código</th>
                <th style="width: 45%;">Descripción del Indicador</th>
                <th style="width: 30%;">Valor Consolidado</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>GRI 305-1</strong></td>
                <td>Emisiones directas de gases de efecto invernadero (Alcance 1)</td>
                <td><strong>{{ number_format($summary['alcances']['scope_1']['total'], 2) }} tCO2e</strong></td>
            </tr>
            <tr>
                <td><strong>GRI 305-2</strong></td>
                <td>Emisiones indirectas de gases de efecto invernadero al adquirir energía (Alcance 2)</td>
                <td><strong>{{ number_format($summary['alcances']['scope_2']['total'], 2) }} tCO2e</strong></td>
            </tr>
            <tr>
                <td><strong>GRI 305-3</strong></td>
                <td>Otras emisiones indirectas de gases de efecto invernadero (Alcance 3)</td>
                <td><strong>{{ number_format($summary['alcances']['scope_3']['total'], 2) }} tCO2e</strong></td>
            </tr>
            <tr>
                <td><strong>SASB IF-EU-120a.1</strong></td>
                <td>Emisiones de gases de efecto invernadero asociadas al consumo de energía eléctrica</td>
                <td><strong>{{ number_format($summary['alcances']['scope_2']['total'], 2) }} tCO2e</strong></td>
            </tr>
            <tr>
                <td><strong>SASB IF-EU-140a.1</strong></td>
                <td>Volumen de recursos hídricos consumidos y retirados en la operación</td>
                <td><strong>{{ number_format($summary['neutralizados'] ?? 0, 2) }} m³ (Tratados / Compensados)</strong></td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Este reporte es generado de forma automática por el motor de cálculo de Zia Carbon Control.<br>
        Basado en los PCG actualizados (CH4: 28, N2O: 265) conforme a los requerimientos del proyecto.
    </div>
</body>
</html>
