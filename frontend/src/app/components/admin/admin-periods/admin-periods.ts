import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { MatMenuModule } from '@angular/material/menu';
import { AdminService } from '../../../services/admin.service';
import { AuthService } from '../../../services/auth.service';

// SA-15: states and their allowed transitions
const PERIOD_STATES: Record<string, { label: string; icon: string; color: string }> = {
  open:      { label: 'Abierto',      icon: 'lock_open',    color: '#16a34a' },
  active:    { label: 'Activo',       icon: 'lock_open',    color: '#16a34a' },
  in_review: { label: 'En Revisión',  icon: 'rate_review',  color: '#0284c7' },
  closed:    { label: 'Cerrado',      icon: 'lock',         color: '#ef4444' },
  archived:  { label: 'Archivado',    icon: 'archive',      color: '#9ca3af' },
};

@Component({
  selector: 'app-admin-periods',
  standalone: true,
  imports: [
    CommonModule,
    MatTableModule,
    MatButtonModule,
    MatIconModule,
    MatProgressSpinnerModule,
    MatTooltipModule,
    MatSnackBarModule,
    MatMenuModule,
  ],
  template: `
    <div class="management-page">
      <div class="header-section">
        <div class="title-group">
          <h1>Gestión de Períodos</h1>
          <p class="subtitle">Ciclo de vida de los períodos de reporte: Abierto → En Revisión → Cerrado → Archivado.</p>
        </div>
      </div>

      <div class="glass-card table-wrapper">
        <div class="spinner-container" *ngIf="loading">
          <mat-spinner diameter="40"></mat-spinner>
          <p>Cargando períodos...</p>
        </div>

        <div class="table-container" *ngIf="!loading">
          <table mat-table [dataSource]="dataSource" class="prestige-table">

            <ng-container matColumnDef="company">
              <th mat-header-cell *matHeaderCellDef>Empresa</th>
              <td mat-cell *matCellDef="let row">
                <span class="company-name">{{ row.company_name }}</span>
              </td>
            </ng-container>

            <ng-container matColumnDef="year">
              <th mat-header-cell *matHeaderCellDef>Año</th>
              <td mat-cell *matCellDef="let row">
                <span class="year-badge">{{ row.year }}</span>
                <span class="base-year-tag" *ngIf="row.is_base_year">BASE</span>
              </td>
            </ng-container>

            <ng-container matColumnDef="status">
              <th mat-header-cell *matHeaderCellDef>Estado</th>
              <td mat-cell *matCellDef="let row">
                <span class="status-chip" [ngClass]="getStatusClass(row.status)">
                  <mat-icon class="chip-icon">{{ getStateInfo(row.status).icon }}</mat-icon>
                  {{ getStateInfo(row.status).label }}
                </span>
              </td>
            </ng-container>

            <ng-container matColumnDef="actions">
              <th mat-header-cell *matHeaderCellDef>Acciones</th>
              <td mat-cell *matCellDef="let row">
                <div class="action-group">
                  <!-- Abierto / Activo → Enviar a Revisión -->
                  <button mat-stroked-button class="action-review"
                    *ngIf="isOpenState(row.status)"
                    (click)="sendToReview(row)" [disabled]="row.busy"
                    matTooltip="Enviar a revisión: el período queda en espera de validación">
                    <mat-icon>rate_review</mat-icon> Revisar
                  </button>

                  <!-- En Revisión → Cerrar -->
                  <button mat-stroked-button class="action-close"
                    *ngIf="row.status === 'in_review'"
                    (click)="closePeriod(row)" [disabled]="row.busy"
                    matTooltip="Cerrar período: se consolidan las emisiones finales">
                    <mat-icon>lock</mat-icon> Cerrar
                  </button>

                  <!-- En Revisión → Reabrir -->
                  <button mat-stroked-button class="action-reopen"
                    *ngIf="row.status === 'in_review'"
                    (click)="reopenPeriod(row)" [disabled]="row.busy"
                    matTooltip="Devolver a abierto para corregir datos">
                    <mat-icon>lock_open</mat-icon> Devolver
                  </button>

                  <!-- Cerrado → Archivar -->
                  <button mat-stroked-button class="action-archive"
                    *ngIf="row.status === 'closed'"
                    (click)="archivePeriod(row)" [disabled]="row.busy"
                    matTooltip="Archivar: el período pasa a modo de solo lectura histórico">
                    <mat-icon>archive</mat-icon> Archivar
                  </button>

                  <!-- Cerrado → Reabrir (excepción) -->
                  <button mat-icon-button class="action-reopen"
                    *ngIf="row.status === 'closed' && isSuperadmin"
                    (click)="reopenPeriod(row)" [disabled]="row.busy"
                    matTooltip="Reabrir (solo superadmin — uso excepcional)">
                    <mat-icon>lock_open</mat-icon>
                  </button>
                </div>
              </td>
            </ng-container>

            <tr mat-header-row *matHeaderRowDef="displayedColumns"></tr>
            <tr mat-row *matRowDef="let row; columns: displayedColumns;" class="prestige-row"
              [class.row-review]="row.status === 'in_review'"
              [class.row-archived]="row.status === 'archived'">
            </tr>

            <tr class="mat-row" *matNoDataRow>
              <td class="mat-cell empty-state" colspan="4">
                <mat-icon>event_busy</mat-icon>
                <p>No hay períodos registrados.</p>
              </td>
            </tr>
          </table>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .management-page { padding: 24px; max-width: 1200px; margin: 0 auto; }
    .header-section { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px; }
    .title-group h1 { font-size: 28px; font-weight: 600; color: var(--prestige-primary); margin: 0 0 4px 0; }
    .subtitle { color: var(--prestige-text-muted); margin: 0; font-size: 13px; }

    .glass-card { border-radius: 16px; overflow: hidden; }
    .table-wrapper { padding: 0; }
    .table-container { width: 100%; overflow-x: auto; }
    .prestige-table { width: 100%; }
    .prestige-row:hover { background: var(--row-hover-bg) !important; }
    .row-review { background: rgba(2, 132, 199, 0.04) !important; }
    .row-archived td { opacity: 0.5; }

    .spinner-container { padding: 48px; text-align: center; color: var(--prestige-text-muted); }
    .empty-state { padding: 48px; text-align: center; color: var(--prestige-text-muted); }
    .empty-state mat-icon { font-size: 40px; width: 40px; height: 40px; opacity: 0.3; display: block; margin: 0 auto 12px; }

    .company-name { font-weight: 600; color: var(--prestige-text); }
    .year-badge { background: var(--status-neutral-bg); color: var(--status-neutral-text); padding: 3px 10px; border-radius: 8px; font-weight: 700; font-size: 13px; }
    .base-year-tag { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; border-radius: 8px; padding: 1px 6px; font-size: 9px; font-weight: 800; text-transform: uppercase; margin-left: 6px; }

    .status-chip { display: inline-flex; align-items: center; gap: 4px; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
    .status-chip.s-open, .status-chip.s-active { background: var(--status-success-bg); color: var(--status-success-text); }
    .status-chip.s-in_review { background: #e0f2fe; color: #0284c7; }
    .status-chip.s-closed { background: var(--status-error-bg); color: var(--status-error-text); }
    .status-chip.s-archived { background: var(--status-neutral-bg); color: var(--status-neutral-text); }
    .chip-icon { font-size: 14px; width: 14px; height: 14px; }

    .action-group { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
    .action-review { color: #0284c7; border-color: #0284c7; font-size: 12px; height: 32px; }
    .action-close { color: #ef4444; border-color: #ef4444; font-size: 12px; height: 32px; }
    .action-reopen { color: #16a34a; border-color: #16a34a; font-size: 12px; height: 32px; }
    .action-archive { color: #6b7280; border-color: #6b7280; font-size: 12px; height: 32px; }
  `]
})
export class AdminPeriodsComponent implements OnInit {
  private adminService = inject(AdminService);
  private authService = inject(AuthService);
  private snackBar = inject(MatSnackBar);

  dataSource = new MatTableDataSource<any>([]);
  displayedColumns = ['company', 'year', 'status', 'actions'];
  loading = true;
  isSuperadmin = false;

  ngOnInit() {
    this.isSuperadmin = this.authService.currentUser()?.role === 'superadmin';
    this.load();
  }

  load() {
    this.loading = true;
    this.adminService.getCompanies().subscribe({
      next: (companies) => {
        const rows: any[] = [];
        for (const c of companies || []) {
          for (const p of c.periods || []) {
            rows.push({ ...p, company_name: c.name, busy: false });
          }
        }
        rows.sort((a, b) => b.year - a.year);
        this.dataSource.data = rows;
        this.loading = false;
      },
      error: () => { this.loading = false; }
    });
  }

  getStateInfo(status: string) {
    return PERIOD_STATES[status] || PERIOD_STATES['open'];
  }

  getStatusClass(status: string): string {
    return `s-${status}`;
  }

  isOpenState(status: string): boolean {
    return status === 'open' || status === 'active';
  }

  sendToReview(row: any) {
    row.busy = true;
    this.adminService.sendPeriodToReview(row.id).subscribe({
      next: (p) => { row.status = p.status; row.busy = false; this.snackBar.open(`Período ${row.year} enviado a revisión.`, '', { duration: 3000 }); },
      error: (err) => { row.busy = false; this.snackBar.open(err.error?.message || 'Error.', '', { duration: 3000 }); }
    });
  }

  closePeriod(row: any) {
    row.busy = true;
    this.adminService.closePeriod(row.id).subscribe({
      next: (p) => { row.status = p.status; row.busy = false; this.snackBar.open(`Período ${row.year} cerrado.`, '', { duration: 3000 }); },
      error: () => { row.busy = false; this.snackBar.open('Error al cerrar.', '', { duration: 3000 }); }
    });
  }

  reopenPeriod(row: any) {
    row.busy = true;
    this.adminService.reopenPeriod(row.id).subscribe({
      next: (p) => { row.status = p.status; row.busy = false; this.snackBar.open(`Período ${row.year} reabierto.`, '', { duration: 3000 }); },
      error: () => { row.busy = false; this.snackBar.open('Error.', '', { duration: 3000 }); }
    });
  }

  archivePeriod(row: any) {
    row.busy = true;
    this.adminService.archivePeriod(row.id).subscribe({
      next: (p) => { row.status = p.status; row.busy = false; this.snackBar.open(`Período ${row.year} archivado.`, '', { duration: 3000 }); },
      error: (err) => { row.busy = false; this.snackBar.open(err.error?.message || 'Error.', '', { duration: 3000 }); }
    });
  }
}
