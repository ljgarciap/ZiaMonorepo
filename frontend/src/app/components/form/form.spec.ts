import { ComponentFixture, TestBed } from '@angular/core/testing';
import { NoopAnimationsModule } from '@angular/platform-browser/animations';
import { signal } from '@angular/core';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';

import { FormComponent } from './form';
import { MasterDataService } from '../../services/master-data.service';
import { ContextService } from '../../services/context.service';
import { AuthService } from '../../services/auth';
import { CarbonService } from '../../services/carbon.service';
import { createMockCarbonService, createMockMasterDataService } from '../../../testing/mocks';

describe('FormComponent', () => {
  let component: FormComponent;
  let fixture: ComponentFixture<FormComponent>;
  let carbonMock: ReturnType<typeof createMockCarbonService>;
  let masterMock: ReturnType<typeof createMockMasterDataService>;

  function buildAuthMock(role = 'user') {
    return {
      currentContext: signal({ role, label: 'ECONOVA', type: 'company', id: 1 }),
      currentUser: signal({ id: 1, name: 'Test User' }),
    };
  }

  function buildContextMock() {
    return {
      selectedCompany: signal<any>(null),
      selectedPeriod: signal<any>(null),
      reset: vi.fn(),
    };
  }

  beforeEach(async () => {
    carbonMock = createMockCarbonService();
    masterMock = createMockMasterDataService();

    await TestBed.configureTestingModule({
      imports: [FormComponent, NoopAnimationsModule],
      providers: [
        { provide: MasterDataService, useValue: masterMock },
        { provide: ContextService, useValue: buildContextMock() },
        { provide: AuthService, useValue: buildAuthMock() },
        { provide: CarbonService, useValue: carbonMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(FormComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  // --- existing smoke test ---------------------------------------------------
  it('should create', () => {
    expect(component).toBeTruthy();
  });

  // --- 5 new tests ----------------------------------------------------------

  it('onSubmit() calls carbonService.storeEmission for each staged item', () => {
    component.selectedCompany = { id: 1, name: 'ECONOVA' };
    component.selectedPeriod = { id: 7, year: 2026 };

    // Manually stage two items (simulates user clicking "Agregar")
    const fakeCategory1 = {
      name: 'Gasolina',
      selectedFactor: { id: 3, name: 'Gasolina E10', factor_total_co2e: '7.618', unit: { symbol: 'L' } },
      inputAmount: '100',
      factors: [],
      children: [],
    };
    component.addEmission(fakeCategory1, 1);

    const fakeCategory2 = {
      name: 'Electricidad',
      selectedFactor: { id: 7, name: 'Red Eléctrica', factor_total_co2e: '0.4', unit: { symbol: 'kWh' } },
      inputAmount: '500',
      factors: [],
      children: [],
    };
    component.addEmission(fakeCategory2, 2);

    carbonMock.storeEmission.mockReturnValue(of({ id: 1, calculated_co2e: 0.5 }));
    component.onSubmit();

    expect(carbonMock.storeEmission).toHaveBeenCalledTimes(2);
    // Each call must use the real period id (7), not a hardcoded value
    expect(carbonMock.storeEmission).toHaveBeenCalledWith(7, expect.objectContaining({
      emission_factor_id: 3,
    }));
  });

  it('submitting flag is set to false after all requests complete', (done) => {
    component.selectedCompany = { id: 1, name: 'ECONOVA' };
    component.selectedPeriod = { id: 7, year: 2026 };

    const fakeCategory = {
      name: 'Gasolina',
      selectedFactor: { id: 3, name: 'Gasolina E10', factor_total_co2e: '7.618', unit: { symbol: 'L' } },
      inputAmount: '100',
      factors: [],
      children: [],
    };
    component.addEmission(fakeCategory, 1);

    carbonMock.storeEmission.mockReturnValue(of({ id: 1, calculated_co2e: 0.5 }));
    component.onSubmit();

    // submitting resets to false after forkJoin completes
    setTimeout(() => {
      expect(component.submitting).toBe(false);
      done();
    }, 0);
  });

  it('onSubmit() does not call carbonService when no company is selected', () => {
    component.selectedCompany = null;
    component.selectedPeriod = { id: 7, year: 2026 };

    component.onSubmit();

    expect(carbonMock.storeEmission).not.toHaveBeenCalled();
  });

  it('onSubmit() does not call carbonService when no items are staged', () => {
    component.selectedCompany = { id: 1, name: 'ECONOVA' };
    component.selectedPeriod = { id: 7, year: 2026 };
    // No addEmission() called — dataSources are empty

    component.onSubmit();

    expect(carbonMock.storeEmission).not.toHaveBeenCalled();
  });

  it('onSubmit() continues saving other items when one request fails', (done) => {
    component.selectedCompany = { id: 1, name: 'ECONOVA' };
    component.selectedPeriod = { id: 7, year: 2026 };

    const makeCategory = (name: string, factorId: number) => ({
      name,
      selectedFactor: { id: factorId, name, factor_total_co2e: '1.0', unit: { symbol: 'u' } },
      inputAmount: '10',
      factors: [],
      children: [],
    });

    component.addEmission(makeCategory('GasA', 1), 1);
    component.addEmission(makeCategory('GasB', 2), 1);

    // First call fails, second succeeds — forkJoin must still complete
    carbonMock.storeEmission
      .mockReturnValueOnce(throwError(() => ({ status: 500 })))
      .mockReturnValueOnce(of({ id: 2, calculated_co2e: 0.1 }));

    component.onSubmit();

    setTimeout(() => {
      // Both calls were made (forkJoin waits for all)
      expect(carbonMock.storeEmission).toHaveBeenCalledTimes(2);
      expect(component.submitting).toBe(false);
      done();
    }, 0);
  });
});
