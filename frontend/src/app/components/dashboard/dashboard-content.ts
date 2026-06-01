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
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

import { MatMenuModule } from '@angular/material/menu';
import { MatButtonModule } from '@angular/material/button';

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
                    <button mat-menu-item (click)="onDownloadPdf()">
                        <mat-icon>picture_as_pdf</mat-icon>
                        <span>Resumen Ejecutivo (PDF)</span>
                    </button>
                    <button mat-menu-item (click)="onDownloadExcel()">
                        <mat-icon>table_view</mat-icon>
                        <span>Detalle Científico (Excel)</span>
                    </button>
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

    <div *ngIf="selectedCompany && selectedPeriod">
        <!-- Top Summary Cards -->
        <div class="summary-grid">
            <div class="glass-card summary-card">
                <span class="card-title">Huella Total</span>
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
    .card-footer span:last-child { color: var(--prestige-primary); font-weight: 700; }
    :host-context(.dark-theme) .card-footer span:last-child { color: var(--prestige-primary-light); }
    :host-context(.dark-theme) .main-value { color: var(--prestige-text); }
    :host-context(.dark-theme) .card-title { color: var(--prestige-text-muted); }
    
    .middle-grid { display: grid; grid-template-columns: 1fr 2fr 1fr; gap: 24px; margin-bottom: 32px; }
    .chart-card { padding: 24px; }
    .donut-wrap { height: 250px; position: relative; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .table-scroll { max-height: 300px; overflow-y: auto; }
    .prestige-mini-table { width: 100%; border-collapse: collapse; }
    .prestige-mini-table th { text-align: left; padding: 12px; font-size: 10px; text-transform: uppercase; background: var(--table-header-bg); color: var(--prestige-text-muted); }
    :host-context(.dark-theme) .prestige-mini-table th { color: var(--prestige-text); }
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
    @media (max-width: 1200px) { .summary-grid { grid-template-columns: 1fr 1fr; } .middle-grid { grid-template-columns: 1fr; } }
    @media (max-width: 768px) { .summary-grid { grid-template-columns: 1fr; } .bottom-grid { grid-template-columns: 1fr; } }
  `]
})
export class DashboardContentComponent implements OnInit, AfterViewInit, OnDestroy {
  private dashboardService = inject(DashboardService);
  private context = inject(ContextService);
  private authService = inject(AuthService); // Injected
  private router = inject(Router); // Injected
  private cdr = inject(ChangeDetectorRef);

  @ViewChild('donutChart') donutCanvas!: ElementRef;
  @ViewChild('lineChart') lineCanvas!: ElementRef;
  @ViewChild('barChart') barCanvas!: ElementRef;

  summary: any = null;
  loading = false; // Start as false to prevent blocking initial screen

  selectedCompany: any = null;
  selectedPeriod: any = null;

  get isSuperAdmin(): boolean {
    return this.authService.currentUser()?.role === 'superadmin';
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
          plugins: { legend: { position: 'bottom' } },
          cutout: '70%'
        }
      });
    } catch (e) {
      console.error('Error rendering donut chart:', e);
    }
  }

  initializeTrends(res: any) {
    if (!res) return;

    try {
      if (this.lineCanvas) {
        if (this.lineChartInst) this.lineChartInst.destroy();
        this.lineChartInst = new Chart(this.lineCanvas.nativeElement.getContext('2d'), {
          type: 'line',
          data: res.revenue_trend,
          options: { responsive: true, maintainAspectRatio: false }
        });
      }
      if (this.barCanvas) {
        if (this.barChartInst) this.barChartInst.destroy();
        this.barChartInst = new Chart(this.barCanvas.nativeElement.getContext('2d'), {
          type: 'bar',
          data: res.sales_quantity,
          options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y' }
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
