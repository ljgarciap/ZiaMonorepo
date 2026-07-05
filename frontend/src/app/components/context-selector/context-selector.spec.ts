import { ComponentFixture, TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { vi } from 'vitest';

import { ContextSelectorComponent } from './context-selector';
import { ContextService } from '../../services/context.service';
import { MasterDataService } from '../../services/master-data.service';
import { createMockMasterDataService } from '../../../testing/mocks';

describe('ContextSelectorComponent', () => {
  let component: ContextSelectorComponent;
  let fixture: ComponentFixture<ContextSelectorComponent>;
  let masterMock: any;

  const companyA = { id: 1, name: 'Empresa A' };
  const companyB = { id: 2, name: 'Empresa B' };
  const periodA2024 = { id: 10, year: 2024 };
  const periodB2023 = { id: 20, year: 2023 };

  beforeEach(async () => {
    localStorage.clear();
    masterMock = createMockMasterDataService();
    masterMock.getCompanies.mockReturnValue(of([companyA, companyB]));

    await TestBed.configureTestingModule({
      imports: [ContextSelectorComponent],
      providers: [
        ContextService,
        { provide: MasterDataService, useValue: masterMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(ContextSelectorComponent);
    component = fixture.componentInstance;
  });

  afterEach(() => localStorage.clear());

  it('resets selectedPeriod when switching companies', () => {
    masterMock.getPeriods.mockReturnValue(of([periodA2024]));
    fixture.detectChanges(); // ngOnInit -> loadCompanies -> auto-selects companyA + periodA2024

    expect(component.selectedPeriod).toEqual(periodA2024);

    masterMock.getPeriods.mockReturnValue(of([periodB2023]));
    component.onCompanyChange(companyB);

    // Must not still be holding companyA's period while companyB's periods load
    expect(component.selectedPeriod).toEqual(periodB2023);
  });

  it('clears the period in ContextService immediately on company change', () => {
    const context = TestBed.inject(ContextService);
    masterMock.getPeriods.mockReturnValue(of([periodA2024]));
    fixture.detectChanges();

    masterMock.getPeriods.mockReturnValue(of([]));
    component.onCompanyChange(companyB);

    expect(context.selectedCompany()).toEqual(companyB);
    expect(component.selectedPeriod).toBeNull();
  });
});
