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

const mockCalculateResultMultiYear = {
  breakdown: [
    { id: 1, name: 'Ajuste de Horario HVAC', scope: 2, annual_co2e_tco2e: 1.1372, annual_savings_cop: 4251200, total_co2e_tco2e: 5.686, total_savings_cop: 21256000 },
  ],
  years: 5,
  totals: { annual_co2e_tco2e: 1.1372, annual_savings_cop: 4251200, total_co2e_tco2e: 5.686, total_savings_cop: 21256000 },
  projection: [
    { year: 2026, co2e_tco2e: 1.1372, savings_cop: 4251200 },
    { year: 2027, co2e_tco2e: 1.1372, savings_cop: 4251200 },
    { year: 2028, co2e_tco2e: 1.1372, savings_cop: 4251200 },
    { year: 2029, co2e_tco2e: 1.1372, savings_cop: 4251200 },
    { year: 2030, co2e_tco2e: 1.1372, savings_cop: 4251200 },
  ],
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

  // --- template rendering after load -------------------------------------------

  it('renders scenario cards after scenarios are loaded', () => {
    fixture.detectChanges();
    http.expectOne(`${environment.apiUrl}/simulator/scenarios`).flush(mockScenarios);
    fixture.detectChanges(); // re-render after loading=false

    const cards = fixture.nativeElement.querySelectorAll('.scenario-card');
    expect(cards.length).toBe(3);
  });

  it('renders empty-impact panel when no scenarios are selected', () => {
    fixture.detectChanges();
    http.expectOne(`${environment.apiUrl}/simulator/scenarios`).flush(mockScenarios);
    fixture.detectChanges();

    const emptyImpact = fixture.nativeElement.querySelector('.empty-impact');
    expect(emptyImpact).not.toBeNull();
  });

  it('renders metrics for a selected scenario', () => {
    fixture.detectChanges();
    http.expectOne(`${environment.apiUrl}/simulator/scenarios`).flush(mockScenarios);

    // Select first scenario
    component.scenarios.update(list => list.map((s, i) => ({ ...s, selected: i === 0 })));
    fixture.detectChanges();

    // card-metrics should be visible for selected scenario
    const metrics = fixture.nativeElement.querySelector('.card-metrics');
    expect(metrics).not.toBeNull();
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

  it('renders impact panel after result is set', () => {
    fixture.detectChanges();
    http.expectOne(`${environment.apiUrl}/simulator/scenarios`).flush(mockScenarios);

    // Manually set a result to test impact panel rendering
    component.result.set(mockCalculateResult as any);
    fixture.detectChanges();

    const impactHeader = fixture.nativeElement.querySelector('.impact-header');
    expect(impactHeader).not.toBeNull();

    const breakdown = fixture.nativeElement.querySelector('.breakdown');
    expect(breakdown).not.toBeNull();

    const equivalences = fixture.nativeElement.querySelector('.equivalences');
    expect(equivalences).not.toBeNull();
  });

  it('renders projection table when selectedYears > 1 and result is set', () => {
    fixture.detectChanges();
    http.expectOne(`${environment.apiUrl}/simulator/scenarios`).flush(mockScenarios);

    component.selectedYears = 5;
    component.result.set(mockCalculateResultMultiYear as any);
    fixture.detectChanges();

    const projection = fixture.nativeElement.querySelector('.projection');
    expect(projection).not.toBeNull();

    // Bar chart should render for multi-year
    const barChart = fixture.nativeElement.querySelector('.bar-chart');
    expect(barChart).not.toBeNull();
  });

  it('does NOT render projection section when selectedYears === 1', () => {
    fixture.detectChanges();
    http.expectOne(`${environment.apiUrl}/simulator/scenarios`).flush(mockScenarios);

    component.selectedYears = 1;
    component.result.set(mockCalculateResult as any);
    fixture.detectChanges();

    const projection = fixture.nativeElement.querySelector('.projection');
    expect(projection).toBeNull();
  });

  it('renders scope-1 result (annual_savings_cop === 0) correctly', () => {
    fixture.detectChanges();
    http.expectOne(`${environment.apiUrl}/simulator/scenarios`).flush(mockScenarios);

    const scope1Result = {
      ...mockCalculateResult,
      totals: { annual_co2e_tco2e: 4.56, annual_savings_cop: 0, total_co2e_tco2e: 4.56, total_savings_cop: 0 },
      breakdown: [{ id: 4, name: 'Refrigerante', scope: 1, annual_co2e_tco2e: 4.56, annual_savings_cop: 0, total_co2e_tco2e: 4.56, total_savings_cop: 0 }],
    };

    component.result.set(scope1Result as any);
    fixture.detectChanges();

    // impact-kpi should render without the savings column when savings_cop === 0
    const kpis = fixture.nativeElement.querySelectorAll('.impact-kpi');
    expect(kpis.length).toBe(1); // only CO2e, no savings column
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
