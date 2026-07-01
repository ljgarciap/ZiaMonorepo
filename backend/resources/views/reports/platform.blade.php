<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Informe Global de Plataforma ZIA</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Helvetica Neue', Arial, sans-serif; }
  body { font-size: 11px; color: #1e293b; background: #fff; padding: 32px; }
  .cover { text-align: center; padding: 60px 0 40px; border-bottom: 3px solid #1a237e; margin-bottom: 36px; }
  .cover h1 { font-size: 26px; font-weight: 800; color: #1a237e; letter-spacing: -0.02em; margin-bottom: 8px; }
  .cover .subtitle { font-size: 13px; color: #64748b; }
  .cover .generated { margin-top: 16px; font-size: 10px; color: #94a3b8; }
  h2 { font-size: 14px; font-weight: 700; color: #1a237e; margin-bottom: 14px; padding-bottom: 6px; border-bottom: 2px solid #e2e8f0; }
  .section { margin-bottom: 32px; }
  .kpi-row { display: flex; gap: 12px; margin-bottom: 24px; }
  .kpi-box { flex: 1; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; text-align: center; }
  .kpi-box .kpi-val { font-size: 22px; font-weight: 800; color: #1a237e; }
  .kpi-box .kpi-lbl { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: .05em; margin-top: 3px; }
  table { width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 16px; }
  th { background: #1a237e; color: white; padding: 6px 10px; text-align: left; font-weight: 700; font-size: 9px; text-transform: uppercase; letter-spacing: .04em; }
  td { padding: 6px 10px; border-bottom: 1px solid #f1f5f9; }
  tr:nth-child(even) td { background: #f8fafc; }
  .badge-scope1 { background: #dbeafe; color: #1d4ed8; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 9px; }
  .badge-scope2 { background: #d1fae5; color: #065f46; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 9px; }
  .badge-scope3 { background: #fef3c7; color: #92400e; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 9px; }
  .trend-up { color: #dc2626; font-weight: 700; }
  .trend-down { color: #16a34a; font-weight: 700; }
  .footer { text-align: center; margin-top: 40px; padding-top: 16px; border-top: 1px solid #e2e8f0; font-size: 9px; color: #94a3b8; }
  .bar-wrap { background: #e2e8f0; border-radius: 3px; height: 8px; width: 100%; }
  .bar { background: #1a237e; height: 8px; border-radius: 3px; }
</style>
</head>
<body>

<div class="cover">
  <h1>Informe Global de Emisiones — Plataforma ZIA</h1>
  <div class="subtitle">Consolidado multiorganización · Protocolo GHG</div>
  <div class="generated">Generado: {{ now()->format('d/m/Y H:i') }}</div>
</div>

<!-- RESUMEN EJECUTIVO -->
<div class="section">
  <h2>Resumen Ejecutivo</h2>
  <div class="kpi-row">
    <div class="kpi-box">
      <div class="kpi-val">{{ number_format($stats['companies']['active']) }}</div>
      <div class="kpi-lbl">Organizaciones activas</div>
    </div>
    <div class="kpi-box">
      <div class="kpi-val">{{ number_format($stats['emissions']['total_co2e'], 1) }}</div>
      <div class="kpi-lbl">tCO₂e totales</div>
    </div>
    <div class="kpi-box">
      <div class="kpi-val">{{ number_format($stats['periods']['open']) }}</div>
      <div class="kpi-lbl">Períodos abiertos</div>
    </div>
    <div class="kpi-box">
      <div class="kpi-val">{{ number_format($stats['users']['total']) }}</div>
      <div class="kpi-lbl">Usuarios registrados</div>
    </div>
    <div class="kpi-box">
      <div class="kpi-val">{{ number_format($stats['iot']['devices']) }}</div>
      <div class="kpi-lbl">Dispositivos IoT</div>
    </div>
  </div>
</div>

<!-- TOP ORGANIZACIONES -->
@if(count($stats['top_companies']))
<div class="section">
  <h2>Top Organizaciones por Emisiones ({{ now()->year }})</h2>
  <table>
    <tr>
      <th>#</th>
      <th>Organización</th>
      <th style="text-align:right;">tCO₂e</th>
      <th>% del Total</th>
    </tr>
    @php $topTotal = collect($stats['top_companies'])->sum('total_co2e'); @endphp
    @foreach($stats['top_companies'] as $i => $c)
    <tr>
      <td>{{ $i + 1 }}</td>
      <td>{{ $c['name'] }}</td>
      <td style="text-align:right; font-weight:700;">{{ number_format($c['total_co2e'], 1) }}</td>
      <td>
        @php $pct = $topTotal > 0 ? round($c['total_co2e']/$topTotal*100, 1) : 0; @endphp
        <div style="display:flex;align-items:center;gap:6px;">
          <div class="bar-wrap"><div class="bar" style="width:{{ $pct }}%;"></div></div>
          {{ $pct }}%
        </div>
      </td>
    </tr>
    @endforeach
  </table>
</div>
@endif

<!-- TENDENCIA AÑO A AÑO -->
@if(count($stats['emissions_trend']))
<div class="section">
  <h2>Tendencia de Emisiones — Plataforma Consolidada</h2>
  <table>
    <tr>
      <th>Año</th>
      <th style="text-align:right;">tCO₂e</th>
      <th>Variación vs. año anterior</th>
    </tr>
    @foreach($stats['emissions_trend'] as $i => $t)
    @php
      $prev = $i > 0 ? $stats['emissions_trend'][$i-1]['total_co2e'] : null;
      $delta = $prev ? $t['total_co2e'] - $prev : null;
      $deltaPct = ($prev && $prev > 0) ? round($delta/$prev*100, 1) : null;
    @endphp
    <tr>
      <td>{{ $t['year'] }}</td>
      <td style="text-align:right; font-weight:700;">{{ number_format($t['total_co2e'], 1) }}</td>
      <td>
        @if($delta !== null)
          <span class="{{ $delta > 0 ? 'trend-up' : 'trend-down' }}">
            {{ $delta > 0 ? '▲' : '▼' }} {{ number_format(abs($delta), 1) }} tCO₂e ({{ abs($deltaPct) }}%)
          </span>
        @else
          <span style="color:#94a3b8;">Año base</span>
        @endif
      </td>
    </tr>
    @endforeach
  </table>
</div>
@endif

<!-- DESGLOSE POR ALCANCE -->
@if(count($scopeBreakdown))
<div class="section">
  <h2>Desglose por Alcance — Total Plataforma</h2>
  <table>
    <tr>
      <th>Alcance</th>
      <th>Descripción</th>
      <th style="text-align:right;">tCO₂e</th>
      <th style="text-align:right;">% Total</th>
    </tr>
    @php $totalScope = collect($scopeBreakdown)->sum('total_co2e'); @endphp
    @foreach($scopeBreakdown as $scope)
    <tr>
      <td><span class="badge-scope{{ $scope['scope_number'] }}">Alcance {{ $scope['scope_number'] }}</span></td>
      <td>{{ $scope['scope_name'] }}</td>
      <td style="text-align:right; font-weight:700;">{{ number_format($scope['total_co2e'], 1) }}</td>
      <td style="text-align:right;">{{ $totalScope > 0 ? number_format($scope['total_co2e']/$totalScope*100, 1) : 0 }}%</td>
    </tr>
    @endforeach
  </table>
</div>
@endif

<div class="footer">
  Generado por ZIA Carbon Control · Protocolo GHG (WBCSD/WRI) ·
  Este informe es de uso exclusivo interno. Datos a {{ now()->format('d/m/Y') }}.
</div>
</body>
</html>
