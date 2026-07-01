<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte IoT — ZIA Carbon Control</title>
    <style>
        @page { margin: 40px 45px; }
        body { font-family: 'Helvetica', sans-serif; color: #222; line-height: 1.5; font-size: 11px; }

        .header { border-bottom: 3px solid #00897b; padding-bottom: 12px; margin-bottom: 24px; }
        .header h1 { color: #00897b; margin: 0; font-size: 18px; font-weight: 700; }
        .header .subtitle { color: #555; font-size: 10px; margin: 3px 0 0 0; }
        .header .generated { font-size: 9px; color: #999; }

        .company-block { background: #e0f2f1; border-left: 4px solid #00897b; padding: 10px 14px; margin-bottom: 22px; border-radius: 0 4px 4px 0; }
        .company-block table { width: 100%; border-collapse: collapse; }
        .company-block td { padding: 2px 12px 2px 0; font-size: 10px; }
        .company-block .lbl { font-weight: 700; color: #00695c; width: 90px; }

        .kpi-row { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .kpi-row td { padding: 0 8px 0 0; width: 25%; vertical-align: top; }
        .kpi-row td:last-child { padding-right: 0; }
        .kpi-card { padding: 12px 8px; border: 1px solid #b2dfdb; border-radius: 6px; text-align: center; background: #f5fffe; }
        .kpi-card.iot { border-color: #00897b; background: #e0f2f1; }
        .kpi-title { font-size: 9px; text-transform: uppercase; letter-spacing: 0.4px; color: #666; margin-bottom: 4px; }
        .kpi-value { font-size: 20px; font-weight: 800; color: #00897b; }
        .kpi-unit { font-size: 9px; color: #888; margin-top: 2px; }

        .section-title { font-size: 13px; font-weight: 700; color: #00897b; border-bottom: 1px solid #b2dfdb; padding-bottom: 4px; margin: 22px 0 12px 0; }
        .section-ref { font-size: 9px; color: #888; font-weight: normal; margin-left: 6px; }

        table.data { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 10px; }
        table.data th { background: #e0f2f1; color: #00695c; text-align: left; padding: 6px 8px; border-bottom: 2px solid #b2dfdb; font-size: 9px; text-transform: uppercase; }
        table.data td { padding: 6px 8px; border-bottom: 1px solid #eee; }
        table.data tr:last-child td { border-bottom: none; }
        td.num { text-align: right; font-weight: 600; }
        td.center { text-align: center; }

        .alert-critical { background: #ffebee; }
        .alert-warning { background: #fff3e0; }
        .alert-info { background: #e3f2fd; }

        .badge-resolved { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 8px; background: #e8f5e9; color: #2e7d32; font-weight: 700; }
        .badge-open { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 8px; background: #ffebee; color: #c62828; font-weight: 700; }

        .contrast-box { border: 1px solid #b2dfdb; border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; background: #f5fffe; }
        .contrast-box .ctitle { font-size: 11px; font-weight: 700; color: #00695c; margin-bottom: 8px; }
        .contrast-row { display: flex; justify-content: space-between; font-size: 10px; padding: 4px 0; border-bottom: 1px solid #e0f2f1; }
        .contrast-row:last-child { border-bottom: none; }

        .no-data { text-align: center; color: #888; padding: 20px; font-style: italic; font-size: 10px; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 9px; color: #aaa; border-top: 1px solid #eee; padding-top: 6px; }
        .note { font-size: 9px; color: #888; font-style: italic; margin-top: 12px; }
        .clear { clear: both; }
    </style>
</head>
<body>

{{-- ── HEADER ────────────────────────────────────────────────────────────── --}}
<div class="header">
    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
        <div>
            <h1>REPORTE DE TELEMETRÍA Y DATOS IoT</h1>
            <p class="subtitle">ZIA Carbon Control — Monitoreo Continuo de Emisiones</p>
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
            <td class="lbl">Período</td>
            <td><strong>{{ $period->year }}</strong></td>
            <td class="lbl">Rango</td>
            <td>{{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</td>
        </tr>
    </table>
</div>

{{-- ── KPI CARDS ─────────────────────────────────────────────────────────── --}}
<table class="kpi-row">
    <tr>
        <td>
            <div class="kpi-card iot">
                <div class="kpi-title">Dispositivos Activos</div>
                <div class="kpi-value">{{ $byDevice->count() }}</div>
                <div class="kpi-unit">equipos IoT</div>
            </div>
        </td>
        <td>
            <div class="kpi-card">
                <div class="kpi-title">Lecturas Totales</div>
                <div class="kpi-value">{{ number_format($readings->count()) }}</div>
                <div class="kpi-unit">registros</div>
            </div>
        </td>
        <td>
            <div class="kpi-card">
                <div class="kpi-title">Alertas Generadas</div>
                <div class="kpi-value" style="{{ $alerts->count() > 0 ? 'color:#c62828;' : '' }}">{{ $alerts->count() }}</div>
                <div class="kpi-unit">alertas</div>
            </div>
        </td>
        <td>
            <div class="kpi-card">
                <div class="kpi-title">Métricas Monitoreadas</div>
                <div class="kpi-value">{{ $metricStats->count() }}</div>
                <div class="kpi-unit">tipos de métrica</div>
            </div>
        </td>
    </tr>
</table>
<div class="clear"></div>

{{-- ── ESTADÍSTICAS POR MÉTRICA ─────────────────────────────────────────── --}}
<div class="section-title">Estadísticas por Métrica de Medición</div>
@if($metricStats->count() > 0)
<table class="data">
    <thead>
        <tr>
            <th>Métrica</th>
            <th style="text-align:right;">N° Lecturas</th>
            <th style="text-align:right;">Mínimo</th>
            <th style="text-align:right;">Máximo</th>
            <th style="text-align:right;">Promedio</th>
            <th style="text-align:right;">Suma Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($metricStats as $m)
        <tr>
            <td><strong>{{ $m['metric'] }}</strong></td>
            <td class="num">{{ number_format($m['count']) }}</td>
            <td class="num">{{ number_format($m['min'], 4) }}</td>
            <td class="num">{{ number_format($m['max'], 4) }}</td>
            <td class="num">{{ number_format($m['avg'], 4) }}</td>
            <td class="num">{{ number_format($m['sum'], 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@else
<p class="no-data">No se registraron lecturas IoT durante este período.</p>
@endif

{{-- ── RESUMEN POR DISPOSITIVO ──────────────────────────────────────────── --}}
@if($byDevice->count() > 0)
<div class="section-title">Actividad por Dispositivo</div>
<table class="data">
    <thead>
        <tr>
            <th>Dispositivo</th>
            <th style="text-align:right;">Lecturas</th>
            <th>Métricas Registradas</th>
        </tr>
    </thead>
    <tbody>
        @foreach($byDevice as $d)
        <tr>
            <td><strong>{{ $d['name'] }}</strong></td>
            <td class="num">{{ number_format($d['count']) }}</td>
            <td>{{ $d['metrics'] ?: '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- ── ALERTAS ──────────────────────────────────────────────────────────── --}}
<div class="section-title">Alertas del Período <span class="section-ref">(máx. 50 más recientes)</span></div>
@if($alerts->count() > 0)
<table class="data">
    <thead>
        <tr>
            <th>Fecha/Hora</th>
            <th>Dispositivo</th>
            <th>Métrica</th>
            <th>Nivel</th>
            <th>Valor</th>
            <th style="text-align:center;">Estado</th>
        </tr>
    </thead>
    <tbody>
        @foreach($alerts as $alert)
        <tr class="{{ $alert->severity === 'critical' ? 'alert-critical' : ($alert->severity === 'warning' ? 'alert-warning' : 'alert-info') }}">
            <td>{{ \Carbon\Carbon::parse($alert->detected_at)->format('d/m/Y H:i') }}</td>
            <td>{{ $alert->device?->name ?? '—' }}</td>
            <td>{{ $alert->alert_type ?? '—' }}</td>
            <td>{{ ucfirst($alert->severity ?? 'info') }}</td>
            <td class="num">{{ $alert->actual_value ?? '—' }}</td>
            <td class="center">
                @if($alert->resolved)
                    <span class="badge-resolved">Resuelto</span>
                @else
                    <span class="badge-open">Abierto</span>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@else
<p class="no-data">No se generaron alertas durante este período.</p>
@endif

{{-- ── CONTRASTE IOT vs. DECLARADO ─────────────────────────────────────── --}}
<div class="section-title">Contraste IoT vs. Emisiones Declaradas</div>
<div class="contrast-box">
    <div class="ctitle">Inventario GHG del período {{ $period->year }}</div>
    <div class="contrast-row">
        <span>Emisiones declaradas (inventario manual + IA)</span>
        <strong>{{ number_format($declaredCo2e, 2) }} tCO₂e</strong>
    </div>
    <div class="contrast-row">
        <span>Lecturas IoT capturadas</span>
        <strong>{{ number_format($readings->count()) }} registros de {{ $metricStats->count() }} métrica(s)</strong>
    </div>
    <div class="contrast-row">
        <span>Alertas de calidad de datos</span>
        <strong>{{ $alerts->count() }} alerta(s) en el período</strong>
    </div>
</div>

<p class="note">Las lecturas IoT reflejan datos de sensores en tiempo real capturados por dispositivos registrados en ZIA Carbon Control.
Para una cuantificación de emisiones basada en IoT (ej. consumo eléctrico desde medidores inteligentes), los factores de emisión
correspondientes deben estar configurados en el Motor de Cálculo y las emisiones asociadas calculadas a través del módulo de registro.</p>

<div class="footer">ZIA Carbon Control — Reporte IoT — {{ $period->company->name }} — {{ now()->format('d/m/Y') }}</div>
</body>
</html>
