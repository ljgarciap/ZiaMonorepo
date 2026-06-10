import { Component, inject, OnInit, OnDestroy, signal, computed, ViewChild, ElementRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { Chart, registerables } from 'chart.js';
import { ContextService } from '../../services/context.service';
import { interval, Subscription } from 'rxjs';

Chart.register(...registerables);

interface TelemetryReading {
  metric_name: string;
  value: number;
  timestamp: string;
  device_name: string;
}

interface TelemetryAlert {
  id: number;
  alert_type: string;
  severity: 'warning' | 'critical';
  message: string;
  threshold_value: number;
  actual_value: number;
  detected_at: string;
  resolved: boolean;
}

@Component({
  selector: 'app-zia-live',
  standalone: true,
  imports: [
    CommonModule,
    MatCardModule,
    MatIconModule,
    MatButtonModule,
    MatProgressSpinnerModule
  ],
  template: `
    <div class="zia-live-container">
      <div class="header-section">
        <div class="badge-row">
          <span class="live-indicator">
            <span class="dot"></span> LIVE TRANSMISSION
          </span>
        </div>
        <h1>Zia Live Telemetría</h1>
        <p>Monitoreo de telemetría IoT y optimización energética en tiempo real en ECONOVA.</p>
      </div>

      <!-- Operational State Banner -->
      <div class="status-banner glass-card" [ngClass]="systemState()">
        <mat-icon class="status-icon">{{systemState() === 'optimal' ? 'check_circle' : 'warning'}}</mat-icon>
        <div class="status-info">
          <h2>Estado del Edificio: {{systemState() === 'optimal' ? 'Eficiencia Óptima' : 'Alerta de Ineficiencia Detéctada'}}</h2>
          <p>{{systemState() === 'optimal' ? 'Todos los subsistemas eléctricos y sanitarios operan dentro de los rangos de eficiencia estipulados.' : 'Se registran consumos inusuales de energía o agua fuera de horario laboral. Revise el panel de alertas.'}}</p>
        </div>
      </div>

      <!-- Realtime KPI Cards -->
      <div class="live-grid">
        <div class="glass-card kpi-card">
          <div class="kpi-header">
            <span class="kpi-title">Carga Eléctrica Actual</span>
            <mat-icon class="kpi-icon energy">bolt</mat-icon>
          </div>
          <div class="kpi-value">
            <span class="val-num">{{latestEnergyValue() | number:'1.1-1'}}</span>
            <span class="val-unit">kWh</span>
          </div>
          <div class="kpi-footer">
            <span>Última lectura: {{latestEnergyTime() | date:'HH:mm:ss'}}</span>
          </div>
        </div>

        <div class="glass-card kpi-card">
          <div class="kpi-header">
            <span class="kpi-title">Flujo de Agua Actual</span>
            <mat-icon class="kpi-icon water">water_drop</mat-icon>
          </div>
          <div class="kpi-value">
            <span class="val-num">{{latestWaterValue() | number:'1.2-2'}}</span>
            <span class="val-unit">m³</span>
          </div>
          <div class="kpi-footer">
            <span>Última lectura: {{latestWaterTime() | date:'HH:mm:ss'}}</span>
          </div>
        </div>
      </div>

      <!-- Telemetry Charts -->
      <div class="chart-section glass-card">
        <h3>Monitoreo de Telemetría (Últimas 10 Lecturas)</h3>
        <div class="chart-container">
          <canvas #telemetryChart></canvas>
        </div>
      </div>

      <!-- Active Alerts Logs -->
      <div class="alerts-section glass-card">
        <div class="alerts-header">
          <h3>Alertas Autónomas de Ineficiencia (Fuera de Horario)</h3>
          <button mat-stroked-button color="primary" (click)="loadLiveTelemetry()">
            <mat-icon>refresh</mat-icon> ACTUALIZAR
          </button>
        </div>

        <div class="alerts-list">
          <div class="alert-item" *ngFor="let alert of activeAlerts()" [ngClass]="alert.severity">
            <mat-icon class="alert-icon">{{alert.severity === 'critical' ? 'dangerous' : 'warning'}}</mat-icon>
            <div class="alert-details">
              <span class="alert-time">{{alert.detected_at | date:'dd/MM/yyyy HH:mm:ss'}}</span>
              <p class="alert-msg">{{alert.message}}</p>
              <div class="alert-metrics">
                <span>Registrado: <strong>{{alert.actual_value}}</strong> vs. Umbral Máximo: <strong>{{alert.threshold_value}}</strong></span>
              </div>
            </div>
            <span class="severity-badge">{{alert.severity | uppercase}}</span>
          </div>

          <div class="empty-alerts" *ngIf="activeAlerts().length === 0">
            <mat-icon>verified</mat-icon>
            <p>No se registran alertas operativas. El edificio opera al 100% de eficiencia.</p>
          </div>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .zia-live-container { padding: 24px; max-width: 1200px; margin: 0 auto; font-family: 'Outfit', sans-serif; }
    .header-section { margin-bottom: 24px; }
    .badge-row { margin-bottom: 8px; }
    .live-indicator { background: rgba(216, 27, 96, 0.1); color: #d81b60; font-size: 10px; font-weight: 700; padding: 4px 10px; border-radius: 20px; display: inline-flex; align-items: center; gap: 6px; letter-spacing: 0.5px; }
    .live-indicator .dot { width: 6px; height: 6px; background: #d81b60; border-radius: 50%; display: inline-block; animation: pulse 1.5s infinite; }
    .header-section h1 { font-size: 28px; font-weight: 700; color: var(--prestige-primary); margin: 0; }
    .header-section p { color: var(--prestige-text-muted); margin-top: 6px; }

    .glass-card { 
      background: rgba(255, 255, 255, 0.95); 
      border: 1px solid var(--prestige-border); 
      box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.05); 
      border-radius: 16px; 
      padding: 24px;
      margin-bottom: 24px;
    }
    :host-context(.dark-theme) .glass-card {
      background: var(--prestige-card-bg);
      border-color: var(--prestige-border);
    }

    .status-banner { display: flex; align-items: center; gap: 16px; border-left: 6px solid; }
    .status-banner.optimal { border-left-color: #00897b; background: rgba(0, 137, 123, 0.03); }
    .status-banner.inefficient { border-left-color: #d81b60; background: rgba(216, 27, 96, 0.03); }
    .status-icon { font-size: 36px; width: 36px; height: 36px; }
    .status-banner.optimal .status-icon { color: #00897b; }
    .status-banner.inefficient .status-icon { color: #d81b60; }
    .status-info h2 { margin: 0; font-size: 16px; font-weight: 600; color: var(--prestige-text); }
    .status-info p { margin: 4px 0 0 0; font-size: 12px; color: var(--prestige-text-muted); }

    .live-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 24px; }
    .kpi-card { padding: 20px; display: flex; flex-direction: column; justify-content: space-between; }
    .kpi-header { display: flex; justify-content: space-between; align-items: center; }
    .kpi-title { font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--prestige-text-muted); }
    .kpi-icon { padding: 8px; border-radius: 8px; font-size: 20px; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; }
    .kpi-icon.energy { background: rgba(255, 152, 0, 0.1); color: #ff9800; }
    .kpi-icon.water { background: rgba(33, 150, 243, 0.1); color: #2196f3; }
    .kpi-value { margin: 16px 0; display: flex; align-items: baseline; gap: 6px; }
    .val-num { font-size: 32px; font-weight: 800; color: var(--prestige-text); }
    .val-unit { font-size: 14px; font-weight: 600; color: var(--prestige-text-muted); }
    .kpi-footer { font-size: 10px; color: var(--prestige-text-muted); }

    .chart-section h3 { font-size: 15px; font-weight: 600; margin-bottom: 16px; color: var(--prestige-text); }
    .chart-container { height: 260px; position: relative; }

    .alerts-section .alerts-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .alerts-section h3 { font-size: 15px; font-weight: 600; margin: 0; color: var(--prestige-text); }
    .alerts-list { display: flex; flex-direction: column; gap: 12px; }

    .alert-item { display: flex; align-items: center; gap: 14px; padding: 16px; border-radius: 12px; border: 1px solid; }
    .alert-item.warning { background: rgba(255, 152, 0, 0.03); border-color: rgba(255, 152, 0, 0.2); }
    .alert-item.critical { background: rgba(216, 27, 96, 0.03); border-color: rgba(216, 27, 96, 0.2); }
    .alert-icon { font-size: 24px; width: 24px; height: 24px; }
    .alert-item.warning .alert-icon { color: #ff9800; }
    .alert-item.critical .alert-icon { color: #d81b60; }
    .alert-details { flex-grow: 1; }
    .alert-time { font-size: 10px; font-weight: 600; color: var(--prestige-text-muted); }
    .alert-msg { font-size: 13px; font-weight: 500; margin: 4px 0; color: var(--prestige-text); }
    .alert-metrics { font-size: 11px; color: var(--prestige-text-muted); }
    .severity-badge { font-size: 8px; font-weight: 700; padding: 2px 6px; border-radius: 4px; color: white; }
    .alert-item.warning .severity-badge { background: #ff9800; }
    .alert-item.critical .severity-badge { background: #d81b60; }

    .empty-alerts { text-align: center; padding: 40px; color: var(--prestige-text-muted); display: flex; flex-direction: column; align-items: center; gap: 8px; }
    .empty-alerts mat-icon { font-size: 36px; width: 36px; height: 36px; color: #00897b; }

    @keyframes pulse {
      0% { transform: scale(0.9); opacity: 0.6; }
      50% { transform: scale(1.2); opacity: 1; }
      100% { transform: scale(0.9); opacity: 0.6; }
    }

    @media(max-width: 768px) {
      .live-grid { grid-template-columns: 1fr; }
      .status-banner { flex-direction: column; align-items: flex-start; }
    }
  `]
})
export class ZiaLiveComponent implements OnInit, OnDestroy {
  private http = inject(HttpClient);
  private context = inject(ContextService);

  @ViewChild('telemetryChart') chartCanvas!: ElementRef;

  // Reactivity state
  liveTelemetry = signal<TelemetryReading[]>([]);
  activeAlerts = signal<TelemetryAlert[]>([]);

  // Computed Values
  latestEnergy = computed(() => {
    return this.liveTelemetry().find(t => t.metric_name === 'electricity_kwh');
  });

  latestEnergyValue = computed(() => this.latestEnergy()?.value || 0.0);
  latestEnergyTime = computed(() => this.latestEnergy()?.timestamp || '');

  latestWater = computed(() => {
    return this.liveTelemetry().find(t => t.metric_name === 'water_m3');
  });

  latestWaterValue = computed(() => this.latestWater()?.value || 0.0);
  latestWaterTime = computed(() => this.latestWater()?.timestamp || '');

  systemState = computed(() => {
    return this.activeAlerts().length > 0 ? 'inefficient' : 'optimal';
  });

  private chartInst: any;
  private pollSub!: Subscription;

  ngOnInit() {
    this.loadLiveTelemetry();

    // Set up dynamic live polling (every 10 seconds in UI for live effects)
    this.pollSub = interval(10000).subscribe(() => {
      this.loadLiveTelemetry();
    });
  }

  ngOnDestroy() {
    if (this.pollSub) this.pollSub.unsubscribe();
    if (this.chartInst) this.chartInst.destroy();
  }

  loadLiveTelemetry() {
    const company = this.context.selectedCompany();
    if (!company) return;

    // Simulate backend fetch from DB, providing realistic telemetry logs
    // In production we query Laravel endpoints:
    // Route::get('/telemetry/live', ...)
    
    // We mock values matching standard readings
    const nowTime = new Date();
    const energyData: TelemetryReading[] = [];
    const waterData: TelemetryReading[] = [];

    for (let i = 9; i >= 0; i--) {
      const ts = new Date(nowTime.getTime() - i * 5 * 60 * 1000); // 5 min intervals
      const hour = ts.getHours();
      const isWorking = (hour >= 8 && hour < 18);
      
      // Energy
      energyData.push({
        metric_name: 'electricity_kwh',
        value: isWorking ? 60 + Math.random() * 15 : 10 + Math.random() * 5,
        timestamp: ts.toISOString(),
        device_name: 'Medidor Eléctrico ECONOVA'
      });

      // Water
      waterData.push({
        metric_name: 'water_m3',
        value: isWorking ? 1.2 + Math.random() * 0.4 : 0.05 + Math.random() * 0.08,
        timestamp: ts.toISOString(),
        device_name: 'Medidor de Agua Principal'
      });
    }

    const merged = [...energyData, ...waterData];
    this.liveTelemetry.set(merged);

    // Filter dynamic alerts
    // If night electricity was high, trigger warning
    const currentHour = nowTime.getHours();
    const currentDay = nowTime.getDay();
    const isOffHours = (currentDay === 0 || currentDay === 6 || currentHour >= 20 || currentHour < 6);

    const alerts: TelemetryAlert[] = [];
    if (isOffHours && Math.random() > 0.7) {
      alerts.push({
        id: 1,
        alert_type: 'off_hours_excess',
        severity: 'warning',
        message: 'Consumo eléctrico ineficiente detectado fuera de horario comercial en Edificio ECONOVA - Tablero Principal.',
        threshold_value: 25.0,
        actual_value: 38.5,
        detected_at: nowTime.toISOString(),
        resolved: false
      });
    }

    this.activeAlerts.set(alerts);

    setTimeout(() => this.updateChart(), 50);
  }

  updateChart() {
    if (!this.chartCanvas) return;

    try {
      const ctx = this.chartCanvas.nativeElement.getContext('2d');
      if (this.chartInst) this.chartInst.destroy();

      const energyReadings = this.liveTelemetry().filter(t => t.metric_name === 'electricity_kwh');
      const waterReadings = this.liveTelemetry().filter(t => t.metric_name === 'water_m3');

      const labels = energyReadings.map(t => {
        const d = new Date(t.timestamp);
        return `${d.getHours()}:${d.getMinutes().toString().padStart(2, '0')}`;
      });

      this.chartInst = new Chart(ctx, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [
            {
              label: 'Energía Activa (kWh)',
              data: energyReadings.map(t => t.value),
              borderColor: '#ff9800',
              backgroundColor: 'rgba(255, 152, 0, 0.1)',
              yAxisID: 'y_energy',
              tension: 0.3,
              fill: true
            },
            {
              label: 'Consumo de Agua (m³)',
              data: waterReadings.map(t => t.value),
              borderColor: '#2196f3',
              backgroundColor: 'rgba(33, 150, 243, 0.1)',
              yAxisID: 'y_water',
              tension: 0.3,
              fill: true
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y_energy: {
              type: 'linear',
              position: 'left',
              title: { display: true, text: 'Energía (kWh)', color: '#ff9800' }
            },
            y_water: {
              type: 'linear',
              position: 'right',
              title: { display: true, text: 'Agua (m³)', color: '#2196f3' },
              grid: { drawOnChartArea: false } // only want the grid lines for one axis
            }
          }
        }
      });
    } catch (e) {
      console.error(e);
    }
  }
}
