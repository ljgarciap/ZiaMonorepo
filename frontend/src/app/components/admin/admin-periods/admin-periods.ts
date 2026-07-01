import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { AdminService } from '../../../services/admin.service';
import { AuthService } from '../../../services/auth.service';

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
  ],
  template: `
    <div class="management-page">
      <div class="header-section">
        <div class="title-group">
          <h1>Gestión de Períodos</h1>
          <p class="subtitle">Consulta y controla el estado de los períodos de reporte de tus empresas.</p>
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
              </td>
            </ng-container>

            <ng-container matColumnDef="status">
              <th mat-header-cell *matHeaderCellDef>Estado</th>
              <td mat-cell *matCellDef="let row">
                <span class="status-chip" [class.open]="row.status === 'active'" [class.closed]="row.status === 'closed'">
                  <mat-icon class="chip-icon">{{ row.status === 'active' ? 'lock_open' : 'lock' }}</mat-icon>
                  {{ row.status === 'active' ? 'Abierto' : 'Cerrado' }}
                </span>
              </td>
            </ng-container>

            <ng-container matColumnDef="actions">
              <th mat-header-cell *matHeaderCellDef>Acción</th>
              <td mat-cell *matCellDef="let row">
                <button mat-stroked-button class="action-close" *ngIf="row.status === 'active'"
                  (click)="closePeriod(row)" [disabled]="row.busy"
                  matTooltip="Cerrar período: no se podrán registrar nuevas emisiones">
                  <mat-icon>lock</mat-icon> Cerrar
                </button>
                <button mat-stroked-button class="action-reopen" *ngIf="row.status === 'closed'"
                  (click)="reopenPeriod(row)" [disabled]="row.busy"
                  matTooltip="Reabrir período: se habilitarán nuevos registros">
                  <mat-icon>lock_open</mat-icon> Reabrir
                </button>
              </td>
            </ng-container>

            <tr mat-header-row *matHeaderRowDef="displayedColumns"></tr>
            <tr mat-row *matRowDef="let row; columns: displayedColumns;" class="prestige-row"></tr>

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
    .subtitle { color: var(--prestige-text-muted); margin: 0; font-size: 14px; }

    .glass-card { border-radius: 16px; overflow: hidden; }
    .table-wrapper { padding: 0; }
    .table-container { width: 100%; overflow-x: auto; }
    .prestige-table { width: 100%; }
    .prestige-row:hover { background: var(--row-hover-bg) !important; }

    .spinner-container { padding: 48px; text-align: center; color: var(--prestige-text-muted); font-size: 14px; }
    .empty-state { padding: 48px; text-align: center; color: var(--prestige-text-muted); }
    .empty-state mat-icon { font-size: 40px; width: 40px; height: 40px; opacity: 0.3; display: block; margin: 0 auto 12px; }

    .company-name { font-weight: 600; color: var(--prestige-text); }

    .year-badge {
      background: var(--status-neutral-bg); color: var(--status-neutral-text);
      padding: 3px 10px; border-radius: 8px; font-weight: 700; font-size: 13px;
    }

    .status-chip {
      display: inline-flex; align-items: center; gap: 4px;
      padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700;
      text-transform: uppercase;
    }
    .status-chip.open { background: var(--status-success-bg); color: var(--status-success-text); }
    .status-chip.closed { background: var(--status-error-bg); color: var(--status-error-text); }
    .chip-icon { font-size: 14px; width: 14px; height: 14px; }

    .action-close { color: #ef6c00; border-color: #ef6c00; font-size: 13px; }
    .action-close:hover { background: rgba(239, 108, 0, 0.06); }
    .action-reopen { color: #2e7d32; border-color: #2e7d32; font-size: 13px; }
    .action-reopen:hover { background: rgba(46, 125, 50, 0.06); }
  `]
})
export class AdminPeriodsComponent implements OnInit {
  private adminService = inject(AdminService);
  private authService = inject(AuthService);
  private snackBar = inject(MatSnackBar);

  dataSource = new MatTableDataSource<any>([]);
  displayedColumns = ['company', 'year', 'status', 'actions'];
  loading = true;

  ngOnInit() {
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

  closePeriod(row: any) {
    row.busy = true;
    this.adminService.closePeriod(row.id).subscribe({
      next: () => {
        row.status = 'closed';
        row.busy = false;
        this.snackBar.open(`Período ${row.year} cerrado.`, '', { duration: 3000 });
      },
      error: () => {
        row.busy = false;
        this.snackBar.open('Error al cerrar el período.', '', { duration: 3000 });
      }
    });
  }

  reopenPeriod(row: any) {
    row.busy = true;
    this.adminService.reopenPeriod(row.id).subscribe({
      next: () => {
        row.status = 'active';
        row.busy = false;
        this.snackBar.open(`Período ${row.year} reabierto.`, '', { duration: 3000 });
      },
      error: () => {
        row.busy = false;
        this.snackBar.open('Error al reabrir el período.', '', { duration: 3000 });
      }
    });
  }
}
