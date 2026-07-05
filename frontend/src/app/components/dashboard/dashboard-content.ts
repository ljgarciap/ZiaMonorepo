import { Component, inject, OnInit, ViewChild, ElementRef, AfterViewInit, OnDestroy, effect, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router'; // Added Router
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { ContextSelectorComponent } from '../context-selector/context-selector';
import { DashboardService } from '../../services/dashboard.service';
import { ContextService } from '../../services/context.service';
import { AuthService } from '../../services/auth'; // Added AuthService
import { AdminService } from '../../services/admin.service';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

import { MatMenuModule } from '@angular/material/menu';
import { MatButtonModule } from '@angular/material/button';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatDividerModule } from '@angular/material/divider';

@Component({
  selector: 'app-dashboard-content',
  standalone: true,
  imports: [
    CommonModule,
    MatCardModule,
    MatIconModule,
    MatTooltipModule,
    MatProgressSpinnerModule,
    MatMenuModule,
    MatButtonModule,
    MatProgressBarModule,
    MatDividerModule,
    ContextSelectorComponent
  ],
  template: `
<div class="dashboard-container">
    <div class="dashboard-header">
        <div class="flex-between">
            <div>
                <h1>ZIA Carbon Control</h1>
                <p>Huella de Carbono Corporativa</p>
            </div>
            
            <div class="header-actions">
                <button mat-stroked-button class="audit-btn" (click)="goToAudit()" *ngIf="isSuperAdmin">
                    <mat-icon>security</mat-icon>
                    AUDITORÍA
                </button>

                <button mat-flat-button color="primary" [matMenuTriggerFor]="reportMenu" *ngIf="selectedPeriod">
                    <mat-icon>download</mat-icon>
                    GENERAR REPORTES
                </button>
                <mat-menu #reportMenu="matMenu" class="prestige-menu">
                    <div mat-subheader style="font-size:10px;text-transform:uppercase;letter-spacing:.4px;">Reporte de Medición</div>
                    <button mat-menu-item (click)="onDownloadPdf()">
                        <mat-icon>picture_as_pdf</mat-icon>
                        <span>Inventario GHG — Resumen Ejecutivo (PDF)</span>
                    </button>
                    <button mat-menu-item (click)="onDownloadExcel()">
                        <mat-icon>table_view</mat-icon>
                        <span>Inventario GHG — Datos Completos (Excel)</span>
                    </button>
                    <mat-divider></mat-divider>
                    <div mat-subheader style="font-size:10px;text-transform:uppercase;letter-spacing:.4px;">Reporte de Avance</div>
                    <button mat-menu-item (click)="onDownloadProgress()">
                        <mat-icon>trending_down</mat-icon>
                        <span>Progreso de Reducción vs. Año Base (PDF)</span>
                    </button>
                    <mat-divider></mat-divider>
                    <div mat-subheader style="font-size:10px;text-transform:uppercase;letter-spacing:.4px;">Reporte IoT</div>
                    <button mat-menu-item (click)="onDownloadIot()">
                        <mat-icon>sensors</mat-icon>
                        <span>Telemetría y Datos IoT del Período (PDF)</span>
                    </button>
                    <ng-container *ngIf="isSuperAdmin">
                      <mat-divider></mat-divider>
                      <div mat-subheader style="font-size:10px;text-transform:uppercase;letter-spacing:.4px;">Informe Global</div>
                      <button mat-menu-item (click)="onDownloadPlatformReport()">
                        <mat-icon>public</mat-icon>
                        <span>Informe Consolidado Plataforma (PDF)</span>
                      </button>
                    </ng-container>
                </mat-menu>
                <app-context-selector></app-context-selector>
            </div>
        </div>
    </div>

    <!-- Empty State / No Context -->
    <div *ngIf="!selectedCompany || !selectedPeriod" class="empty-state-card glass-card">
        <mat-icon>info_outline</mat-icon>
        <p>Por favor, seleccione una empresa y un año para ver los resultados.</p>
    </div>

    <!-- Acceso denegado: el rol actual no tiene dashboard (ej. Técnico IoT) -->
    <div *ngIf="selectedCompany && selectedPeriod && accessDenied" class="empty-state-card glass-card">
        <mat-icon>block</mat-icon>
        <p>Tu rol no tiene acceso al Dashboard. Usa el menú lateral para ir a la sección que te corresponde.</p>
    </div>

    <div *ngIf="selectedCompany && selectedPeriod && !accessDenied">
        <!-- Top Summary Cards -->
        <div class="summary-grid">
            <div class="glass-card summary-card">
                <span class="card-title">{{summary?.scope === 'own' ? 'Mi Huella' : 'Huella Total'}}</span>
                <div class="card-value">
                    <span class="main-value">{{summary?.huella_total || 0 | number:'1.2-2'}}</span>
                    <span class="unit">tCO2e</span>
                </div>
                <div class="card-footer">
                    <span class="footer-label">Neutralizados</span>
                    <span class="footer-value">{{summary?.neutralizados || 0}} tCO2e</span>
                </div>
            </div>

            <div class="glass-card summary-card" *ngFor="let s of ['1','2','3']">
                <span class="card-title">Alcance {{s}}</span>
                <div class="card-value">
                    <span class="main-value">{{(summary?.alcances && summary.alcances['scope_'+s]?.total) ? (summary.alcances['scope_'+s].total | number:'1.2-2') : '0.00'}}</span>
                    <span class="unit">tCO2e</span>
                </div>
                <div class="card-footer">
                    <span class="footer-label">{{summary?.alcances ? summary.alcances['scope_'+s]?.percentage : 0}}% del total</span>
                    <span class="footer-value">{{summary?.alcances ? summary.alcances['scope_'+s]?.neutralizado : 0}} tCO2e</span>
                </div>
            </div>
        </div>

        <!-- A01: Panel de Completitud Administrativa (solo admin y superadmin) -->
        <div class="admin-panel" *ngIf="isAdminOrAbove && summary?.admin_panel as panel">
            <div class="admin-panel-header">
                <mat-icon class="panel-header-icon">admin_panel_settings</mat-icon>
                <h3>Panel de Completitud Administrativa</h3>
                <span class="panel-subtitle">Estado de registro de emisiones para el período seleccionado</span>
            </div>

            <div class="admin-panel-body">
                <!-- Progreso de registro -->
                <div class="glass-card progress-card">
                    <div class="progress-card-header">
                        <mat-icon class="progress-icon">how_to_reg</mat-icon>
                        <div>
                            <span class="progress-label">Usuarios que han registrado datos</span>
                            <span class="progress-fraction">
                                {{ panel.registration_progress.users_with_data }} / {{ panel.registration_progress.total_users }}
                            </span>
                        </div>
                        <span class="progress-pct" [class.pct-full]="panel.registration_progress.percentage === 100">
                            {{ panel.registration_progress.percentage }}%
                        </span>
                    </div>
                    <mat-progress-bar
                        mode="determinate"
                        [value]="panel.registration_progress.percentage"
                        [color]="panel.registration_progress.percentage === 100 ? 'accent' : 'primary'">
                    </mat-progress-bar>
                    <p class="progress-hint" *ngIf="panel.registration_progress.percentage < 100">
                        {{ panel.registration_progress.total_users - panel.registration_progress.users_with_data }}
                        usuario(s) aún no han registrado emisiones en este período.
                    </p>
                    <p class="progress-hint hint-ok" *ngIf="panel.registration_progress.percentage === 100">
                        Todos los usuarios han registrado datos. ✓
                    </p>
                </div>

                <div class="completeness-tables">
                    <!-- Por Unidad Operativa -->
                    <div class="glass-card completeness-table-card">
                        <h4 class="completeness-title">
                            <mat-icon>account_tree</mat-icon>
                            Emisiones por Unidad Operativa
                        </h4>
                        <div class="completeness-scroll" *ngIf="panel.by_unit?.length; else noUnitData">
                            <table class="completeness-mini-table">
                                <thead>
                                    <tr>
                                        <th>Unidad</th>
                                        <th>tCO₂e</th>
                                        <th>% del total</th>
                                        <th>Registros</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr *ngFor="let u of panel.by_unit">
                                        <td>
                                            <div class="unit-name-cell">
                                                <div class="unit-dot"></div>
                                                {{ u.unit_name }}
                                            </div>
                                        </td>
                                        <td><strong>{{ u.total_co2e | number:'1.2-2' }}</strong></td>
                                        <td>
                                            <div class="pct-bar-wrap">
                                                <div class="pct-bar" [style.width.%]="u.percentage"></div>
                                                <span class="pct-txt">{{ u.percentage }}%</span>
                                            </div>
                                        </td>
                                        <td class="text-muted">{{ u.entries }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <ng-template #noUnitData>
                            <p class="no-data-hint">Sin unidades operativas configuradas o sin registros.</p>
                        </ng-template>
                    </div>

                    <!-- Por Usuario -->
                    <div class="glass-card completeness-table-card">
                        <h4 class="completeness-title">
                            <mat-icon>people</mat-icon>
                            Emisiones por Usuario
                        </h4>
                        <div class="completeness-scroll" *ngIf="panel.by_user?.length; else noUserData">
                            <table class="completeness-mini-table">
                                <thead>
                                    <tr>
                                        <th>Usuario</th>
                                        <th>tCO₂e</th>
                                        <th>% del total</th>
                                        <th>Registros</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr *ngFor="let u of panel.by_user">
                                        <td>
                                            <div class="user-name-cell">
                                                <div class="user-avatar-sm">{{ u.user_name.charAt(0) }}</div>
                                                {{ u.user_name }}
                                            </div>
                                        </td>
                                        <td><strong>{{ u.total_co2e | number:'1.2-2' }}</strong></td>
                                        <td>
                                            <div class="pct-bar-wrap">
                                                <div class="pct-bar user-bar" [style.width.%]="u.percentage"></div>
                                                <span class="pct-txt">{{ u.percentage }}%</span>
                                            </div>
                                        </td>
                                        <td class="text-muted">{{ u.entries }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <ng-template #noUserData>
                            <p class="no-data-hint">Ningún usuario ha registrado emisiones aún.</p>
                        </ng-template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Intensity KPIs -->
        <div class="intensity-grid" *ngIf="summary?.intensidad_kpis">
            <div class="glass-card intensity-card">
                <mat-icon class="intensity-icon">square_foot</mat-icon>
                <div class="intensity-body">
                    <span class="card-title">Intensidad por Superficie</span>
                    <div class="card-value">
                        <span class="main-value">
                            {{summary.intensidad_kpis.tco2e_por_m2 != null ? (summary.intensidad_kpis.tco2e_por_m2 | number:'1.4-4') : 'N/D'}}
                        </span>
                        <span class="unit" *ngIf="summary.intensidad_kpis.tco2e_por_m2 != null">tCO2e/m²</span>
                    </div>
                </div>
            </div>
            <div class="glass-card intensity-card">
                <mat-icon class="intensity-icon">group</mat-icon>
                <div class="intensity-body">
                    <span class="card-title">Intensidad por Empleado</span>
                    <div class="card-value">
                        <span class="main-value">
                            {{summary.intensidad_kpis.tco2e_por_empleado != null ? (summary.intensidad_kpis.tco2e_por_empleado | number:'1.4-4') : 'N/D'}}
                        </span>
                        <span class="unit" *ngIf="summary.intensidad_kpis.tco2e_por_empleado != null">tCO2e/empleado</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Middle Section -->
        <div class="middle-grid">
            <div class="glass-card chart-card">
                <h3 class="chart-title">Distribución por Alcance</h3>
                <div class="donut-wrap">
                    <canvas #donutChart></canvas>
                    <div *ngIf="loading" class="spinner-overlay">
                        <mat-progress-spinner diameter="40" mode="indeterminate"></mat-progress-spinner>
                    </div>
                </div>
            </div>

            <div class="glass-card details-card">
                <div class="details-header">
                    <h3 class="chart-title">Detalles de Fuentes de Emisión</h3>
                </div>
                <div class="table-scroll">
                    <table class="prestige-mini-table">
                        <thead>
                            <tr>
                                <th>Alcance</th>
                                <th>Fuente</th>
                                <th>Total (tCO2e)</th>
                                <th>% del Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr *ngFor="let item of summary?.chart_data?.details">
                                <td><span class="scope-badge" [ngClass]="getScopeClass(item.scope)">Alcance {{item.scope}}</span></td>
                                <td>{{item.source}}</td>
                                <td><strong>{{item.total | number:'1.2-2'}}</strong></td>
                                <td class="text-muted">{{item.percentage}}%</td>
                            </tr>
                            <tr *ngIf="!summary?.chart_data?.details || summary?.chart_data?.details.length === 0">
                                <td colspan="4" class="text-center">No hay datos para mostrar</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="glass-card equivalency-card" *ngIf="summary?.equivalency">
                <span class="eq-title">Tu huella equivale a:</span>
                <span class="eq-value">{{summary.equivalency.value | number:'1.0-1'}}</span>
                <span class="eq-label">{{summary.equivalency.label}}</span>
            </div>
        </div>

        <!-- Bottom Section -->
        <div class="bottom-grid">
            <div class="glass-card trend-card">
                <h3 class="chart-title">Evolución Temporal de Emisiones</h3>
                <div class="chart-h-wrap">
                    <canvas #lineChart></canvas>
                </div>
            </div>
            <div class="glass-card trend-card">
                <h3 class="chart-title">Distribución por Categoría</h3>
                <div class="chart-h-wrap">
                    <canvas #barChart></canvas>
                </div>
            </div>
        </div>
    </div>
</div>
  `,
  styles: [`
    .dashboard-container { padding: 24px; max-width: 1600px; margin: 0 auto; font-family: 'Outfit', sans-serif; position: relative; }
    .flex-between { display: flex; justify-content: space-between; align-items: flex-end; }
    .dashboard-header h1 { font-size: 28px; font-weight: 700; color: var(--prestige-primary); margin: 0; }
    .dashboard-header p { color: var(--prestige-text-muted); font-size: 14px; }
    .header-actions { display: flex; align-items: center; gap: 16px; }
    .audit-btn { border-color: var(--prestige-primary); color: var(--prestige-primary); font-weight: 600; }
    .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 32px; margin-top: 24px;}
    .summary-card { padding: 24px; display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.3s; }
    .summary-card:hover { transform: translateY(-4px); }
    .card-title { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--prestige-text-muted); }
    .card-footer { margin-top: 16px; border-top: 1px solid var(--prestige-border); display: flex; justify-content: space-between; font-size: 11px; padding-top: 12px; color: var(--prestige-text-muted); }
    .card-footer span:last-child { color: var(--zia-tertiary); font-weight: 700; }
    .main-value { color: var(--zia-text); }
    .card-title { color: var(--zia-text-muted); }
    
    .intensity-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 24px; }
    .intensity-card { padding: 20px 24px; display: flex; align-items: center; gap: 16px; }
    .intensity-icon { font-size: 32px; width: 32px; height: 32px; color: var(--prestige-primary); opacity: 0.8; }
    .intensity-body { display: flex; flex-direction: column; }
    .middle-grid { display: grid; grid-template-columns: 1fr 2fr 1fr; gap: 24px; margin-bottom: 32px; }
    .chart-card { padding: 24px; }
    .donut-wrap { height: 250px; position: relative; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .table-scroll { max-height: 300px; overflow-y: auto; }
    .prestige-mini-table { width: 100%; border-collapse: collapse; }
    .prestige-mini-table th { text-align: left; padding: 12px; font-size: 10px; text-transform: uppercase; background: var(--table-header-bg); color: var(--zia-text-muted); }
    .prestige-mini-table td { padding: 12px; font-size: 13px; border-bottom: 1px solid var(--prestige-border); color: var(--prestige-text); }
    .scope-badge { padding: 2px 6px; border-radius: 4px; font-size: 9px; color: white; display: inline-block; }
    .scope-1 { background: #1a237e; }
    .scope-2 { background: #00897b; }
    .scope-3 { background: #f59e0b; }
    .equivalency-card { background: linear-gradient(135deg, var(--prestige-primary), #1a237e); color: white; text-align: center; padding: 32px; border-radius: 16px; display: flex; flex-direction: column; justify-content: center; }
    .eq-value { font-size: 36px; font-weight: 800; display: block; margin: 10px 0; }
    .bottom-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
    .trend-card { padding: 24px; }
    .chart-h-wrap { height: 250px; position: relative; }
    .spinner-overlay { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 5; }
    .empty-state-card { padding: 60px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 16px; margin-top: 40px; color: var(--prestige-text-muted); }
    .empty-state-card mat-icon { font-size: 48px; width: 48px; height: 48px; opacity: 0.5; }
    /* A01 — Admin Panel de Completitud */
    .admin-panel { margin-bottom: 32px; }
    .admin-panel-header {
      display: flex; align-items: center; gap: 10px; margin-bottom: 16px;
    }
    .admin-panel-header h3 {
      font-size: 16px; font-weight: 700; color: var(--prestige-primary); margin: 0;
    }
    .panel-header-icon { color: var(--prestige-primary); }
    .panel-subtitle { font-size: 12px; color: var(--prestige-text-muted); margin-left: auto; }

    .admin-panel-body { display: flex; flex-direction: column; gap: 16px; }

    /* Progress card */
    .progress-card { padding: 20px 24px; }
    .progress-card-header {
      display: flex; align-items: center; gap: 14px; margin-bottom: 12px;
    }
    .progress-icon { color: var(--prestige-primary); font-size: 28px; width: 28px; height: 28px; }
    .progress-label { display: block; font-size: 12px; font-weight: 600; color: var(--prestige-text-muted); text-transform: uppercase; }
    .progress-fraction { display: block; font-size: 22px; font-weight: 800; color: var(--prestige-text); margin-top: 2px; }
    .progress-pct {
      margin-left: auto; font-size: 24px; font-weight: 800;
      color: var(--prestige-primary);
    }
    .progress-pct.pct-full { color: #2e7d32; }
    .progress-hint { font-size: 12px; color: var(--prestige-text-muted); margin: 8px 0 0; }
    .progress-hint.hint-ok { color: #2e7d32; }

    /* Tables by unit / user */
    .completeness-tables { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .completeness-table-card { padding: 20px 0 0; overflow: hidden; }
    .completeness-title {
      display: flex; align-items: center; gap: 6px;
      font-size: 13px; font-weight: 700; color: var(--prestige-text); margin: 0 0 12px;
      padding: 0 20px;
    }
    .completeness-title mat-icon { font-size: 16px; width: 16px; height: 16px; color: var(--prestige-primary); }
    .completeness-scroll { max-height: 260px; overflow-y: auto; }

    .completeness-mini-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .completeness-mini-table th {
      padding: 10px 16px; font-size: 10px; font-weight: 700; text-transform: uppercase;
      color: var(--prestige-text-muted); background: var(--table-header-bg);
      text-align: left; position: sticky; top: 0;
    }
    .completeness-mini-table td {
      padding: 10px 16px; color: var(--prestige-text);
      border-bottom: 1px solid var(--prestige-border);
    }
    .completeness-mini-table tr:last-child td { border-bottom: none; }
    .text-muted { color: var(--prestige-text-muted) !important; }

    .unit-name-cell, .user-name-cell { display: flex; align-items: center; gap: 8px; }
    .unit-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--prestige-primary); flex-shrink: 0; }
    .user-avatar-sm {
      width: 24px; height: 24px; border-radius: 50%;
      background: var(--status-info-bg); color: var(--status-info-text);
      display: flex; align-items: center; justify-content: center;
      font-weight: 700; font-size: 11px; flex-shrink: 0;
    }

    .pct-bar-wrap { display: flex; align-items: center; gap: 6px; min-width: 80px; }
    .pct-bar {
      height: 6px; border-radius: 3px; background: var(--prestige-primary); opacity: 0.7; min-width: 2px;
    }
    .user-bar { background: #00897b; }
    .pct-txt { font-size: 11px; color: var(--prestige-text-muted); white-space: nowrap; }

    .no-data-hint {
      padding: 24px 20px; color: var(--prestige-text-muted); font-size: 13px; text-align: center;
    }

    @media (max-width: 1200px) { .summary-grid { grid-template-columns: 1fr 1fr; } .middle-grid { grid-template-columns: 1fr; } .completeness-tables { grid-template-columns: 1fr; } }
    @media (max-width: 768px) { .summary-grid { grid-template-columns: 1fr; } .bottom-grid { grid-template-columns: 1fr; } .panel-subtitle { display: none; } }
  `]
})
export class DashboardContentComponent implements OnInit, AfterViewInit, OnDestroy {
  private dashboardService = inject(DashboardService);
  private context = inject(ContextService);
  private authService = inject(AuthService); // Injected
  private router = inject(Router); // Injected
  private adminService = inject(AdminService);
  private cdr = inject(ChangeDetectorRef);

  @ViewChild('donutChart') donutCanvas!: ElementRef;
  @ViewChild('lineChart') lineCanvas!: ElementRef;
  @ViewChild('barChart') barCanvas!: ElementRef;

  summary: any = null;
  accessDenied = false;
  loading = false; // Start as false to prevent blocking initial screen

  selectedCompany: any = null;
  selectedPeriod: any = null;

  get isSuperAdmin(): boolean {
    return this.authService.currentUser()?.role === 'superadmin';
  }

  get activeRole(): string {
    return this.authService.currentContext()?.role || this.authService.currentUser()?.role || '';
  }

  get isAdminOrAbove(): boolean {
    const r = this.activeRole;
    return r === 'admin' || r === 'superadmin';
  }

  goToAudit() {
    this.router.navigate(['/admin/audit']);
  }

  private donutChartInst: any;
  private lineChartInst: any;
  private barChartInst: any;

  constructor() {
    effect(() => {
      this.selectedCompany = this.context.selectedCompany();
      this.selectedPeriod = this.context.selectedPeriod();

      console.log('Dashboard Signal Update:', {
        company: this.selectedCompany?.name,
        period: this.selectedPeriod?.year
      });

      if (this.selectedCompany && this.selectedPeriod) {
        this.loadDashboardData(this.selectedCompany.id, this.selectedPeriod.id);
      } else {
        this.loading = false;
        this.cdr.detectChanges();
      }
    });
  }

  ngOnInit() {
    this.selectedCompany = this.context.selectedCompany();
    this.selectedPeriod = this.context.selectedPeriod();
    if (this.selectedCompany && this.selectedPeriod) {
      this.loadDashboardData(this.selectedCompany.id, this.selectedPeriod.id);
    }
  }

  ngAfterViewInit() { }

  ngOnDestroy() {
    this.destroyCharts();
  }

  loadDashboardData(companyId: number, periodId: number) {
    console.log('Starting Dashboard API Fetch...', { companyId, periodId });
    this.loading = true;
    this.accessDenied = false;
    this.cdr.detectChanges();

    this.dashboardService.getSummary(companyId, periodId).subscribe({
      next: (res) => {
        console.log('Dashboard Summary Received:', res);
        this.summary = res;
        this.loading = false;
        this.cdr.detectChanges();
        setTimeout(() => this.updateCharts(), 50); // Slight delay for DOM stability
      },
      error: (err) => {
        console.error('Error loading dashboard summary:', err);
        this.summary = null;
        this.accessDenied = err.status === 403;
        this.loading = false;
        this.cdr.detectChanges();
      }
    });

    this.dashboardService.getTrends(companyId).subscribe({
      next: (res) => {
        console.log('Dashboard Trends Received:', res);
        setTimeout(() => this.initializeTrends(res), 50);
      },
      error: (err) => console.error('Error loading dashboard trends:', err)
    });
  }

  onDownloadPdf() {
    if (!this.selectedPeriod) return;
    this.dashboardService.downloadPdf(this.selectedPeriod.id).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        const dateStr = new Date().toISOString().split('T')[0];
        link.download = `zia_reporte_${this.selectedCompany.name.toLowerCase().replace(/ /g, '_')}_${this.selectedPeriod.year}_${dateStr}.pdf`;
        link.click();
      }
    });
  }

  onDownloadExcel() {
    if (!this.selectedPeriod) return;
    this.dashboardService.downloadExcel(this.selectedPeriod.id).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        const dateStr = new Date().toISOString().split('T')[0];
        link.download = `zia_datos_${this.selectedCompany.name.toLowerCase().replace(/ /g, '_')}_${this.selectedPeriod.year}_${dateStr}.xlsx`;
        link.click();
      }
    });
  }

  onDownloadProgress() {
    if (!this.selectedPeriod) return;
    this.dashboardService.downloadProgress(this.selectedPeriod.id).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        const dateStr = new Date().toISOString().split('T')[0];
        link.download = `zia_avance_${this.selectedCompany.name.toLowerCase().replace(/ /g, '_')}_${this.selectedPeriod.year}_${dateStr}.pdf`;
        link.click();
      }
    });
  }

  onDownloadIot() {
    if (!this.selectedPeriod) return;
    this.dashboardService.downloadIot(this.selectedPeriod.id).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        const dateStr = new Date().toISOString().split('T')[0];
        link.download = `zia_iot_${this.selectedCompany.name.toLowerCase().replace(/ /g, '_')}_${this.selectedPeriod.year}_${dateStr}.pdf`;
        link.click();
      }
    });
  }

  onDownloadPlatformReport() {
    this.adminService.downloadPlatformReport();
  }

  updateCharts() {
    if (!this.summary || !this.donutCanvas) {
      console.warn('Charts update skipped: missing data or canvas');
      return;
    }

    try {
      if (this.donutChartInst) this.donutChartInst.destroy();
      const ctx = this.donutCanvas.nativeElement.getContext('2d');
      const data = this.summary.chart_data?.donut || [];

      if (data.length === 0) return;

      const { text } = this.getChartColors();
      this.donutChartInst = new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: data.map((d: any) => d.label),
          datasets: [{
            data: data.map((d: any) => d.value),
            backgroundColor: data.map((d: any) => d.color),
            borderWidth: 0
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { position: 'bottom', labels: { color: text } } },
          cutout: '70%'
        }
      });
    } catch (e) {
      console.error('Error rendering donut chart:', e);
    }
  }

  private getChartColors() {
    const style = getComputedStyle(document.body);
    return {
      text:   style.getPropertyValue('--zia-text').trim()        || '#ffffff',
      muted:  style.getPropertyValue('--zia-text-muted').trim()  || 'rgba(255,255,255,0.55)',
      grid:   style.getPropertyValue('--zia-border').trim()      || 'rgba(255,255,255,0.08)',
    };
  }

  initializeTrends(res: any) {
    if (!res) return;
    const { text, muted, grid } = this.getChartColors();

    const scaleDefaults = {
      ticks: { color: muted },
      grid:  { color: grid  },
    };
    const legendDefaults = {
      labels: { color: text },
    };

    try {
      if (this.lineCanvas) {
        if (this.lineChartInst) this.lineChartInst.destroy();
        this.lineChartInst = new Chart(this.lineCanvas.nativeElement.getContext('2d'), {
          type: 'line',
          data: res.revenue_trend,
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: legendDefaults },
            scales: { x: scaleDefaults, y: scaleDefaults },
          }
        });
      }
      if (this.barCanvas) {
        if (this.barChartInst) this.barChartInst.destroy();
        this.barChartInst = new Chart(this.barCanvas.nativeElement.getContext('2d'), {
          type: 'bar',
          data: res.sales_quantity,
          options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: { legend: legendDefaults },
            scales: { x: scaleDefaults, y: scaleDefaults },
          }
        });
      }
    } catch (e) {
      console.error('Error rendering trend charts:', e);
    }
  }

  private destroyCharts() {
    if (this.donutChartInst) this.donutChartInst.destroy();
    if (this.lineChartInst) this.lineChartInst.destroy();
    if (this.barChartInst) this.barChartInst.destroy();
  }

  getScopeClass(scope: number) {
    return 'scope-' + scope;
  }
}
