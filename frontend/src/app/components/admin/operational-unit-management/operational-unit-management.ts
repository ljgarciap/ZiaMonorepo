import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatDialogModule, MatDialog } from '@angular/material/dialog';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { AdminService } from '../../../services/admin.service';
import { ConfirmDialog } from '../admin-dialogs';

@Component({
  selector: 'app-operational-unit-management',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatTableModule,
    MatButtonModule,
    MatIconModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatProgressSpinnerModule,
    MatTooltipModule,
    MatDialogModule,
    MatSnackBarModule,
  ],
  template: `
    <div class="management-page">
      <div class="header-section">
        <div class="title-group">
          <h1>Unidades Operativas</h1>
          <p class="subtitle">Gestiona las áreas o divisiones de tus empresas para segmentar el registro de emisiones.</p>
        </div>
      </div>

      <!-- Company selector -->
      <div class="glass-card company-selector-card" *ngIf="companies.length > 1">
        <mat-form-field appearance="outline" class="company-select-field prestige-field">
          <mat-label>Empresa</mat-label>
          <mat-select [(ngModel)]="selectedCompanyId" (ngModelChange)="onCompanyChange($event)">
            <mat-option *ngFor="let c of companies" [value]="c.id">{{ c.name }}</mat-option>
          </mat-select>
        </mat-form-field>
      </div>

      <div class="spinner-container" *ngIf="loading">
        <mat-spinner diameter="40"></mat-spinner>
        <p>Cargando unidades...</p>
      </div>

      <ng-container *ngIf="!loading && selectedCompanyId">
        <!-- Add unit form -->
        <div class="glass-card add-form-card">
          <h3 class="form-title">{{ editingUnit ? 'Editar Unidad' : 'Nueva Unidad Operativa' }}</h3>
          <div class="form-row">
            <mat-form-field appearance="outline" class="prestige-field flex-field">
              <mat-label>Nombre</mat-label>
              <input matInput [(ngModel)]="formData.name" placeholder="Ej: Planta Norte, Área Administrativa">
            </mat-form-field>
            <mat-form-field appearance="outline" class="prestige-field flex-field">
              <mat-label>Descripción (opcional)</mat-label>
              <input matInput [(ngModel)]="formData.description" placeholder="Descripción breve">
            </mat-form-field>
            <button mat-flat-button class="btn-prestige" (click)="save()" [disabled]="saving || !formData.name.trim()">
              <mat-icon>{{ editingUnit ? 'save' : 'add' }}</mat-icon>
              {{ editingUnit ? 'Guardar' : 'Agregar' }}
            </button>
            <button mat-stroked-button *ngIf="editingUnit" (click)="cancelEdit()">Cancelar</button>
          </div>
        </div>

        <!-- Units table -->
        <div class="glass-card table-wrapper">
          <div class="table-container">
            <table mat-table [dataSource]="dataSource" class="prestige-table">

              <ng-container matColumnDef="name">
                <th mat-header-cell *matHeaderCellDef>Unidad</th>
                <td mat-cell *matCellDef="let unit">
                  <div class="unit-name">{{ unit.name }}</div>
                  <div class="unit-desc" *ngIf="unit.description">{{ unit.description }}</div>
                </td>
              </ng-container>

              <ng-container matColumnDef="users_count">
                <th mat-header-cell *matHeaderCellDef>Usuarios</th>
                <td mat-cell *matCellDef="let unit">
                  <span class="count-badge">{{ unit.users_count ?? (unit.users?.length ?? 0) }}</span>
                </td>
              </ng-container>

              <ng-container matColumnDef="actions">
                <th mat-header-cell *matHeaderCellDef>Acciones</th>
                <td mat-cell *matCellDef="let unit">
                  <div class="action-buttons">
                    <button mat-icon-button class="action-btn edit" (click)="startEdit(unit)" matTooltip="Editar">
                      <mat-icon>edit</mat-icon>
                    </button>
                    <button mat-icon-button class="action-btn delete" (click)="deleteUnit(unit)" matTooltip="Eliminar">
                      <mat-icon>delete_outline</mat-icon>
                    </button>
                  </div>
                </td>
              </ng-container>

              <tr mat-header-row *matHeaderRowDef="displayedColumns"></tr>
              <tr mat-row *matRowDef="let row; columns: displayedColumns;" class="prestige-row"></tr>

              <tr class="mat-row" *matNoDataRow>
                <td class="mat-cell empty-state" colspan="3">
                  <mat-icon>workspaces</mat-icon>
                  <p>No hay unidades operativas. Agrega la primera arriba.</p>
                </td>
              </tr>
            </table>
          </div>
        </div>
      </ng-container>
    </div>
  `,
  styles: [`
    .management-page { padding: 24px; max-width: 1200px; margin: 0 auto; }
    .header-section { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; }
    .title-group h1 { font-size: 28px; font-weight: 600; color: var(--prestige-primary); margin: 0 0 4px; }
    .subtitle { color: var(--prestige-text-muted); margin: 0; font-size: 14px; }

    .company-selector-card { padding: 16px 24px; margin-bottom: 16px; }
    .company-select-field { min-width: 260px; margin: 0; }

    .add-form-card { padding: 20px 24px; margin-bottom: 16px; }
    .form-title { font-size: 15px; font-weight: 600; color: var(--prestige-text); margin: 0 0 16px; }
    .form-row { display: flex; gap: 12px; align-items: flex-start; flex-wrap: wrap; }
    .flex-field { flex: 1; min-width: 200px; }

    .btn-prestige {
      background: var(--prestige-primary); color: white; padding: 0 20px;
      border-radius: 10px; font-weight: 500; height: 42px; margin-top: 4px;
    }

    .glass-card { border-radius: 16px; overflow: hidden; }
    .table-wrapper { padding: 0; margin-top: 0; }
    .table-container { width: 100%; overflow-x: auto; }
    .prestige-table { width: 100%; }
    .prestige-row:hover { background: var(--row-hover-bg) !important; }

    .unit-name { font-weight: 600; color: var(--prestige-text); font-size: 14px; }
    .unit-desc { font-size: 12px; color: var(--prestige-text-muted); margin-top: 2px; }

    .count-badge {
      background: var(--status-neutral-bg); color: var(--status-neutral-text);
      padding: 3px 10px; border-radius: 8px; font-weight: 700; font-size: 12px;
    }

    .action-buttons { display: flex; gap: 2px; }
    .action-btn { color: var(--prestige-text-muted); width: 36px; height: 36px; }
    .action-btn.edit:hover { color: var(--prestige-primary); background: rgba(26, 35, 126, 0.05); }
    .action-btn.delete:hover { color: #d32f2f; background: #ffebee; }

    .spinner-container { padding: 48px; text-align: center; color: var(--prestige-text-muted); font-size: 14px; }
    .empty-state { padding: 48px; text-align: center; color: var(--prestige-text-muted); }
    .empty-state mat-icon { font-size: 40px; width: 40px; height: 40px; opacity: 0.3; display: block; margin: 0 auto 12px; }
  `]
})
export class OperationalUnitManagementComponent implements OnInit {
  private adminService = inject(AdminService);
  private dialog = inject(MatDialog);
  private snackBar = inject(MatSnackBar);

  companies: any[] = [];
  selectedCompanyId: number | null = null;
  units: any[] = [];
  dataSource = new MatTableDataSource<any>([]);
  displayedColumns = ['name', 'users_count', 'actions'];
  loading = false;
  saving = false;

  editingUnit: any = null;
  formData = { name: '', description: '' };

  ngOnInit() {
    this.adminService.getCompanies().subscribe({
      next: (data) => {
        this.companies = data || [];
        if (this.companies.length === 1) {
          this.selectedCompanyId = this.companies[0].id;
          this.loadUnits();
        } else if (this.companies.length > 1) {
          this.selectedCompanyId = this.companies[0].id;
          this.loadUnits();
        }
      }
    });
  }

  onCompanyChange(id: number) {
    this.selectedCompanyId = id;
    this.loadUnits();
  }

  loadUnits() {
    if (!this.selectedCompanyId) return;
    this.loading = true;
    this.adminService.getOperationalUnits(this.selectedCompanyId).subscribe({
      next: (data) => { this.dataSource.data = data || []; this.loading = false; },
      error: () => { this.loading = false; }
    });
  }

  save() {
    if (!this.selectedCompanyId || !this.formData.name.trim()) return;
    this.saving = true;

    const call = this.editingUnit
      ? this.adminService.updateOperationalUnit(this.selectedCompanyId, this.editingUnit.id, this.formData)
      : this.adminService.createOperationalUnit(this.selectedCompanyId, this.formData);

    call.subscribe({
      next: () => {
        this.saving = false;
        this.cancelEdit();
        this.loadUnits();
        this.snackBar.open('Unidad guardada correctamente.', '', { duration: 3000 });
      },
      error: () => {
        this.saving = false;
        this.snackBar.open('Error al guardar la unidad.', '', { duration: 3000 });
      }
    });
  }

  startEdit(unit: any) {
    this.editingUnit = unit;
    this.formData = { name: unit.name, description: unit.description || '' };
  }

  cancelEdit() {
    this.editingUnit = null;
    this.formData = { name: '', description: '' };
  }

  deleteUnit(unit: any) {
    const ref = this.dialog.open(ConfirmDialog, {
      data: {
        title: 'Eliminar Unidad Operativa',
        message: `¿Eliminar "${unit.name}"? Los usuarios asignados quedarán sin unidad.`,
        confirmText: 'Eliminar',
        color: 'warn'
      }
    });
    ref.afterClosed().subscribe(ok => {
      if (ok && this.selectedCompanyId) {
        this.adminService.deleteOperationalUnit(this.selectedCompanyId, unit.id).subscribe({
          next: () => { this.loadUnits(); this.snackBar.open('Unidad eliminada.', '', { duration: 3000 }); },
          error: () => { this.snackBar.open('Error al eliminar.', '', { duration: 3000 }); }
        });
      }
    });
  }
}
