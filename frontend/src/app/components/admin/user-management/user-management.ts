import { Component, inject, OnInit, ChangeDetectorRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDialogModule, MatDialog } from '@angular/material/dialog';
import { MatSelectModule } from '@angular/material/select';
import { MatOptionModule } from '@angular/material/core';
import { MatTabsModule } from '@angular/material/tabs';
import { MatPaginatorModule, MatPaginator } from '@angular/material/paginator';
import { AdminService } from '../../../services/admin.service';
import { UserDialog, ConfirmDialog, UserCompaniesDialog } from '../admin-dialogs';

@Component({
  selector: 'app-user-management',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatTableModule,
    MatButtonModule,
    MatIconModule,
    MatFormFieldModule,
    MatInputModule,
    MatTooltipModule,
    MatProgressSpinnerModule,
    MatDialogModule,
    MatSelectModule,
    MatOptionModule,
    MatTabsModule,
    MatPaginatorModule,
  ],
  template: `
    <div class="management-page">
      <div class="header-section">
        <div class="title-group">
          <h1>Control de Accesos</h1>
          <p class="subtitle">Gestiona los permisos, roles y empresas asociadas a los integrantes de la plataforma.</p>
        </div>
        <button mat-flat-button class="btn-prestige" (click)="onCreate()">
          <mat-icon>person_add</mat-icon> Nuevo Usuario
        </button>
      </div>

      <div class="spinner-container" *ngIf="loading">
        <mat-spinner diameter="40"></mat-spinner>
        <p>Obteniendo usuarios...</p>
      </div>

      <div class="tabs-container" *ngIf="!loading">
        <mat-tab-group class="prestige-tabs" (selectedTabChange)="onTabChange()">
          <!-- TAB: ACTIVOS -->
          <mat-tab label="Activos">
            <div class="glass-card table-wrapper">
              <div class="table-header">
                <div class="search-container">
                  <mat-icon class="search-icon">search</mat-icon>
                  <input class="search-input" (keyup)="applyActiveFilter($event)" placeholder="Buscar por nombre o correo...">
                </div>
                <!-- SA-14: filtro por empresa -->
                <mat-form-field appearance="outline" class="company-filter">
                  <mat-label>Filtrar por empresa</mat-label>
                  <mat-select [(ngModel)]="selectedCompanyFilter" (ngModelChange)="applyCompanyFilter()">
                    <mat-option [value]="null">Todas las empresas</mat-option>
                    <mat-option *ngFor="let c of companies" [value]="c.id">{{ c.name }}</mat-option>
                  </mat-select>
                </mat-form-field>
              </div>

              <div class="table-container">
                <table mat-table [dataSource]="activeDataSource" class="prestige-table">
                  <ng-container matColumnDef="name">
                    <th mat-header-cell *matHeaderCellDef>Usuario</th>
                    <td mat-cell *matCellDef="let user">
                      <div class="user-profile-cell">
                        <div class="avatar-prestige" [style.background]="getAvatarColor(user.role)">
                          {{user.name?.charAt(0) || '?'}}
                        </div>
                        <div class="user-info">
                          <span class="full-name">{{user.name}}</span>
                          <span class="user-email">{{user.email}}</span>
                        </div>
                      </div>
                    </td>
                  </ng-container>

                  <ng-container matColumnDef="role">
                    <th mat-header-cell *matHeaderCellDef>Nivel</th>
                    <td mat-cell *matCellDef="let user">
                      <div class="role-chip" [ngClass]="user.role">
                        {{ user.role === 'superadmin' ? 'Super Admin' : (user.role === 'admin' ? 'Administrador' : 'Usuario') }}
                      </div>
                    </td>
                  </ng-container>

                  <!-- SA-14: empresa asignada principal -->
                  <ng-container matColumnDef="company">
                    <th mat-header-cell *matHeaderCellDef>Empresa Principal</th>
                    <td mat-cell *matCellDef="let user">
                      <span class="company-tag" *ngIf="user.companies?.length">{{ user.companies[0]?.name }}</span>
                      <span class="text-muted" *ngIf="!user.companies?.length">—</span>
                      <span class="more-badge" *ngIf="user.companies?.length > 1" matTooltip="{{ getCompanyNames(user) }}">
                        +{{ user.companies.length - 1 }}
                      </span>
                    </td>
                  </ng-container>

                  <!-- SA-14: columna created_at -->
                  <ng-container matColumnDef="created_at">
                    <th mat-header-cell *matHeaderCellDef>Registrado</th>
                    <td mat-cell *matCellDef="let user">
                      <span class="date-text">{{ user.created_at | date:'dd/MM/yyyy' }}</span>
                    </td>
                  </ng-container>

                  <ng-container matColumnDef="actions">
                    <th mat-header-cell *matHeaderCellDef>Acciones</th>
                    <td mat-cell *matCellDef="let user">
                      <div class="action-buttons">
                        <button mat-icon-button class="action-btn edit" (click)="onEdit(user)" matTooltip="Editar Perfil">
                          <mat-icon>edit</mat-icon>
                        </button>
                        <button mat-icon-button class="action-btn" (click)="onViewCompanies(user)" matTooltip="Gestionar Empresas">
                          <mat-icon>business</mat-icon>
                        </button>
                        <button mat-icon-button class="action-btn delete" (click)="onDelete(user)" matTooltip="Suspender">
                          <mat-icon>person_off</mat-icon>
                        </button>
                      </div>
                    </td>
                  </ng-container>

                  <tr mat-header-row *matHeaderRowDef="displayedColumns"></tr>
                  <tr mat-row *matRowDef="let row; columns: displayedColumns;" class="prestige-row"></tr>

                  <tr class="mat-row empty-state-row" *matNoDataRow>
                    <td class="mat-cell" colspan="5">
                       <p>No se encontraron resultados para su búsqueda.</p>
                    </td>
                  </tr>
                </table>
              </div>
              <mat-paginator #activePaginator [pageSizeOptions]="[5, 10, 20]" showFirstLastButtons class="prestige-paginator"></mat-paginator>
            </div>
          </mat-tab>

          <!-- TAB: INACTIVOS -->
          <mat-tab label="Inactivos">
            <div class="glass-card table-wrapper">
              <div class="table-header">
                 <div class="search-container">
                    <mat-icon class="search-icon">search</mat-icon>
                    <input class="search-input" (keyup)="applyInactiveFilter($event)" placeholder="Filtrar inactivos por nombre o correo...">
                 </div>
              </div>
              <div class="table-container">
                <table mat-table [dataSource]="inactiveDataSource" class="prestige-table">
                  <ng-container matColumnDef="name">
                    <th mat-header-cell *matHeaderCellDef>Usuario</th>
                    <td mat-cell *matCellDef="let user">
                       <div class="user-profile-cell">
                          <div class="avatar-prestige suspended" [style.background]="'#9ca3af'">
                            {{user.name?.charAt(0) || '?'}}
                          </div>
                          <div class="user-info">
                            <span class="full-name text-muted">{{user.name}}</span>
                            <span class="user-email">{{user.email}}</span>
                          </div>
                       </div>
                    </td>
                  </ng-container>
                  <ng-container matColumnDef="role">
                    <th mat-header-cell *matHeaderCellDef>Nivel</th>
                    <td mat-cell *matCellDef="let user">
                      <div class="role-chip" [ngClass]="user.role" style="opacity: 0.6;">
                        {{ user.role === 'superadmin' ? 'Super Admin' : (user.role === 'admin' ? 'Administrador' : 'Usuario') }}
                      </div>
                    </td>
                  </ng-container>
                  <ng-container matColumnDef="company">
                    <th mat-header-cell *matHeaderCellDef>Empresa</th>
                    <td mat-cell *matCellDef="let user">
                      <span class="company-tag" style="opacity:0.5;" *ngIf="user.companies?.length">{{ user.companies[0]?.name }}</span>
                      <span class="text-muted" *ngIf="!user.companies?.length">—</span>
                    </td>
                  </ng-container>
                  <ng-container matColumnDef="created_at">
                    <th mat-header-cell *matHeaderCellDef>Registrado</th>
                    <td mat-cell *matCellDef="let user">
                      <span class="date-text">{{ user.created_at | date:'dd/MM/yyyy' }}</span>
                    </td>
                  </ng-container>
                  <ng-container matColumnDef="actions">
                    <th mat-header-cell *matHeaderCellDef>Acciones</th>
                    <td mat-cell *matCellDef="let user">
                      <div class="action-buttons">
                        <button mat-icon-button class="action-btn activate" (click)="onRestore(user)" matTooltip="Reactivar Acceso">
                          <mat-icon>person_add</mat-icon>
                        </button>
                      </div>
                    </td>
                  </ng-container>
                  <tr mat-header-row *matHeaderRowDef="displayedColumns"></tr>
                  <tr mat-row *matRowDef="let row; columns: displayedColumns;" class="prestige-row"></tr>
                  <tr class="mat-row empty-state-row" *matNoDataRow>
                    <td class="mat-cell" colspan="5">
                       <p>No se encontraron resultados.</p>
                    </td>
                  </tr>
                </table>
              </div>
              <mat-paginator #inactivePaginator [pageSizeOptions]="[5, 10, 20]" showFirstLastButtons class="prestige-paginator"></mat-paginator>
            </div>
          </mat-tab>
        </mat-tab-group>
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

    .tabs-container { margin-top: 16px; }
    .table-wrapper { padding: 0; border-radius: 0 0 12px 12px; border-top: none; }

    .table-header {
      padding: 16px 24px; border-bottom: 1px solid var(--prestige-border);
      display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
    }
    .search-container {
      position: relative; display: flex; align-items: center; width: 300px;
      background: rgba(255, 255, 255, 0.03); border: 1px solid var(--prestige-border);
      border-radius: 8px; padding: 6px 12px; transition: all 0.3s ease;
    }
    .search-container:focus-within {
      border-color: var(--prestige-primary);
      box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
    }
    .search-icon { color: var(--prestige-text-muted); margin-right: 8px; font-size: 20px; width: 20px; height: 20px; }
    .search-input { border: none; background: transparent; color: var(--prestige-text); font-size: 13.5px; outline: none; width: 100%; }

    .company-filter { width: 220px; margin: 0; }
    ::ng-deep .company-filter .mat-mdc-form-field-wrapper { padding-bottom: 0; }

    .prestige-table { width: 100%; min-width: 700px; }
    .table-container { width: 100%; overflow-x: auto; position: relative; min-height: 200px; }

    .user-profile-cell { display: flex; align-items: center; gap: 12px; padding: 8px 0; }
    .avatar-prestige {
      width: 36px; height: 36px; border-radius: 50%; color: white;
      display: flex; align-items: center; justify-content: center;
      font-weight: 600; font-size: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .avatar-prestige.suspended { box-shadow: none; border: 1px dashed var(--prestige-border); }
    .user-info { display: flex; flex-direction: column; }
    .full-name { font-weight: 600; color: var(--prestige-text); font-size: 14px; }
    .full-name.text-muted { color: var(--prestige-text-muted); text-decoration: line-through; }
    .user-email { font-size: 12px; color: var(--prestige-text-muted); }

    .company-tag {
      background: var(--status-info-bg); color: var(--status-info-text);
      border: 1px solid var(--prestige-border); border-radius: 12px;
      padding: 2px 8px; font-size: 11px; font-weight: 500;
    }
    .more-badge {
      background: var(--prestige-border); color: var(--prestige-text-muted);
      border-radius: 10px; padding: 1px 6px; font-size: 10px; font-weight: 700;
      margin-left: 4px; cursor: default;
    }
    .text-muted { color: var(--prestige-text-muted); font-size: 12px; }
    .date-text { font-size: 12px; color: var(--prestige-text-muted); }

    .role-chip {
      padding: 4px 12px; border-radius: 30px; font-size: 10px; font-weight: 700;
      text-transform: uppercase; letter-spacing: 0.03em; border: 1px solid transparent;
      width: fit-content;
    }
    .role-chip.superadmin { background: var(--status-error-bg) !important; color: var(--status-error-text) !important; border-color: var(--prestige-border); }
    .role-chip.admin { background: var(--status-info-bg) !important; color: var(--status-info-text) !important; border-color: var(--prestige-border); }
    .role-chip.user { background: var(--status-neutral-bg) !important; color: var(--status-neutral-text) !important; border-color: var(--prestige-border); }

    .action-buttons { display: flex; gap: 4px; }
    .action-btn { color: var(--prestige-text-muted); width: 36px; height: 36px; }
    .action-btn:hover { background: var(--row-hover-bg); }
    .action-btn.edit:hover { color: var(--prestige-primary); }
    .action-btn.delete:hover { color: var(--status-error-text); }
    .action-btn.activate:hover { color: var(--status-success-text); }

    .spinner-container { padding: 48px; text-align: center; color: var(--prestige-text-muted); font-size: 14px; }
    .empty-state-row td { padding: 40px; text-align: center; color: var(--prestige-text-muted); }
    .prestige-paginator { border-radius: 0 0 12px 12px; }

    @media (max-width: 768px) {
      .management-page { padding: 16px; }
      .header-section { flex-direction: column; align-items: flex-start; gap: 16px; }
    }
  `]
})
export class UserManagementComponent implements OnInit {
  private adminService = inject(AdminService);
  private cdr = inject(ChangeDetectorRef);
  private dialog = inject(MatDialog);

  activeDataSource = new MatTableDataSource<any>([]);
  inactiveDataSource = new MatTableDataSource<any>([]);
  displayedColumns = ['name', 'role', 'company', 'created_at', 'actions'];
  loading = true;
  companies: any[] = [];
  selectedCompanyFilter: number | null = null;
  private allActiveUsers: any[] = [];

  @ViewChild('activePaginator') activePaginator!: MatPaginator;
  @ViewChild('inactivePaginator') inactivePaginator!: MatPaginator;

  ngOnInit() {
    this.loadUsers();
    this.loadCompanies();
  }

  loadUsers() {
    this.loading = true;
    this.adminService.getUsers().subscribe({
      next: (data) => {
        const users = data || [];
        this.allActiveUsers = users.filter((u: any) => !u.deleted_at);
        const inactiveUsers = users.filter((u: any) => u.deleted_at);

        this.activeDataSource.data = this.allActiveUsers;
        this.inactiveDataSource.data = inactiveUsers;

        setTimeout(() => {
          this.activeDataSource.paginator = this.activePaginator;
          this.inactiveDataSource.paginator = this.inactivePaginator;
        });

        this.loading = false;
        this.cdr.detectChanges();
      },
      error: () => { this.loading = false; this.cdr.detectChanges(); }
    });
  }

  loadCompanies() {
    this.adminService.getCompanies().subscribe(data => {
      this.companies = data || [];
    });
  }

  applyActiveFilter(event: Event) {
    const filterValue = (event.target as HTMLInputElement).value;
    this.activeDataSource.filter = filterValue.trim().toLowerCase();
  }

  applyInactiveFilter(event: Event) {
    const filterValue = (event.target as HTMLInputElement).value;
    this.inactiveDataSource.filter = filterValue.trim().toLowerCase();
  }

  applyCompanyFilter() {
    if (!this.selectedCompanyFilter) {
      this.activeDataSource.data = this.allActiveUsers;
    } else {
      this.activeDataSource.data = this.allActiveUsers.filter(u =>
        u.companies?.some((c: any) => c.id === this.selectedCompanyFilter)
      );
    }
    setTimeout(() => { this.activeDataSource.paginator = this.activePaginator; });
    this.cdr.detectChanges();
  }

  onTabChange() {
    this.cdr.detectChanges();
  }

  getCompanyNames(user: any): string {
    return (user.companies || []).map((c: any) => c.name).join(', ');
  }

  onCreate() {
    const dialogRef = this.dialog.open(UserDialog, {
      data: { allCompanies: this.companies }
    });
    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.adminService.createUser(result).subscribe(() => this.loadUsers());
      }
    });
  }

  onEdit(user: any) {
    const dialogRef = this.dialog.open(UserDialog, {
      data: { ...user, allCompanies: this.companies }
    });
    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.adminService.updateUser(user.id, result).subscribe(() => this.loadUsers());
      }
    });
  }

  onViewCompanies(user: any) {
    const dialogRef = this.dialog.open(UserCompaniesDialog, {
      data: { user }
    });
    dialogRef.afterClosed().subscribe(hasChanges => {
      if (hasChanges) this.loadUsers();
    });
  }

  getAvatarColor(role: string): string {
    switch (role) {
      case 'superadmin': return '#ef4444';
      case 'admin': return '#3b82f6';
      default: return '#10b981';
    }
  }

  onDelete(user: any) {
    const dialogRef = this.dialog.open(ConfirmDialog, {
      data: {
        title: 'Suspender Usuario',
        message: `¿Estás seguro de que deseas desactivar el acceso para ${user.email}?`,
        confirmText: 'Suspender Acceso',
        color: 'warn'
      }
    });
    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.adminService.deleteUser(user.id).subscribe(() => this.loadUsers());
      }
    });
  }

  onRestore(user: any) {
    const dialogRef = this.dialog.open(ConfirmDialog, {
      data: {
        title: 'Reactivar Usuario',
        message: `¿Deseas restablecer el acceso para ${user.email}?`,
        confirmText: 'Reactivar Acceso',
        color: 'primary'
      }
    });
    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.adminService.restoreUser(user.id).subscribe(() => this.loadUsers());
      }
    });
  }
}
