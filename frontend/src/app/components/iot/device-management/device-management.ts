import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MatCardModule } from '@angular/material/card';
import { MatTableModule } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatSelectModule } from '@angular/material/select';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { AuthService } from '../../../services/auth';
import { IotDeviceService } from '../../../services/iot-device.service';

@Component({
    selector: 'app-iot-device-management',
    standalone: true,
    imports: [
        CommonModule,
        FormsModule,
        MatCardModule,
        MatTableModule,
        MatButtonModule,
        MatIconModule,
        MatInputModule,
        MatFormFieldModule,
        MatSelectModule,
        MatProgressSpinnerModule,
        MatTooltipModule,
        MatSnackBarModule,
    ],
    template: `
    <div class="iot-page">
      <div class="header-section">
        <div class="title-group">
          <h1>Dispositivos IoT</h1>
          <p class="subtitle">
            {{ canManage ? 'Registro, configuración y calibración de sensores de ' + companyName + '.' : 'Consulta de sensores de ' + companyName + ' (solo lectura).' }}
          </p>
        </div>
        <button mat-flat-button color="primary" *ngIf="canManage" (click)="showCreateForm = !showCreateForm">
          <mat-icon>add</mat-icon> Registrar dispositivo
        </button>
      </div>

      <mat-card class="glass-card form-card" *ngIf="showCreateForm">
        <form (ngSubmit)="createDevice()">
          <div class="form-grid">
            <mat-form-field appearance="outline">
              <mat-label>Nombre</mat-label>
              <input matInput [(ngModel)]="newDevice.name" name="name" required>
            </mat-form-field>
            <mat-form-field appearance="outline">
              <mat-label>Tipo</mat-label>
              <mat-select [(ngModel)]="newDevice.type" name="type" required>
                <mat-option value="energy">Energía</mat-option>
                <mat-option value="water">Agua</mat-option>
                <mat-option value="waste">Residuos</mat-option>
              </mat-select>
            </mat-form-field>
            <mat-form-field appearance="outline">
              <mat-label>Ubicación</mat-label>
              <input matInput [(ngModel)]="newDevice.location" name="location">
            </mat-form-field>
            <mat-form-field appearance="outline">
              <mat-label>Unidad</mat-label>
              <input matInput [(ngModel)]="newDevice.unit" name="unit" placeholder="kWh, m3...">
            </mat-form-field>
            <mat-form-field appearance="outline">
              <mat-label>ID ThingsBoard</mat-label>
              <input matInput [(ngModel)]="newDevice.thingsboard_id" name="thingsboard_id">
            </mat-form-field>
          </div>
          <button mat-raised-button color="primary" type="submit" [disabled]="!newDevice.name || !newDevice.type">
            Guardar dispositivo
          </button>
        </form>
      </mat-card>

      <div class="spinner-wrap" *ngIf="loading">
        <mat-spinner diameter="44"></mat-spinner>
      </div>

      <div class="glass-card table-card" *ngIf="!loading">
        <table mat-table [dataSource]="devices" class="device-table">
          <ng-container matColumnDef="name">
            <th mat-header-cell *matHeaderCellDef>Dispositivo</th>
            <td mat-cell *matCellDef="let d">
              <div class="device-name">{{ d.name }}</div>
              <div class="device-meta">{{ d.type }} · {{ d.location || 'sin ubicación' }}</div>
            </td>
          </ng-container>

          <ng-container matColumnDef="readings">
            <th mat-header-cell *matHeaderCellDef>Lecturas</th>
            <td mat-cell *matCellDef="let d">{{ d.readings_count }}</td>
          </ng-container>

          <ng-container matColumnDef="alerts">
            <th mat-header-cell *matHeaderCellDef>Alertas pendientes</th>
            <td mat-cell *matCellDef="let d">
              <span class="alert-badge" [class.warning]="d.pending_alerts_count > 0">{{ d.pending_alerts_count }}</span>
            </td>
          </ng-container>

          <ng-container matColumnDef="calibration">
            <th mat-header-cell *matHeaderCellDef>Última calibración</th>
            <td mat-cell *matCellDef="let d">
              {{ d.last_calibrated_at ? (d.last_calibrated_at | date:'medium') : 'Nunca' }}
            </td>
          </ng-container>

          <ng-container matColumnDef="actions">
            <th mat-header-cell *matHeaderCellDef>Acciones</th>
            <td mat-cell *matCellDef="let d">
              <button mat-icon-button matTooltip="Registrar calibración" (click)="calibrate(d)">
                <mat-icon>build</mat-icon>
              </button>
              <button mat-icon-button matTooltip="Eliminar" (click)="deleteDevice(d)">
                <mat-icon>delete</mat-icon>
              </button>
            </td>
          </ng-container>

          <tr mat-header-row *matHeaderRowDef="columns"></tr>
          <tr mat-row *matRowDef="let row; columns: columns;"></tr>
        </table>

        <div *ngIf="devices.length === 0" class="empty-state">
          <mat-icon>developer_board</mat-icon>
          <p>No hay dispositivos registrados en esta empresa.</p>
        </div>
      </div>

      <div class="glass-card table-card alerts-card" *ngIf="!loading && alerts.length > 0">
        <h2>Alertas de telemetría sin resolver</h2>
        <div class="alert-row" *ngFor="let a of alerts">
          <div class="alert-info">
            <span class="alert-device">{{ a.device?.name }}</span>
            <span class="alert-msg">{{ a.message }}</span>
          </div>
          <button mat-stroked-button *ngIf="canManage" (click)="resolveAlert(a)">Diagnosticar y resolver</button>
        </div>
      </div>
    </div>
  `,
    styles: [`
    .iot-page { padding: 24px; max-width: 1200px; margin: 0 auto; }
    .header-section { margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
    .title-group h1 { font-size: 28px; font-weight: 600; margin: 0; }
    .subtitle { color: var(--prestige-text-muted); margin: 0; }

    .glass-card {
      background: rgba(255, 255, 255, 0.9); border: 1px solid var(--prestige-border);
      border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); margin-bottom: 24px; padding: 16px;
    }
    .form-card { padding: 24px; }
    .form-grid { display: flex; flex-wrap: wrap; gap: 16px; }

    .table-card { overflow-x: auto; }
    .device-table { width: 100%; }
    .device-name { font-weight: 600; }
    .device-meta { font-size: 12px; color: var(--prestige-text-muted); }

    .alert-badge { padding: 2px 8px; border-radius: 6px; background: #e5e7eb; }
    .alert-badge.warning { background: #fef3c7; color: #92400e; font-weight: 700; }

    .alerts-card h2 { font-size: 16px; margin: 0 0 12px; }
    .alert-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--prestige-border); }
    .alert-info { display: flex; flex-direction: column; }
    .alert-device { font-weight: 600; font-size: 13px; }
    .alert-msg { font-size: 12px; color: var(--prestige-text-muted); }

    .spinner-wrap { display: flex; justify-content: center; padding: 40px; }
    .empty-state { text-align: center; padding: 40px; color: var(--prestige-text-muted); }
    .empty-state mat-icon { font-size: 40px; width: 40px; height: 40px; opacity: 0.5; }
  `]
})
export class IotDeviceManagementComponent implements OnInit {
    authService = inject(AuthService);
    private iotDeviceService = inject(IotDeviceService);
    private snackBar = inject(MatSnackBar);

    devices: any[] = [];
    alerts: any[] = [];
    loading = false;
    showCreateForm = false;
    columns: string[] = [];

    newDevice: any = { name: '', type: 'energy', location: '', unit: '', thingsboard_id: '' };

    get companyId(): number | undefined {
        return this.authService.currentContext()?.id;
    }

    get companyName(): string {
        return this.authService.currentContext()?.label || 'tu empresa asignada';
    }

    // Backend: Superadmin/Técnico IoT tienen CRUD completo; Admin solo lee (routes/api.php:197-206)
    get canManage(): boolean {
        const role = this.authService.currentContext()?.role;
        return role === 'superadmin' || role === 'iot_tech';
    }

    ngOnInit() {
        this.columns = this.canManage
            ? ['name', 'readings', 'alerts', 'calibration', 'actions']
            : ['name', 'readings', 'alerts', 'calibration'];
        this.loadData();
    }

    loadData() {
        if (!this.companyId) return;
        this.loading = true;

        this.iotDeviceService.getDevices(this.companyId).subscribe({
            next: (devices) => {
                this.devices = devices;
                this.loading = false;
            },
            error: () => { this.loading = false; }
        });

        this.iotDeviceService.getLiveAlerts().subscribe({
            next: (res) => { this.alerts = res.alerts || []; }
        });
    }

    createDevice() {
        if (!this.companyId || !this.newDevice.name || !this.newDevice.type) return;

        this.iotDeviceService.createDevice(this.companyId, this.newDevice).subscribe({
            next: () => {
                this.snackBar.open('Dispositivo registrado', 'Cerrar', { duration: 3000 });
                this.newDevice = { name: '', type: 'energy', location: '', unit: '', thingsboard_id: '' };
                this.showCreateForm = false;
                this.loadData();
            },
            error: () => this.snackBar.open('No se pudo registrar el dispositivo', 'Cerrar', { duration: 3000 })
        });
    }

    calibrate(device: any) {
        const notes = window.prompt('Resultado de la calibración (opcional):') || '';
        this.iotDeviceService.calibrateDevice(device.id, notes).subscribe({
            next: () => {
                this.snackBar.open('Calibración registrada', 'Cerrar', { duration: 3000 });
                this.loadData();
            }
        });
    }

    deleteDevice(device: any) {
        if (!window.confirm(`¿Eliminar el dispositivo "${device.name}"?`)) return;
        this.iotDeviceService.deleteDevice(device.id).subscribe({
            next: () => {
                this.snackBar.open('Dispositivo eliminado', 'Cerrar', { duration: 3000 });
                this.loadData();
            }
        });
    }

    resolveAlert(alert: any) {
        const note = window.prompt('Diagnóstico de la alerta:') || '';
        this.iotDeviceService.resolveAlert(alert.id, note).subscribe({
            next: () => {
                this.snackBar.open('Alerta resuelta', 'Cerrar', { duration: 3000 });
                this.loadData();
            }
        });
    }
}
