import { Component, Inject, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatDialogModule, MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { AdminService } from '../../../services/admin.service';
import { FormsModule } from '@angular/forms';

@Component({
  selector: 'app-company-factor-settings-dialog',
  standalone: true,
  imports: [
    CommonModule,
    MatDialogModule,
    MatButtonModule,
    MatIconModule,
    MatCheckboxModule,
    MatProgressSpinnerModule,
    FormsModule
  ],
  template: `
    <h2 mat-dialog-title>Configurar Factores para {{ data.company.name }}</h2>
    <mat-dialog-content class="dialog-content">
      <p class="summary">Seleccione los factores de emisión que desea habilitar para esta empresa.</p>
      
      <div *ngIf="loading" class="spinner-container">
        <mat-progress-spinner diameter="40" mode="indeterminate"></mat-progress-spinner>
      </div>

      <div *ngIf="!loading && factors.length > 0" class="factors-list">
        <!-- Group by Category -->
        <div *ngFor="let group of groupedFactors | keyvalue" class="category-group">
          <h3 class="category-title">{{ group.key }}</h3>
          <div class="factors-grid">
            <div *ngFor="let factor of group.value" class="factor-item">
              <mat-checkbox [(ngModel)]="factor.is_enabled">
                {{ factor.name }}
              </mat-checkbox>
            </div>
          </div>
        </div>
      </div>

      <div *ngIf="!loading && factors.length === 0" class="empty-state">
        No hay factores configurados en el sistema.
      </div>
    </mat-dialog-content>

    <mat-dialog-actions align="end">
      <button mat-button [mat-dialog-close]="null" [disabled]="saving">CANCELAR</button>
      <button mat-flat-button color="primary" (click)="save()" [disabled]="loading || saving">
        {{ saving ? 'GUARDANDO...' : 'GUARDAR CAMBIOS' }}
      </button>
    </mat-dialog-actions>
  `,
  styles: [`
    .dialog-content { min-width: 500px; max-height: 70vh; color: var(--prestige-text); }
    .summary { color: var(--prestige-text-muted); margin-bottom: 20px; font-size: 14px; }
    .spinner-container { display: flex; justify-content: center; padding: 40px; }
    
    .category-group { margin-bottom: 24px; }
    .category-title { 
      font-size: 12px; font-weight: 700; text-transform: uppercase; 
      color: var(--prestige-primary); border-bottom: 1px solid var(--prestige-border); 
      padding-bottom: 8px; margin-bottom: 12px;
    }

    .factors-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
    .factor-item { padding: 4px 0; color: var(--prestige-text); }
    
    .empty-state { text-align: center; padding: 40px; color: var(--prestige-text-muted); }
  `]
})
export class CompanyFactorSettingsDialog implements OnInit {
  adminService = inject(AdminService);
  cdr = inject(ChangeDetectorRef);

  factors: any[] = [];
  groupedFactors: { [key: string]: any[] } = {};
  loading = true;
  saving = false;

  constructor(
    public dialogRef: MatDialogRef<CompanyFactorSettingsDialog>,
    @Inject(MAT_DIALOG_DATA) public data: { company: any }
  ) { }

  ngOnInit() {
    this.loadFactors();
  }

  loadFactors() {
    this.loading = true;
    this.adminService.getCompanyFactors(this.data.company.id).subscribe({
      next: (res) => {
        this.factors = res;
        this.groupFactors();
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error loading company factors', err);
        this.loading = false;
        this.cdr.detectChanges();
      }
    });
  }

  groupFactors() {
    this.groupedFactors = {};
    this.factors.forEach(f => {
      const cat = f.category_name || 'Sin Categoría';
      if (!this.groupedFactors[cat]) this.groupedFactors[cat] = [];
      this.groupedFactors[cat].push(f);
    });
  }

  save() {
    this.saving = true;
    const updateData = this.factors.map(f => ({ id: f.id, is_enabled: f.is_enabled }));

    this.adminService.updateCompanyFactors(this.data.company.id, updateData).subscribe({
      next: () => {
        this.saving = false;
        this.dialogRef.close(true);
      },
      error: (err) => {
        console.error('Error saving factors', err);
        this.saving = false;
      }
    });
  }
}
