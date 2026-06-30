import { Component, inject, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatButtonToggleModule } from '@angular/material/button-toggle';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatDividerModule } from '@angular/material/divider';
import { FormsModule } from '@angular/forms';
import { environment } from '../../../environments/environment';

interface SimulatorScenario {
  id: number;
  code: string;
  name: string;
  description: string;
  category: 'hvac' | 'lighting' | 'refrigerant' | 'motor';
  scope: number;
  annual_co2e_tco2e: number;
  annual_savings_cop: number;
  selected?: boolean;
}

interface CalculateResult {
  breakdown: {
    id: number;
    name: string;
    scope: number;
    annual_co2e_tco2e: number;
    annual_savings_cop: number;
    total_co2e_tco2e: number;
    total_savings_cop: number;
  }[];
  years: number;
  totals: {
    annual_co2e_tco2e: number;
    annual_savings_cop: number;
    total_co2e_tco2e: number;
    total_savings_cop: number;
  };
  projection: { year: number; co2e_tco2e: number; savings_cop: number }[];
}

@Component({
  selector: 'app-simulator',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatCardModule,
    MatIconModule,
    MatButtonModule,
    MatButtonToggleModule,
    MatSlideToggleModule,
    MatProgressBarModule,
    MatTooltipModule,
    MatDividerModule,
  ],
  template: `
    <div class="sim-container">

      <!-- Header -->
      <div class="header-section">
        <div class="badge-row">
          <span class="sim-badge"><mat-icon>science</mat-icon> GEMELO DIGITAL</span>
          <span class="scope-badge scope-1">Alcance 1</span>
          <span class="scope-badge scope-2">Alcance 2</span>
        </div>
        <h1>Simulador What-if</h1>
        <p>Activa escenarios de optimización y visualiza el impacto en emisiones y ahorro financiero para <strong>Edificio ECONOVA</strong>.</p>
      </div>

      <!-- Loading -->
      <div *ngIf="loading()" class="loading-state">
        <mat-progress-bar mode="indeterminate"></mat-progress-bar>
        <p>Cargando escenarios…</p>
      </div>

      <div class="sim-body" *ngIf="!loading()">

        <!-- Left: Scenario cards -->
        <div class="scenarios-col">

          <!-- Year selector -->
          <div class="year-selector glass-card">
            <span class="selector-label"><mat-icon>calendar_today</mat-icon> Horizonte de proyección</span>
            <mat-button-toggle-group [(ngModel)]="selectedYears" (change)="recalculate()">
              <mat-button-toggle [value]="1">1 año</mat-button-toggle>
              <mat-button-toggle [value]="5">5 años</mat-button-toggle>
              <mat-button-toggle [value]="10">10 años</mat-button-toggle>
            </mat-button-toggle-group>
          </div>

          <!-- Scenario cards -->
          <div class="scenario-card glass-card" *ngFor="let s of scenarios()"
               [class.selected]="s.selected"
               [class.scope-1-border]="s.scope === 1"
               [class.scope-2-border]="s.scope === 2">

            <div class="card-header">
              <div class="card-title-row">
                <mat-icon class="cat-icon" [class]="'cat-' + s.category">{{ categoryIcon(s.category) }}</mat-icon>
                <div class="card-titles">
                  <h3>{{ s.name }}</h3>
                  <span class="scope-badge" [class]="'scope-' + s.scope">Alcance {{ s.scope }}</span>
                </div>
              </div>
              <mat-slide-toggle
                [(ngModel)]="s.selected"
                (change)="recalculate()"
                color="primary">
              </mat-slide-toggle>
            </div>

            <p class="card-desc">{{ s.description }}</p>

            <div class="card-metrics" *ngIf="s.selected">
              <div class="metric">
                <span class="metric-val co2e">{{ s.annual_co2e_tco2e | number:'1.2-2' }}</span>
                <span class="metric-lbl">tCO₂e/año evitadas</span>
              </div>
              <div class="metric" *ngIf="s.annual_savings_cop > 0">
                <span class="metric-val savings">{{ (s.annual_savings_cop / 1000000) | number:'1.2-2' }}M</span>
                <span class="metric-lbl">COP/año ahorrados</span>
              </div>
              <div class="metric" *ngIf="s.annual_savings_cop === 0">
                <span class="metric-val co2e-scope1">Alcance 1</span>
                <span class="metric-lbl">Emisiones directas evitadas</span>
              </div>
            </div>
          </div>

        </div>

        <!-- Right: Impact summary -->
        <div class="impact-col">

          <!-- Empty state -->
          <div class="empty-impact glass-card" *ngIf="selectedCount() === 0">
            <mat-icon>toggle_off</mat-icon>
            <p>Activa al menos un escenario para ver el impacto proyectado.</p>
          </div>

          <!-- Results -->
          <ng-container *ngIf="result() as r">

            <div class="impact-header glass-card">
              <div class="impact-kpi">
                <span class="kpi-val co2e-big">{{ r.totals.total_co2e_tco2e | number:'1.2-2' }}</span>
                <span class="kpi-unit">tCO₂e evitadas en {{ selectedYears }} año{{ selectedYears > 1 ? 's' : '' }}</span>
              </div>
              <mat-divider vertical *ngIf="r.totals.total_savings_cop > 0"></mat-divider>
              <div class="impact-kpi" *ngIf="r.totals.total_savings_cop > 0">
                <span class="kpi-val savings-big">{{ (r.totals.total_savings_cop / 1000000) | number:'1.1-1' }}M</span>
                <span class="kpi-unit">COP ahorrados en {{ selectedYears }} año{{ selectedYears > 1 ? 's' : '' }}</span>
              </div>
            </div>

            <!-- Breakdown -->
            <div class="breakdown glass-card">
              <h4>Desglose por escenario</h4>
              <div class="breakdown-row" *ngFor="let b of r.breakdown">
                <span class="b-name">{{ b.name }}</span>
                <span class="b-co2e">{{ b.total_co2e_tco2e | number:'1.2-2' }} tCO₂e</span>
                <span class="b-savings" *ngIf="b.total_savings_cop > 0">
                  {{ (b.total_savings_cop / 1000000) | number:'1.1-1' }}M COP
                </span>
              </div>
            </div>

            <!-- Year-by-year projection -->
            <div class="projection glass-card" *ngIf="selectedYears > 1">
              <h4>Proyección acumulada</h4>
              <div class="proj-table">
                <div class="proj-row header-row">
                  <span>Año</span>
                  <span>CO₂e evitado (t)</span>
                  <span *ngIf="r.totals.total_savings_cop > 0">Ahorro (M COP)</span>
                </div>
                <div class="proj-row" *ngFor="let p of r.projection">
                  <span class="proj-year">{{ p.year }}</span>
                  <span class="proj-co2e">{{ p.co2e_tco2e | number:'1.2-2' }}</span>
                  <span class="proj-save" *ngIf="r.totals.total_savings_cop > 0">
                    {{ (p.savings_cop / 1000000) | number:'1.1-1' }}
                  </span>
                </div>
              </div>

              <!-- Visual bar chart -->
              <div class="bar-chart">
                <div class="bar-item" *ngFor="let p of r.projection">
                  <div class="bar-fill"
                    [style.height.%]="(p.co2e_tco2e / r.totals.total_co2e_tco2e) * 100"
                    [matTooltip]="p.co2e_tco2e + ' tCO₂e'">
                  </div>
                  <span class="bar-label">{{ p.year }}</span>
                </div>
              </div>
            </div>

            <!-- Equivalences -->
            <div class="equivalences glass-card">
              <h4>¿Qué significan {{ r.totals.total_co2e_tco2e | number:'1.1-1' }} tCO₂e?</h4>
              <div class="equiv-row">
                <mat-icon class="equiv-icon">forest</mat-icon>
                <span>≈ <strong>{{ (r.totals.total_co2e_tco2e * 45) | number:'1.0-0' }} árboles</strong> sembrados y maduros</span>
              </div>
              <div class="equiv-row">
                <mat-icon class="equiv-icon">directions_car</mat-icon>
                <span>≈ <strong>{{ (r.totals.total_co2e_tco2e * 4348) | number:'1.0-0' }} km</strong> en auto dejados de recorrer</span>
              </div>
              <div class="equiv-row">
                <mat-icon class="equiv-icon">bolt</mat-icon>
                <span>≈ <strong>{{ (r.totals.total_co2e_tco2e / 0.214 * 1000 | number:'1.0-0') }} kWh</strong> de energía limpia</span>
              </div>
            </div>

          </ng-container>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .sim-container { padding: 24px; max-width: 1200px; margin: 0 auto; }

    .header-section { margin-bottom: 28px; }
    .badge-row { display: flex; gap: 8px; align-items: center; margin-bottom: 10px; flex-wrap: wrap; }
    .sim-badge { display: flex; align-items: center; gap: 4px; background: var(--zia-primary); color: white; padding: 3px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; }
    .sim-badge mat-icon { font-size: 14px; width: 14px; height: 14px; }
    .scope-badge { padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
    .scope-1 { background: rgba(229, 57, 53, 0.15); color: #e53935; border: 1px solid rgba(229,57,53,0.3); }
    .scope-2 { background: rgba(0, 188, 212, 0.15); color: var(--zia-primary); border: 1px solid rgba(0,188,212,0.3); }
    h1 { font-size: 26px; font-weight: 700; color: var(--zia-text); margin: 0 0 6px; }
    .header-section p { color: var(--zia-text-muted); margin: 0; }

    .loading-state { padding: 40px; text-align: center; color: var(--zia-text-muted); }

    .sim-body { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    @media(max-width: 900px) { .sim-body { grid-template-columns: 1fr; } }

    .scenarios-col, .impact-col { display: flex; flex-direction: column; gap: 16px; }

    .glass-card {
      background: var(--glass-bg);
      border: 1px solid var(--glass-border);
      border-radius: 16px;
      padding: 20px;
    }

    /* Year selector */
    .year-selector { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; padding: 14px 20px; }
    .selector-label { display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: var(--zia-text-muted); white-space: nowrap; }
    .selector-label mat-icon { font-size: 16px; width: 16px; height: 16px; }

    /* Scenario cards */
    .scenario-card { transition: all 0.2s; cursor: default; }
    .scenario-card.selected { border-color: var(--zia-primary); }
    .scenario-card.scope-1-border.selected { border-color: #e53935; }
    .scope-1-border { border-left: 3px solid rgba(229,57,53,0.4); }
    .scope-2-border { border-left: 3px solid rgba(0,188,212,0.4); }

    .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; gap: 12px; }
    .card-title-row { display: flex; align-items: flex-start; gap: 10px; flex: 1; }
    .cat-icon { font-size: 24px; width: 24px; height: 24px; margin-top: 2px; }
    .cat-hvac { color: #26c6da; }
    .cat-lighting { color: #ffd54f; }
    .cat-refrigerant { color: #e53935; }
    .cat-motor { color: #9575cd; }
    .card-titles { display: flex; flex-direction: column; gap: 4px; }
    .card-titles h3 { margin: 0; font-size: 14px; font-weight: 600; color: var(--zia-text); line-height: 1.3; }
    .card-desc { font-size: 12px; color: var(--zia-text-muted); margin: 0 0 12px; line-height: 1.5; }

    .card-metrics { display: flex; gap: 20px; padding-top: 10px; border-top: 1px solid var(--glass-border); flex-wrap: wrap; }
    .metric { display: flex; flex-direction: column; }
    .metric-val { font-size: 20px; font-weight: 700; line-height: 1.2; }
    .metric-val.co2e { color: var(--zia-tertiary); }
    .metric-val.savings { color: #66bb6a; }
    .metric-val.co2e-scope1 { color: #e53935; font-size: 14px; }
    .metric-lbl { font-size: 11px; color: var(--zia-text-muted); font-weight: 500; }

    /* Impact col */
    .empty-impact { display: flex; flex-direction: column; align-items: center; gap: 12px; text-align: center; padding: 48px; color: var(--zia-text-muted); }
    .empty-impact mat-icon { font-size: 48px; width: 48px; height: 48px; color: var(--glass-border); }

    .impact-header { display: flex; gap: 24px; align-items: center; flex-wrap: wrap; }
    .impact-kpi { display: flex; flex-direction: column; gap: 4px; flex: 1; }
    .kpi-val { font-size: 36px; font-weight: 800; line-height: 1; }
    .kpi-val.co2e-big { color: var(--zia-tertiary); }
    .kpi-val.savings-big { color: #66bb6a; }
    .kpi-unit { font-size: 12px; color: var(--zia-text-muted); font-weight: 500; }

    /* Breakdown */
    .breakdown h4, .projection h4, .equivalences h4 { margin: 0 0 14px; font-size: 13px; font-weight: 600; color: var(--zia-text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
    .breakdown-row { display: flex; align-items: center; gap: 8px; padding: 8px 0; border-bottom: 1px solid var(--glass-border); font-size: 13px; }
    .breakdown-row:last-child { border-bottom: none; }
    .b-name { flex: 1; color: var(--zia-text); }
    .b-co2e { color: var(--zia-tertiary); font-weight: 600; white-space: nowrap; }
    .b-savings { color: #66bb6a; font-weight: 600; white-space: nowrap; }

    /* Projection */
    .proj-table { display: flex; flex-direction: column; gap: 4px; margin-bottom: 16px; }
    .proj-row { display: grid; grid-template-columns: 50px 1fr 1fr; gap: 8px; font-size: 12px; padding: 6px 4px; }
    .header-row { font-weight: 600; color: var(--zia-text-muted); border-bottom: 1px solid var(--glass-border); padding-bottom: 8px; }
    .proj-year { font-weight: 700; color: var(--zia-text); }
    .proj-co2e { color: var(--zia-tertiary); font-weight: 600; }
    .proj-save { color: #66bb6a; font-weight: 600; }

    .bar-chart { display: flex; align-items: flex-end; gap: 8px; height: 80px; margin-top: 8px; }
    .bar-item { display: flex; flex-direction: column; align-items: center; gap: 4px; flex: 1; height: 100%; }
    .bar-fill { background: var(--zia-primary); border-radius: 4px 4px 0 0; width: 100%; min-height: 4px; transition: height 0.3s; cursor: default; }
    .bar-label { font-size: 10px; color: var(--zia-text-muted); }

    /* Equivalences */
    .equiv-row { display: flex; align-items: center; gap: 10px; padding: 6px 0; font-size: 13px; color: var(--zia-text); }
    .equiv-icon { font-size: 18px; width: 18px; height: 18px; color: var(--zia-tertiary); flex-shrink: 0; }
  `]
})
export class SimulatorComponent implements OnInit {
  private http = inject(HttpClient);

  scenarios   = signal<SimulatorScenario[]>([]);
  loading     = signal(true);
  result      = signal<CalculateResult | null>(null);
  selectedYears = 1;

  selectedCount = computed(() => this.scenarios().filter(s => s.selected).length);

  ngOnInit() {
    this.http.get<SimulatorScenario[]>(`${environment.apiUrl}/simulator/scenarios`).subscribe({
      next: (data) => {
        this.scenarios.set(data.map(s => ({ ...s, selected: false })));
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  recalculate() {
    const ids = this.scenarios().filter(s => s.selected).map(s => s.id);
    if (ids.length === 0) {
      this.result.set(null);
      return;
    }
    this.http.post<CalculateResult>(`${environment.apiUrl}/simulator/calculate`, {
      scenario_ids: ids,
      years: this.selectedYears,
    }).subscribe({ next: (r) => this.result.set(r) });
  }

  categoryIcon(cat: string): string {
    const icons: Record<string, string> = {
      hvac: 'ac_unit',
      lighting: 'lightbulb',
      refrigerant: 'thermostat',
      motor: 'settings',
    };
    return icons[cat] ?? 'eco';
  }
}
