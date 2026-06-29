import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';

import { CarbonService } from './carbon.service';

const API = 'http://127.0.0.1:8000/api';

describe('CarbonService', () => {
  let service: CarbonService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    service = TestBed.inject(CarbonService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpMock.verify());

  it('getHistory() calls GET /companies/{id}/emissions/history with params', () => {
    service.getHistory(1, { page: 2, search: 'Gasolina', sort_by: 'calculated_co2e', sort_dir: 'desc' }).subscribe();

    const req = httpMock.expectOne(r =>
      r.url === `${API}/companies/1/emissions/history` &&
      r.params.get('page') === '2' &&
      r.params.get('search') === 'Gasolina' &&
      r.params.get('sort_by') === 'calculated_co2e' &&
      r.params.get('sort_dir') === 'desc'
    );
    expect(req.request.method).toBe('GET');
    req.flush({ data: [], total: 0 });
  });

  it('storeEmission() calls POST /periods/{id}/emissions with correct body', () => {
    const payload = { emission_factor_id: 5, quantity: 100, notes: 'test' };
    service.storeEmission(7, payload).subscribe();

    const req = httpMock.expectOne(`${API}/periods/7/emissions`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual(payload);
    req.flush({ id: 1, calculated_co2e: 0.7 });
  });

  it('getHistory() omits undefined params from URL', () => {
    service.getHistory(3, {}).subscribe();

    // Expect exactly the URL — no query params added
    const req = httpMock.expectOne(r =>
      r.url === `${API}/companies/3/emissions/history` &&
      r.params.keys().length === 0
    );
    expect(req.request.method).toBe('GET');
    req.flush({ data: [], total: 0 });
  });

  it('HTTP error from storeEmission propagates as Observable error', () => {
    return new Promise<void>((resolve) => {
      service.storeEmission(1, { emission_factor_id: 999 }).subscribe({
        error: (err) => {
          expect(err.status).toBe(422);
          resolve();
        },
      });
      const req = httpMock.expectOne(`${API}/periods/1/emissions`);
      req.flush({ message: 'Validation error' }, { status: 422, statusText: 'Unprocessable Entity' });
    });
  });
});
