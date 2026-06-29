import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';

import { DashboardService } from './dashboard.service';

const API = 'http://127.0.0.1:8000/api';

describe('DashboardService', () => {
  let service: DashboardService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    service = TestBed.inject(DashboardService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpMock.verify());

  it('getSummary() calls /dashboard/summary with company_id and period_id', () => {
    service.getSummary(3, 8).subscribe();

    const req = httpMock.expectOne(r =>
      r.url === `${API}/dashboard/summary` &&
      r.params.get('company_id') === '3' &&
      r.params.get('period_id') === '8'
    );
    expect(req.request.method).toBe('GET');
    req.flush({ huella_total: 0, scope_1: 0, scope_2: 0, scope_3: 0 });
  });

  it('downloadPdf() calls GET with responseType blob', () => {
    service.downloadPdf(5).subscribe();

    const req = httpMock.expectOne(`${API}/reports/periods/5/pdf`);
    expect(req.request.method).toBe('GET');
    expect(req.request.responseType).toBe('blob');
    req.flush(new Blob());
  });

  it('downloadExcel() calls GET with responseType blob', () => {
    service.downloadExcel(5).subscribe();

    const req = httpMock.expectOne(`${API}/reports/periods/5/excel`);
    expect(req.request.method).toBe('GET');
    expect(req.request.responseType).toBe('blob');
    req.flush(new Blob());
  });
});
