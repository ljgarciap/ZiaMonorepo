import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MatTableModule } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { AdminService } from '../../../services/admin.service';

@Component({
    selector: 'app-tag-management',
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
        MatSlideToggleModule,
        MatTooltipModule,
        MatSnackBarModule,
    ],
    template: `
    <div class="management-page">
      <div class="header-section">
        <div class="title-group">
          <h1>Catálogo de Tags</h1>
          <p class="subtitle">Tags preconfigurados globales o por sector, para que cada Admin los asigne a su empresa.</p>
        </div>
      </div>

      <div class="glass-card form-card">
        <form (ngSubmit)="createTag()">
          <div class="form-grid">
            <mat-form-field appearance="outline">
              <mat-label>Nombre</mat-label>
              <input matInput [(ngModel)]="newTag.name" name="name" required>
            </mat-form-field>
            <mat-form-field appearance="outline">
              <mat-label>Sector (opcional)</mat-label>
              <mat-select [(ngModel)]="newTag.company_sector_id" name="company_sector_id">
                <mat-option [value]="null">Global (todos los sectores)</mat-option>
                <mat-option *ngFor="let s of sectors" [value]="s.id">{{ s.name }}</mat-option>
              </mat-select>
            </mat-form-field>
          </div>
          <button mat-raised-button color="primary" type="submit" [disabled]="!newTag.name">
            <mat-icon>add</mat-icon> Crear tag
          </button>
        </form>
      </div>

      <div class="glass-card table-card">
        <table mat-table [dataSource]="tags" class="premium-table">
          <ng-container matColumnDef="name">
            <th mat-header-cell *matHeaderCellDef>Nombre</th>
            <td mat-cell *matCellDef="let t">{{ t.name }}</td>
          </ng-container>

          <ng-container matColumnDef="sector">
            <th mat-header-cell *matHeaderCellDef>Sector</th>
            <td mat-cell *matCellDef="let t">{{ t.sector?.name || 'Global' }}</td>
          </ng-container>

          <ng-container matColumnDef="active">
            <th mat-header-cell *matHeaderCellDef>Activo</th>
            <td mat-cell *matCellDef="let t">
              <mat-slide-toggle [checked]="t.is_active" (change)="toggle(t)"></mat-slide-toggle>
            </td>
          </ng-container>

          <ng-container matColumnDef="actions">
            <th mat-header-cell *matHeaderCellDef>Acciones</th>
            <td mat-cell *matCellDef="let t">
              <button mat-icon-button matTooltip="Eliminar" (click)="deleteTag(t)">
                <mat-icon>delete</mat-icon>
              </button>
            </td>
          </ng-container>

          <tr mat-header-row *matHeaderRowDef="columns"></tr>
          <tr mat-row *matRowDef="let row; columns: columns;"></tr>
        </table>

        <div *ngIf="tags.length === 0" class="empty-state">
          <mat-icon>sell</mat-icon>
          <p>Sin tags registrados todavía.</p>
        </div>
      </div>
    </div>
  `,
    styles: [`
    .management-page { padding: 24px; max-width: 1000px; margin: 0 auto; }
    .header-section { margin-bottom: 24px; }
    .title-group h1 { font-size: 28px; font-weight: 600; margin: 0; }
    .subtitle { color: var(--prestige-text-muted); margin: 0; }

    .glass-card {
      background: rgba(255, 255, 255, 0.9); border: 1px solid var(--prestige-border);
      border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); margin-bottom: 24px; padding: 16px;
    }
    .form-card { padding: 24px; }
    .form-grid { display: flex; flex-wrap: wrap; gap: 16px; }

    .table-card { overflow-x: auto; }
    .premium-table { width: 100%; }

    .empty-state { text-align: center; padding: 40px; color: var(--prestige-text-muted); }
    .empty-state mat-icon { font-size: 40px; width: 40px; height: 40px; opacity: 0.5; }
  `]
})
export class TagManagementComponent implements OnInit {
    private adminService = inject(AdminService);
    private snackBar = inject(MatSnackBar);

    tags: any[] = [];
    sectors: any[] = [];
    columns = ['name', 'sector', 'active', 'actions'];

    newTag: { name: string; company_sector_id: number | null } = { name: '', company_sector_id: null };

    ngOnInit() {
        this.loadTags();
        this.adminService.getSectors().subscribe({
            next: (sectors) => { this.sectors = sectors; }
        });
    }

    loadTags() {
        this.adminService.getTags().subscribe({
            next: (tags) => { this.tags = tags; }
        });
    }

    createTag() {
        if (!this.newTag.name) return;

        this.adminService.createTag(this.newTag).subscribe({
            next: () => {
                this.snackBar.open('Tag creado', 'Cerrar', { duration: 3000 });
                this.newTag = { name: '', company_sector_id: null };
                this.loadTags();
            },
            error: () => this.snackBar.open('No se pudo crear el tag', 'Cerrar', { duration: 3000 })
        });
    }

    toggle(tag: any) {
        this.adminService.toggleTag(tag.id).subscribe({
            next: () => this.loadTags()
        });
    }

    deleteTag(tag: any) {
        if (!window.confirm(`¿Eliminar el tag "${tag.name}"?`)) return;
        this.adminService.deleteTag(tag.id).subscribe({
            next: () => {
                this.snackBar.open('Tag eliminado', 'Cerrar', { duration: 3000 });
                this.loadTags();
            }
        });
    }
}
