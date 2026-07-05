import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MatCardModule } from '@angular/material/card';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatSelectModule } from '@angular/material/select';
import { MatInputModule } from '@angular/material/input';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { AuthService } from '../../../services/auth';
import { MasterDataService } from '../../../services/master-data.service';
import { AuditObservationService } from '../../../services/audit-observation.service';

@Component({
    selector: 'app-audit-observations',
    standalone: true,
    imports: [
        CommonModule,
        FormsModule,
        MatCardModule,
        MatButtonModule,
        MatIconModule,
        MatFormFieldModule,
        MatSelectModule,
        MatInputModule,
        MatProgressSpinnerModule,
        MatTooltipModule,
        MatSnackBarModule,
    ],
    template: `
    <div class="obs-page">
      <div class="header-section">
        <div class="title-group">
          <h1>Observaciones de Auditoría</h1>
          <p class="subtitle">Hallazgos y dictamen de verificación por período — {{ companyName }}.</p>
        </div>
      </div>

      <div class="glass-card period-picker" *ngIf="!noCompany">
        <mat-form-field appearance="outline">
          <mat-label>Período</mat-label>
          <mat-select [(ngModel)]="selectedPeriodId" (selectionChange)="loadObservations()">
            <mat-option *ngFor="let p of periods()" [value]="p.id">{{ p.year }} ({{ p.status }})</mat-option>
          </mat-select>
        </mat-form-field>
      </div>

      <div class="empty-state" *ngIf="noCompany">
        <mat-icon>business</mat-icon>
        <p>Selecciona una empresa en tu contexto de sesión para ver sus observaciones de auditoría.</p>
      </div>

      <ng-container *ngIf="selectedPeriodId && !noCompany">
        <mat-card class="glass-card form-card" *ngIf="canCreate">
          <form (ngSubmit)="createObservation()">
            <mat-form-field appearance="outline" class="body-field">
              <mat-label>Hallazgo / observación</mat-label>
              <textarea matInput rows="3" [(ngModel)]="newObservation.body" name="body" required></textarea>
            </mat-form-field>
            <mat-form-field appearance="outline">
              <mat-label>Dictamen (opcional)</mat-label>
              <mat-select [(ngModel)]="newObservation.verdict" name="verdict">
                <mat-option [value]="null">Sin dictamen</mat-option>
                <mat-option value="conforme">Conforme</mat-option>
                <mat-option value="observado">Observado</mat-option>
                <mat-option value="no_conforme">No conforme</mat-option>
              </mat-select>
            </mat-form-field>
            <button mat-raised-button color="primary" type="submit" [disabled]="!newObservation.body">
              Registrar observación
            </button>
          </form>
        </mat-card>

        <div class="spinner-wrap" *ngIf="loading()">
          <mat-spinner diameter="44"></mat-spinner>
        </div>

        <div class="glass-card" *ngIf="!loading()">
          <div class="obs-row" *ngFor="let o of observations()">
            <div class="obs-header">
              <span class="obs-author">{{ o.user?.name }}</span>
              <span class="obs-verdict" *ngIf="o.verdict" [ngClass]="o.verdict">{{ verdictLabel(o.verdict) }}</span>
              <span class="obs-date">{{ o.created_at | date:'medium' }}</span>
            </div>
            <p class="obs-body">{{ o.body }}</p>
            <button mat-icon-button *ngIf="canModerate" matTooltip="Eliminar" (click)="deleteObservation(o)">
              <mat-icon>delete</mat-icon>
            </button>
          </div>

          <div *ngIf="observations().length === 0" class="empty-state">
            <mat-icon>fact_check</mat-icon>
            <p>Sin observaciones registradas para este período.</p>
          </div>
        </div>
      </ng-container>
    </div>
  `,
    styles: [`
    .obs-page { padding: 24px; max-width: 900px; margin: 0 auto; }
    .header-section { margin-bottom: 24px; }
    .title-group h1 { font-size: 28px; font-weight: 600; margin: 0; }
    .subtitle { color: var(--prestige-text-muted); margin: 0; }

    .glass-card {
      background: rgba(255, 255, 255, 0.9); border: 1px solid var(--prestige-border);
      border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); margin-bottom: 24px; padding: 16px;
    }
    .period-picker { padding: 16px 24px; }
    .form-card { padding: 24px; }
    .body-field { width: 100%; }

    .obs-row { padding: 12px 8px; border-bottom: 1px solid var(--prestige-border); position: relative; }
    .obs-row:last-child { border-bottom: none; }
    .obs-header { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
    .obs-author { font-weight: 600; font-size: 13px; }
    .obs-date { font-size: 11px; color: var(--prestige-text-muted); margin-left: auto; }
    .obs-body { margin: 0; font-size: 13px; }
    .obs-verdict { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 6px; text-transform: uppercase; }
    .obs-verdict.conforme { background: #dcfce7; color: #166534; }
    .obs-verdict.observado { background: #fef3c7; color: #92400e; }
    .obs-verdict.no_conforme { background: #fee2e2; color: #991b1b; }

    .spinner-wrap { display: flex; justify-content: center; padding: 40px; }
    .empty-state { text-align: center; padding: 40px; color: var(--prestige-text-muted); }
    .empty-state mat-icon { font-size: 40px; width: 40px; height: 40px; opacity: 0.5; }
  `]
})
export class AuditObservationsComponent implements OnInit {
    authService = inject(AuthService);
    private masterDataService = inject(MasterDataService);
    private observationService = inject(AuditObservationService);
    private snackBar = inject(MatSnackBar);

    periods = signal<any[]>([]);
    observations = signal<any[]>([]);
    selectedPeriodId: number | null = null;
    loading = signal(false);

    newObservation: { body: string; verdict: string | null } = { body: '', verdict: null };

    get companyId(): number | undefined {
        return this.authService.currentContext()?.id;
    }

    get companyName(): string {
        return this.authService.currentContext()?.label || '';
    }

    get noCompany(): boolean {
        return !this.companyId;
    }

    get role(): string | undefined {
        return this.authService.currentContext()?.role;
    }

    get canCreate(): boolean {
        return this.role === 'auditor' || this.role === 'superadmin';
    }

    get canModerate(): boolean {
        return this.role === 'admin' || this.role === 'superadmin';
    }

    ngOnInit() {
        if (!this.companyId) return;

        this.masterDataService.getPeriods(this.companyId).subscribe({
            next: (periods) => {
                this.periods.set(periods);
                if (periods.length > 0) {
                    this.selectedPeriodId = periods[0].id;
                    this.loadObservations();
                }
            }
        });
    }

    loadObservations() {
        if (!this.companyId || !this.selectedPeriodId) return;
        this.loading.set(true);

        this.observationService.getObservations(this.companyId, this.selectedPeriodId).subscribe({
            next: (observations) => {
                this.observations.set(observations);
                this.loading.set(false);
            },
            error: () => { this.loading.set(false); }
        });
    }

    createObservation() {
        if (!this.companyId || !this.selectedPeriodId || !this.newObservation.body) return;

        this.observationService.createObservation(this.companyId, this.selectedPeriodId, this.newObservation).subscribe({
            next: () => {
                this.snackBar.open('Observación registrada', 'Cerrar', { duration: 3000 });
                this.newObservation = { body: '', verdict: null };
                this.loadObservations();
            },
            error: () => this.snackBar.open('No se pudo registrar la observación', 'Cerrar', { duration: 3000 })
        });
    }

    deleteObservation(observation: any) {
        if (!this.companyId || !this.selectedPeriodId) return;
        if (!window.confirm('¿Eliminar esta observación?')) return;

        this.observationService.deleteObservation(this.companyId, this.selectedPeriodId, observation.id).subscribe({
            next: () => {
                this.snackBar.open('Observación eliminada', 'Cerrar', { duration: 3000 });
                this.loadObservations();
            }
        });
    }

    verdictLabel(verdict: string): string {
        const labels: Record<string, string> = {
            conforme: 'Conforme',
            observado: 'Observado',
            no_conforme: 'No conforme',
        };
        return labels[verdict] || verdict;
    }
}
