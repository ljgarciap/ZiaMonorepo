import { ComponentFixture, TestBed } from '@angular/core/testing';
import { NoopAnimationsModule } from '@angular/platform-browser/animations';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';

import { SimulatorComponent } from './simulator';
import { environment } from '../../../environments/environment';

const mockScenarios = [
  {
    id: 1, code: 'HVAC_SCHEDULE', name: 'Ajuste de Horario HVAC',
    description: 'Reducir 1 hora diaria de operación del AC.',
    category: 'hvac', scope: 2,
    annual_co2e_tco2e: 1.1372, annual_savings_cop: 4251200,
  },
  {
    id: 2, code: 'HVAC_SETPOINT', name: 'Setpoint HVAC +2°C',
    description: 'Subir 2°C el setpoint del AC.',
    category: 'hvac', scope: 2,
    annual_co2e_tco2e: 0.7163, annual_savings_cop: 2677600,
  },
  {
    id: 4, code: 'REFRIGERANT_MAINTENANCE', name: 'Mantenimiento Refrigerante R-410A',
    description: 'Reducir tasa de fuga de R-410A.',
    category: 'refrigerant', scope: 1,
    annual_co2e_tco2e: 4.5602, annual_savings_cop: 0,
  },
];

const mockCalculateResult = {
  breakdown: [
    { id: 1, name: 'Ajuste de Horario HVAC', scope: 2, annual_co2e_tco2e: 1.1372, annual_savings_cop: 4251200, total_co2e_tco2e: 1.1372, total_savings_cop: 4251200 },
  ],
  years: 1,
  totals: { annual_co2e_tco2e: 1.1372, annual_savings_cop: 4251200, total_co2e_tco2e: 1.1372, total_savings_cop: 4251200 },
  projection: [{ year: 2026, co2e_tco2e: 1.1372, savings_cop: 4251200 }],
};

describe('SimulatorComponent', () => {
  let component: SimulatorComponent;
  let fixture: ComponentFixture<SimulatorComponent>;
  let http: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [SimulatorComponent, NoopAnimationsModule],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(SimulatorComponent);
    component = fixture.componentInstance;
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  // --- bootstrap ---------------------------------------------------------------

  it('should create', () => {
    fixture.detectChanges();
    http.expectOne(`${environment.apiUrl}/simulator/scenarios`).flush(mockScenarios);
    expect(component).toBeTruthy();
  });

  it('loads scenarios on init and marks them as not selected', () => {
    fixture.detectChanges();
    http.expectOne(`${environment.apiUrl}/simulator/scenarios`).flush(mockScenarios);

    expect(component.scenarios().length).toBe(3);
    expect(component.scenarios().every(s => s.selected === false)).toBe(true);
  });

  it('sets loading=false after scenarios are fetched', () => {
    fixture.detectChanges();
    expect(component.loading()).toBe(true);
    http.expectOne(`${environment.apiUrl}/simulator/scenarios`).flush(mockScenarios);
    expect(component.loading()).toBe(false);
  });

  it('sets loading=false even on HTTP error', () => {
    fixture.detectChanges();
    http.expectOne(`${environment.apiUrl}/simulator/scenarios`).error(new ProgressEvent('error'));
    expect(component.loading()).toBe(false);
  });

  // --- selectedCount -----------------------------------------------------------

  it('selectedCount() returns 0 when no scenarios are selected', () => {
    fixture.detectChanges();
    http.expectOne(`${environment.apiUrl}/simulator/scenarios`).flush(mockScenarios);
    expect(component.selectedCount()).toBe(0);
  });

  it('selectedCount() returns correct count after toggling', () => {
    fixture.detectChanges();
    http.expectOne(`${environment.apiUrl}/simulator/scenarios`).flush(mockScenarios);

    const updated = component.scenarios().map((s, i) => ({ ...s, selected: i < 2 }));
    component.scenarios.set(updated);
    expect(component.selectedCount()).toBe(2);
  });

  // --- recalculate -------------------------------------------------------------

  it('recalculate() clears result when no scenarios are selected', () => {
    fixture.detectChanges();
    http.expectOne(`${environment.apiUrl}/simulator/scenarios`).flush(mockScenarios);

    component.result.set(mockCalculateResult as any);
    component.recalculate();
    http.expectNone(`${environment.apiUrl}/simulator/calculate`);
    expect(component.result()).toBeNull();
  });

  it('recalculate() POSTs selected scenario ids and current year horizon', () => {
    fixture.detectChanges();
    http.expectOne(`${environment.apiUrl}/simulator/scenarios`).flush(mockScenarios);

    // Select first scenario
    component.scenarios.set(component.scenarios().map((s, i) => ({ ...s, selected: i === 0 })));
    component.selectedYears = 5;
    component.recalculate();

    const req = http.expectOne(`${environment.apiUrl}/simulator/calculate`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({ scenario_ids: [1], years: 5 });
    req.flush(mockCalculateResult);

    expect(component.result()).toBeTruthy();
    expect(component.result()!.totals.annual_co2e_tco2e).toBe(1.1372);
  });

  // --- categoryIcon ------------------------------------------------------------

  it('categoryIcon() maps known categories to mat-icon names', () => {
    expect(component.categoryIcon('hvac')).toBe('ac_unit');
    expect(component.categoryIcon('lighting')).toBe('lightbulb');
    expect(component.categoryIcon('refrigerant')).toBe('thermostat');
    expect(component.categoryIcon('motor')).toBe('settings');
  });

  it('categoryIcon() returns fallback icon for unknown category', () => {
    expect(component.categoryIcon('unknown')).toBe('eco');
  });
});
