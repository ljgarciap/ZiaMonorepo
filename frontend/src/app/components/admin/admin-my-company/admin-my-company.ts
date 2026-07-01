import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDividerModule } from '@angular/material/divider';
import { AdminService } from '../../../services/admin.service';

@Component({
  selector: 'app-admin-my-company',
  standalone: true,
  imports: [
    CommonModule,
    MatCardModule,
    MatIconModule,
    MatProgressSpinnerModule,
    MatDividerModule,
  ],
  template: `
    <div class="management-page">
      <div class="header-section">
        <div class="title-group">
          <h1>Mi Empresa</h1>
          <p class="subtitle">Información de la organización bajo tu administración.</p>
        </div>
      </div>

      <div class="spinner-container" *ngIf="loading">
        <mat-spinner diameter="40"></mat-spinner>
      </div>

      <ng-container *ngIf="!loading">
        <div class="company-grid">
          <div class="glass-card company-card" *ngFor="let company of companies">
            <div class="company-header">
              <div class="company-avatar">{{ company.name?.charAt(0) || '?' }}</div>
              <div>
                <h2 class="company-name">{{ company.name }}</h2>
                <span class="sector-label">{{ company.sector?.name || company.sector || 'Sin sector' }}</span>
              </div>
              <span class="status-pill" [class.active]="!company.deleted_at">
                {{ company.deleted_at ? 'Suspendida' : 'Activa' }}
              </span>
            </div>

            <mat-divider class="divider"></mat-divider>

            <div class="info-grid">
              <div class="info-item">
                <mat-icon class="info-icon">badge</mat-icon>
                <div>
                  <span class="info-label">NIT</span>
                  <span class="info-value">{{ company.nit || '—' }}</span>
                </div>
              </div>
              <div class="info-item">
                <mat-icon class="info-icon">location_on</mat-icon>
                <div>
                  <span class="info-label">Dirección</span>
                  <span class="info-value">{{ company.address || '—' }}</span>
                </div>
              </div>
              <div class="info-item">
                <mat-icon class="info-icon">phone</mat-icon>
                <div>
                  <span class="info-label">Teléfono</span>
                  <span class="info-value">{{ company.phone || '—' }}</span>
                </div>
              </div>
              <div class="info-item">
                <mat-icon class="info-icon">email</mat-icon>
                <div>
                  <span class="info-label">Correo</span>
                  <span class="info-value">{{ company.email || '—' }}</span>
                </div>
              </div>
            </div>

            <mat-divider class="divider"></mat-divider>

            <div class="periods-section">
              <h3 class="section-label"><mat-icon>calendar_month</mat-icon> Períodos de reporte</h3>
              <div class="periods-wrap" *ngIf="company.periods?.length; else noPeriods">
                <div class="period-chip" *ngFor="let p of company.periods"
                  [class.open]="p.status === 'active'" [class.closed]="p.status === 'closed'">
                  <mat-icon class="period-icon">{{ p.status === 'active' ? 'lock_open' : 'lock' }}</mat-icon>
                  {{ p.year }}
                </div>
              </div>
              <ng-template #noPeriods>
                <p class="empty-text">Sin períodos registrados.</p>
              </ng-template>
            </div>
          </div>
        </div>

        <div class="empty-state" *ngIf="!companies.length">
          <mat-icon>domain_disabled</mat-icon>
          <p>No tienes empresas asignadas.</p>
        </div>
      </ng-container>
    </div>
  `,
  styles: [`
    .management-page { padding: 24px; max-width: 1200px; margin: 0 auto; }
    .header-section { margin-bottom: 32px; }
    .title-group h1 { font-size: 28px; font-weight: 600; color: var(--prestige-primary); margin: 0 0 4px; }
    .subtitle { color: var(--prestige-text-muted); margin: 0; font-size: 14px; }

    .spinner-container { padding: 48px; text-align: center; }
    .empty-state { text-align: center; padding: 48px; color: var(--prestige-text-muted); }
    .empty-state mat-icon { font-size: 48px; width: 48px; height: 48px; opacity: 0.3; display: block; margin: 0 auto 16px; }

    .company-grid { display: grid; gap: 24px; grid-template-columns: repeat(auto-fill, minmax(480px, 1fr)); }

    .company-card { padding: 24px; }

    .company-header { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
    .company-avatar {
      width: 52px; height: 52px; border-radius: 14px;
      background: var(--status-info-bg); color: var(--status-info-text);
      display: flex; align-items: center; justify-content: center;
      font-weight: 800; font-size: 22px; flex-shrink: 0;
    }
    .company-name { margin: 0 0 4px; font-size: 20px; font-weight: 700; color: var(--prestige-text); }
    .sector-label { font-size: 12px; color: var(--prestige-text-muted); }
    .status-pill {
      margin-left: auto; padding: 4px 12px; border-radius: 20px;
      font-size: 11px; font-weight: 700; text-transform: uppercase;
      background: var(--status-error-bg); color: var(--status-error-text);
    }
    .status-pill.active { background: var(--status-success-bg); color: var(--status-success-text); }

    .divider { margin: 16px 0; }

    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 4px; }
    .info-item { display: flex; align-items: flex-start; gap: 10px; }
    .info-icon { color: var(--prestige-text-muted); font-size: 18px; width: 18px; height: 18px; margin-top: 2px; }
    .info-label { display: block; font-size: 11px; color: var(--prestige-text-muted); margin-bottom: 2px; }
    .info-value { display: block; font-size: 14px; font-weight: 500; color: var(--prestige-text); }

    .periods-section { }
    .section-label { display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: var(--prestige-text-muted); margin: 0 0 12px; }
    .section-label mat-icon { font-size: 16px; width: 16px; height: 16px; }

    .periods-wrap { display: flex; flex-wrap: wrap; gap: 8px; }
    .period-chip {
      display: inline-flex; align-items: center; gap: 4px;
      padding: 5px 12px; border-radius: 20px;
      font-size: 12px; font-weight: 700;
    }
    .period-chip.open { background: var(--status-success-bg); color: var(--status-success-text); }
    .period-chip.closed { background: var(--status-neutral-bg); color: var(--status-neutral-text); }
    .period-icon { font-size: 13px; width: 13px; height: 13px; }
    .empty-text { color: var(--prestige-text-muted); font-size: 13px; margin: 0; }
  `]
})
export class AdminMyCompanyComponent implements OnInit {
  private adminService = inject(AdminService);

  companies: any[] = [];
  loading = true;

  ngOnInit() {
    this.adminService.getCompanies().subscribe({
      next: (data) => { this.companies = data || []; this.loading = false; },
      error: () => { this.loading = false; }
    });
  }
}
