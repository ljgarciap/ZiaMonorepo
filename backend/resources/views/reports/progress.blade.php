<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Avance — ZIA Carbon Control</title>
    <style>
        @page { margin: 40px 45px; }
        body { font-family: 'Helvetica', sans-serif; color: #222; line-height: 1.5; font-size: 11px; }

        .header { border-bottom: 3px solid #1a237e; padding-bottom: 12px; margin-bottom: 24px; }
        .header h1 { color: #1a237e; margin: 0; font-size: 18px; font-weight: 700; }
        .header .subtitle { color: #555; font-size: 10px; margin: 3px 0 0 0; }
        .header .generated { font-size: 9px; color: #999; }

        .company-block { background: #f0f4ff; border-left: 4px solid #1a237e; padding: 10px 14px; margin-bottom: 22px; border-radius: 0 4px 4px 0; }
        .company-block table { width: 100%; border-collapse: collapse; }
        .company-block td { padding: 2px 12px 2px 0; font-size: 10px; }
        .company-block .lbl { font-weight: 700; color: #1a237e; width: 90px; }

        .kpi-row { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .kpi-row td { padding: 0 8px 0 0; width: 25%; vertical-align: top; }
        .kpi-row td:last-child { padding-right: 0; }
        .kpi-card { padding: 12px 8px; border: 1px solid #ddd; border-radius: 6px; text-align: center; background: #fafafa; }
        .kpi-card.base { border-color: #1a237e; background: #e8eaf6; }
        .kpi-card.current { border-color: #00897b; background: #e0f2f1; }
        .kpi-card.reduction { border-color: #2e7d32; background: #e8f5e9; }
        .kpi-card.increase { border-color: #c62828; background: #ffebee; }
        .kpi-title { font-size: 9px; text-transform: uppercase; letter-spacing: 0.4px; color: #666; margin-bottom: 4px; }
        .kpi-value { font-size: 20px; font-weight: 800; color: #1a237e; }
        .kpi-value.green { color: #2e7d32; }
        .kpi-value.red { color: #c62828; }
        .kpi-unit { font-size: 9px; color: #888; margin-top: 2px; }

        .section-title { font-size: 13px; font-weight: 700; color: #1a237e; border-bottom: 1px solid #c5cae9; padding-bottom: 4px; margin: 22px 0 12px 0; }
        .section-ref { font-size: 9px; color: #888; font-weight: normal; margin-left: 6px; }

        table.data { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 10px; }
        table.data th { background: #e8eaf6; color: #1a237e; text-align: left; padding: 6px 8px; border-bottom: 2px solid #c5cae9; font-size: 9px; text-transform: uppercase; }
        table.data td { padding: 6px 8px; border-bottom: 1px solid #eee; }
        table.data tr:last-child td { border-bottom: none; }
        td.num { text-align: right; font-weight: 600; }
        td.pct { text-align: right; }

        .arrow-down { color: #2e7d32; font-weight: 800; }
        .arrow-up { color: #c62828; font-weight: 800; }
        .arrow-neutral { color: #888; }

        .trend-table td:first-child { font-weight: 700; text-align: center; }
        .current-year td { background: #e0f2f1; font-weight: 700; }
        .base-year td { background: #e8eaf6; }

        .trajectory-box { border: 1px solid #ddd; border-radius: 6px; padding: 14px 18px; margin-bottom: 20px; }
        .trajectory-box.on-track { border-color: #2e7d32; background: #f1f8e9; }
        .trajectory-box.off-track { border-color: #c62828; background: #fff8f8; }
        .trajectory-box.neutral { border-color: #888; background: #fafafa; }
        .trajectory-title { font-size: 12px; font-weight: 700; margin-bottom: 6px; }
        .trajectory-title.green { color: #2e7d32; }
        .trajectory-title.red { color: #c62828; }
        .trajectory-title.gray { color: #555; }
        .trajectory-desc { font-size: 10px; color: #444; }

        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 8px; color: #fff; font-weight: 700; }
        .s1 { background: #1a237e; }
        .s2 { background: #00897b; }
        .s3 { background: #f59e0b; }

        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 9px; color: #aaa; border-top: 1px solid #eee; padding-top: 6px; }
        .clear { clear: both; }
        .note { font-size: 9px; color: #888; font-style: italic; margin-top: 12px; }
    </style>
</head>
<body>

{{-- ── HEADER ────────────────────────────────────────────────────────────── --}}
<div class="header">
    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
        <div>
            <h1>REPORTE DE AVANCE EN REDUCCIÓN DE EMISIONES</h1>
            <p class="subtitle">ZIA Carbon Control &mdash; Análisis de Progreso GHG Protocol</p>
        </div>
        <div>
            <p class="generated">Generado: {{ now()->format('d/m/Y H:i') }}</p>
        </div>
    </div>
</div>

{{-- ── COMPANY INFO ─────────────────────────────────────────────────────── --}}
<div class="company-block">
    <table>
        <tr>
            <td class="lbl">Empresa</td>
            <td>{{ $period->company->name }}</td>
            <td class="lbl">NIT</td>
            <td>{{ $period->company->nit ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Año Base</td>
            <td><strong>{{ $basePeriod?->year ?? 'N/D' }}</strong></td>
            <td class="lbl">Período Actual</td>
            <td><strong>{{ $period->year }}</strong></td>
        </tr>
    </table>
</div>

{{-- ── KPI CARDS ─────────────────────────────────────────────────────────── --}}
@if($baseData && $currentData)
<table class="kpi-row">
    <tr>
        <td>
            <div class="kpi-card base">
                <div class="kpi-title">Emisiones Año Base ({{ $basePeriod?->year }})</div>
                <div class="kpi-value">{{ number_format((float)$baseData->total, 2) }}</div>
                <div class="kpi-unit">tCO₂e</div>
            </div>
        </td>
        <td>
            <div class="kpi-card current">
                <div class="kpi-title">Emisiones {{ $period->year }}</div>
                <div class="kpi-value" style="color:#00897b;">{{ number_format((float)$currentData->total, 2) }}</div>
                <div class="kpi-unit">tCO₂e</div>
            </div>
        </td>
        @if($progress)
        <td>
            <div class="kpi-card {{ $progress['absolute_change'] < 0 ? 'reduction' : 'increase' }}">
                <div class="kpi-title">Variación Absoluta</div>
                <div class="kpi-value {{ $progress['absolute_change'] < 0 ? 'green' : 'red' }}">
                    {{ $progress['absolute_change'] > 0 ? '+' : '' }}{{ number_format($progress['absolute_change'], 2) }}
                </div>
                <div class="kpi-unit">tCO₂e</div>
            </div>
        </td>
        <td>
            <div class="kpi-card {{ $progress['pct_change'] < 0 ? 'reduction' : 'increase' }}">
                <div class="kpi-title">Variación Porcentual</div>
                <div class="kpi-value {{ $progress['pct_change'] < 0 ? 'green' : 'red' }}">
                    {{ $progress['pct_change'] > 0 ? '+' : '' }}{{ number_format($progress['pct_change'], 1) }}%
                </div>
                <div class="kpi-unit">vs. año base</div>
            </div>
        </td>
        @endif
    </tr>
</table>
<div class="clear"></div>
@endif

{{-- ── TRAYECTORIA ──────────────────────────────────────────────────────── --}}
@if($progress)
@php
    $isReduction = $progress['pct_change'] <= 0;
    $boxClass = $isReduction ? 'on-track' : 'off-track';
    $titleClass = $isReduction ? 'green' : 'red';
    $icon = $isReduction ? '▼' : '▲';
@endphp
<div class="trajectory-box {{ $boxClass }}">
    <div class="trajectory-title {{ $titleClass }}">
        {{ $icon }} Trayectoria: {{ $isReduction ? 'REDUCCIÓN CONFIRMADA' : 'INCREMENTO DETECTADO' }}
    </div>
    <div class="trajectory-desc">
        @if($isReduction)
            Las emisiones del período {{ $period->year }} son <strong>{{ abs($progress['pct_change']) }}% menores</strong>
            respecto al año base ({{ $basePeriod?->year }}), representando una reducción de
            <strong>{{ abs($progress['absolute_change']) }} tCO₂e</strong>.
        @else
            Las emisiones del período {{ $period->year }} son <strong>{{ $progress['pct_change'] }}% mayores</strong>
            respecto al año base ({{ $basePeriod?->year }}), representando un incremento de
            <strong>{{ $progress['absolute_change'] }} tCO₂e</strong>.
            Se recomienda revisar las fuentes de Alcance 1 y 2 para identificar oportunidades de reducción.
        @endif
    </div>
</div>
@endif

{{-- ── COMPARACIÓN POR ALCANCE ──────────────────────────────────────────── --}}
@if($baseData && $currentData && $progress)
<div class="section-title">Variación por Alcance <span class="section-ref">GHG Protocol — Tracking Emissions over Time</span></div>
<table class="data">
    <thead>
        <tr>
            <th>Alcance</th>
            <th style="text-align:right;">Año Base ({{ $basePeriod?->year }}) tCO₂e</th>
            <th style="text-align:right;">{{ $period->year }} tCO₂e</th>
            <th style="text-align:right;">Variación (tCO₂e)</th>
            <th style="text-align:right;">Variación (%)</th>
        </tr>
    </thead>
    <tbody>
        @php
            $scopes = [
                ['label' => 'Alcance 1 — Emisiones Directas', 'badge' => 's1', 'base' => (float)$baseData->scope1, 'cur' => (float)$currentData->scope1, 'diff' => $progress['scope1_change']],
                ['label' => 'Alcance 2 — Electricidad', 'badge' => 's2', 'base' => (float)$baseData->scope2, 'cur' => (float)$currentData->scope2, 'diff' => $progress['scope2_change']],
                ['label' => 'Alcance 3 — Cadena de Valor', 'badge' => 's3', 'base' => (float)$baseData->scope3, 'cur' => (float)$currentData->scope3, 'diff' => $progress['scope3_change']],
            ];
        @endphp
        @foreach($scopes as $s)
        @php $pct = $s['base'] > 0 ? round(($s['diff'] / $s['base']) * 100, 1) : null; @endphp
        <tr>
            <td><span class="badge {{ $s['badge'] }}">{{ strtoupper($s['badge']) }}</span> {{ $s['label'] }}</td>
            <td class="num">{{ number_format($s['base'], 2) }}</td>
            <td class="num">{{ number_format($s['cur'], 2) }}</td>
            <td class="num {{ $s['diff'] < 0 ? 'arrow-down' : ($s['diff'] > 0 ? 'arrow-up' : 'arrow-neutral') }}">
                {{ $s['diff'] > 0 ? '+' : '' }}{{ number_format($s['diff'], 2) }}
            </td>
            <td class="pct {{ $s['diff'] < 0 ? 'arrow-down' : ($s['diff'] > 0 ? 'arrow-up' : 'arrow-neutral') }}">
                @if($pct !== null){{ $pct > 0 ? '+' : '' }}{{ $pct }}%@else—@endif
            </td>
        </tr>
        @endforeach
        <tr style="font-weight:700; background:#f5f5f5;">
            <td>TOTAL</td>
            <td class="num">{{ number_format((float)$baseData->total, 2) }}</td>
            <td class="num">{{ number_format((float)$currentData->total, 2) }}</td>
            <td class="num {{ $progress['absolute_change'] < 0 ? 'arrow-down' : 'arrow-up' }}">
                {{ $progress['absolute_change'] > 0 ? '+' : '' }}{{ number_format($progress['absolute_change'], 2) }}
            </td>
            <td class="pct {{ $progress['pct_change'] < 0 ? 'arrow-down' : 'arrow-up' }}">
                {{ $progress['pct_change'] > 0 ? '+' : '' }}{{ number_format($progress['pct_change'], 1) }}%
            </td>
        </tr>
    </tbody>
</table>
@endif

{{-- ── EVOLUCIÓN PLURIANUAL ─────────────────────────────────────────────── --}}
@if($emissionsByPeriod->count() > 1)
<div class="section-title">Evolución Histórica de Emisiones</div>
<table class="data trend-table">
    <thead>
        <tr>
            <th style="text-align:center;">Año</th>
            <th style="text-align:right;">Alcance 1</th>
            <th style="text-align:right;">Alcance 2</th>
            <th style="text-align:right;">Alcance 3</th>
            <th style="text-align:right;">Total tCO₂e</th>
            <th style="text-align:right;">Δ vs. año anterior</th>
        </tr>
    </thead>
    <tbody>
        @php $prev = null; @endphp
        @foreach($emissionsByPeriod as $row)
        @php
            $delta = $prev !== null ? round((float)$row->total - (float)$prev->total, 2) : null;
            $isCurrent = $row->year == $period->year;
            $isBase = $row->year == $basePeriod?->year;
        @endphp
        <tr class="{{ $isCurrent ? 'current-year' : ($isBase ? 'base-year' : '') }}">
            <td>
                {{ $row->year }}
                @if($isBase) <span style="font-size:8px;color:#1a237e;">(BASE)</span>@endif
                @if($isCurrent) <span style="font-size:8px;color:#00897b;">(ACTUAL)</span>@endif
            </td>
            <td class="num">{{ number_format((float)$row->scope1, 2) }}</td>
            <td class="num">{{ number_format((float)$row->scope2, 2) }}</td>
            <td class="num">{{ number_format((float)$row->scope3, 2) }}</td>
            <td class="num">{{ number_format((float)$row->total, 2) }}</td>
            <td class="num {{ $delta === null ? '' : ($delta < 0 ? 'arrow-down' : ($delta > 0 ? 'arrow-up' : '')) }}">
                @if($delta !== null){{ $delta > 0 ? '+' : '' }}{{ number_format($delta, 2) }}@else—@endif
            </td>
        </tr>
        @php $prev = $row; @endphp
        @endforeach
    </tbody>
</table>
@endif

<p class="note">Este reporte fue generado automáticamente por ZIA Carbon Control. Los datos provienen del inventario de emisiones declarado por la organización bajo los lineamientos del GHG Protocol Corporate Standard.</p>

<div class="footer">ZIA Carbon Control — Reporte de Avance — {{ $period->company->name }} — {{ now()->format('d/m/Y') }}</div>
</body>
</html>
