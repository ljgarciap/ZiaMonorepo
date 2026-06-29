import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { Router } from '@angular/router';
import { vi } from 'vitest';

import { AuthService } from './auth';
import { ContextService } from './context.service';
import { createMockContextService } from '../../testing/mocks';

const API = 'http://127.0.0.1:8000/api';

describe('AuthService', () => {
  let httpMock: HttpTestingController;
  let ctxMock: ReturnType<typeof createMockContextService>;

  const mockRouter = {
    navigate: vi.fn().mockResolvedValue(true),
    navigateByUrl: vi.fn().mockResolvedValue(true),
    url: '/',
  };

  // Configure TestBed without injecting the service yet so each test can
  // pre-populate localStorage before the constructor runs.
  function setup() {
    ctxMock = createMockContextService();
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: Router, useValue: mockRouter },
        { provide: ContextService, useValue: ctxMock },
      ],
    });
    httpMock = TestBed.inject(HttpTestingController);
  }

  beforeEach(() => {
    localStorage.clear();
    vi.clearAllMocks();
    setup();
  });

  afterEach(() => {
    httpMock.verify();
    localStorage.clear();
  });

  // --- existing smoke test ---------------------------------------------------
  it('should be created', () => {
    const service = TestBed.inject(AuthService);
    expect(service).toBeTruthy();
  });

  // --- 8 new tests -----------------------------------------------------------

  it('hydrates currentUser signal from localStorage on construction', () => {
    localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Ana' }));
    const service = TestBed.inject(AuthService);
    expect(service.currentUser()).toEqual({ id: 1, name: 'Ana' });
  });

  it('handles corrupted JSON in localStorage without throwing', () => {
    localStorage.setItem('user', '{not valid json');
    expect(() => TestBed.inject(AuthService)).not.toThrow();
    // Constructor calls logout() which removes the token
    expect(localStorage.getItem('token')).toBeNull();
  });

  it('logout() removes token from localStorage', () => {
    localStorage.setItem('token', 'abc123');
    const service = TestBed.inject(AuthService);
    service.logout();
    expect(localStorage.getItem('token')).toBeNull();
  });

  it('getToken() returns null when not logged in', () => {
    const service = TestBed.inject(AuthService);
    expect(service.getToken()).toBeNull();
  });

  it('login() makes POST to correct URL', () => {
    const service = TestBed.inject(AuthService);
    service.login({ email: 'a@b.com', password: 'secret123' }).subscribe();
    const req = httpMock.expectOne(`${API}/login`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({ email: 'a@b.com', password: 'secret123' });
    req.flush({ token: 't', user: { id: 1 } });
  });

  it('login() stores token in localStorage on success', () => {
    const service = TestBed.inject(AuthService);
    service.login({ email: 'a@b.com', password: 'secret123' }).subscribe();
    const req = httpMock.expectOne(`${API}/login`);
    req.flush({
      token: 'my-token',
      user: { id: 1, name: 'Test' },
      context: { type: 'company', id: 1, label: 'ECONOVA', role: 'user' },
    });
    expect(localStorage.getItem('token')).toBe('my-token');
  });

  it('selectContext() updates currentContext signal', () => {
    const service = TestBed.inject(AuthService);
    const ctx = { type: 'company' as const, id: 5, label: 'Empresa XYZ', role: 'user' };
    service.selectContext(ctx, false); // navigate=false avoids window.location
    expect(service.currentContext()).toEqual(ctx);
  });

  it('availableContexts() returns stored contexts from localStorage', () => {
    const ctxs = [{ type: 'company', id: 1, label: 'ECONOVA', role: 'user' }];
    localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Test' }));
    localStorage.setItem('available_contexts', JSON.stringify(ctxs));
    const service = TestBed.inject(AuthService);
    expect(service.availableContexts()).toEqual(ctxs);
  });
});
