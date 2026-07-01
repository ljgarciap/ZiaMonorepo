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
import { SectorDialog, ConfirmDialog } from '../admin-dialogs';

@Component({
    selector: 'app-sector-management',
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
          <h1>Gestión de Sectores</h1>
          <p class="subtitle">Sectores del catálogo CIIU Rev. 4 y sectores personalizados.</p>
        </div>
        <button mat-flat-button class="btn-prestige" (click)="onCreate()">
          <mat-icon>add</mat-icon> Nuevo Sector
        </button>
      </div>

      <div class="glass-card table-wrapper">
        <div class="table-header">
           <mat-form-field appearance="outline" class="search-field prestige-field">
              <mat-label>Buscar sector</mat-label>
              <input matInput (keyup)="applyFilter($event)" placeholder="Nombre o código CIIU" #input>
              <mat-icon matSuffix>search</mat-icon>
            </mat-form-field>
            <div class="legend-chip">
              <mat-icon style="font-size:14px;width:14px;height:14px;color:#1a237e">verified</mat-icon>
              Catálogo CIIU Rev. 4
            </div>
        </div>

        <div class="spinner-container" *ngIf="loading">
          <mat-spinner diameter="40"></mat-spinner>
          <p>Cargando sectores...</p>
        </div>

        <div class="table-container">
          <table mat-table [dataSource]="dataSource" class="prestige-table">

            <ng-container matColumnDef="ciiu_code">
              <th mat-header-cell *matHeaderCellDef>Código CIIU</th>
              <td mat-cell *matCellDef="let sector">
                <span *ngIf="sector.ciiu_code" class="ciiu-badge" [class.ciiu-section]="sector.ciiu_code?.length === 1">
                  <mat-icon *ngIf="sector.is_ciiu" style="font-size:12px;width:12px;height:12px;vertical-align:middle;margin-right:2px">verified</mat-icon>
                  {{sector.ciiu_code}}
                </span>
                <span *ngIf="!sector.ciiu_code" class="text-muted">—</span>
              </td>
            </ng-container>

            <ng-container matColumnDef="name">
              <th mat-header-cell *matHeaderCellDef>Nombre</th>
              <td mat-cell *matCellDef="let sector">
                <span class="sector-name">{{sector.name}}</span>
              </td>
            </ng-container>

            <ng-container matColumnDef="description">
              <th mat-header-cell *matHeaderCellDef>Descripcion</th>
              <td mat-cell *matCellDef="let sector">{{sector.description || '—'}}</td>
            </ng-container>

            <ng-container matColumnDef="actions">
              <th mat-header-cell *matHeaderCellDef>Acciones</th>
              <td mat-cell *matCellDef="let sector">
                <div class="action-buttons">
                  <button mat-icon-button class="action-btn edit" (click)="onEdit(sector)" matTooltip="Editar">
                    <mat-icon>edit</mat-icon>
                  </button>
                  <button mat-icon-button class="action-btn delete" (click)="onDelete(sector)"
                    [matTooltip]="sector.is_ciiu ? 'No se puede eliminar un sector del catalogo CIIU' : 'Eliminar'"
                    [disabled]="sector.is_ciiu">
                    <mat-icon>delete</mat-icon>
                  </button>
                </div>
              </td>
            </ng-container>

            <tr mat-header-row *matHeaderRowDef="displayedColumns"></tr>
            <tr mat-row *matRowDef="let row; columns: displayedColumns;" class="prestige-row" [class.ciiu-row]="row.is_ciiu"></tr>

            <tr class="mat-row" *matNoDataRow>
              <td class="mat-cell empty-state" colspan="4">
                <div class="empty-msg-wrap" *ngIf="!loading">
                   <mat-icon>list</mat-icon>
                   <p>No se encontraron sectores.</p>
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
    .header-section { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px; gap: 20px; }
    .title-group h1 { font-size: 28px; font-weight: 600; color: var(--prestige-primary); margin: 0 0 4px 0; letter-spacing: -0.02em; }
    .subtitle { color: var(--prestige-text-muted); margin: 0; font-size: 14px; }
    .btn-prestige { background: var(--prestige-primary); color: white; padding: 0 20px; border-radius: 10px; font-weight: 500; height: 42px; font-size: 14px; }
    .table-wrapper { padding: 0; overflow: hidden; }
    .table-header { padding: 24px 24px 16px 24px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--prestige-border); }
    .legend-chip { display: flex; align-items: center; gap: 4px; font-size: 11px; color: var(--prestige-text-muted); padding: 4px 10px; background: rgba(26,35,126,0.05); border-radius: 16px; border: 1px solid rgba(26,35,126,0.15); }
    .search-field { width: 320px; font-size: 13px; }
    .prestige-table { width: 100%; border: none; min-width: 700px; }
    .table-container { width: 100%; overflow-x: auto; position: relative; min-height: 200px; }
    .prestige-row:hover { background: var(--row-hover-bg) !important; cursor: pointer; }
    .ciiu-row td { background: rgba(26,35,126,0.02); }
    .ciiu-badge { display: inline-flex; align-items: center; font-size: 11px; font-weight: 700; background: rgba(26,35,126,0.08); color: #1a237e; border-radius: 4px; padding: 2px 7px; letter-spacing: .04em; }
    .ciiu-badge.ciiu-section { background: #1a237e; color: white; }
    .sector-name { font-weight: 600; color: var(--prestige-text); }
    .text-muted { color: var(--prestige-text-muted); }
    .action-buttons { display: flex; gap: 2px; }
    .action-btn { color: var(--prestige-text-muted); width: 36px; height: 36px; }
    .action-btn.edit:hover { color: var(--prestige-primary); background: rgba(26, 35, 126, 0.05); }
    .action-btn.delete:hover { color: #d32f2f; background: #ffebee; }
    .action-btn:disabled { opacity: 0.3; }
    .spinner-container { padding: 48px; text-align: center; color: var(--prestige-text-muted); }
    .empty-state { padding: 48px; text-align: center; color: var(--prestige-text-muted); }
  `]
})
export class SectorManagementComponent implements OnInit {
    private adminService = inject(AdminService);
    private cdr = inject(ChangeDetectorRef);
    private dialog = inject(MatDialog);

    dataSource = new MatTableDataSource<any>([]);
    displayedColumns = ['ciiu_code', 'name', 'description', 'actions'];
    loading = true;

    ngOnInit() {
        this.loadSectors();
    }

    loadSectors() {
        this.loading = true;
        this.adminService.getSectors().subscribe({
            next: (data) => {
                this.dataSource.data = data || [];
                this.loading = false;
                this.cdr.detectChanges();
            },
            error: (err) => {
                console.error('[SectorMgmt] Error:', err);
                this.loading = false;
                this.cdr.detectChanges();
            }
        });
    }

    applyFilter(event: Event) {
        const filterValue = (event.target as HTMLInputElement).value;
        this.dataSource.filter = filterValue.trim().toLowerCase();
    }

    onCreate() {
        const dialogRef = this.dialog.open(SectorDialog, { data: {} });
        dialogRef.afterClosed().subscribe(result => {
            if (result) {
                this.adminService.createSector(result).subscribe(() => this.loadSectors());
            }
        });
    }

    onEdit(sector: any) {
        const dialogRef = this.dialog.open(SectorDialog, { data: { ...sector } });
        dialogRef.afterClosed().subscribe(result => {
            if (result) {
                this.adminService.updateSector(sector.id, result).subscribe(() => this.loadSectors());
            }
        });
    }

    onDelete(sector: any) {
        if (sector.is_ciiu) return;
        const dialogRef = this.dialog.open(ConfirmDialog, {
            data: {
                title: 'Eliminar Sector',
                message: `Esta seguro de eliminar el sector "${sector.name}"?`,
                confirmText: 'Eliminar',
                color: 'warn'
            }
        });

        dialogRef.afterClosed().subscribe(result => {
            if (result) {
                this.adminService.deleteSector(sector.id).subscribe(() => this.loadSectors());
            }
        });
    }
}
