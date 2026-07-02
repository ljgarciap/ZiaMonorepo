import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';

import { AuditorAssignmentService } from './auditor-assignment.service';
import { environment } from '../../environments/environment';

describe('AuditorAssignmentService', () => {
  let service: AuditorAssignmentService;
  let http: HttpTestingController;
  const apiUrl = `${environment.apiUrl}/admin/auditor-assignments`;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });

    service = TestBed.inject(AuditorAssignmentService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('getAssignments() GETs the assignments endpoint', () => {
    service.getAssignments().subscribe();
    const req = http.expectOne(apiUrl);
    expect(req.request.method).toBe('GET');
    req.flush([]);
  });

  it('grant() POSTs the grant payload', () => {
    service.grant({ user_id: 1, period_id: 2, expires_at: null }).subscribe();
    const req = http.expectOne(apiUrl);
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({ user_id: 1, period_id: 2, expires_at: null });
    req.flush({});
  });

  it('revoke() DELETEs the assignment by id', () => {
    service.revoke(5).subscribe();
    const req = http.expectOne(`${apiUrl}/5`);
    expect(req.request.method).toBe('DELETE');
    req.flush({});
  });
});
