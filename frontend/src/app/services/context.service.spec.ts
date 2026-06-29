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
});
