import { ChangeDetectorRef, Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatInputModule } from '@angular/material/input';
import { MatFormFieldModule } from '@angular/material/form-field';
import { FormsModule } from '@angular/forms';
import { AdminService } from '../../../services/admin.service';

@Component({
  selector: 'app-iot-devices',
  standalone: true,
  imports: [
    CommonModule,
    MatTableModule,
    MatButtonModule,
    MatIconModule,
    MatTooltipModule,
    MatProgressSpinnerModule,
    MatInputModule,
    MatFormFieldModule,
    FormsModule,
  ],
  template: `
<div class="management-page">
  <div class="header-section">
    <div class="title-group">
      <h1>Seguimiento de Dispositivos IoT</h1>
      <p class="subtitle">Estado operativo en tiempo real de todos los sensores y dispositivos conectados en la plataforma.</p>
    </div>
    <button mat-flat-button class="btn-refresh" (click)="loadData()">
      <mat-icon>refresh</mat-icon> Actualizar
    </button>
  </div>

  <div class="spinner-wrap" *ngIf="loading()">
    <mat-spinner diameter="44"></mat-spinner>
  </div>

  <ng-container *ngIf="!loading() && data() as data">
    <!-- KPI cards -->
    <div class="kpi-row">
      <div class="kpi-card">
        <mat-icon class="kpi-icon" style="color:#1a237e;">devices</mat-icon>
        <div class="kpi-body">
          <span class="kpi-lbl">Total dispositivos</span>
          <span class="kpi-val">{{ data.totals.devices }}</span>
        </div>
      </div>
      <div class="kpi-card online">
        <mat-icon class="kpi-icon" style="color:#16a34a;">wifi</mat-icon>
        <div class="kpi-body">
          <span class="kpi-lbl">En línea</span>
          <span class="kpi-val">{{ data.totals.online }}</span>
        </div>
      </div>
      <div class="kpi-card offline">
        <mat-icon class="kpi-icon" style="color:#9ca3af;">wifi_off</mat-icon>
        <div class="kpi-body">
          <span class="kpi-lbl">Fuera de línea</span>
          <span class="kpi-val">{{ data.totals.offline }}</span>
        </div>
      </div>
      <div class="kpi-card" [class.warning-card]="data.totals.warning > 0">
        <mat-icon class="kpi-icon" style="color:#d97706;">warning</mat-icon>
        <div class="kpi-body">
          <span class="kpi-lbl">Con alertas</span>
          <span class="kpi-val">{{ data.totals.warning }}</span>
        </div>
      </div>
    </div>

    <!-- Filtro de búsqueda -->
    <div class="search-bar">
      <mat-icon class="search-icon">search</mat-icon>
      <input class="search-input" [(ngModel)]="filterText" (ngModelChange)="applyFilter()" placeholder="Buscar por dispositivo, empresa, tipo...">
    </div>

    <!-- Tabla de dispositivos -->
    <div class="glass-card">
      <table mat-table [dataSource]="dataSource" class="iot-table">

        <ng-container matColumnDef="status">
          <th mat-header-cell *matHeaderCellDef>Estado</th>
          <td mat-cell *matCellDef="let d">
            <span class="status-dot" [ngClass]="d.status"
              [matTooltip]="d.status === 'online' ? 'En línea — último dato hace menos de 24h' : (d.status === 'warning' ? 'Alertas activas sin resolver' : 'Sin datos recientes')">
            </span>
            <span class="status-label" [ngClass]="d.status">
              {{ d.status === 'online' ? 'En línea' : (d.status === 'warning' ? 'Alerta' : 'Fuera de línea') }}
            </span>
          </td>
        </ng-container>

        <ng-container matColumnDef="name">
          <th mat-header-cell *matHeaderCellDef>Dispositivo</th>
          <td mat-cell *matCellDef="let d">
            <div class="device-cell">
              <mat-icon class="device-icon">{{ getDeviceIcon(d.type) }}</mat-icon>
              <div>
                <div class="device-name">{{ d.name }}</div>
                <div class="device-type">{{ d.type }} · {{ d.location || 'Sin ubicación' }}</div>
              </div>
            </div>
          </td>
        </ng-container>

        <ng-container matColumnDef="company">
          <th mat-header-cell *matHeaderCellDef>Empresa</th>
          <td mat-cell *matCellDef="let d">
            <span class="company-chip">{{ d.company_name }}</span>
          </td>
        </ng-container>

        <ng-container matColumnDef="last_seen">
          <th mat-header-cell *matHeaderCellDef>Última Lectura</th>
          <td mat-cell *matCellDef="let d">
            <span *ngIf="d.last_seen" class="last-seen">
              {{ d.last_seen | date:'dd/MM/yyyy HH:mm' }}
              <span class="metric-hint" *ngIf="d.last_metric">
                — {{ d.last_metric }}: {{ d.last_value }}
              </span>
            </span>
            <span *ngIf="!d.last_seen" class="no-data">Sin lecturas</span>
          </td>
        </ng-container>

        <ng-container matColumnDef="readings">
          <th mat-header-cell *matHeaderCellDef>Lecturas</th>
          <td mat-cell *matCellDef="let d">
            <span class="reading-count">{{ d.total_readings | number }}</span>
          </td>
        </ng-container>

        <ng-container matColumnDef="alerts">
          <th mat-header-cell *matHeaderCellDef>Alertas</th>
          <td mat-cell *matCellDef="let d">
            <span class="alert-badge" *ngIf="d.pending_alerts > 0">
              ⚠ {{ d.pending_alerts }}
            </span>
            <span class="no-alerts" *ngIf="d.pending_alerts === 0">✓</span>
          </td>
        </ng-container>

        <tr mat-header-row *matHeaderRowDef="displayedColumns"></tr>
        <tr mat-row *matRowDef="let row; columns: displayedColumns;" [ngClass]="'row-' + row.status"></tr>

        <tr class="mat-row empty-row" *matNoDataRow>
          <td class="mat-cell" colspan="6">
            <p style="text-align:center; padding: 40px; color: var(--prestige-text-muted);">
              {{ filterText ? 'Sin resultados para "' + filterText + '"' : 'No hay dispositivos IoT registrados.' }}
            </p>
          </td>
        </tr>
      </table>
    </div>

    <!-- Resumen por empresa -->
    <div class="section-title" style="margin-top: 32px;">
      <mat-icon>leaderboard</mat-icon> Resumen por Empresa
    </div>
    <div class="summary-grid">
      <div class="summary-card glass-card" *ngFor="let s of data.summary">
        <div class="summary-name">{{ s.company_name }}</div>
        <div class="summary-stats">
          <span><mat-icon>devices</mat-icon> {{ s.device_count }} dispositivos</span>
          <span [class.has-alert]="s.alert_count > 0"><mat-icon>warning</mat-icon> {{ s.alert_count }} alertas</span>
        </div>
      </div>
      <div class="summary-card glass-card no-company" *ngIf="!data.summary.length">
        <p>Sin datos por empresa disponibles.</p>
      </div>
    </div>
  </ng-container>
</div>
  `,
  styles: [`
    .management-page { padding: 24px; max-width: 1400px; margin: 0 auto; }
    .header-section { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 28px; }
    .title-group h1 { font-size: 28px; font-weight: 600; color: var(--prestige-primary); margin: 0 0 4px; }
    .subtitle { color: var(--prestige-text-muted); font-size: 13px; margin: 0; }
    .btn-refresh { background: var(--prestige-primary); color: white; border-radius: 10px; height: 42px; }
    .spinner-wrap { display: flex; justify-content: center; padding: 80px; }

    .kpi-row { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
    .kpi-card {
      flex: 1; min-width: 160px;
      background: rgba(255,255,255,0.9); border: 1px solid var(--prestige-border);
      border-radius: 14px; padding: 16px 20px; display: flex; align-items: center; gap: 12px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    }
    .kpi-card.warning-card { border-color: #f59e0b; background: #fffbeb; }
    .kpi-icon { font-size: 28px; width: 28px; height: 28px; }
    .kpi-body { display: flex; flex-direction: column; }
    .kpi-lbl { font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--prestige-text-muted); }
    .kpi-val { font-size: 24px; font-weight: 800; color: var(--prestige-text); }

    .search-bar {
      display: flex; align-items: center;
      background: rgba(255,255,255,0.03); border: 1px solid var(--prestige-border);
      border-radius: 10px; padding: 8px 16px; margin-bottom: 20px;
    }
    .search-icon { color: var(--prestige-text-muted); margin-right: 8px; font-size: 20px; }
    .search-input { border: none; background: transparent; color: var(--prestige-text); font-size: 14px; outline: none; flex: 1; }

    .glass-card {
      background: rgba(255,255,255,0.9); border: 1px solid var(--prestige-border);
      border-radius: 14px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    }
    .iot-table { width: 100%; background: transparent !important; }
    .iot-table th { font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--prestige-text-muted); }

    .status-dot {
      display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px;
    }
    .status-dot.online { background: #16a34a; box-shadow: 0 0 6px #16a34a88; }
    .status-dot.warning { background: #d97706; box-shadow: 0 0 6px #d9770688; }
    .status-dot.offline { background: #9ca3af; }
    .status-label { font-size: 12px; font-weight: 600; }
    .status-label.online { color: #16a34a; }
    .status-label.warning { color: #d97706; }
    .status-label.offline { color: #9ca3af; }

    .row-warning { background: rgba(253, 230, 138, 0.08) !important; }

    .device-cell { display: flex; align-items: center; gap: 10px; padding: 6px 0; }
    .device-icon { color: var(--prestige-primary); font-size: 22px; width: 22px; height: 22px; }
    .device-name { font-weight: 600; font-size: 13px; color: var(--prestige-text); }
    .device-type { font-size: 11px; color: var(--prestige-text-muted); }

    .company-chip {
      background: var(--status-info-bg); color: var(--status-info-text);
      border-radius: 12px; padding: 2px 10px; font-size: 11px; font-weight: 600;
    }

    .last-seen { font-size: 12px; color: var(--prestige-text-muted); }
    .metric-hint { font-weight: 600; color: var(--prestige-text); }
    .no-data { font-size: 12px; color: var(--prestige-text-muted); font-style: italic; }
    .reading-count { font-weight: 700; font-size: 13px; color: var(--prestige-text); }

    .alert-badge {
      background: #fef3c7; color: #d97706; border: 1px solid #fde68a;
      border-radius: 12px; padding: 2px 8px; font-size: 11px; font-weight: 700;
    }
    .no-alerts { color: #16a34a; font-size: 16px; font-weight: 700; }

    .section-title {
      display: flex; align-items: center; gap: 8px;
      font-size: 14px; font-weight: 700; color: var(--prestige-primary);
      margin-bottom: 16px;
    }
    .section-title mat-icon { font-size: 18px; width: 18px; height: 18px; }

    .summary-grid { display: flex; gap: 16px; flex-wrap: wrap; }
    .summary-card { padding: 16px 20px; flex: 1; min-width: 200px; }
    .summary-name { font-weight: 700; font-size: 14px; color: var(--prestige-text); margin-bottom: 8px; }
    .summary-stats { display: flex; gap: 16px; }
    .summary-stats span { display: flex; align-items: center; gap: 4px; font-size: 12px; color: var(--prestige-text-muted); }
    .summary-stats span mat-icon { font-size: 14px; width: 14px; height: 14px; }
    .summary-stats .has-alert { color: #d97706; font-weight: 600; }
    .no-company { text-align: center; padding: 20px; color: var(--prestige-text-muted); font-size: 13px; }
  `]
})
export class IotDevicesComponent implements OnInit {
  private adminService = inject(AdminService);
  private cdr = inject(ChangeDetectorRef);

  data = signal<any>(null);
  loading = signal(true);
  filterText = '';
  dataSource = new MatTableDataSource<any>([]);
  displayedColumns = ['status', 'name', 'company', 'last_seen', 'readings', 'alerts'];

  ngOnInit() {
    this.loadData();
  }

  loadData() {
    this.loading.set(true);
    this.adminService.getIotDevicesOverview().subscribe({
      next: (res) => {
        this.data.set(res);
        this.dataSource.data = res.devices || [];
        this.cdr.markForCheck();
        this.loading.set(false);
      },
      error: () => { this.loading.set(false); }
    });
  }

  applyFilter() {
    this.dataSource.filter = this.filterText.trim().toLowerCase();
  }

  getDeviceIcon(type: string): string {
    switch ((type || '').toLowerCase()) {
      case 'electricity_meter': return 'electric_meter';
      case 'gas_meter': return 'gas_meter';
      case 'temperature': return 'thermostat';
      case 'flow_meter': return 'water';
      default: return 'sensors';
    }
  }
}
