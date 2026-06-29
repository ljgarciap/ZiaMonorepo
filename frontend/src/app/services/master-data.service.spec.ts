import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';

import { MasterDataService } from './master-data.service';

const API = 'http://127.0.0.1:8000/api';

describe('MasterDataService', () => {
  let service: MasterDataService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    service  = TestBed.inject(MasterDataService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpMock.verify());

  it('getCompanies() calls the correct endpoint', () => {
    service.getCompanies().subscribe();
    const req = httpMock.expectOne(`${API}/companies`);
    expect(req.request.method).toBe('GET');
    req.flush([]);
  });

  it('getPeriods() includes companyId in the URL', () => {
    service.getPeriods(7).subscribe();
    const req = httpMock.expectOne(`${API}/companies/7/periods`);
    expect(req.request.method).toBe('GET');
    req.flush([]);
  });

  it('getEmissionFactors() without companyId omits query param', () => {
    service.getEmissionFactors().subscribe();
    const req = httpMock.expectOne(`${API}/dictionaries/factors`);
    expect(req.request.method).toBe('GET');
    req.flush([]);
  });

  it('getEmissionFactors() with companyId appends company_id query param', () => {
    service.getEmissionFactors(3).subscribe();
    const req = httpMock.expectOne(`${API}/dictionaries/factors?company_id=3`);
    expect(req.request.method).toBe('GET');
    req.flush([]);
  });

  it('getQuestionnaire() appends sector code to URL', () => {
    service.getQuestionnaire('servicios').subscribe();
    const req = httpMock.expectOne(`${API}/dictionaries/questionnaire?sector=servicios`);
    expect(req.request.method).toBe('GET');
    req.flush([]);
  });
});
