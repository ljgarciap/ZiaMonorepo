import { Component, inject, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatDialogModule, MatDialog } from '@angular/material/dialog';
import { MatTooltipModule } from '@angular/material/tooltip';
import { AdminService } from '../../../services/admin.service';
import { ScopeDialog } from '../admin-dialogs';

@Component({
  selector: 'app-scope-management',
  standalone: true,
  imports: [
    CommonModule,
    MatTableModule,
    MatButtonModule,
    MatIconModule,
    MatDialogModule,
    MatTooltipModule
  ],
  template: `
    <div class="management-page">
      <div class="header-section">
        <div class="title-group">
          <h1>Alcances (Scopes)</h1>
          <p class="subtitle">Gestiona los alcances disponibles y su documentación.</p>
        </div>
        <button mat-flat-button class="btn-prestige" (click)="onCreate()">
          <mat-icon>add</mat-icon> Nuevo Alcance
        </button>
      </div>

      <div class="scopes-grid">
        <div class="scope-card" *ngFor="let scope of scopes" [ngClass]="'scope-border-' + scope.id">
          <div class="scope-header">
            <div class="scope-badge" [ngStyle]="{'background-color': getScopeColor(scope.id)}">Alcance {{scope.id}}</div>
            <div class="scope-title">{{scope.name}}</div>
            <div class="actions">
                <button mat-icon-button class="edit-btn" (click)="onEdit(scope)" matTooltip="Editar">
                    <mat-icon>edit</mat-icon>
                </button>
                <button mat-icon-button color="warn" (click)="onDelete(scope)" matTooltip="Eliminar" *ngIf="scope.id > 3">
                    <mat-icon>delete</mat-icon>
                </button>
            </div>
          </div>
          
          <div class="scope-body">
            <div class="field-group">
              <label>Descripción Corta:</label>
              <p>{{ scope.description || 'Sin descripción' }}</p>
            </div>
            <div class="field-group">
              <label>Documentación / Ayuda:</label>
              <p class="doc-text">{{ scope.documentation_text || 'Sin texto de ayuda' }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .management-page { padding: 24px; max-width: 1200px; margin: 0 auto; }
    .header-section { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; }
    .title-group h1 { font-size: 28px; font-weight: 600; color: var(--prestige-primary); margin: 0; }
    .subtitle { color: var(--prestige-text-muted); margin-top: 5px; }
    .btn-prestige { background-color: var(--prestige-primary); color: white; border-radius: 10px; height: 42px; }

    .scopes-grid { display: grid; gap: 24px; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); }
    
    .scope-card {
      background: var(--prestige-card-bg);
      border-radius: 16px;
      box-shadow: var(--glass-shadow);
      border: 1px solid var(--prestige-border);
      overflow: hidden;
      display: flex; flex-direction: column;
      transition: transform 0.2s ease;
    }
    .scope-card:hover { transform: translateY(-4px); }

    .scope-header {
      padding: 16px 20px;
      border-bottom: 1px solid var(--prestige-border);
      display: flex; align-items: center; justify-content: space-between;
      background: var(--table-header-bg);
    }

    .scope-badge {
      padding: 4px 12px; border-radius: 8px; color: white; font-weight: 800; font-size: 11px;
      text-transform: uppercase; letter-spacing: 0.05em;
    }

    .scope-title { font-weight: 700; font-size: 16px; margin-left: 12px; flex: 1; color: var(--prestige-text); }
    .actions { display: flex; gap: 4px; }
    .edit-btn { color: var(--prestige-primary) !important; }

    .scope-body { padding: 20px; flex: 1; display: flex; flex-direction: column; gap: 16px; }

    .field-group label {
      display: block; font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em;
      color: var(--prestige-primary); font-weight: 700; margin-bottom: 6px;
    }
    .field-group p { margin: 0; font-size: 14px; color: var(--prestige-text); line-height: 1.6; }
    .doc-text { white-space: pre-wrap; font-size: 13px !important; color: var(--prestige-text-muted) !important; padding: 12px; background: var(--row-hover-bg); border-radius: 8px; }

    .scope-border-1 { border-top: 5px solid #1a237e; }
    .scope-border-2 { border-top: 5px solid #00897b; }
    .scope-border-3 { border-top: 5px solid #f59e0b; }
  `]
})
export class ScopeManagementComponent implements OnInit {
  private adminService = inject(AdminService);
  private dialog = inject(MatDialog);
  private cdr = inject(ChangeDetectorRef);

  scopes: any[] = [];

  ngOnInit() {
    this.loadData();
  }

  loadData() {
    this.adminService.getScopes().subscribe(data => {
      this.scopes = data;
      this.cdr.detectChanges();
    });
  }

  getScopeColor(id: number): string {
    switch (id) {
      case 1: return '#1a237e';
      case 2: return '#00897b';
      case 3: return '#f59e0b';
      default: return '#64748b';
    }
  }

  onCreate() {
    const dialogRef = this.dialog.open(ScopeDialog, { width: '500px', data: {} });
    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.adminService.createScope(result).subscribe(() => this.loadData());
      }
    });
  }

  onEdit(scope: any) {
    const dialogRef = this.dialog.open(ScopeDialog, { width: '500px', data: { ...scope } });
    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.adminService.updateScope(scope.id, result).subscribe(() => this.loadData());
      }
    });
  }

  onDelete(scope: any) {
    if (confirm(`¿Estás seguro de eliminar el alcance "${scope.name}"?`)) {
      this.adminService.deleteScope(scope.id).subscribe({
        next: () => this.loadData(),
        error: (err) => alert(err.error?.message || 'Error al eliminar')
      });
    }
  }
}
