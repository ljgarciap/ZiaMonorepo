<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Huella de Carbono — ZIA Carbon Control</title>
    <style>
        @page { margin: 40px 45px; }
        body { font-family: 'Helvetica', sans-serif; color: #222; line-height: 1.5; font-size: 11px; padding: 0; }

        /* ── Header ─────────────────────────────────────────── */
        .header { border-bottom: 3px solid #004d40; padding-bottom: 12px; margin-bottom: 24px; }
        .header-top { display: flex; justify-content: space-between; align-items: flex-end; }
        .header h1 { color: #004d40; margin: 0; font-size: 20px; font-weight: 700; letter-spacing: 0.5px; }
        .header .subtitle { color: #555; font-size: 10px; margin: 3px 0 0 0; }
        .header .generated { font-size: 9px; color: #999; text-align: right; }

        /* ── Company info ────────────────────────────────────── */
        .company-block { background: #f5f5f0; border-left: 4px solid #004d40; padding: 10px 14px; margin-bottom: 22px; border-radius: 0 4px 4px 0; }
        .company-block table { width: 100%; border-collapse: collapse; }
        .company-block td { padding: 2px 12px 2px 0; font-size: 10px; }
        .company-block .lbl { font-weight: 700; color: #004d40; width: 90px; }

        /* ── KPI cards ───────────────────────────────────────── */
        .kpi-row { width: 100%; margin-bottom: 22px; border-collapse: collapse; }
        .kpi-row td { padding: 0 8px 0 0; width: 25%; vertical-align: top; }
        .kpi-row td:last-child { padding-right: 0; }
        .kpi-card { padding: 12px 8px; border: 1px solid #ddd; border-radius: 6px; text-align: center; background: #fafafa; }
        .kpi-card.total { border-color: #004d40; background: #e8f5e9; }
        .kpi-title { font-size: 9px; text-transform: uppercase; letter-spacing: 0.4px; color: #666; margin-bottom: 4px; }
        .kpi-value { font-size: 20px; font-weight: 800; color: #004d40; }
        .kpi-unit  { font-size: 9px; color: #888; margin-top: 2px; }

        /* ── Sections ────────────────────────────────────────── */
        .section-title { font-size: 13px; font-weight: 700; color: #004d40; border-bottom: 1px solid #c8e6c9; padding-bottom: 4px; margin: 22px 0 12px 0; }
        .section-ref  { font-size: 9px; color: #888; font-weight: normal; margin-left: 6px; }

        /* ── Tables ──────────────────────────────────────────── */
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 10px; }
        table.data th { background: #e8f5e9; color: #004d40; text-align: left; padding: 6px 8px; border-bottom: 2px solid #c8e6c9; font-size: 9px; text-transform: uppercase; letter-spacing: 0.3px; }
        table.data td { padding: 6px 8px; border-bottom: 1px solid #eee; }
        table.data tr:last-child td { border-bottom: none; }

        /* ── Scope badges ────────────────────────────────────── */
        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 8px; color: #fff; font-weight: 700; }
        .s1 { background: #1a237e; }
        .s2 { background: #00897b; }
        .s3 { background: #f59e0b; }

        /* ── Net balance ─────────────────────────────────────── */
        .balance-grid { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .balance-grid td { padding: 6px 10px; font-size: 10px; border: 1px solid #eee; }
        .balance-grid .lbl { color: #555; width: 55%; }
        .balance-grid .val { font-weight: 700; color: #004d40; text-align: right; }
        .balance-grid .net-row td { background: #e8f5e9; font-size: 11px; }
        .balance-grid .biogenic-row td { background: #fff8e1; color: #f57f17; }
        .negative { color: #c62828; }

        /* ── Intensity ───────────────────────────────────────── */
        .intensity-row { background: #f9fbe7; }

        /* ── Comparison chart-table ──────────────────────────── */
        table.comparison th { background: #004d40; color: #fff; }
        table.comparison td { text-align: right; }
        table.comparison td:first-child { text-align: center; font-weight: 700; }
        .current-year td { background: #e8f5e9; font-weight: 700; }

        /* ── SASB table ──────────────────────────────────────── */
        table.sasb td:first-child { font-weight: 700; color: #004d40; white-space: nowrap; width: 22%; }

        /* ── Footer ──────────────────────────────────────────── */
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 9px; color: #aaa; border-top: 1px solid #eee; padding-top: 6px; }
        .clear { clear: both; }
    </style>
</head>
<body>

{{-- ── HEADER ────────────────────────────────────────────────────────────── --}}
<div class="header">
    <h1>REPORTE DE HUELLA DE CARBONO CORPORATIVA</h1>
    <p class="subtitle">Generado por ZIA Carbon Control &mdash; Motor de Cálculo GHG Protocol Corporate Standard</p>
    <p class="generated">Generado: {{ now()->format('d/m/Y H:i') }}</p>
</div>

{{-- ── COMPANY INFO ────────────────────────────────────────────────────────── --}}
<div class="company-block">
    <table>
        <tr>
            <td class="lbl">Empresa</td>
            <td>{{ $period->company->name }}</td>
            <td class="lbl">NIT</td>
            <td>{{ $period->company->nit }}</td>
        </tr>
        <tr>
            <td class="lbl">Período</td>
            <td><strong>{{ $period->year }}</strong></td>
            <td class="lbl">Estado</td>
            <td>{{ ucfirst($period->status) }}</td>
        </tr>
        @if($period->company->num_employees || $period->company->floor_sqm)
        <tr>
            <td class="lbl">Empleados</td>
            <td>{{ $period->company->num_employees ? number_format($period->company->num_employees) : 'N/D' }}</td>
            <td class="lbl">Área (m²)</td>
            <td>{{ $period->company->floor_sqm ? number_format($period->company->floor_sqm) : 'N/D' }}</td>
        </tr>
        @endif
    </table>
</div>

{{-- ── KPI CARDS ───────────────────────────────────────────────────────────── --}}
<table class="kpi-row">
    <tr>
        <td>
            <div class="kpi-card total">
                <div class="kpi-title">Huella Total GEI</div>
                <div class="kpi-value">{{ number_format($summary['huella_total'], 2) }}</div>
                <div class="kpi-unit">tCO₂e</div>
            </div>
        </td>
        <td>
            <div class="kpi-card">
                <div class="kpi-title"><span class="badge s1">A1</span> Alcance 1</div>
                <div class="kpi-value" style="color:#1a237e;">{{ number_format($summary['alcances']['scope_1']['total'], 2) }}</div>
                <div class="kpi-unit">tCO₂e</div>
            </div>
        </td>
        <td>
            <div class="kpi-card">
                <div class="kpi-title"><span class="badge s2">A2</span> Alcance 2</div>
                <div class="kpi-value" style="color:#00897b;">{{ number_format($summary['alcances']['scope_2']['total'], 2) }}</div>
                <div class="kpi-unit">tCO₂e</div>
            </div>
        </td>
        <td>
            <div class="kpi-card">
                <div class="kpi-title"><span class="badge s3">A3</span> Alcance 3</div>
                <div class="kpi-value" style="color:#d97706;">{{ number_format($summary['alcances']['scope_3']['total'], 2) }}</div>
                <div class="kpi-unit">tCO₂e</div>
            </div>
        </td>
    </tr>
</table>
<div class="clear"></div>

{{-- ── GRI 305-1 / 305-2 / 305-3  Detalle de Fuentes ────────────────────────── --}}
<div class="section-title">Distribución por Fuente de Emisión <span class="section-ref">GRI 305-1 / GRI 305-2 / GRI 305-3</span></div>
<table class="data">
    <thead>
        <tr>
            <th style="width:12%;">Alcance</th>
            <th style="width:28%;">Fuente / Factor</th>
            <th style="width:16%;">Actividad</th>
            <th style="width:9%;">Unidad</th>
            <th style="width:14%;">CO₂ (t)</th>
            <th style="width:14%;">Total (tCO₂e)</th>
            <th style="width:7%;">Incerti. (%)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($period->emissions as $em)
        @php
            $scopeNum = $em->factor->category->scope_id ?? '?';
            $scopeClass = 's' . $scopeNum;
        @endphp
        <tr>
            <td><span class="badge {{ $scopeClass }}">A{{ $scopeNum }}</span></td>
            <td>{{ $em->factor->name }}</td>
            <td style="text-align:right;">{{ number_format($em->quantity, 2) }}</td>
            <td>{{ $em->factor->unit->symbol ?? $em->factor->unit->name ?? '—' }}</td>
            <td style="text-align:right;">{{ number_format($em->emissions_co2 ?? 0, 4) }}</td>
            <td style="text-align:right;"><strong>{{ number_format($em->calculated_co2e, 4) }}</strong>
                @if($em->biogenic_co2e > 0)<br><small style="color:#888;font-size:8px;">Biog.: {{ number_format($em->biogenic_co2e, 4) }}</small>@endif
                @if($em->calculated_co2e < 0)<small style="color:#c62828;font-size:8px;"> ↩ Remoción</small>@endif
            </td>
            <td style="text-align:right;">{{ number_format($em->uncertainty_result, 2) }}%</td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;color:#999;padding:16px;">Sin registros de emisiones para este período.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- ── GRI 305-5  Balance Neto ────────────────────────────────────────────── --}}
<div class="section-title">Balance Neto de GEI <span class="section-ref">GRI 305-5</span></div>
<table class="balance-grid">
    <tr>
        <td class="lbl">Emisiones brutas (Alcances 1+2+3)</td>
        <td class="val">{{ number_format($grossEmissions, 4) }} tCO₂e</td>
    </tr>
    <tr>
        <td class="lbl">Remociones de carbono (LULUCF / reforestación)</td>
        <td class="val negative">− {{ number_format($removals, 4) }} tCO₂e</td>
    </tr>
    @if($carbonStored > 0)
    <tr>
        <td class="lbl">Carbono almacenado</td>
        <td class="val negative">− {{ number_format($carbonStored, 4) }} tCO₂e</td>
    </tr>
    @endif
    @if($avoidedEmissions > 0)
    <tr>
        <td class="lbl">Emisiones evitadas (informativo)</td>
        <td class="val">{{ number_format($avoidedEmissions, 4) }} tCO₂e</td>
    </tr>
    @endif
    <tr class="net-row">
        <td class="lbl"><strong>Huella Neta de GEI</strong></td>
        <td class="val"><strong>{{ number_format($netBalance, 4) }} tCO₂e</strong></td>
    </tr>
    @if($biogenicTotal > 0)
    <tr class="biogenic-row">
        <td class="lbl">CO₂ biogénico (excluido del inventario GEI, reportado por transparencia)</td>
        <td class="val">{{ number_format($biogenicTotal, 4) }} tCO₂e</td>
    </tr>
    @endif
</table>

{{-- ── GRI 305-4  Indicadores de Intensidad ───────────────────────────────── --}}
<div class="section-title">Indicadores de Intensidad <span class="section-ref">GRI 305-4</span></div>
<table class="data">
    <thead>
        <tr>
            <th>Indicador</th>
            <th>Numerador</th>
            <th>Denominador</th>
            <th>Valor</th>
        </tr>
    </thead>
    <tbody>
        <tr class="intensity-row">
            <td>Intensidad por área construida</td>
            <td>{{ number_format($summary['huella_total'], 2) }} tCO₂e</td>
            <td>{{ $floorSqm > 0 ? number_format($floorSqm) . ' m²' : 'N/D' }}</td>
            <td style="font-weight:700;text-align:right;">
                @if($intensityPerSqm !== null)
                    {{ number_format($intensityPerSqm, 6) }} tCO₂e/m²
                @else
                    <span style="color:#999;">Sin datos de área</span>
                @endif
            </td>
        </tr>
        <tr class="intensity-row">
            <td>Intensidad por empleado</td>
            <td>{{ number_format($summary['huella_total'], 2) }} tCO₂e</td>
            <td>{{ $numEmployees > 0 ? number_format($numEmployees) . ' empleados' : 'N/D' }}</td>
            <td style="font-weight:700;text-align:right;">
                @if($intensityPerEmployee !== null)
                    {{ number_format($intensityPerEmployee, 4) }} tCO₂e/empleado
                @else
                    <span style="color:#999;">Sin datos de empleados</span>
                @endif
            </td>
        </tr>
    </tbody>
</table>

{{-- ── COMPARATIVO MULTI-AÑO ────────────────────────────────────────────────── --}}
@if($comparisonData->count() > 1)
<div class="section-title">Evolución Histórica de Emisiones</div>
<table class="data comparison">
    <thead>
        <tr>
            <th style="text-align:center;width:12%;">Año</th>
            <th style="text-align:right;">Alcance 1 (tCO₂e)</th>
            <th style="text-align:right;">Alcance 2 (tCO₂e)</th>
            <th style="text-align:right;">Alcance 3 (tCO₂e)</th>
            <th style="text-align:right;">Total (tCO₂e)</th>
            <th style="text-align:right;">Var. vs Anterior</th>
        </tr>
    </thead>
    <tbody>
        @php $prevTotal = null; @endphp
        @foreach($comparisonData as $row)
        @php
            $isCurrentYear = ((int)$row->year === (int)$period->year);
            $total = (float) $row->total;
            $varPct = ($prevTotal !== null && $prevTotal > 0) ? (($total - $prevTotal) / $prevTotal * 100) : null;
            $prevTotal = $total;
        @endphp
        <tr class="{{ $isCurrentYear ? 'current-year' : '' }}">
            <td>{{ $row->year }}{{ $isCurrentYear ? ' ◀' : '' }}</td>
            <td>{{ number_format((float)$row->scope1, 2) }}</td>
            <td>{{ number_format((float)$row->scope2, 2) }}</td>
            <td>{{ number_format((float)$row->scope3, 2) }}</td>
            <td><strong>{{ number_format($total, 2) }}</strong></td>
            <td>
                @if($varPct !== null)
                    <span style="color:{{ $varPct > 0 ? '#c62828' : '#2e7d32' }};">
                        {{ $varPct > 0 ? '▲' : '▼' }} {{ number_format(abs($varPct), 1) }}%
                    </span>
                @else
                    —
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- ── EQUIVALENCIAS ────────────────────────────────────────────────────────── --}}
<div class="section-title">Equivalencias de Impacto Ambiental</div>
<p style="margin:0 0 6px 0;">La huella de carbono de este período equivale aproximadamente a:</p>
<p style="margin:0;font-size:11px;"><strong>{{ number_format($summary['equivalency']['value'], 1) }}</strong> {{ $summary['equivalency']['label'] }}</p>

{{-- ── INDICADORES GRI / SASB ──────────────────────────────────────────────── --}}
<div class="section-title">Declaración de Indicadores Estándar</div>
<table class="data sasb">
    <thead>
        <tr>
            <th style="width:22%;">Código</th>
            <th style="width:48%;">Indicador</th>
            <th style="width:30%;">Valor</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>GRI 305-1</td>
            <td>Emisiones directas de GEI (Alcance 1)</td>
            <td>{{ number_format($summary['alcances']['scope_1']['total'], 4) }} tCO₂e</td>
        </tr>
        <tr>
            <td>GRI 305-2</td>
            <td>Emisiones indirectas por energía adquirida (Alcance 2, método basado en ubicación)</td>
            <td>{{ number_format($summary['alcances']['scope_2']['total'], 4) }} tCO₂e</td>
        </tr>
        <tr>
            <td>GRI 305-3</td>
            <td>Otras emisiones indirectas de GEI (Alcance 3)</td>
            <td>{{ number_format($summary['alcances']['scope_3']['total'], 4) }} tCO₂e</td>
        </tr>
        <tr>
            <td>GRI 305-4</td>
            <td>Intensidad de emisiones de GEI</td>
            <td>
                @if($intensityPerSqm !== null)
                    {{ number_format($intensityPerSqm, 6) }} tCO₂e/m²<br>
                @endif
                @if($intensityPerEmployee !== null)
                    {{ number_format($intensityPerEmployee, 4) }} tCO₂e/empleado
                @endif
                @if($intensityPerSqm === null && $intensityPerEmployee === null)
                    N/D
                @endif
            </td>
        </tr>
        <tr>
            <td>GRI 305-5</td>
            <td>Reducción de emisiones de GEI (remociones + carbono almacenado)</td>
            <td>{{ number_format($removals + $carbonStored, 4) }} tCO₂e</td>
        </tr>
        {{-- SASB IF-RE Real Estate Standard --}}
        <tr>
            <td>SASB IF-RE-120a.1</td>
            <td>Emisiones brutas de GEI (Alcance 1 + Alcance 2) — Sector Real Estate / Servicios</td>
            <td>{{ number_format($summary['alcances']['scope_1']['total'] + $summary['alcances']['scope_2']['total'], 4) }} tCO₂e</td>
        </tr>
        <tr>
            <td>SASB IF-RE-130a.1</td>
            <td>Consumo energético de la cartera — como-comparable (como proxy de Alcance 2 + Alcance 3 Cat.6)</td>
            <td>{{ number_format($summary['alcances']['scope_2']['total'] + $summary['alcances']['scope_3']['total'], 4) }} tCO₂e</td>
        </tr>
    </tbody>
</table>

{{-- ── NOTA METODOLÓGICA ────────────────────────────────────────────────────── --}}
<p style="font-size:9px;color:#888;margin-top:10px;border-top:1px solid #eee;padding-top:8px;">
    <strong>Nota metodológica:</strong> Los factores de calentamiento global (GWP) utilizados corresponden al IPCC AR6 adoptados
    por el GHG Protocol (agosto 2024): CO₂ = 1, CH₄ = 29.8 (fósil), N₂O = 273, SF₆ = 25 200, NF₃ = 17 400.
    El CO₂ biogénico se excluye del inventario de GEI y se reporta de forma separada (GHG Protocol Corporate Standard §5.4).
    SASB: IF-RE = Real Estate Standard (Building Products &amp; Furnishings — Service sector).
    Reporte generado automáticamente por ZIA Carbon Control.
</p>

<div class="footer">
    ZIA Carbon Control &bull; Reporte {{ $period->company->name }} &bull; Período {{ $period->year }} &bull; {{ now()->format('d/m/Y') }}
</div>

</body>
</html>
