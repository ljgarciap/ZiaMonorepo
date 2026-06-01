import { Component, inject, OnInit, ChangeDetectorRef, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatCardModule } from '@angular/material/card';
import { MatTabsModule } from '@angular/material/tabs';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { evaluate } from 'mathjs';
import { ContextService } from '../../services/context.service';
import { CarbonService } from '../../services/carbon.service';
import { AuthService } from '../../services/auth';

interface Question {
  id: string;
  label: string;
  type: 'number' | 'select' | 'text';
  placeholder?: string;
  variableName: string; // Used in mathjs formula
  options?: { label: string; value: any; factor?: number }[];
  value: any;
}

interface QuestionnaireSection {
  id: string;
  title: string;
  icon: string;
  tag: string; // sectorial tag for filtering
  formula: string; // mathjs formula (e.g. "consumption * density * factor")
  resultUnit: string;
  questions: Question[];
  factor: number; // default factor
  calculatedResult: number;
}

@Component({
  selector: 'app-smart-intake',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatButtonModule,
    MatIconModule,
    MatCardModule,
    MatTabsModule,
    MatProgressBarModule,
    MatSnackBarModule
  ],
  template: `
    <div class="smart-intake-container">
      <div class="header-section">
        <h1>Smart Intake</h1>
        <p>Cuestionario Inteligente de Carga de Datos - ECONOVA</p>
      </div>

      <!-- Ally Profiles Tags Info -->
      <div class="profile-card glass-card" *ngIf="selectedCompany()">
        <mat-icon>business</mat-icon>
        <div class="company-details">
          <h2>{{selectedCompany().name}}</h2>
          <div class="tag-row">
            <span class="profile-badge">Aliado ECONOVA</span>
            <span class="sector-tag" *ngFor="let tag of activeTags()">#{{tag}}</span>
            <span class="sector-tag empty-tag" *ngIf="activeTags().length === 0">Sin etiquetas de sector (Acceso Total)</span>
          </div>
        </div>
      </div>

      <!-- No Context Selected -->
      <div *ngIf="!selectedCompany() || !selectedPeriod()" class="empty-state-card glass-card">
        <mat-icon>warning</mat-icon>
        <p>Por favor seleccione una Empresa y Período en el panel superior para cargar el Smart Intake.</p>
      </div>

      <!-- Main Questionnaire Forms -->
      <div class="questionnaire-body" *ngIf="selectedCompany() && selectedPeriod()">
        <mat-tab-group class="custom-tabs" animationDuration="300ms">
          <mat-tab *ngFor="let section of filteredSections()">
            <ng-template matTabLabel>
              <mat-icon class="tab-icon">{{section.icon}}</mat-icon>
              <span>{{section.title}}</span>
            </ng-template>

            <div class="section-card glass-card">
              <h3>{{section.title}}</h3>
              <p class="section-desc">Complete las variables físicas requeridas. El motor científico evaluará la huella en tiempo real.</p>
              
              <div class="questions-grid">
                <div *ngFor="let q of section.questions" class="question-item">
                  <label class="q-label">{{q.label}}</label>

                  <mat-form-field appearance="outline" class="full-width-field" *ngIf="q.type === 'number'">
                    <input matInput type="number" [(ngModel)]="q.value" (ngModelChange)="evaluateSection(section)" [placeholder]="q.placeholder || ''">
                  </mat-form-field>

                  <mat-form-field appearance="outline" class="full-width-field" *ngIf="q.type === 'select'">
                    <mat-select [(ngModel)]="q.value" (ngModelChange)="evaluateSection(section)">
                      <mat-option *ngFor="let opt of q.options" [value]="opt.value">
                        {{opt.label}}
                      </mat-option>
                    </mat-select>
                  </mat-form-field>

                  <mat-form-field appearance="outline" class="full-width-field" *ngIf="q.type === 'text'">
                    <input matInput [(ngModel)]="q.value" [placeholder]="q.placeholder || ''">
                  </mat-form-field>
                </div>
              </div>

              <!-- Realtime Mathjs Dynamic Math Preview -->
              <div class="calculator-preview">
                <div class="math-expr">
                  <span class="label">Fórmula de Cálculo (mathjs):</span>
                  <code>{{section.formula}}</code>
                </div>
                <div class="calculator-result">
                  <span class="res-lbl">Resultado Estimado:</span>
                  <span class="res-val">{{section.calculatedResult | number:'1.2-4'}}</span>
                  <span class="res-unit">{{section.resultUnit}}</span>
                </div>
              </div>

              <div class="action-bar">
                <button mat-flat-button color="primary" [disabled]="section.calculatedResult <= 0 || submitting" (click)="saveSectionEmission(section)">
                  <mat-icon *ngIf="!submitting">save</mat-icon>
                  <span *ngIf="!submitting">REGISTRAR EN EMISIONES</span>
                  <span *ngIf="submitting">REGISTRANDO...</span>
                </button>
              </div>
            </div>
          </mat-tab>
        </mat-tab-group>
      </div>
    </div>
  `,
  styles: [`
    .smart-intake-container { padding: 24px; max-width: 1200px; margin: 0 auto; font-family: 'Outfit', sans-serif; }
    .header-section { margin-bottom: 24px; }
    .header-section h1 { font-size: 28px; font-weight: 700; color: var(--prestige-primary); margin: 0; }
    .header-section p { color: var(--prestige-text-muted); margin-top: 6px; }

    .glass-card { 
      background: rgba(255, 255, 255, 0.95); 
      border: 1px solid var(--prestige-border); 
      box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.05); 
      border-radius: 16px; 
      padding: 24px;
      margin-bottom: 24px;
    }
    :host-context(.dark-theme) .glass-card {
      background: var(--prestige-card-bg);
      border-color: var(--prestige-border);
    }

    .profile-card { display: flex; align-items: center; gap: 16px; }
    .profile-card mat-icon { font-size: 40px; width: 40px; height: 40px; color: var(--prestige-primary); }
    .company-details h2 { margin: 0; font-size: 18px; font-weight: 600; color: var(--prestige-text); }
    .tag-row { display: flex; gap: 8px; margin-top: 6px; align-items: center; }
    .profile-badge { background: #1a237e; color: white; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 600; }
    .sector-tag { background: #00897b; color: white; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 600; }
    .empty-tag { background: #666 !important; }

    .custom-tabs { margin-top: 16px; }
    .tab-icon { margin-right: 8px; }
    .section-card { border-top-left-radius: 0; border-top-right-radius: 0; padding: 32px; }
    .section-card h3 { font-size: 20px; font-weight: 600; margin: 0; color: var(--prestige-text); }
    .section-desc { font-size: 13px; color: var(--prestige-text-muted); margin-bottom: 24px; }

    .questions-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-bottom: 32px; }
    .question-item { display: flex; flex-direction: column; gap: 6px; }
    .q-label { font-size: 13px; font-weight: 500; color: var(--prestige-text); }
    .full-width-field { width: 100%; }

    .calculator-preview { 
      background: rgba(26, 35, 126, 0.03); 
      border-radius: 12px; 
      padding: 20px; 
      display: flex; 
      justify-content: space-between; 
      align-items: center; 
      border: 1px dashed var(--prestige-border);
      margin-bottom: 32px;
    }
    :host-context(.dark-theme) .calculator-preview {
      background: rgba(255, 255, 255, 0.02);
    }
    .math-expr { display: flex; flex-direction: column; gap: 4px; }
    .math-expr .label { font-size: 11px; text-transform: uppercase; color: var(--prestige-text-muted); font-weight: 700; }
    .math-expr code { font-family: 'Courier New', Courier, monospace; background: rgba(0,0,0,0.05); padding: 2px 6px; border-radius: 4px; color: #d81b60; font-size: 13px; }
    :host-context(.dark-theme) .math-expr code { background: rgba(255,255,255,0.08); color: #f48fb1; }
    
    .calculator-result { text-align: right; display: flex; flex-direction: column; }
    .res-lbl { font-size: 11px; text-transform: uppercase; color: var(--prestige-text-muted); font-weight: 700; }
    .res-val { font-size: 28px; font-weight: 800; color: var(--prestige-primary); line-height: 1.2; }
    :host-context(.dark-theme) .res-val { color: var(--prestige-primary-light); }
    .res-unit { font-size: 11px; font-weight: 600; color: var(--prestige-text-muted); }

    .action-bar { display: flex; justify-content: flex-end; }
    .empty-state-card { display: flex; flex-direction: column; align-items: center; gap: 16px; text-align: center; color: var(--prestige-text-muted); padding: 48px; }
    .empty-state-card mat-icon { font-size: 48px; width: 48px; height: 48px; color: #ff9800; }

    @media(max-width: 768px) {
      .questions-grid { grid-template-columns: 1fr; }
      .calculator-preview { flex-direction: column; gap: 16px; align-items: flex-start; }
      .calculator-result { text-align: left; }
    }
  `]
})
export class SmartIntakeComponent implements OnInit {
  private context = inject(ContextService);
  private carbonService = inject(CarbonService);
  private snack = inject(MatSnackBar);

  // Directly link signals from ContextService
  selectedCompany = this.context.selectedCompany;
  selectedPeriod = this.context.selectedPeriod;

  submitting = false;

  // Compute tags based on company name reactively!
  activeTags = computed(() => {
    const company = this.selectedCompany();
    if (!company) return [];
    
    const name = company.name.toLowerCase();
    if (name.includes('econova') || name.includes('energy')) {
      return ['energia', 'agua'];
    } else if (name.includes('trans') || name.includes('logistica')) {
      return ['transporte'];
    }
    return [];
  });

  // Master Questionnaire list
  questionnaireSections: QuestionnaireSection[] = [
    {
      id: 'combustion_gas',
      title: 'Combustión Estacionaria (Gas Natural)',
      icon: 'local_fire_department',
      tag: 'energia',
      formula: 'consumption * factor',
      resultUnit: 'tCO2e',
      factor: 1.956, // kg CO2e / m3
      calculatedResult: 0,
      questions: [
        { id: 'q1', label: 'Consumo de Gas Natural (m³)', type: 'number', placeholder: 'Ej. 500', variableName: 'consumption', value: '' }
      ]
    },
    {
      id: 'energia_electrica',
      title: 'Consumo Eléctrico (Red Comercial)',
      icon: 'bolt',
      tag: 'energia',
      formula: 'consumption * grid_factor',
      resultUnit: 'tCO2e',
      factor: 0.126, // default factor
      calculatedResult: 0,
      questions: [
        { id: 'q2', label: 'Energía Activa Consumida (kWh)', type: 'number', placeholder: 'Ej. 12500', variableName: 'consumption', value: '' },
        { 
          id: 'q3', 
          label: 'Subestación / Red de Distribución', 
          type: 'select', 
          variableName: 'grid_factor', 
          value: 0.126,
          options: [
            { label: 'Sistema Interconectado Nacional (0.126 kg CO2/kWh)', value: 0.126 },
            { label: 'Red de Distribución Local - Antioquia (0.114 kg CO2/kWh)', value: 0.114 },
            { label: 'Generación Diésel de Respaldo (0.680 kg CO2/kWh)', value: 0.680 }
          ]
        }
      ]
    },
    {
      id: 'flota_vehicular',
      title: 'Transporte y Movilidad (Combustión Móvil)',
      icon: 'directions_car',
      tag: 'transporte',
      formula: 'gallons * density * factor',
      resultUnit: 'tCO2e',
      factor: 8.78, // default factor
      calculatedResult: 0,
      questions: [
        { id: 'q4', label: 'Galones de Combustible Cargados (gal)', type: 'number', placeholder: 'Ej. 150', variableName: 'gallons', value: '' },
        { 
          id: 'q5', 
          label: 'Tipo de Combustible', 
          type: 'select', 
          variableName: 'density', 
          value: 1.0,
          options: [
            { label: 'Gasolina Corriente (Densidad 1.0)', value: 1.0 },
            { label: 'Diésel / ACPM (Densidad 1.15)', value: 1.15 }
          ]
        },
        {
          id: 'q6',
          label: 'Factor de Emisión Combustible (kg/gal)',
          type: 'select',
          variableName: 'factor',
          value: 8.78,
          options: [
            { label: 'Gasolina Regular (8.78 kg CO2e/gal)', value: 8.78 },
            { label: 'Diésel Comercial (10.21 kg CO2e/gal)', value: 10.21 }
          ]
        }
      ]
    },
    {
      id: 'agua_consumo',
      title: 'Consumo y Tratamiento de Agua',
      icon: 'water_drop',
      tag: 'agua',
      formula: 'm3_consumed * 0.00035 + m3_treated * 0.00078',
      resultUnit: 'tCO2e',
      factor: 0.00035,
      calculatedResult: 0,
      questions: [
        { id: 'q7', label: 'Volumen de Agua Consumida (m³)', type: 'number', placeholder: 'Ej. 25', variableName: 'm3_consumed', value: '' },
        { id: 'q8', label: 'Volumen de Aguas Residuales Tratadas (m³)', type: 'number', placeholder: 'Ej. 22', variableName: 'm3_treated', value: '' }
      ]
    }
  ];

  // Compute filtered sections reactively!
  filteredSections = computed(() => {
    const tags = this.activeTags();
    if (tags.length === 0) {
      return this.questionnaireSections;
    }
    return this.questionnaireSections.filter(s => tags.includes(s.tag));
  });

  ngOnInit() {}

  /**
   * Evaluate dynamic formula using mathjs
   */
  evaluateSection(section: QuestionnaireSection) {
    try {
      const scopeVariables: { [key: string]: number } = {};
      
      // Map question values to variables
      section.questions.forEach(q => {
        const val = parseFloat(q.value);
        scopeVariables[q.variableName] = isNaN(val) ? 0 : val;
      });

      // Add default section factor if not overridden in questions
      if (section.factor && scopeVariables['factor'] === undefined) {
        scopeVariables['factor'] = section.factor;
      }

      // Parse and evaluate with mathjs
      const result = evaluate(section.formula, scopeVariables);
      
      // Total in tonnes (factors are typically in kg, so divide by 1000 unless calculated directly)
      section.calculatedResult = result > 0 ? (result / 1000.0) : 0;
    } catch (e) {
      section.calculatedResult = 0;
    }
  }

  saveSectionEmission(section: QuestionnaireSection) {
    const period = this.selectedPeriod();
    if (!period) {
      this.snack.open('Por favor seleccione un período activo.', 'Cerrar', { duration: 3000 });
      return;
    }

    this.submitting = true;

    // We construct notes with variables
    const notesStr = section.questions.map(q => `${q.label}: ${q.value}`).join(', ');

    // Let's call the carbon service
    this.carbonService.storeEmission(period.id, {
      emission_factor_id: 1, // Mock factor id
      quantity: section.calculatedResult * 1000.0, // Convert back to standard units
      notes: `[Smart Intake] ${notesStr} | Fórmula: ${section.formula}`
    }).subscribe({
      next: (res: any) => {
        this.snack.open('Registro de emisión guardado exitosamente.', 'Cerrar', { duration: 3000 });
        
        // Reset questions
        section.questions.forEach(q => {
          if (q.type === 'number') q.value = '';
        });
        section.calculatedResult = 0;
        
        this.submitting = false;
      },
      error: (err: any) => {
        this.snack.open('Error al guardar emisión.', 'Cerrar', { duration: 3000 });
        this.submitting = false;
      }
    });
  }
}
