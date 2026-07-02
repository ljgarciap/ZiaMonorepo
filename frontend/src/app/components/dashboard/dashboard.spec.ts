import { ComponentFixture, TestBed } from '@angular/core/testing';
import { NoopAnimationsModule } from '@angular/platform-browser/animations';
import { provideRouter, Router } from '@angular/router';
import { vi } from 'vitest';

import { DashboardComponent } from './dashboard';
import { AuthService } from '../../services/auth';
import { ThemeService } from '../../services/theme.service';
import {
  createMockAuthService,
  createMockThemeService,
} from '../../../testing/mocks';

describe('DashboardComponent', () => {
  let component: DashboardComponent;
  let fixture: ComponentFixture<DashboardComponent>;
  let authMock: ReturnType<typeof createMockAuthService>;
  let themeMock: ReturnType<typeof createMockThemeService>;

  beforeEach(async () => {
    authMock = createMockAuthService();
    themeMock = createMockThemeService();

    authMock.currentUser.set({ id: 1, name: 'Test User' });
    authMock.currentContext.set({ type: 'company', id: 1, label: 'ECONOVA', role: 'user' });
    authMock.availableContexts.set([]);

    await TestBed.configureTestingModule({
      imports: [DashboardComponent, NoopAnimationsModule],
      providers: [
        provideRouter([]),
        { provide: AuthService, useValue: authMock },
        { provide: ThemeService, useValue: themeMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(DashboardComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  // --- existing smoke test ---------------------------------------------------
  it('should create', () => {
    expect(component).toBeTruthy();
  });

  // --- toggleTheme -----------------------------------------------------------
  it('theme toggle button calls themeService.toggleTheme()', () => {
    fixture.detectChanges();
    component.toggleTheme();
    expect(themeMock.toggleTheme).toHaveBeenCalledTimes(1);
  });

  // --- logout ---------------------------------------------------------------
  it('logout() delegates to authService.logout()', () => {
    component.logout();
    expect(authMock.logout).toHaveBeenCalledTimes(1);
  });

  // --- navigation rendering -------------------------------------------------
  it('renders Historial nav item for user role', () => {
    authMock.currentContext.set({ type: 'company', id: 1, label: 'ECONOVA', role: 'user' });
    fixture.detectChanges();

    const el: HTMLElement = fixture.nativeElement;
    const navText = el.textContent ?? '';
    expect(navText).toContain('Historial');
    expect(navText).not.toContain('Administración');
  });

  it('pageTitle defaults to the current route label', () => {
    fixture.detectChanges();
    // Initial URL is '/' — not in PAGE_TITLES, so falls back to 'ZIA Carbon Control'
    expect(component.pageTitle()).toBeTruthy();
  });

  // --- admin nav visibility -------------------------------------------------
  it('renders Administración nav items for admin role', () => {
    authMock.currentContext.set({ type: 'company', id: 1, label: 'ECONOVA', role: 'admin' });
    fixture.detectChanges();

    const el: HTMLElement = fixture.nativeElement;
    expect(el.textContent).toContain('Administración');
  });

  it('renders Plataforma nav items for superadmin role', () => {
    authMock.currentContext.set({ type: 'company', id: 1, label: 'ECONOVA', role: 'superadmin' });
    fixture.detectChanges();

    const el: HTMLElement = fixture.nativeElement;
    expect(el.textContent).toContain('Plataforma');
  });

  // --- viewer (solo lectura por empresa) --------------------------------------
  it('viewer sees Revisión de Datos but not Huella de Carbono nor Administración', () => {
    authMock.currentContext.set({ type: 'company', id: 1, label: 'ECONOVA', role: 'viewer' });
    fixture.detectChanges();

    const navText = (fixture.nativeElement as HTMLElement).textContent ?? '';
    expect(navText).toContain('Revisión de Datos');
    expect(navText).not.toContain('Huella de Carbono');
    expect(navText).not.toContain('Administración');
  });
});
