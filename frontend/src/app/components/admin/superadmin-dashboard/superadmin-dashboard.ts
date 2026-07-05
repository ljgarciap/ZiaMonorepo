import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTooltipModule } from '@angular/material/tooltip';
import { AdminService } from '../../../services/admin.service';

@Component({
    selector: 'app-superadmin-dashboard',
    standalone: true,
    imports: [CommonModule, MatCardModule, MatIconModule, MatProgressSpinnerModule, MatTooltipModule],
    template: `
<div class="sa-dashboard">
    <div class="header-section">
        <h1>Dashboard Ejecutivo — Plataforma ZIA</h1>
        <p class="subtitle">Vista global de todas las organizaciones, usuarios y dispositivos registrados.</p>
    </div>

    <div class="spinner-wrap" *ngIf="loading()">
        <mat-spinner diameter="48"></mat-spinner>
    </div>

    <ng-container *ngIf="!loading() && stats() as stats">
        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="glass-card kpi-card">
                <mat-icon class="kpi-icon orgs">business</mat-icon>
                <div class="kpi-body">
                    <span class="kpi-label">Organizaciones</span>
                    <span class="kpi-value">{{ stats.companies.active }}</span>
                    <span class="kpi-sub">de {{ stats.companies.total }} registradas</span>
                </div>
            </div>
            <div class="glass-card kpi-card">
                <mat-icon class="kpi-icon periods">calendar_month</mat-icon>
                <div class="kpi-body">
                    <span class="kpi-label">Períodos Abiertos</span>
                    <span class="kpi-value">{{ stats.periods.open }}</span>
                    <span class="kpi-sub">{{ stats.periods.closed }} cerrados</span>
                </div>
            </div>
            <div class="glass-card kpi-card">
                <mat-icon class="kpi-icon emissions">co2</mat-icon>
                <div class="kpi-body">
                    <span class="kpi-label">Total tCO₂e Registradas</span>
                    <span class="kpi-value">{{ stats.emissions.total_co2e | number:'1.0-0' }}</span>
                    <span class="kpi-sub">en todos los inventarios</span>
                </div>
            </div>
            <div class="glass-card kpi-card">
                <mat-icon class="kpi-icon users">people</mat-icon>
                <div class="kpi-body">
                    <span class="kpi-label">Usuarios Activos (30d)</span>
                    <span class="kpi-value">{{ stats.users.active_30d }}</span>
                    <span class="kpi-sub">de {{ stats.users.total }} totales</span>
                </div>
            </div>
            <div class="glass-card kpi-card" [class.alert-card]="stats.iot.pending_alerts > 0">
                <mat-icon class="kpi-icon iot">sensors</mat-icon>
                <div class="kpi-body">
                    <span class="kpi-label">Dispositivos IoT</span>
                    <span class="kpi-value">{{ stats.iot.devices }}</span>
                    <span class="kpi-sub alert-sub" *ngIf="stats.iot.pending_alerts > 0">
                        ⚠ {{ stats.iot.pending_alerts }} alerta(s) pendientes
                    </span>
                    <span class="kpi-sub" *ngIf="stats.iot.pending_alerts === 0">Sin alertas activas ✓</span>
                </div>
            </div>
        </div>

        <!-- Bottom grid: top orgs + tendencia -->
        <div class="bottom-grid">
            <!-- Top empresas por emisiones -->
            <div class="glass-card section-card">
                <h3 class="section-title">
                    <mat-icon>leaderboard</mat-icon>
                    Top Organizaciones por Emisiones ({{ currentYear }})
                </h3>
                <div *ngIf="stats.top_companies.length; else noTopData" class="top-table">
                    <div class="top-row" *ngFor="let c of stats.top_companies; let i = index">
                        <span class="top-rank">#{{ i + 1 }}</span>
                        <span class="top-name">{{ c.name }}</span>
                        <div class="top-bar-wrap">
                            <div class="top-bar" [style.width.%]="getBarPct(c.total_co2e)"></div>
                        </div>
                        <span class="top-value">{{ c.total_co2e | number:'1.0-0' }} tCO₂e</span>
                    </div>
                </div>
                <ng-template #noTopData>
                    <p class="no-data">Sin datos de emisiones registrados para {{ currentYear }}.</p>
                </ng-template>
            </div>

            <!-- Tendencia de emisiones por año -->
            <div class="glass-card section-card">
                <h3 class="section-title">
                    <mat-icon>trending_down</mat-icon>
                    Tendencia de Emisiones — Plataforma Consolidada
                </h3>
                <div *ngIf="stats.emissions_trend.length; else noTrendData" class="trend-table">
                    <div class="trend-row" *ngFor="let t of stats.emissions_trend; let i = index">
                        <span class="trend-year">{{ t.year }}</span>
                        <div class="trend-bar-wrap">
                            <div class="trend-bar" [style.width.%]="getTrendPct(t.total_co2e)"></div>
                        </div>
                        <span class="trend-value">{{ t.total_co2e | number:'1.0-0' }}</span>
                        <span class="trend-delta" *ngIf="i > 0"
                            [class.delta-down]="getDelta(i) < 0"
                            [class.delta-up]="getDelta(i) > 0">
                            {{ getDelta(i) > 0 ? '▲' : '▼' }} {{ getDeltaAbs(i) | number:'1.0-0' }}
                        </span>
                    </div>
                </div>
                <ng-template #noTrendData>
                    <p class="no-data">Sin datos históricos suficientes.</p>
                </ng-template>
            </div>
        </div>
    </ng-container>
</div>
    `,
    styles: [`
    .sa-dashboard { padding: 24px; max-width: 1400px; margin: 0 auto; }
    .header-section { margin-bottom: 28px; }
    .header-section h1 { font-size: 28px; font-weight: 700; color: var(--prestige-primary); margin: 0; }
    .subtitle { color: var(--prestige-text-muted); font-size: 14px; margin: 4px 0 0; }

    .spinner-wrap { display: flex; justify-content: center; padding: 80px; }

    .kpi-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 28px; }
    .glass-card { background: rgba(255,255,255,0.9); border: 1px solid var(--prestige-border); border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
    .kpi-card { padding: 20px 16px; display: flex; align-items: center; gap: 14px; }
    .kpi-card.alert-card { border-color: #f59e0b; background: #fffbeb; }
    .kpi-icon { font-size: 32px; width: 32px; height: 32px; opacity: 0.8; }
    .kpi-icon.orgs { color: #1a237e; }
    .kpi-icon.periods { color: #00897b; }
    .kpi-icon.emissions { color: #e65100; }
    .kpi-icon.users { color: #6d28d9; }
    .kpi-icon.iot { color: #0284c7; }
    .kpi-body { display: flex; flex-direction: column; }
    .kpi-label { font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--prestige-text-muted); letter-spacing: .4px; }
    .kpi-value { font-size: 26px; font-weight: 800; color: var(--prestige-text); line-height: 1.2; }
    .kpi-sub { font-size: 11px; color: var(--prestige-text-muted); }
    .alert-sub { color: #d97706; font-weight: 600; }

    .bottom-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .section-card { padding: 24px; }
    .section-title { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 700; color: var(--prestige-primary); margin: 0 0 20px; }
    .section-title mat-icon { font-size: 18px; width: 18px; height: 18px; }

    .top-row, .trend-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--prestige-border); font-size: 13px; }
    .top-row:last-child, .trend-row:last-child { border-bottom: none; }
    .top-rank { font-weight: 800; color: var(--prestige-primary); width: 24px; text-align: center; }
    .top-name, .trend-year { font-weight: 600; min-width: 90px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .top-bar-wrap, .trend-bar-wrap { flex: 1; height: 8px; background: var(--prestige-border); border-radius: 4px; overflow: hidden; }
    .top-bar { height: 100%; background: var(--prestige-primary); border-radius: 4px; transition: width .4s; }
    .trend-bar { height: 100%; background: #00897b; border-radius: 4px; }
    .top-value, .trend-value { font-size: 12px; font-weight: 700; white-space: nowrap; min-width: 90px; text-align: right; }
    .trend-delta { font-size: 11px; font-weight: 700; min-width: 60px; text-align: right; }
    .delta-down { color: #16a34a; }
    .delta-up { color: #dc2626; }
    .no-data { color: var(--prestige-text-muted); font-style: italic; font-size: 13px; padding: 20px 0; text-align: center; }

    @media (max-width: 1200px) { .kpi-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 768px) { .kpi-grid { grid-template-columns: 1fr 1fr; } .bottom-grid { grid-template-columns: 1fr; } }
    `]
})
export class SuperadminDashboardComponent implements OnInit {
    private adminService = inject(AdminService);

    stats = signal<any>(null);
    loading = signal(true);
    currentYear = new Date().getFullYear();

    ngOnInit() {
        this.adminService.getPlatformStats().subscribe({
            next: (data) => { this.stats.set(data); this.loading.set(false); },
            error: () => { this.loading.set(false); }
        });
    }

    getBarPct(value: number): number {
        const stats = this.stats();
        if (!stats?.top_companies?.length) return 0;
        const max = Math.max(...stats.top_companies.map((c: any) => c.total_co2e));
        return max > 0 ? (value / max) * 100 : 0;
    }

    getTrendPct(value: number): number {
        const stats = this.stats();
        if (!stats?.emissions_trend?.length) return 0;
        const max = Math.max(...stats.emissions_trend.map((t: any) => t.total_co2e));
        return max > 0 ? (value / max) * 100 : 0;
    }

    getDelta(i: number): number {
        const trend = this.stats()?.emissions_trend || [];
        if (i === 0 || !trend[i - 1]) return 0;
        return trend[i].total_co2e - trend[i - 1].total_co2e;
    }

    getDeltaAbs(i: number): number {
        return Math.abs(this.getDelta(i));
    }
}
