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

  // --- 4 new tests ----------------------------------------------------------

  it('saveRule() is guarded by period selection — Registrar button disabled without a value', () => {
    // Provide a rule with no value; the template disables the button when
    // `!rule.value || rule.value <= 0 || submitting()`
    const rule: any = {
      emission_factor_id: 5,
      questionnaire_label: 'Gas Natural',
      variable_name: 'gas_natural',
      input_unit_hint: 'm3',
      is_required: true,
      display_order: 1,
      help_text: null,
      factor_name: 'Gas Natural',
      factor_total_co2e: 1.956,
      unit_symbol: 'm3',
      scope_id: 1,
      scope_name: 'Alcance 1',
      value: null, // <-- no value entered
      estimatedCO2e: 0,
    };
    component.rules.set([rule]);
    fixture.detectChanges();

    // Without a value the storeEmission must not be called
    component.saveRule(rule);
    // saveRule guards with `if (!period)` first, then the template guards
    // button click.  Assert carbonService was NOT called (period is null).
    expect(carbonMock.storeEmission).not.toHaveBeenCalled();
  });

  it('saveRule() calls storeEmission with the period.id from selectedPeriod (not hardcoded)', () => {
    // Bug fix verification: period.id must come from selectedPeriod(), not be hardcoded
    contextService.setCompany({ id: 1, name: 'ECONOVA', sector: { code: 'servicios' } });
    contextService.setPeriod({ id: 99, year: 2026 }); // non-trivial id

    const rule: any = {
      emission_factor_id: 5,
      questionnaire_label: 'Gas Natural',
      variable_name: 'gas_natural',
      input_unit_hint: 'm3',
      is_required: true,
      display_order: 1,
      help_text: null,
      factor_name: 'Gas Natural',
      factor_total_co2e: 1.956,
      unit_symbol: 'm3',
      scope_id: 1,
      scope_name: 'Alcance 1',
      value: 500,
      estimatedCO2e: 0.978,
    };
    component.rules.set([rule]);

    component.saveRule(rule);

    // The first argument to storeEmission must be the period id (99), not 1
    expect(carbonMock.storeEmission).toHaveBeenCalledWith(99, expect.objectContaining({
      emission_factor_id: 5,
      quantity: 500,
    }));
  });

  it('sectorCode() derives from company.sector.code — not from company name string matching', () => {
    // Bug fix verification: old code used `company.name.includes('econova')`
    // New code must use `company?.sector?.code`

    // A company whose name does NOT contain 'econova' but has a sector code
    contextService.setCompany({ id: 2, name: 'Empresa ABC', sector: { code: 'manufactura' } });
    fixture.detectChanges();

    expect(component.sectorCode()).toBe('manufactura');

    // A company with name 'ECONOVA' but no sector — sectorCode must be null
    contextService.setCompany({ id: 1, name: 'ECONOVA', sector: null });
    fixture.detectChanges();

    expect(component.sectorCode()).toBeNull();
  });

  it('saveRule() resets rule.value and estimatedCO2e after successful submit', async () => {
    contextService.setCompany({ id: 1, name: 'ECONOVA', sector: { code: 'servicios' } });
    contextService.setPeriod({ id: 7, year: 2026 });

    const rule: any = {
      emission_factor_id: 5,
      questionnaire_label: 'Gas Natural',
      variable_name: 'gas_natural',
      input_unit_hint: 'm3',
      is_required: true,
      display_order: 1,
      help_text: null,
      factor_name: 'Gas Natural',
      factor_total_co2e: 1.956,
      unit_symbol: 'm3',
      scope_id: 1,
      scope_name: 'Alcance 1',
      value: 500,
      estimatedCO2e: 0.978,
    };
    component.rules.set([rule]);

    carbonMock.storeEmission.mockReturnValue(of({ id: 1, calculated_co2e: 0.978 }));
    component.saveRule(rule);
    fixture.detectChanges();

    // After a successful save the rule must be reset
    expect(rule.value).toBeNull();
    expect(rule.estimatedCO2e).toBe(0);
  });
});
