import { Component, inject, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatDialogModule, MatDialog } from '@angular/material/dialog';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { AdminService } from '../../../services/admin.service';
import { UnitDialog, ConfirmDialog } from '../admin-dialogs';

@Component({
  selector: 'app-unit-management',
  standalone: true,
  imports: [
    CommonModule,
    MatTableModule,
    MatButtonModule,
    MatIconModule,
    MatDialogModule,
    MatTooltipModule,
    MatSlideToggleModule,
  ],
  template: `
    <div class="management-page">
      <div class="header-section">
        <div class="title-group">
          <h1>Unidades de Medida</h1>
          <p class="subtitle">Catálogo GHG Protocol preinstalado. Puedes agregar unidades propias y activar/desactivar las personalizadas.</p>
        </div>
        <button mat-flat-button class="btn-prestige" (click)="onCreate()">
          <mat-icon>add</mat-icon> Nueva Unidad
        </button>
      </div>

      <div class="glass-card">
        <table mat-table [dataSource]="dataSource" class="premium-table">

          <ng-container matColumnDef="name">
            <th mat-header-cell *matHeaderCellDef> Nombre </th>
            <td mat-cell *matCellDef="let element">
              <div class="name-cell">
                {{ element.name }}
                <span class="ghg-badge" *ngIf="element.is_standard" matTooltip="Unidad estándar GHG Protocol — no eliminable">
                  <mat-icon>verified</mat-icon> GHG Protocol
                </span>
              </div>
            </td>
          </ng-container>

          <ng-container matColumnDef="symbol">
            <th mat-header-cell *matHeaderCellDef> Símbolo </th>
            <td mat-cell *matCellDef="let element">
              <span class="unit-badge" [class.inactive-badge]="!element.is_active">{{ element.symbol }}</span>
            </td>
          </ng-container>

          <ng-container matColumnDef="status">
            <th mat-header-cell *matHeaderCellDef> Estado </th>
            <td mat-cell *matCellDef="let element">
              <mat-slide-toggle
                [checked]="element.is_active"
                [disabled]="element.is_standard"
                [matTooltip]="element.is_standard ? 'Las unidades estándar siempre están activas' : (element.is_active ? 'Desactivar' : 'Activar')"
                (change)="onToggle(element)">
                {{ element.is_active ? 'Activa' : 'Inactiva' }}
              </mat-slide-toggle>
            </td>
          </ng-container>

          <ng-container matColumnDef="actions">
            <th mat-header-cell *matHeaderCellDef> Acciones </th>
            <td mat-cell *matCellDef="let element">
              <button mat-icon-button color="primary" (click)="onEdit(element)" matTooltip="Editar">
                <mat-icon>edit</mat-icon>
              </button>
              <button mat-icon-button color="warn" (click)="onDelete(element)"
                [disabled]="element.is_standard"
                [matTooltip]="element.is_standard ? 'No se pueden eliminar unidades GHG estándar' : 'Eliminar'">
                <mat-icon>delete</mat-icon>
              </button>
            </td>
          </ng-container>

          <tr mat-header-row *matHeaderRowDef="displayedColumns"></tr>
          <tr mat-row *matRowDef="let row; columns: displayedColumns;" [class.row-inactive]="!row.is_active"></tr>
        </table>
      </div>
    </div>
  `,
  styles: [`
    .management-page { padding: 24px; max-width: 1200px; margin: 0 auto; }
    .header-section { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; }
    .title-group h1 { font-size: 28px; font-weight: 600; color: var(--prestige-primary); margin: 0; }
    .subtitle { color: var(--prestige-text-muted); margin-top: 5px; font-size: 13px; }

    .glass-card {
      background: var(--glass-bg);
      backdrop-filter: blur(10px);
      border-radius: 12px;
      border: 1px solid var(--prestige-border);
      overflow: hidden;
      box-shadow: var(--glass-shadow);
    }

    .premium-table { width: 100%; background: transparent !important; }
    .premium-table th { color: var(--prestige-text-muted); font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; }
    .premium-table td { color: var(--prestige-text); font-size: 14px; }
    .premium-table tr:hover { background: var(--row-hover-bg) !important; }
    .row-inactive td { opacity: 0.5; }

    .name-cell { display: flex; align-items: center; gap: 8px; }
    .ghg-badge {
      display: inline-flex; align-items: center; gap: 3px;
      background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7;
      border-radius: 12px; padding: 2px 8px; font-size: 10px; font-weight: 700;
    }
    .ghg-badge mat-icon { font-size: 12px; width: 12px; height: 12px; }

    .unit-badge {
      background: var(--status-neutral-bg);
      padding: 4px 10px; border-radius: 6px; font-weight: 700;
      color: var(--status-neutral-text); font-size: 11px;
      font-family: 'Outfit', sans-serif;
      border: 1px solid var(--prestige-border); text-transform: uppercase;
    }
    .inactive-badge { opacity: 0.4; text-decoration: line-through; }

    .btn-prestige { background: var(--prestige-primary); color: white; border-radius: 10px; height: 42px; }
  `]
})
export class UnitManagementComponent implements OnInit {
  private adminService = inject(AdminService);
  private dialog = inject(MatDialog);
  private cdr = inject(ChangeDetectorRef);

  dataSource = new MatTableDataSource<any>([]);
  displayedColumns = ['name', 'symbol', 'status', 'actions'];

  ngOnInit() {
    this.loadData();
  }

  loadData() {
    this.adminService.getUnits().subscribe(data => {
      this.dataSource.data = data;
      this.cdr.detectChanges();
    });
  }

  onCreate() {
    const dialogRef = this.dialog.open(UnitDialog, { width: '400px', data: {} });
    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.adminService.createUnit(result).subscribe(() => this.loadData());
      }
    });
  }

  onEdit(unit: any) {
    const dialogRef = this.dialog.open(UnitDialog, { width: '400px', data: { ...unit } });
    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.adminService.updateUnit(unit.id, result).subscribe(() => this.loadData());
      }
    });
  }

  onToggle(unit: any) {
    this.adminService.toggleUnit(unit.id).subscribe(() => this.loadData());
  }

  onDelete(unit: any) {
    if (unit.is_standard) return;
    const dialogRef = this.dialog.open(ConfirmDialog, {
      data: {
        title: 'Eliminar Unidad',
        message: `¿Estás seguro de eliminar "${unit.name}"? Si está en uso por factores de emisión, se impedirá la eliminación.`,
        confirmText: 'Eliminar',
        color: 'warn'
      }
    });
    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.adminService.deleteUnit(unit.id).subscribe({
          next: () => this.loadData(),
          error: (err) => alert(err.error?.message || 'No se puede eliminar: está en uso.')
        });
      }
    });
  }
}
