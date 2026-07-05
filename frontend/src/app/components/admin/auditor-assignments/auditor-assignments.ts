import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MatCardModule } from '@angular/material/card';
import { MatTableModule } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatSelectModule } from '@angular/material/select';
import { MatInputModule } from '@angular/material/input';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { AdminService } from '../../../services/admin.service';
import { MasterDataService } from '../../../services/master-data.service';
import { AuditorAssignmentService } from '../../../services/auditor-assignment.service';

@Component({
    selector: 'app-auditor-assignments',
    standalone: true,
    imports: [
        CommonModule,
        FormsModule,
        MatCardModule,
        MatTableModule,
        MatButtonModule,
        MatIconModule,
        MatFormFieldModule,
        MatSelectModule,
        MatInputModule,
        MatTooltipModule,
        MatSnackBarModule,
    ],
    template: `
    <div class="management-page">
      <div class="header-section">
        <div class="title-group">
          <h1>Acceso de Auditores externos</h1>
          <p class="subtitle">Autoriza a un Auditor externo para un período específico de una empresa, con vencimiento opcional.</p>
        </div>
      </div>

      <div class="glass-card form-card">
        <form (ngSubmit)="grant()">
          <div class="form-grid">
            <mat-form-field appearance="outline">
              <mat-label>Auditor</mat-label>
              <mat-select [(ngModel)]="newGrant.user_id" name="user_id" required>
                <mat-option *ngFor="let a of auditors()" [value]="a.id">{{ a.name }} ({{ a.email }})</mat-option>
              </mat-select>
            </mat-form-field>
            <mat-form-field appearance="outline">
              <mat-label>Empresa</mat-label>
              <mat-select [(ngModel)]="selectedCompanyId" name="company_id" (selectionChange)="loadPeriods()">
                <mat-option *ngFor="let c of companies()" [value]="c.id">{{ c.name }}</mat-option>
              </mat-select>
            </mat-form-field>
            <mat-form-field appearance="outline">
              <mat-label>Período</mat-label>
              <mat-select [(ngModel)]="newGrant.period_id" name="period_id" required [disabled]="!periods().length">
                <mat-option *ngFor="let p of periods()" [value]="p.id">{{ p.year }} ({{ p.status }})</mat-option>
              </mat-select>
            </mat-form-field>
            <mat-form-field appearance="outline">
              <mat-label>Vence (opcional)</mat-label>
              <input matInput type="date" [(ngModel)]="expiresAt" name="expires_at">
            </mat-form-field>
          </div>
          <button mat-raised-button color="primary" type="submit" [disabled]="!newGrant.user_id || !newGrant.period_id">
            <mat-icon>verified_user</mat-icon> Autorizar
          </button>
        </form>
      </div>

      <div class="glass-card table-card">
        <table mat-table [dataSource]="assignments()" class="premium-table">
          <ng-container matColumnDef="auditor">
            <th mat-header-cell *matHeaderCellDef>Auditor</th>
            <td mat-cell *matCellDef="let a">{{ a.user?.name }}</td>
          </ng-container>

          <ng-container matColumnDef="company">
            <th mat-header-cell *matHeaderCellDef>Empresa</th>
            <td mat-cell *matCellDef="let a">{{ a.company?.name }}</td>
          </ng-container>

          <ng-container matColumnDef="period">
            <th mat-header-cell *matHeaderCellDef>Período</th>
            <td mat-cell *matCellDef="let a">{{ a.period?.year }}</td>
          </ng-container>

          <ng-container matColumnDef="expires">
            <th mat-header-cell *matHeaderCellDef>Vence</th>
            <td mat-cell *matCellDef="let a">{{ a.expires_at ? (a.expires_at | date:'medium') : 'Sin vencimiento' }}</td>
          </ng-container>

          <ng-container matColumnDef="actions">
            <th mat-header-cell *matHeaderCellDef>Acciones</th>
            <td mat-cell *matCellDef="let a">
              <button mat-icon-button matTooltip="Revocar" (click)="revoke(a)">
                <mat-icon>block</mat-icon>
              </button>
            </td>
          </ng-container>

          <tr mat-header-row *matHeaderRowDef="columns"></tr>
          <tr mat-row *matRowDef="let row; columns: columns;"></tr>
        </table>

        <div *ngIf="assignments().length === 0" class="empty-state">
          <mat-icon>verified_user</mat-icon>
          <p>Sin autorizaciones de auditoría registradas.</p>
        </div>
      </div>
    </div>
  `,
    styles: [`
    .management-page { padding: 24px; max-width: 1100px; margin: 0 auto; }
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
export class AuditorAssignmentsComponent implements OnInit {
    private adminService = inject(AdminService);
    private masterDataService = inject(MasterDataService);
    private assignmentService = inject(AuditorAssignmentService);
    private snackBar = inject(MatSnackBar);

    assignments = signal<any[]>([]);
    auditors = signal<any[]>([]);
    companies = signal<any[]>([]);
    periods = signal<any[]>([]);
    columns = ['auditor', 'company', 'period', 'expires', 'actions'];

    selectedCompanyId: number | null = null;
    expiresAt: string | null = null;
    newGrant: { user_id: number | null; period_id: number | null } = { user_id: null, period_id: null };

    ngOnInit() {
        this.loadAssignments();
        this.adminService.getUsers().subscribe({
            next: (users) => { this.auditors.set(users.filter(u => u.role === 'auditor')); }
        });
        this.adminService.getCompanies().subscribe({
            next: (companies) => { this.companies.set(companies); }
        });
    }

    loadAssignments() {
        this.assignmentService.getAssignments().subscribe({
            next: (assignments) => { this.assignments.set(assignments); }
        });
    }

    loadPeriods() {
        this.periods.set([]);
        this.newGrant.period_id = null;
        if (!this.selectedCompanyId) return;

        this.masterDataService.getPeriods(this.selectedCompanyId).subscribe({
            next: (periods) => { this.periods.set(periods); }
        });
    }

    grant() {
        if (!this.newGrant.user_id || !this.newGrant.period_id) return;

        this.assignmentService.grant({
            user_id: this.newGrant.user_id,
            period_id: this.newGrant.period_id,
            expires_at: this.expiresAt || null,
        }).subscribe({
            next: () => {
                this.snackBar.open('Auditor autorizado', 'Cerrar', { duration: 3000 });
                this.newGrant = { user_id: null, period_id: null };
                this.expiresAt = null;
                this.loadAssignments();
            },
            error: () => this.snackBar.open('No se pudo autorizar al auditor', 'Cerrar', { duration: 3000 })
        });
    }

    revoke(assignment: any) {
        if (!window.confirm(`¿Revocar el acceso de ${assignment.user?.name} al período ${assignment.period?.year}?`)) return;

        this.assignmentService.revoke(assignment.id).subscribe({
            next: () => {
                this.snackBar.open('Acceso revocado', 'Cerrar', { duration: 3000 });
                this.loadAssignments();
            }
        });
    }
}
