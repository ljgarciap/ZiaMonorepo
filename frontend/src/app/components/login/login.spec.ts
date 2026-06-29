import { ComponentFixture, TestBed } from '@angular/core/testing';
import { NoopAnimationsModule } from '@angular/platform-browser/animations';
import { provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';

import { LoginComponent } from './login';
import { AuthService } from '../../services/auth';
import { createMockAuthService } from '../../../testing/mocks';

describe('LoginComponent', () => {
  let component: LoginComponent;
  let fixture: ComponentFixture<LoginComponent>;
  let authMock: ReturnType<typeof createMockAuthService>;

  beforeEach(async () => {
    authMock = createMockAuthService();

    await TestBed.configureTestingModule({
      imports: [LoginComponent, NoopAnimationsModule],
      providers: [
        provideRouter([]),
        { provide: AuthService, useValue: authMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(LoginComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    await fixture.whenStable();
  });

  // --- existing smoke test ---------------------------------------------------
  it('should create', () => {
    expect(component).toBeTruthy();
  });

  // --- 4 new tests ----------------------------------------------------------

  it('login form renders with email and password fields', () => {
    const el: HTMLElement = fixture.nativeElement;
    expect(el.querySelector('input[type="email"], input[formControlName="email"]')).toBeTruthy();
    expect(el.querySelector('input[type="password"], input[formControlName="password"]')).toBeTruthy();
  });

  it('onSubmit() calls authService.login() with form credentials', () => {
    authMock.login.mockReturnValue(of({ token: 'tok', user: { id: 1 } }));
    authMock.availableContexts.set([]);

    component.loginForm.setValue({ email: 'user@zia.com', password: 'secret123' });
    component.onSubmit();

    expect(authMock.login).toHaveBeenCalledWith({ email: 'user@zia.com', password: 'secret123' });
  });

  it('shows context selection UI when availableContexts() returns items after login', () => {
    const contexts = [
      { type: 'company', id: 1, label: 'ECONOVA', role: 'user' },
      { type: 'global', id: undefined, label: 'Global', role: 'superadmin' },
    ];
    // login() tap sets availableContexts; we simulate by setting it before subscribe
    authMock.login.mockReturnValue(of({ token: 'tok', require_selection: true }));
    authMock.availableContexts.set(contexts as any);

    component.loginForm.setValue({ email: 'admin@zia.com', password: 'secret123' });
    component.onSubmit();
    fixture.detectChanges();

    expect(component.isContextSelection).toBe(true);
  });

  it('shows error message when login fails', () => {
    authMock.login.mockReturnValue(throwError(() => ({ status: 401, message: 'Unauthorized' })));

    component.loginForm.setValue({ email: 'bad@zia.com', password: 'wrong' });
    component.onSubmit();
    fixture.detectChanges();

    expect(component.error).toBeTruthy();
    const el: HTMLElement = fixture.nativeElement;
    const errorEl = el.querySelector('.error-message');
    expect(errorEl?.textContent).toContain('Credenciales inválidas');
  });
});
