import { Component, inject, ViewChild, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatDialogModule, MatDialog } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { AdminService } from '../../../services/admin.service';
import { CompanyDialog, ConfirmDialog, PeriodDialog } from '../admin-dialogs';
import { CompanyFactorSettingsDialog } from './company-factor-settings-dialog';

@Component({
  selector: 'app-company-management',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatTableModule,
    MatButtonModule,
    MatIconModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatTooltipModule,
    MatProgressSpinnerModule
  ],
  template: `
    <div class="management-page">
      <div class="header-section">
        <div class="title-group">
          <h1>Gestión de Empresas</h1>
          <p class="subtitle">Administra el portafolio de empresas y sus periodos de reporte.</p>
        </div>
        <button mat-flat-button class="btn-prestige" (click)="onCreate()">
          <mat-icon>add</mat-icon> Nueva Empresa
        </button>
      </div>

      <div class="glass-card table-wrapper">
        <div class="table-header">
           <mat-form-field appearance="outline" class="search-field prestige-field">
              <mat-label>Buscador inteligente</mat-label>
              <input matInput (keyup)="applyFilter($event)" placeholder="Nombre, NIT o Sector" #input>
              <mat-icon matSuffix>search</mat-icon>
            </mat-form-field>

            <div class="status-legend">
              <span class="legend-item"><span class="dot active"></span> Activo</span>
              <span class="legend-item"><span class="dot inactive"></span> Inactivo</span>
            </div>
        </div>

        <div class="spinner-container" *ngIf="loading">
          <mat-spinner diameter="40"></mat-spinner>
          <p>Sincronizando datos...</p>
        </div>

        <div class="table-container">
          <table mat-table [dataSource]="dataSource" class="prestige-table">
            <ng-container matColumnDef="name">
              <th mat-header-cell *matHeaderCellDef>Empresa</th>
              <td mat-cell *matCellDef="let company">
                <div class="company-info-cell">
                  <div class="company-logo">{{company.name?.charAt(0) || '?'}}</div>
                  <div class="name-sector">
                    <span class="company-name">{{company.name || 'Empresa sin nombre'}}</span>
                    <span class="company-sector">{{company.sector?.name || company.sector_info?.name || company.sector || 'Sector no definido'}}</span>
                  </div>
                </div>
              </td>
            </ng-container>

            <ng-container matColumnDef="nit">
              <th mat-header-cell *matHeaderCellDef>NIT</th>
              <td mat-cell *matCellDef="let company">{{company.nit || '—'}}</td>
            </ng-container>

            <ng-container matColumnDef="periods">
              <th mat-header-cell *matHeaderCellDef>Reportes</th>
              <td mat-cell *matCellDef="let company">
                <div class="periods-wrap">
                  <span class="period-tag" *ngFor="let p of (company.periods || [])">{{p.year}}</span>
                  <button class="add-period-btn" (click)="onAddPeriod(company)" matTooltip="Vincular periodo">
                    <mat-icon>add</mat-icon>
                  </button>
                </div>
              </td>
            </ng-container>

            <ng-container matColumnDef="status">
              <th mat-header-cell *matHeaderCellDef>Estado</th>
              <td mat-cell *matCellDef="let company">
                <div class="status-indicator" [class.active]="!company.deleted_at">
                  {{ company.deleted_at ? 'Suspendido' : 'Operativo' }}
                </div>
              </td>
            </ng-container>

            <ng-container matColumnDef="actions">
              <th mat-header-cell *matHeaderCellDef>Acciones</th>
              <td mat-cell *matCellDef="let company">
                <div class="action-buttons">
                  <button mat-icon-button class="action-btn edit" (click)="onEdit(company)" matTooltip="Ajustes">
                    <mat-icon>settings</mat-icon>
                  </button>
                  <button mat-icon-button class="action-btn factor" (click)="onManageFactors(company)" matTooltip="Configurar Factores">
                    <mat-icon>fact_check</mat-icon>
                  </button>
                  <button mat-icon-button class="action-btn delete" *ngIf="!company.deleted_at" (click)="onDelete(company)" matTooltip="Desactivar">
                    <mat-icon>block</mat-icon>
                  </button>
                </div>
              </td>
            </ng-container>

            <tr mat-header-row *matHeaderRowDef="displayedColumns"></tr>
            <tr mat-row *matRowDef="let row; columns: displayedColumns;" class="prestige-row"></tr>

            <tr class="mat-row" *matNoDataRow>
              <td class="mat-cell empty-state" colspan="5">
                <div class="empty-msg-wrap" *ngIf="!loading">
                  <mat-icon>search_off</mat-icon>
                  <p>No se encontraron resultados para la búsqueda.</p>
                </div>
              </td>
            </tr>
          </table>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .management-page { padding: 24px; max-width: 1400px; margin: 0 auto; }
    
    .header-section { 
      display: flex; justify-content: space-between; align-items: flex-end; 
      margin-bottom: 32px; gap: 20px;
    }
    .title-group h1 { 
      font-size: 28px; font-weight: 600; color: var(--prestige-primary); 
      margin: 0 0 4px 0; letter-spacing: -0.02em;
    }
    .subtitle { color: var(--prestige-text-muted); margin: 0; font-size: 14px; }

    .btn-prestige { 
      background: var(--prestige-primary); color: white; padding: 0 20px; 
      border-radius: 10px; font-weight: 500; height: 42px; font-size: 14px;
    }

    .table-wrapper { padding: 0; overflow: hidden; }
    
    .table-header { 
      padding: 24px 24px 16px 24px; display: flex; justify-content: space-between; align-items: center;
      border-bottom: 1px solid var(--prestige-border); gap: 16px; flex-wrap: wrap;
    }
    .search-field { width: 320px; margin-bottom: 16px; font-size: 13px; }

    .status-legend { display: flex; gap: 16px; font-size: 11px; color: var(--prestige-text-muted); }
    .legend-item { display: flex; align-items: center; gap: 6px; }
    .dot { width: 6px; height: 6px; border-radius: 50%; }
    .dot.active { background: #4caf50; box-shadow: 0 0 6px rgba(76, 175, 80, 0.4); }
    .dot.inactive { background: #f44336; }

    .prestige-table { width: 100%; border: none; min-width: 800px; }
    .table-container { 
      width: 100%; 
      overflow-x: auto; 
      position: relative;
      min-height: 200px;
    }
    
    .prestige-row { transition: all 0.2s; }
    .prestige-row:hover { background: var(--row-hover-bg) !important; cursor: pointer; }

    .company-info-cell { display: flex; align-items: center; gap: 12px; padding: 8px 0; }
    .company-logo { 
      width: 36px; height: 36px; border-radius: 10px; background: var(--status-info-bg) !important; 
      color: var(--status-info-text) !important; display: flex; align-items: center; justify-content: center;
      font-weight: 800; font-size: 16px; border: 1px solid var(--prestige-border);
    }
    .name-sector { display: flex; flex-direction: column; }
    .company-name { font-weight: 600; color: var(--prestige-text); font-size: 14px; }
    .company-sector { font-size: 11px; color: var(--prestige-text-muted); }

    .periods-wrap { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
    .period-tag { 
      background: var(--status-neutral-bg); color: var(--status-neutral-text); padding: 2px 8px; 
      border-radius: 6px; font-size: 11px; font-weight: 600; border: 1px solid var(--prestige-border);
    }
    .add-period-btn { 
      width: 24px; height: 24px; border-radius: 6px; border: 1px dashed var(--prestige-text-muted);
      background: transparent; color: var(--prestige-text-muted); cursor: pointer;
      display: flex; align-items: center; justify-content: center;
    }
    .add-period-btn mat-icon { font-size: 14px; width: 14px; height: 14px; }

    .status-indicator { 
      padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 700;
      text-transform: uppercase; background: var(--status-error-bg) !important; color: var(--status-error-text) !important; border: 1px solid var(--prestige-border);
      width: fit-content;
    }
    .status-indicator.active { 
      background: var(--status-success-bg) !important; color: var(--status-success-text) !important; border: 1px solid var(--prestige-border);
    }

    .action-buttons { display: flex; gap: 2px; }
    .action-btn { color: var(--prestige-text-muted); width: 36px; height: 36px; }
    .action-btn.edit:hover { color: var(--prestige-primary); background: rgba(26, 35, 126, 0.05); }
    .action-btn.factor:hover { color: #00897b; background: rgba(0, 137, 123, 0.05); }
    .action-btn.delete:hover { color: #d32f2f; background: #ffebee; }

    .spinner-container { padding: 48px; text-align: center; color: var(--prestige-text-muted); font-size: 14px; }
    .empty-state { padding: 48px; text-align: center; color: var(--prestige-text-muted); }
    .empty-state mat-icon { font-size: 40px; width: 40px; height: 40px; opacity: 0.3; margin-bottom: 12px; }

    @media (max-width: 768px) {
      .management-page { padding: 16px; }
      .header-section { flex-direction: column; align-items: flex-start; gap: 16px; }
      .search-field { width: 100%; }
      .table-header { flex-direction: column; align-items: flex-start; }
    }
  `]
})
export class CompanyManagementComponent implements OnInit {
  private adminService = inject(AdminService);
  private cdr = inject(ChangeDetectorRef);
  private dialog = inject(MatDialog);

  dataSource = new MatTableDataSource<any>([]);
  displayedColumns = ['name', 'nit', 'periods', 'status', 'actions'];
  sectors: any[] = [];
  loading = true;

  ngOnInit() {
    this.loadCompanies();
    this.loadSectors();
  }

  loadCompanies() {
    this.loading = true;
    this.adminService.getCompanies().subscribe({
      next: (data) => {
        this.dataSource.data = data || [];
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('[CompanyMgmt] Error:', err);
        this.loading = false;
        this.cdr.detectChanges();
      }
    });
  }

  loadSectors() {
    this.adminService.getSectors().subscribe(data => {
      this.sectors = data || [];
    });
  }

  applyFilter(event: Event) {
    const filterValue = (event.target as HTMLInputElement).value;
    this.dataSource.filter = filterValue.trim().toLowerCase();
  }

  onCreate() {
    const dialogRef = this.dialog.open(CompanyDialog, {
      data: { company: {}, sectors: this.sectors }
    });
    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.adminService.createCompany(result).subscribe(() => this.loadCompanies());
      }
    });
  }

  onEdit(company: any) {
    const dialogRef = this.dialog.open(CompanyDialog, {
      data: { company: { ...company }, sectors: this.sectors }
    });
    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.adminService.updateCompany(company.id, result).subscribe(() => this.loadCompanies());
      }
    });
  }

  onAddPeriod(company: any) {
    const dialogRef = this.dialog.open(PeriodDialog, {
      data: { company },
      width: '400px'
    });

    dialogRef.afterClosed().subscribe(result => {
      if (result && result.year) {
        this.adminService.addPeriod(company.id, { year: result.year, status: 'open' }).subscribe(() => this.loadCompanies());
      }
    });
  }

  onDelete(company: any) {
    const dialogRef = this.dialog.open(ConfirmDialog, {
      data: {
        title: 'Desactivar Empresa',
        message: `¿Estás seguro de que deseas desactivar la empresa "${company.name}"? Los datos históricos permanecerán pero no se podrán realizar nuevos registros.`,
        confirmText: 'Desactivar',
        color: 'warn'
      }
    });

    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.adminService.deleteCompany(company.id).subscribe(() => this.loadCompanies());
      }
    });
  }

  onManageFactors(company: any) {
    this.dialog.open(CompanyFactorSettingsDialog, {
      data: { company },
      width: '600px'
    });
  }
}
