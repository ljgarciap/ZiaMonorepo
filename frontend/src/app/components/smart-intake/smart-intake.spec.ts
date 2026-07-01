import { ComponentFixture, TestBed } from '@angular/core/testing';
import { NoopAnimationsModule } from '@angular/platform-browser/animations';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';

import { SmartIntakeComponent } from './smart-intake';
import { ContextService } from '../../services/context.service';
import { CarbonService } from '../../services/carbon.service';
import { MasterDataService } from '../../services/master-data.service';
import { createMockCarbonService, createMockMasterDataService } from '../../../testing/mocks';

const mockRules = [
  {
    id: 1, emission_factor_id: 5, questionnaire_label: 'Gas Natural',
    variable_name: 'gas_natural', input_unit_hint: 'm3', is_required: true,
    display_order: 1, help_text: 'Ingresa el consumo mensual', factor_name: 'Gas Natural',
    factor_total_co2e: 1.956, unit_symbol: 'm3', scope_id: 1, scope_name: 'Alcance 1',
  },
  {
    id: 2, emission_factor_id: 7, questionnaire_label: 'Electricidad Red',
    variable_name: 'electricidad', input_unit_hint: 'kWh', is_required: true,
    display_order: 2, help_text: null, factor_name: 'Red Eléctrica Colombia',
    factor_total_co2e: 0.4, unit_symbol: 'kWh', scope_id: 2, scope_name: 'Alcance 2',
  },
];

describe('SmartIntakeComponent', () => {
  let component: SmartIntakeComponent;
  let fixture: ComponentFixture<SmartIntakeComponent>;
  let contextService: ContextService;
  let carbonMock: ReturnType<typeof createMockCarbonService>;
  let masterMock: ReturnType<typeof createMockMasterDataService>;

  beforeEach(async () => {
    localStorage.clear();
    carbonMock = createMockCarbonService();
    masterMock = createMockMasterDataService();

    await TestBed.configureTestingModule({
      imports: [SmartIntakeComponent, NoopAnimationsModule],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        ContextService,
        { provide: CarbonService, useValue: carbonMock },
        { provide: MasterDataService, useValue: masterMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(SmartIntakeComponent);
    component = fixture.componentInstance;
    contextService = TestBed.inject(ContextService);
    fixture.detectChanges();
  });

  afterEach(() => localStorage.clear());

  // --- smoke test -----------------------------------------------------------
  it('should create the component', () => {
    expect(component).toBeTruthy();
  });

  // --- template state: no context -------------------------------------------
  it('renders empty-state card when no company or period is selected', () => {
    fixture.detectChanges();
    const el: HTMLElement = fixture.nativeElement;
    // No company selected → empty state visible
    expect(el.textContent).toContain('Selecciona una empresa');
  });

  // --- template state: company with no sector -------------------------------
  it('renders "no sector" message when company has no sector code', () => {
    contextService.setCompany({ id: 1, name: 'ECONOVA', sector: null });
    contextService.setPeriod({ id: 7, year: 2026 });
    fixture.detectChanges();

    expect(component.sectorCode()).toBeNull();
    const el: HTMLElement = fixture.nativeElement;
    expect(el.textContent).toContain('sector configurado');
  });

  // --- template state: company with sector shows profile card ---------------
  it('renders company profile card when company is selected', () => {
    contextService.setCompany({ id: 1, name: 'Empresa XYZ', sector: { code: 'manufactura', name: 'Manufactura' } });
    contextService.setPeriod({ id: 7, year: 2026 });

    // master data returns empty rules (no questionnaire yet)
    masterMock.getQuestionnaire.mockReturnValue(of([]));
    fixture.detectChanges();

    const profileCard = fixture.nativeElement.querySelector('.profile-card');
    expect(profileCard).not.toBeNull();
    expect(profileCard.textContent).toContain('Empresa XYZ');
  });

  // --- sectorCode -----------------------------------------------------------
  it('saveRule() is guarded by period selection — Registrar button disabled without a value', () => {
    const rule: any = {
      emission_factor_id: 5, questionnaire_label: 'Gas Natural',
      variable_name: 'gas_natural', input_unit_hint: 'm3', is_required: true,
      display_order: 1, help_text: null, factor_name: 'Gas Natural',
      factor_total_co2e: 1.956, unit_symbol: 'm3', scope_id: 1, scope_name: 'Alcance 1',
      value: null, estimatedCO2e: 0,
    };
    component.rules.set([rule]);
    fixture.detectChanges();

    component.saveRule(rule);
    expect(carbonMock.storeEmission).not.toHaveBeenCalled();
  });

  it('saveRule() calls storeEmission with the period.id from selectedPeriod (not hardcoded)', () => {
    contextService.setCompany({ id: 1, name: 'ECONOVA', sector: { code: 'servicios' } });
    contextService.setPeriod({ id: 99, year: 2026 });

    const rule: any = {
      emission_factor_id: 5, questionnaire_label: 'Gas Natural',
      variable_name: 'gas_natural', input_unit_hint: 'm3', is_required: true,
      display_order: 1, help_text: null, factor_name: 'Gas Natural',
      factor_total_co2e: 1.956, unit_symbol: 'm3', scope_id: 1, scope_name: 'Alcance 1',
      value: 500, estimatedCO2e: 0.978,
    };
    component.rules.set([rule]);

    component.saveRule(rule);

    expect(carbonMock.storeEmission).toHaveBeenCalledWith(99, expect.objectContaining({
      emission_factor_id: 5,
      quantity: 500,
    }));
  });

  it('sectorCode() derives from company.sector.code — not from company name string matching', () => {
    contextService.setCompany({ id: 2, name: 'Empresa ABC', sector: { code: 'manufactura' } });
    fixture.detectChanges();
    expect(component.sectorCode()).toBe('manufactura');

    contextService.setCompany({ id: 1, name: 'ECONOVA', sector: null });
    fixture.detectChanges();
    expect(component.sectorCode()).toBeNull();
  });

  it('saveRule() resets rule.value and estimatedCO2e after successful submit', async () => {
    contextService.setCompany({ id: 1, name: 'ECONOVA', sector: { code: 'servicios' } });
    contextService.setPeriod({ id: 7, year: 2026 });

    const rule: any = {
      emission_factor_id: 5, questionnaire_label: 'Gas Natural',
      variable_name: 'gas_natural', input_unit_hint: 'm3', is_required: true,
      display_order: 1, help_text: null, factor_name: 'Gas Natural',
      factor_total_co2e: 1.956, unit_symbol: 'm3', scope_id: 1, scope_name: 'Alcance 1',
      value: 500, estimatedCO2e: 0.978,
    };
    component.rules.set([rule]);

    carbonMock.storeEmission.mockReturnValue(of({ id: 1, calculated_co2e: 0.978 }));
    component.saveRule(rule);
    fixture.detectChanges();

    expect(rule.value).toBeNull();
    expect(rule.estimatedCO2e).toBe(0);
  });

  // --- saveRule error path --------------------------------------------------
  it('saveRule() sets submitting back to false on HTTP error', () => {
    contextService.setCompany({ id: 1, name: 'ECONOVA', sector: { code: 'servicios' } });
    contextService.setPeriod({ id: 7, year: 2026 });

    const rule: any = {
      emission_factor_id: 5, questionnaire_label: 'Gas Natural',
      variable_name: 'gas_natural', input_unit_hint: 'm3', is_required: true,
      display_order: 1, help_text: null, factor_name: 'Gas Natural',
      factor_total_co2e: 1.956, unit_symbol: 'm3', scope_id: 1, scope_name: 'Alcance 1',
      value: 100, estimatedCO2e: 0.1956,
    };
    component.rules.set([rule]);

    carbonMock.storeEmission.mockReturnValue(throwError(() => ({ status: 422 })));
    component.saveRule(rule);

    expect(component.submitting()).toBe(false);
  });

  // --- recalculate ----------------------------------------------------------
  it('recalculate() computes estimatedCO2e = (value * factor) / 1000', () => {
    const rule: any = {
      emission_factor_id: 5, questionnaire_label: 'Gas Natural',
      variable_name: 'gas_natural', input_unit_hint: 'm3', is_required: true,
      display_order: 1, help_text: null, factor_name: 'Gas Natural',
      factor_total_co2e: 2.0, unit_symbol: 'm3', scope_id: 1, scope_name: 'Alcance 1',
      value: 500, estimatedCO2e: 0,
    };
    component.recalculate(rule);
    expect(rule.estimatedCO2e).toBeCloseTo(1.0, 5); // 500 * 2.0 / 1000
  });

  it('recalculate() sets estimatedCO2e to 0 when value is 0 or null', () => {
    const rule: any = {
      emission_factor_id: 5, questionnaire_label: 'Test',
      variable_name: 'test', input_unit_hint: null, is_required: false,
      display_order: 1, help_text: null, factor_name: 'Test Factor',
      factor_total_co2e: 5.0, unit_symbol: 'kg', scope_id: 1, scope_name: 'Alcance 1',
      value: 0, estimatedCO2e: 9.99,
    };
    component.recalculate(rule);
    expect(rule.estimatedCO2e).toBe(0);
  });

  // --- scopeGroups computed --------------------------------------------------
  it('scopeGroups() groups rules by scope_id in ascending order', () => {
    const rules = mockRules.map(r => ({ ...r, value: null, estimatedCO2e: 0 }));
    component.rules.set(rules);

    const groups = component.scopeGroups();
    expect(groups.length).toBe(2);
    expect(groups[0].scope_id).toBe(1);
    expect(groups[1].scope_id).toBe(2);
    expect(groups[0].rules.length).toBe(1);
  });

  // --- completedCount / progressPct -----------------------------------------
  it('completedCount() counts rules with value > 0', () => {
    const rules = mockRules.map(r => ({ ...r, value: null as number | null, estimatedCO2e: 0 }));
    rules[0].value = 100; // first rule has a value
    component.rules.set(rules);

    expect(component.completedCount()).toBe(1);
    expect(component.progressPct()).toBeCloseTo(50, 0);
  });

  // --- scopeIcon ------------------------------------------------------------
  it('scopeIcon() maps scope IDs to material icons', () => {
    expect(component.scopeIcon(1)).toBe('local_fire_department');
    expect(component.scopeIcon(2)).toBe('bolt');
    expect(component.scopeIcon(3)).toBe('public');
    expect(component.scopeIcon(99)).toBe('eco');
  });
});
