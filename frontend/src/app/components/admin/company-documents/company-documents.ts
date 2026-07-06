import { Component, inject, OnInit, ChangeDetectorRef, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { MatChipsModule } from '@angular/material/chips';
import { AdminService } from '../../../services/admin.service';
import { ContextService } from '../../../services/context.service';

const STATUS_LABELS: Record<string, { label: string; icon: string; color: string }> = {
  pending:    { label: 'En cola',     icon: 'schedule',      color: '#9ca3af' },
  processing: { label: 'Procesando', icon: 'autorenew',      color: '#0284c7' },
  processed:  { label: 'Listo',       icon: 'check_circle',  color: '#16a34a' },
  failed:     { label: 'Error',       icon: 'error',          color: '#ef4444' },
};

@Component({
  selector: 'app-company-documents',
  standalone: true,
  imports: [
    CommonModule,
    MatTableModule,
    MatButtonModule,
    MatIconModule,
    MatProgressSpinnerModule,
    MatTooltipModule,
    MatSnackBarModule,
    MatChipsModule,
  ],
  template: `
    <div class="management-page">
      <div class="header-section">
        <div class="title-group">
          <h1>Documentos de la Empresa</h1>
          <p class="subtitle">
            Sube facturas, certificados o reportes previos — el agente ZIA puede consultarlos
            para responder preguntas que no están en los datos estructurados de la plataforma.
          </p>
        </div>
        <button mat-flat-button color="primary" (click)="fileInput.click()" [disabled]="!companyId() || uploading()">
          <mat-icon>upload_file</mat-icon> Subir Documento
        </button>
        <input #fileInput type="file" hidden accept=".pdf,.txt,.md" (change)="onFileSelected($event)">
      </div>

      <div class="empty-state-card glass-card" *ngIf="!companyId()">
        <mat-icon>business</mat-icon>
        <p>Selecciona una empresa en tu contexto de sesión para ver sus documentos.</p>
      </div>

      <div class="glass-card table-wrapper" *ngIf="companyId()">
        <div class="spinner-container" *ngIf="loading()">
          <mat-spinner diameter="40"></mat-spinner>
        </div>

        <div class="table-container" *ngIf="!loading()">
          <table mat-table [dataSource]="dataSource" class="prestige-table">
            <ng-container matColumnDef="title">
              <th mat-header-cell *matHeaderCellDef>Documento</th>
              <td mat-cell *matCellDef="let row">{{ row.title }}</td>
            </ng-container>

            <ng-container matColumnDef="status">
              <th mat-header-cell *matHeaderCellDef>Estado</th>
              <td mat-cell *matCellDef="let row">
                <span class="status-chip" [style.color]="statusInfo(row.status).color"
                  [matTooltip]="row.status === 'failed' ? row.error_message : ''">
                  <mat-icon class="chip-icon">{{ statusInfo(row.status).icon }}</mat-icon>
                  {{ statusInfo(row.status).label }}
                </span>
              </td>
            </ng-container>

            <ng-container matColumnDef="uploader">
              <th mat-header-cell *matHeaderCellDef>Subido por</th>
              <td mat-cell *matCellDef="let row">{{ row.uploader?.name || '—' }}</td>
            </ng-container>

            <ng-container matColumnDef="created_at">
              <th mat-header-cell *matHeaderCellDef>Fecha</th>
              <td mat-cell *matCellDef="let row">{{ row.created_at | date:'medium' }}</td>
            </ng-container>

            <ng-container matColumnDef="actions">
              <th mat-header-cell *matHeaderCellDef></th>
              <td mat-cell *matCellDef="let row">
                <button mat-icon-button color="warn" (click)="onDelete(row)" matTooltip="Eliminar">
                  <mat-icon>delete</mat-icon>
                </button>
              </td>
            </ng-container>

            <tr mat-header-row *matHeaderRowDef="displayedColumns"></tr>
            <tr mat-row *matRowDef="let row; columns: displayedColumns;" class="prestige-row"></tr>

            <tr class="mat-row" *matNoDataRow>
              <td class="mat-cell empty-state" colspan="5">
                <mat-icon>description</mat-icon>
                <p>No hay documentos subidos todavía.</p>
              </td>
            </tr>
          </table>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .management-page { padding: 24px; max-width: 1200px; margin: 0 auto; }
    .header-section { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px; gap: 16px; }
    .title-group h1 { font-size: 28px; font-weight: 600; color: var(--prestige-primary); margin: 0 0 4px 0; }
    .subtitle { color: var(--prestige-text-muted); margin: 0; font-size: 13px; max-width: 560px; }

    .glass-card { border-radius: 16px; overflow: hidden; }
    .table-wrapper { padding: 0; }
    .table-container { width: 100%; overflow-x: auto; }
    .prestige-table { width: 100%; }
    .prestige-row:hover { background: var(--row-hover-bg) !important; }

    .spinner-container { padding: 48px; text-align: center; color: var(--prestige-text-muted); }
    .empty-state { padding: 48px; text-align: center; color: var(--prestige-text-muted); }
    .empty-state mat-icon { font-size: 40px; width: 40px; height: 40px; opacity: 0.3; display: block; margin: 0 auto 12px; }
    .empty-state-card { text-align: center; padding: 40px; color: var(--prestige-text-muted); border-radius: 16px; }
    .empty-state-card mat-icon { font-size: 32px; width: 32px; height: 32px; opacity: 0.4; }

    .status-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 700; }
    .chip-icon { font-size: 16px; width: 16px; height: 16px; }
  `]
})
export class CompanyDocumentsComponent implements OnInit {
  private adminService = inject(AdminService);
  private context = inject(ContextService);
  private snackBar = inject(MatSnackBar);
  private cdr = inject(ChangeDetectorRef);

  dataSource = new MatTableDataSource<any>([]);
  displayedColumns = ['title', 'status', 'uploader', 'created_at', 'actions'];
  loading = signal(true);
  uploading = signal(false);

  companyId = signal<number | null>(null);

  constructor() {
    this.companyId.set(this.context.selectedCompany()?.id ?? null);
  }

  ngOnInit() {
    if (this.companyId()) {
      this.load();
    } else {
      this.loading.set(false);
    }
  }

  load() {
    const id = this.companyId();
    if (!id) return;

    this.loading.set(true);
    this.adminService.getCompanyDocuments(id).subscribe({
      next: (documents) => {
        this.dataSource.data = documents || [];
        this.loading.set(false);
        this.cdr.markForCheck();
      },
      error: () => { this.loading.set(false); this.cdr.markForCheck(); }
    });
  }

  statusInfo(status: string) {
    return STATUS_LABELS[status] || STATUS_LABELS['pending'];
  }

  onFileSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    const id = this.companyId();
    if (!file || !id) return;

    this.uploading.set(true);
    this.adminService.uploadCompanyDocument(id, file).subscribe({
      next: () => {
        this.snackBar.open('Documento subido — procesando en segundo plano.', '', { duration: 3000 });
        this.uploading.set(false);
        input.value = '';
        this.load();
      },
      error: (err) => {
        this.snackBar.open(err.error?.message || 'Error al subir el documento.', '', { duration: 4000 });
        this.uploading.set(false);
        input.value = '';
      }
    });
  }

  onDelete(row: any) {
    const id = this.companyId();
    if (!id || !window.confirm(`¿Eliminar "${row.title}"? Esto también borra su contenido indexado para el agente.`)) return;

    this.adminService.deleteCompanyDocument(id, row.id).subscribe({
      next: () => {
        this.snackBar.open('Documento eliminado.', '', { duration: 3000 });
        this.load();
      },
      error: () => this.snackBar.open('No se pudo eliminar el documento.', '', { duration: 3000 })
    });
  }
}
