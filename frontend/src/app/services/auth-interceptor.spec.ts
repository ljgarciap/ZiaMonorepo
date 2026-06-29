import { TestBed } from '@angular/core/testing';
import { HttpClient, HttpInterceptorFn, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { vi } from 'vitest';

import { authInterceptor } from './auth-interceptor';
import { AuthService } from './auth';
import { createMockAuthService } from '../../testing/mocks';

describe('authInterceptor', () => {
  // Functional interceptor — use runInInjectionContext for unit-level check
  const interceptor: HttpInterceptorFn = (req, next) =>
    TestBed.runInInjectionContext(() => authInterceptor(req, next));

  let http: HttpClient;
  let httpMock: HttpTestingController;
  let authMock: ReturnType<typeof createMockAuthService>;

  beforeEach(() => {
    authMock = createMockAuthService();

    TestBed.configureTestingModule({
      providers: [
        // Wire the real interceptor into the HTTP pipeline so integration tests work
        provideHttpClient(withInterceptors([authInterceptor])),
        provideHttpClientTesting(),
        { provide: AuthService, useValue: authMock },
      ],
    });

    http = TestBed.inject(HttpClient);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
    vi.clearAllMocks();
  });

  // --- existing smoke test ---------------------------------------------------
  it('should be created', () => {
    expect(interceptor).toBeTruthy();
  });

  // --- 2 new tests ----------------------------------------------------------

  it('attaches Authorization header when a token exists', () => {
    authMock.getToken.mockReturnValue('my-bearer-token');

    http.get('/api/test').subscribe();

    const req = httpMock.expectOne('/api/test');
    expect(req.request.headers.get('Authorization')).toBe('Bearer my-bearer-token');
    req.flush({});
  });

  it('does NOT attach Authorization header when no token is present', () => {
    authMock.getToken.mockReturnValue(null);

    http.get('/api/test').subscribe();

    const req = httpMock.expectOne('/api/test');
    expect(req.request.headers.has('Authorization')).toBe(false);
    req.flush({});
  });
});
