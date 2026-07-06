import { Component, inject, OnInit, ChangeDetectorRef, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { AdminService } from '../../../services/admin.service';

interface CredentialRow {
  key: string;
  description: string;
  is_set: boolean;
  masked_value: string | null;
  updated_at: string | null;
  updated_by: string | null;
  draftValue: string;
  saving: boolean;
}

@Component({
  selector: 'app-api-credentials',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatCardModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatIconModule,
    MatTooltipModule,
    MatSnackBarModule,
    MatProgressSpinnerModule,
  ],
  template: `
    <div class="management-page">
      <div class="header-section">
        <div class="title-group">
          <h1>API Keys</h1>
          <p class="subtitle">
            Credenciales de integraciones externas (proveedores de IA, observabilidad, IoT).
            Se guardan encriptadas en la base de datos — el Asistente ZIA las recoge
            automáticamente en menos de un minuto, sin necesitar reiniciar ningún servicio.
          </p>
        </div>
      </div>

      <div class="spinner-container" *ngIf="loading()">
        <mat-spinner diameter="40"></mat-spinner>
      </div>

      <div class="credentials-grid" *ngIf="!loading()">
        <mat-card class="glass-card credential-card" *ngFor="let row of rows()">
          <div class="card-header">
            <h3>{{ row.key }}</h3>
            <span class="status-chip" [class.set]="row.is_set" [class.unset]="!row.is_set">
              <mat-icon>{{ row.is_set ? 'check_circle' : 'radio_button_unchecked' }}</mat-icon>
              {{ row.is_set ? 'Configurada' : 'No configurada' }}
            </span>
          </div>
          <p class="description">{{ row.description }}</p>

          <p class="current-value" *ngIf="row.is_set">
            Valor actual: <code>{{ row.masked_value }}</code>
            <span class="meta" *ngIf="row.updated_by">— actualizada por {{ row.updated_by }}</span>
          </p>

          <div class="form-row">
            <mat-form-field appearance="outline" class="value-field">
              <mat-label>{{ row.is_set ? 'Nuevo valor' : 'Valor' }}</mat-label>
              <input matInput type="password" [(ngModel)]="row.draftValue" autocomplete="new-password"
                placeholder="Pega la key aquí para guardarla o reemplazarla">
            </mat-form-field>
            <button mat-flat-button color="primary" [disabled]="!row.draftValue || row.saving"
              (click)="onSave(row)" matTooltip="Guardar">
              <mat-icon>save</mat-icon>
            </button>
            <button mat-icon-button color="warn" *ngIf="row.is_set" [disabled]="row.saving"
              (click)="onClear(row)" matTooltip="Quitar (vuelve a usar el valor de .env si existe)">
              <mat-icon>delete_outline</mat-icon>
            </button>
          </div>
        </mat-card>
      </div>
    </div>
  `,
  styles: [`
    .management-page { padding: 24px; max-width: 1200px; margin: 0 auto; }
    .header-section { margin-bottom: 32px; }
    .title-group h1 { font-size: 28px; font-weight: 600; color: var(--prestige-primary); margin: 0 0 4px 0; }
    .subtitle { color: var(--prestige-text-muted); margin: 0; font-size: 13px; max-width: 640px; }

    .spinner-container { padding: 48px; text-align: center; }

    .credentials-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 16px; }
    .glass-card { border-radius: 16px; padding: 20px; }
    .credential-card { display: flex; flex-direction: column; gap: 8px; }

    .card-header { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .card-header h3 { margin: 0; font-size: 14px; font-weight: 700; font-family: monospace; }

    .status-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 600; white-space: nowrap; }
    .status-chip mat-icon { font-size: 16px; width: 16px; height: 16px; }
    .status-chip.set { color: #16a34a; }
    .status-chip.unset { color: var(--prestige-text-muted); }

    .description { font-size: 12.5px; color: var(--prestige-text-muted); margin: 0; line-height: 1.4; }
    .current-value { font-size: 12px; margin: 0; }
    .current-value code { font-family: monospace; background: rgba(127,127,127,0.15); padding: 1px 6px; border-radius: 4px; }
    .current-value .meta { color: var(--prestige-text-muted); }

    .form-row { display: flex; align-items: center; gap: 8px; margin-top: 4px; }
    .value-field { flex: 1; }
  `]
})
export class ApiCredentialsComponent implements OnInit {
  private adminService = inject(AdminService);
  private snackBar = inject(MatSnackBar);
  private cdr = inject(ChangeDetectorRef);

  loading = signal(true);
  rows = signal<CredentialRow[]>([]);

  ngOnInit() {
    this.load();
  }

  load() {
    this.loading.set(true);
    this.adminService.getApiCredentials().subscribe({
      next: (data) => {
        this.rows.set((data || []).map((r) => ({ ...r, draftValue: '', saving: false })));
        this.loading.set(false);
        this.cdr.markForCheck();
      },
      error: () => { this.loading.set(false); this.cdr.markForCheck(); }
    });
  }

  onSave(row: CredentialRow) {
    if (!row.draftValue) return;

    row.saving = true;
    this.cdr.markForCheck();

    this.adminService.updateApiCredential(row.key, row.draftValue).subscribe({
      next: () => {
        this.snackBar.open(`${row.key} guardada. El Asistente la recogerá en menos de un minuto.`, '', { duration: 4000 });
        row.draftValue = '';
        row.saving = false;
        this.load();
      },
      error: () => {
        this.snackBar.open(`No se pudo guardar ${row.key}.`, '', { duration: 3000 });
        row.saving = false;
        this.cdr.markForCheck();
      }
    });
  }

  onClear(row: CredentialRow) {
    if (!window.confirm(`¿Quitar la key configurada para ${row.key}? Si existe un valor en .env, el sistema volverá a usar ese.`)) return;

    row.saving = true;
    this.cdr.markForCheck();

    this.adminService.deleteApiCredential(row.key).subscribe({
      next: () => {
        this.snackBar.open(`${row.key} eliminada.`, '', { duration: 3000 });
        this.load();
      },
      error: () => {
        this.snackBar.open(`No se pudo eliminar ${row.key}.`, '', { duration: 3000 });
        row.saving = false;
        this.cdr.markForCheck();
      }
    });
  }
}
