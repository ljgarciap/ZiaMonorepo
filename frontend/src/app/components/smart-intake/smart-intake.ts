import { Component, inject, OnInit, signal, computed, effect } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatCardModule } from '@angular/material/card';
import { MatTabsModule } from '@angular/material/tabs';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { MatTooltipModule } from '@angular/material/tooltip';
import { ContextService } from '../../services/context.service';
import { CarbonService } from '../../services/carbon.service';
import { MasterDataService } from '../../services/master-data.service';

interface QuestionnaireRule {
  id: number;
  emission_factor_id: number;
  questionnaire_label: string;
  variable_name: string;
  input_unit_hint: string | null;
  is_required: boolean;
  display_order: number;
  help_text: string | null;
  factor_name: string;
  factor_total_co2e: number;
  unit_symbol: string | null;
  scope_id: number;
  scope_name: string;
  // runtime state
  value: number | null;
  estimatedCO2e: number;
}

@Component({
  selector: 'app-smart-intake',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatIconModule,
    MatCardModule,
    MatTabsModule,
    MatProgressBarModule,
    MatProgressSpinnerModule,
    MatSnackBarModule,
    MatTooltipModule,
  ],
  template: `
    <div class="smart-intake-container">
      <div class="header-section">
        <h1>Smart Intake</h1>
        <p>Cuestionario de Captura de Emisiones por Sector</p>
      </div>

      <!-- Company context card -->
      <div class="profile-card glass-card" *ngIf="selectedCompany()">
        <mat-icon>business</mat-icon>
        <div class="company-details">
          <h2>{{ selectedCompany().name }}</h2>
          <div class="tag-row">
            <span class="profile-badge">{{ selectedCompany().sector?.name || 'Sin sector' }}</span>
            <span class="sector-tag" *ngIf="selectedCompany().subsector_code">#{{ selectedCompany().subsector_code }}</span>
            <span class="period-tag" *ngIf="selectedPeriod()">Período {{ selectedPeriod().year }}</span>
          </div>
        </div>
      </div>

      <!-- No context -->
      <div *ngIf="!selectedCompany() || !selectedPeriod()" class="empty-state-card glass-card">
        <mat-icon>warning</mat-icon>
        <p>Selecciona una empresa y período en el panel superior para cargar el cuestionario.</p>
      </div>

      <!-- No sector configured -->
      <div *ngIf="selectedCompany() && selectedPeriod() && !sectorCode() && !loading()" class="empty-state-card glass-card">
        <mat-icon>category</mat-icon>
        <p>La empresa no tiene sector configurado. Un administrador debe asignar el sector desde el panel de Empresas.</p>
      </div>

      <!-- Loading -->
      <div *ngIf="loading()" class="loading-state">
        <mat-spinner diameter="40"></mat-spinner>
        <p>Cargando cuestionario para {{ sectorCode() }}…</p>
      </div>

      <!-- Questionnaire by scope tabs -->
      <div class="questionnaire-body" *ngIf="!loading() && rules().length > 0 && selectedCompany() && selectedPeriod()">

        <div class="scope-progress">
          <span>{{ completedCount() }} / {{ rules().length }} fuentes capturadas</span>
          <mat-progress-bar mode="determinate" [value]="progressPct()"></mat-progress-bar>
        </div>

        <mat-tab-group class="custom-tabs" animationDuration="200ms">
          <mat-tab *ngFor="let scopeGroup of scopeGroups()">
            <ng-template matTabLabel>
              <mat-icon class="tab-icon">{{ scopeIcon(scopeGroup.scope_id) }}</mat-icon>
              <span>{{ scopeGroup.scope_name }}</span>
            </ng-template>

            <div class="scope-card glass-card">
              <div *ngFor="let rule of scopeGroup.rules" class="rule-row">
                <div class="rule-header">
                  <span class="rule-label">
                    {{ rule.questionnaire_label }}
                    <span class="required-badge" *ngIf="rule.is_required">*</span>
                  </span>
                  <mat-icon class="help-icon" *ngIf="rule.help_text"
                    [matTooltip]="rule.help_text" matTooltipPosition="above">
                    help_outline
                  </mat-icon>
                </div>

                <div class="rule-input-row">
                  <mat-form-field appearance="outline" class="value-field">
                    <input matInput type="number" min="0"
                      [(ngModel)]="rule.value"
                      (ngModelChange)="recalculate(rule)"
                      [placeholder]="'Ingresa en ' + (rule.input_unit_hint || rule.unit_symbol || 'unidades')">
                    <span matSuffix *ngIf="rule.input_unit_hint">{{ rule.input_unit_hint }}</span>
                  </mat-form-field>

                  <div class="rule-result" *ngIf="rule.value !== null && rule.value! > 0">
                    <span class="co2e-val">{{ rule.estimatedCO2e | number:'1.4-4' }}</span>
                    <span class="co2e-unit">tCO₂e</span>
                  </div>

                  <button mat-flat-button color="primary"
                    [disabled]="!rule.value || rule.value! <= 0 || submitting()"
                    (click)="saveRule(rule)">
                    <mat-icon>save</mat-icon>
                    Registrar
                  </button>
                </div>

                <p class="factor-hint">
                  Factor: {{ rule.factor_name }} — {{ rule.factor_total_co2e }} tCO₂e / {{ rule.unit_symbol || rule.input_unit_hint }}
                </p>
              </div>
            </div>
          </mat-tab>
        </mat-tab-group>
      </div>
    </div>
  `,
  styles: [`
    .smart-intake-container { padding: 24px; max-width: 900px; margin: 0 auto; }
    .header-section { margin-bottom: 24px; }
    .header-section h1 { font-size: 26px; font-weight: 700; color: var(--zia-primary); margin: 0; }
    .header-section p { color: var(--zia-text-muted); margin-top: 6px; }

    .glass-card {
      background: var(--glass-bg);
      border: 1px solid var(--glass-border);
      border-radius: 16px;
      padding: 24px;
      margin-bottom: 20px;
    }

    .profile-card { display: flex; align-items: center; gap: 16px; }
    .profile-card mat-icon { font-size: 40px; width: 40px; height: 40px; color: var(--zia-primary); }
    .company-details h2 { margin: 0; font-size: 18px; font-weight: 600; color: var(--zia-text); }
    .tag-row { display: flex; gap: 8px; margin-top: 6px; flex-wrap: wrap; align-items: center; }
    .profile-badge { background: var(--zia-primary); color: white; padding: 2px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; }
    .sector-tag { background: var(--zia-tertiary); color: white; padding: 2px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; }
    .period-tag { background: transparent; color: var(--zia-text-muted); border: 1px solid var(--glass-border); padding: 2px 10px; border-radius: 4px; font-size: 11px; }

    .empty-state-card { display: flex; flex-direction: column; align-items: center; gap: 12px; text-align: center; color: var(--zia-text-muted); padding: 48px; }
    .empty-state-card mat-icon { font-size: 48px; width: 48px; height: 48px; color: var(--zia-tertiary); }

    .loading-state { display: flex; flex-direction: column; align-items: center; gap: 16px; padding: 48px; color: var(--zia-text-muted); }

    .scope-progress { margin-bottom: 16px; }
    .scope-progress span { font-size: 12px; color: var(--zia-text-muted); display: block; margin-bottom: 6px; }

    .scope-card { border-top-left-radius: 0; border-top-right-radius: 0; padding: 28px; display: flex; flex-direction: column; gap: 28px; }

    .rule-row { display: flex; flex-direction: column; gap: 6px; }
    .rule-header { display: flex; align-items: center; gap: 8px; }
    .rule-label { font-size: 13px; font-weight: 500; color: var(--zia-text); }
    .required-badge { color: #e53935; font-weight: 700; }
    .help-icon { font-size: 16px; width: 16px; height: 16px; color: var(--zia-text-muted); cursor: help; }

    .rule-input-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .value-field { flex: 1; min-width: 180px; }

    .rule-result { display: flex; flex-direction: column; align-items: flex-end; min-width: 90px; }
    .co2e-val { font-size: 20px; font-weight: 700; color: var(--zia-tertiary); line-height: 1.2; }
    .co2e-unit { font-size: 10px; color: var(--zia-text-muted); font-weight: 600; }

    .factor-hint { font-size: 11px; color: var(--zia-text-muted); margin: 0; padding-left: 2px; }

    .tab-icon { margin-right: 6px; font-size: 18px; width: 18px; height: 18px; }

    @media(max-width: 600px) {
      .rule-input-row { flex-direction: column; align-items: stretch; }
      .rule-result { align-items: flex-start; }
    }
  `]
})
export class SmartIntakeComponent implements OnInit {
  private context = inject(ContextService);
  private carbonService = inject(CarbonService);
  private masterData = inject(MasterDataService);
  private snack = inject(MatSnackBar);

  selectedCompany = this.context.selectedCompany;
  selectedPeriod = this.context.selectedPeriod;

  rules = signal<QuestionnaireRule[]>([]);
  loading = signal(false);
  submitting = signal(false);

  sectorCode = computed(() => {
    const company = this.selectedCompany();
    return company?.sector?.code ?? null;
  });

  scopeGroups = computed(() => {
    const grouped = new Map<number, { scope_id: number; scope_name: string; rules: QuestionnaireRule[] }>();
    for (const rule of this.rules()) {
      if (!grouped.has(rule.scope_id)) {
        grouped.set(rule.scope_id, { scope_id: rule.scope_id, scope_name: rule.scope_name, rules: [] });
      }
      grouped.get(rule.scope_id)!.rules.push(rule);
    }
    return Array.from(grouped.values()).sort((a, b) => a.scope_id - b.scope_id);
  });

  completedCount = computed(() => this.rules().filter(r => r.value !== null && r.value > 0).length);
  progressPct = computed(() => {
    const total = this.rules().length;
    return total > 0 ? (this.completedCount() / total) * 100 : 0;
  });

  constructor() {
    effect(() => {
      const code = this.sectorCode();
      if (code) {
        this.loadQuestionnaire(code);
      } else {
        this.rules.set([]);
      }
    });
  }

  ngOnInit() {}

  private loadQuestionnaire(sectorCode: string) {
    this.loading.set(true);
    this.masterData.getQuestionnaire(sectorCode).subscribe({
      next: (data) => {
        this.rules.set(data.map(r => ({ ...r, value: null, estimatedCO2e: 0 })));
        this.loading.set(false);
      },
      error: () => {
        this.snack.open('Error al cargar el cuestionario del sector.', 'Cerrar', { duration: 4000 });
        this.loading.set(false);
      }
    });
  }

  recalculate(rule: QuestionnaireRule) {
    const v = rule.value ?? 0;
    // factor_total_co2e is in kg/unit; result in tCO2e = (v * factor) / 1000
    rule.estimatedCO2e = v > 0 ? (v * rule.factor_total_co2e) / 1000 : 0;
  }

  saveRule(rule: QuestionnaireRule) {
    const period = this.selectedPeriod();
    if (!period) {
      this.snack.open('Selecciona un período activo antes de guardar.', 'Cerrar', { duration: 3000 });
      return;
    }

    this.submitting.set(true);

    this.carbonService.storeEmission(period.id, {
      emission_factor_id: rule.emission_factor_id,
      quantity: rule.value,
      notes: `[Smart Intake] ${rule.questionnaire_label}: ${rule.value} ${rule.input_unit_hint ?? rule.unit_symbol ?? ''}`
    }).subscribe({
      next: () => {
        this.snack.open(`Registradas ${rule.estimatedCO2e.toFixed(4)} tCO₂e de "${rule.factor_name}".`, 'Cerrar', { duration: 4000 });
        rule.value = null;
        rule.estimatedCO2e = 0;
        this.submitting.set(false);
      },
      error: () => {
        this.snack.open('Error al guardar la emisión. Verifica que el período esté activo.', 'Cerrar', { duration: 4000 });
        this.submitting.set(false);
      }
    });
  }

  scopeIcon(scopeId: number): string {
    const icons: Record<number, string> = {
      1: 'local_fire_department',
      2: 'bolt',
      3: 'public',
    };
    return icons[scopeId] ?? 'eco';
  }
}
