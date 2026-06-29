import { TestBed } from '@angular/core/testing';
import { ContextService } from './context.service';

describe('ContextService', () => {
  let service: ContextService;

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({});
    service = TestBed.inject(ContextService);
  });

  afterEach(() => localStorage.clear());

  it('setCompany() updates selectedCompany signal', () => {
    service.setCompany({ id: 1, name: 'ECONOVA' });
    expect(service.selectedCompany()).toEqual({ id: 1, name: 'ECONOVA' });
  });

  it('setCompany() clears selectedPeriod', () => {
    service.setPeriod({ id: 2, year: 2026 });
    expect(service.selectedPeriod()).not.toBeNull();

    service.setCompany({ id: 1, name: 'ECONOVA' });
    expect(service.selectedPeriod()).toBeNull();
  });

  it('setPeriod() updates selectedPeriod signal', () => {
    service.setPeriod({ id: 2, year: 2026 });
    expect(service.selectedPeriod()).toEqual({ id: 2, year: 2026 });
  });

  it('reset() clears both signals and localStorage', () => {
    service.setCompany({ id: 1, name: 'ECONOVA' });
    service.setPeriod({ id: 2, year: 2026 });

    service.reset();

    expect(service.selectedCompany()).toBeNull();
    expect(service.selectedPeriod()).toBeNull();
    expect(localStorage.getItem('zia_selected_company')).toBeNull();
    expect(localStorage.getItem('zia_selected_period')).toBeNull();
  });

});

describe('ContextService — constructor error recovery', () => {
  afterEach(() => {
    localStorage.clear();
    TestBed.resetTestingModule();
  });

  it('handles corrupted localStorage data without throwing', () => {
    localStorage.setItem('zia_selected_company', '{not-valid-json');
    TestBed.configureTestingModule({});
    expect(() => TestBed.inject(ContextService)).not.toThrow();
    expect(localStorage.getItem('zia_selected_company')).toBeNull();
  });
});
