import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatTableDataSource, MatTableModule } from '@angular/material/table';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import { MatSortModule } from '@angular/material/sort';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule } from '@angular/material/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatCardModule } from '@angular/material/card';
import { ReactiveFormsModule, FormsModule, FormBuilder, FormGroup } from '@angular/forms';
import { AdminService } from '../../../services/admin.service';

@Component({
    selector: 'app-audit-logs',
    standalone: true,
    imports: [
        CommonModule,
        MatTableModule,
        MatPaginatorModule,
        MatSortModule,
        MatFormFieldModule,
        MatInputModule,
        MatSelectModule,
        MatDatepickerModule,
        MatNativeDateModule,
        MatButtonModule,
        MatIconModule,
        MatCardModule,
        ReactiveFormsModule,
        FormsModule
    ],
    template: `
    <div class="audit-page">
      <div class="header-section">
        <div class="title-group">
          <h1>Auditoría del Sistema</h1>
          <p class="subtitle">Registro detallado de actividades y cambios</p>
        </div>
      </div>

      <div class="filters-card glass-card">
        <form [formGroup]="filterForm" (ngSubmit)="applyFilters()">
          <div class="filters-grid">
            <mat-form-field appearance="outline">
              <mat-label>Usuario (ID)</mat-label>
              <input matInput formControlName="user_id" placeholder="Ej: 1">
            </mat-form-field>

            <mat-form-field appearance="outline">
              <mat-label>Modelo / Entidad</mat-label>
              <input matInput formControlName="model" placeholder="Ej: User, Scope">
            </mat-form-field>

            <mat-form-field appearance="outline">
              <mat-label>Acción</mat-label>
              <mat-select formControlName="action">
                <mat-option value="">Todas</mat-option>
                <mat-option value="created">Creación</mat-option>
                <mat-option value="updated">Actualización</mat-option>
                <mat-option value="deleted">Eliminación</mat-option>
              </mat-select>
            </mat-form-field>

            <mat-form-field appearance="outline">
              <mat-label>Desde</mat-label>
              <input matInput [matDatepicker]="pickerFrom" formControlName="date_from">
              <mat-datepicker-toggle matSuffix [for]="pickerFrom"></mat-datepicker-toggle>
              <mat-datepicker #pickerFrom></mat-datepicker>
            </mat-form-field>

            <mat-form-field appearance="outline">
              <mat-label>Hasta</mat-label>
              <input matInput [matDatepicker]="pickerTo" formControlName="date_to">
              <mat-datepicker-toggle matSuffix [for]="pickerTo"></mat-datepicker-toggle>
              <mat-datepicker #pickerTo></mat-datepicker>
            </mat-form-field>

            <div class="filter-actions">
              <button mat-raised-button color="primary" type="submit">
                <mat-icon>search</mat-icon> Filtrar
              </button>
              <button mat-stroked-button type="button" (click)="resetFilters()">
                Limpiar
              </button>
            </div>
          </div>
        </form>
      </div>

      <div class="glass-card table-card">
        <div class="table-container">
          <table mat-table [dataSource]="dataSource" class="audit-table">
            
            <ng-container matColumnDef="user">
              <th mat-header-cell *matHeaderCellDef>Usuario</th>
              <td mat-cell *matCellDef="let log">
                <div class="user-cell">
                  <div class="user-avatar">{{ (log.user?.name || '?').charAt(0) }}</div>
                  <div class="user-info">
                    <span class="user-name">{{ log.user?.name || 'Sistema/Desconocido' }}</span>
                    <span class="user-role">{{ log.user?.role || '' }}</span>
                  </div>
                </div>
              </td>
            </ng-container>

            <ng-container matColumnDef="action">
              <th mat-header-cell *matHeaderCellDef>Acción</th>
              <td mat-cell *matCellDef="let log">
                <span class="action-badge" [ngClass]="log.action">
                  {{ log.action | uppercase }}
                </span>
              </td>
            </ng-container>

            <ng-container matColumnDef="details">
              <th mat-header-cell *matHeaderCellDef>Detalle del Cambio</th>
              <td mat-cell *matCellDef="let log" class="details-cell">
                <div class="model-info">
                  <span class="model-name">{{ getModelName(log.model) }}</span>
                  <span class="model-id">#{{ log.model_id }}</span>
                </div>
                <div class="changes-json" *ngIf="log.details">
                  <pre>{{ log.details | json }}</pre>
                </div>
              </td>
            </ng-container>
            
            <ng-container matColumnDef="date">
              <th mat-header-cell *matHeaderCellDef>Fecha</th>
              <td mat-cell *matCellDef="let log" class="date-cell">
                {{ log.created_at | date:'medium' }}
                <div class="ip-address">{{ log.ip_address }}</div>
              </td>
            </ng-container>

            <tr mat-header-row *matHeaderRowDef="displayedColumns"></tr>
            <tr mat-row *matRowDef="let row; columns: displayedColumns;"></tr>
          </table>

          <div *ngIf="dataSource.data.length === 0 && !loading" class="empty-state">
            <mat-icon>history</mat-icon>
            <p>No se encontraron registros de auditoría.</p>
          </div>
          
          <div *ngIf="loading" class="spinner-container">
            Cargando registros...
          </div>
        </div>

        <mat-paginator [length]="totalLogs"
                       [pageSize]="pageSize"
                       [pageSizeOptions]="[10, 20, 50]"
                       (page)="onPageChange($event)">
        </mat-paginator>
      </div>
    </div>
  `,
    styles: [`
    .audit-page { padding: 24px; max-width: 1400px; margin: 0 auto; }
    
    .header-section { margin-bottom: 24px; }
    .title-group h1 { font-size: 28px; font-weight: 600; color: var(--prestige-primary); margin: 0; }
    .subtitle { color: var(--prestige-text-muted); margin: 0; }

    .glass-card { 
      background: rgba(255, 255, 255, 0.9); border: 1px solid var(--prestige-border); 
      border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); margin-bottom: 24px;
    }

    .filters-card { padding: 24px; }
    .filters-grid { display: flex; flex-wrap: wrap; gap: 16px; align-items: center; }
    .filter-actions { display: flex; gap: 8px; margin-left: auto; }

    .table-container { overflow-x: auto; }
    .audit-table { width: 100%; background: transparent; }

    .user-cell { display: flex; align-items: center; gap: 12px; }
    .user-avatar { 
      width: 32px; height: 32px; background: var(--prestige-primary); color: white; 
      border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600;
    }
    .user-info { display: flex; flex-direction: column; line-height: 1.2; }
    .user-name { font-weight: 500; font-size: 14px; }
    .user-role { font-size: 11px; color: var(--prestige-text-muted); }

    .action-badge { 
      padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; 
      text-transform: uppercase; letter-spacing: 0.5px;
    }
    .created { background: #dcfce7; color: #166534; }
    .updated { background: #dbeafe; color: #1e40af; }
    .deleted { background: #fee2e2; color: #991b1b; }

    .model-info { font-size: 12px; font-weight: 600; color: var(--prestige-text); margin-bottom: 4px; }
    .changes-json pre { 
      margin: 0; background: rgba(0,0,0,0.03); padding: 8px; border-radius: 6px; 
      font-size: 11px; max-height: 100px; overflow-y: auto; color: #475569;
    }

    .date-cell { font-size: 13px; color: var(--prestige-text); }
    .ip-address { font-size: 11px; color: var(--prestige-text-muted); }

    .empty-state { text-align: center; padding: 40px; color: var(--prestige-text-muted); }
    .empty-state mat-icon { font-size: 48px; width: 48px; height: 48px; margin-bottom: 16px; opacity: 0.5; }

    .spinner-container { text-align: center; padding: 20px; color: var(--prestige-text-muted); }
  `]
})
export class AuditLogsComponent implements OnInit {
    adminService = inject(AdminService);
    fb = inject(FormBuilder);

    dataSource = new MatTableDataSource<any>([]);
    displayedColumns = ['user', 'action', 'details', 'date'];

    totalLogs = 0;
    pageSize = 20;
    currentPage = 1;
    loading = false;

    filterForm: FormGroup;

    constructor() {
        this.filterForm = this.fb.group({
            user_id: [''],
            model: [''],
            action: [''],
            date_from: [''],
            date_to: ['']
        });
    }

    ngOnInit() {
        this.loadLogs();
    }

    loadLogs() {
        this.loading = true;
        const filters = this.filterForm.value;

        // Format dates if present
        const params: any = {
            page: this.currentPage,
            ...filters
        };

        if (filters.date_from) params.date_from = filters.date_from.toISOString().split('T')[0];
        if (filters.date_to) params.date_to = filters.date_to.toISOString().split('T')[0];

        this.adminService.getAuditLogs(params).subscribe({
            next: (res) => {
                this.dataSource.data = res.data;
                this.totalLogs = res.total;
                this.loading = false;
            },
            error: (err) => {
                console.error('Error loading logs', err);
                this.loading = false;
            }
        });
    }

    applyFilters() {
        this.currentPage = 1;
        this.loadLogs();
    }

    resetFilters() {
        this.filterForm.reset();
        this.currentPage = 1;
        this.loadLogs();
    }

    onPageChange(event: PageEvent) {
        this.currentPage = event.pageIndex + 1;
        this.pageSize = event.pageSize;
        this.loadLogs();
    }

    getModelName(fullClass: string): string {
        return fullClass.split('\\').pop() || fullClass;
    }
}
