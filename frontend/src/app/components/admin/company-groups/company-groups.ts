import { Component, inject, OnInit, ChangeDetectorRef, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatSelectModule } from '@angular/material/select';
import { MatOptionModule } from '@angular/material/core';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { FormsModule } from '@angular/forms';
import { AdminService } from '../../../services/admin.service';
import { CompanyGroupDialog, ConfirmDialog } from '../admin-dialogs';

@Component({
  selector: 'app-company-groups',
  standalone: true,
  imports: [
    CommonModule,
    MatTableModule,
    MatButtonModule,
    MatIconModule,
    MatSelectModule,
    MatOptionModule,
    MatFormFieldModule,
    MatProgressSpinnerModule,
    MatTooltipModule,
    MatSnackBarModule,
    MatDialogModule,
    FormsModule,
  ],
  template: `
    <div class="management-page">
      <div class="header-section">
        <div class="title-group">
          <h1>Grupos de Empresas</h1>
          <p class="subtitle">Agrupa empresas que comparten infraestructura (ej. un edificio) para ver su huella de carbono consolidada.</p>
        </div>
        <button mat-flat-button color="primary" (click)="onCreateGroup()">
          <mat-icon>add</mat-icon> Nuevo Grupo
        </button>
      </div>

      <div class="glass-card table-wrapper">
        <div class="spinner-container" *ngIf="loading()">
          <mat-spinner diameter="40"></mat-spinner>
          <p>Cargando grupos...</p>
        </div>

        <div class="table-container" *ngIf="!loading()">
          <table mat-table [dataSource]="dataSource" class="prestige-table">
            <ng-container matColumnDef="name">
              <th mat-header-cell *matHeaderCellDef>Nombre</th>
              <td mat-cell *matCellDef="let row">
                <span class="group-name">{{ row.name }}</span>
                <p class="group-description" *ngIf="row.description">{{ row.description }}</p>
              </td>
            </ng-container>

            <ng-container matColumnDef="companies">
              <th mat-header-cell *matHeaderCellDef>Empresas</th>
              <td mat-cell *matCellDef="let row">{{ row.companies?.length || 0 }}</td>
            </ng-container>

            <ng-container matColumnDef="actions">
              <th mat-header-cell *matHeaderCellDef>Acciones</th>
              <td mat-cell *matCellDef="let row">
                <button mat-stroked-button (click)="selectGroup(row)" matTooltip="Ver resumen consolidado">
                  <mat-icon>bar_chart</mat-icon> Ver Resumen
                </button>
                <button mat-icon-button color="warn" (click)="onDeleteGroup(row)" matTooltip="Eliminar grupo">
                  <mat-icon>delete</mat-icon>
                </button>
              </td>
            </ng-container>

            <tr mat-header-row *matHeaderRowDef="displayedColumns"></tr>
            <tr mat-row *matRowDef="let row; columns: displayedColumns;" class="prestige-row"></tr>

            <tr class="mat-row" *matNoDataRow>
              <td class="mat-cell empty-state" colspan="3">
                <mat-icon>domain_disabled</mat-icon>
                <p>No hay grupos de empresas registrados todavía.</p>
              </td>
            </tr>
          </table>
        </div>
      </div>

      <!-- Detalle / resumen consolidado del grupo seleccionado -->
      <div class="glass-card detail-card" *ngIf="selectedGroup()">
        <div class="detail-header">
          <h2>{{ selectedGroup().name }}</h2>
          <mat-form-field appearance="outline" class="year-select">
            <mat-label>Año</mat-label>
            <mat-select [(ngModel)]="summaryYear" (selectionChange)="loadSummary()">
              <mat-option [value]="null">Todos los períodos</mat-option>
              <mat-option *ngFor="let y of availableYears" [value]="y">{{ y }}</mat-option>
            </mat-select>
          </mat-form-field>
          <button mat-icon-button (click)="closeDetail()" matTooltip="Cerrar">
            <mat-icon>close</mat-icon>
          </button>
        </div>

        <div class="spinner-container" *ngIf="loadingSummary()">
          <mat-spinner diameter="32"></mat-spinner>
        </div>

        <ng-container *ngIf="!loadingSummary() && summary()">
          <div class="kpi-grid">
            <div class="kpi-card">
              <span class="kpi-label">Huella Total del Grupo</span>
              <span class="kpi-value">{{ summary().total_co2e | number:'1.2-2' }} <small>tCO2e</small></span>
            </div>
            <div class="kpi-card" *ngFor="let s of summary().by_scope">
              <span class="kpi-label">{{ s.scope_name }}</span>
              <span class="kpi-value">{{ s.total_co2e | number:'1.2-2' }} <small>tCO2e</small></span>
            </div>
          </div>

          <table mat-table [dataSource]="summary().by_company" class="prestige-table company-table">
            <ng-container matColumnDef="company_name">
              <th mat-header-cell *matHeaderCellDef>Empresa</th>
              <td mat-cell *matCellDef="let row">{{ row.company_name }}</td>
            </ng-container>
            <ng-container matColumnDef="total_co2e">
              <th mat-header-cell *matHeaderCellDef>Huella (tCO2e)</th>
              <td mat-cell *matCellDef="let row">{{ row.total_co2e | number:'1.2-2' }}</td>
            </ng-container>
            <ng-container matColumnDef="remove">
              <th mat-header-cell *matHeaderCellDef></th>
              <td mat-cell *matCellDef="let row">
                <button mat-icon-button color="warn" (click)="onRemoveCompany(row.company_id)" matTooltip="Quitar del grupo">
                  <mat-icon>remove_circle_outline</mat-icon>
                </button>
              </td>
            </ng-container>
            <tr mat-header-row *matHeaderRowDef="['company_name', 'total_co2e', 'remove']"></tr>
            <tr mat-row *matRowDef="let row; columns: ['company_name', 'total_co2e', 'remove'];"></tr>
            <tr class="mat-row" *matNoDataRow>
              <td class="mat-cell empty-state" colspan="3">
                <p>Este grupo todavía no tiene empresas asignadas.</p>
              </td>
            </tr>
          </table>

          <div class="add-company-row">
            <mat-form-field appearance="outline">
              <mat-label>Agregar empresa al grupo</mat-label>
              <mat-select [(ngModel)]="companyToAdd">
                <mat-option *ngFor="let c of availableCompaniesToAdd()" [value]="c.id">{{ c.name }}</mat-option>
              </mat-select>
            </mat-form-field>
            <button mat-stroked-button [disabled]="!companyToAdd" (click)="onAddCompany()">
              <mat-icon>add</mat-icon> Agregar
            </button>
          </div>
        </ng-container>
      </div>
    </div>
  `,
  styles: [`
    .management-page { padding: 24px; max-width: 1200px; margin: 0 auto; }
    .header-section { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px; }
    .title-group h1 { font-size: 28px; font-weight: 600; color: var(--prestige-primary); margin: 0 0 4px 0; }
    .subtitle { color: var(--prestige-text-muted); margin: 0; font-size: 13px; }

    .glass-card { border-radius: 16px; overflow: hidden; margin-bottom: 24px; }
    .table-wrapper { padding: 0; }
    .table-container { width: 100%; overflow-x: auto; }
    .prestige-table { width: 100%; }
    .prestige-row:hover { background: var(--row-hover-bg) !important; }

    .spinner-container { padding: 48px; text-align: center; color: var(--prestige-text-muted); }
    .empty-state { padding: 48px; text-align: center; color: var(--prestige-text-muted); }
    .empty-state mat-icon { font-size: 40px; width: 40px; height: 40px; opacity: 0.3; display: block; margin: 0 auto 12px; }

    .group-name { font-weight: 600; color: var(--prestige-text); }
    .group-description { margin: 2px 0 0; font-size: 12px; color: var(--prestige-text-muted); }

    .detail-card { padding: 24px; }
    .detail-header { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
    .detail-header h2 { flex: 1; margin: 0; font-size: 20px; }
    .year-select { width: 160px; margin-bottom: -1.25em; }

    .kpi-grid { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; }
    .kpi-card { background: var(--status-neutral-bg); border-radius: 12px; padding: 16px 20px; min-width: 160px; }
    .kpi-label { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .4px; color: var(--prestige-text-muted); margin-bottom: 4px; }
    .kpi-value { font-size: 22px; font-weight: 700; color: var(--prestige-primary); }
    .kpi-value small { font-size: 12px; font-weight: 500; }

    .company-table { margin-bottom: 16px; }
    .add-company-row { display: flex; gap: 12px; align-items: flex-start; }
  `]
})
export class CompanyGroupsComponent implements OnInit {
  private adminService = inject(AdminService);
  private snackBar = inject(MatSnackBar);
  private dialog = inject(MatDialog);
  private cdr = inject(ChangeDetectorRef);

  dataSource = new MatTableDataSource<any>([]);
  displayedColumns = ['name', 'companies', 'actions'];
  loading = signal(true);

  allCompanies: any[] = [];
  selectedGroup = signal<any | null>(null);
  summary = signal<any | null>(null);
  loadingSummary = signal(false);
  summaryYear: number | null = null;
  companyToAdd: number | null = null;
  availableYears: number[] = [];

  ngOnInit() {
    const currentYear = new Date().getFullYear();
    this.availableYears = [currentYear, currentYear - 1, currentYear - 2, currentYear - 3];
    this.load();
    this.adminService.getCompanies().subscribe({
      next: (companies) => { this.allCompanies = companies || []; }
    });
  }

  load() {
    this.loading.set(true);
    this.adminService.getCompanyGroups().subscribe({
      next: (groups) => {
        this.dataSource.data = groups || [];
        this.loading.set(false);
        this.cdr.markForCheck();
      },
      error: () => { this.loading.set(false); this.cdr.markForCheck(); }
    });
  }

  onCreateGroup() {
    const dialogRef = this.dialog.open(CompanyGroupDialog, {
      data: { allCompanies: this.allCompanies }
    });
    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.adminService.createCompanyGroup(result).subscribe({
          next: () => { this.snackBar.open('Grupo creado.', '', { duration: 3000 }); this.load(); },
          error: () => this.snackBar.open('No se pudo crear el grupo.', '', { duration: 3000 })
        });
      }
    });
  }

  onDeleteGroup(group: any) {
    const dialogRef = this.dialog.open(ConfirmDialog, {
      data: {
        title: 'Eliminar Grupo',
        message: `¿Estás seguro de que deseas eliminar el grupo "${group.name}"? Las empresas no se eliminan, solo se desagrupan.`,
        confirmText: 'Eliminar',
        color: 'warn'
      }
    });
    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.adminService.deleteCompanyGroup(group.id).subscribe({
          next: () => {
            this.snackBar.open('Grupo eliminado.', '', { duration: 3000 });
            if (this.selectedGroup()?.id === group.id) this.closeDetail();
            this.load();
          },
          error: () => this.snackBar.open('No se pudo eliminar el grupo.', '', { duration: 3000 })
        });
      }
    });
  }

  selectGroup(group: any) {
    this.selectedGroup.set(group);
    this.summaryYear = null;
    this.loadSummary();
  }

  closeDetail() {
    this.selectedGroup.set(null);
    this.summary.set(null);
  }

  loadSummary() {
    const group = this.selectedGroup();
    if (!group) return;
    this.loadingSummary.set(true);
    this.adminService.getCompanyGroupSummary(group.id, this.summaryYear || undefined).subscribe({
      next: (res) => { this.summary.set(res); this.loadingSummary.set(false); },
      error: () => { this.loadingSummary.set(false); this.snackBar.open('No se pudo cargar el resumen.', '', { duration: 3000 }); }
    });
  }

  availableCompaniesToAdd(): any[] {
    const group = this.selectedGroup();
    if (!group) return [];
    const currentIds = new Set((this.summary()?.by_company || []).map((c: any) => c.company_id));
    return this.allCompanies.filter(c => !currentIds.has(c.id));
  }

  onAddCompany() {
    const group = this.selectedGroup();
    if (!group || !this.companyToAdd) return;
    this.adminService.addCompanyToGroup(group.id, this.companyToAdd).subscribe({
      next: () => {
        this.snackBar.open('Empresa agregada al grupo.', '', { duration: 3000 });
        this.companyToAdd = null;
        this.load();
        this.loadSummary();
      },
      error: () => this.snackBar.open('No se pudo agregar la empresa.', '', { duration: 3000 })
    });
  }

  onRemoveCompany(companyId: number) {
    const group = this.selectedGroup();
    if (!group) return;
    this.adminService.removeCompanyFromGroup(group.id, companyId).subscribe({
      next: () => {
        this.snackBar.open('Empresa removida del grupo.', '', { duration: 3000 });
        this.load();
        this.loadSummary();
      },
      error: () => this.snackBar.open('No se pudo remover la empresa.', '', { duration: 3000 })
    });
  }
}
