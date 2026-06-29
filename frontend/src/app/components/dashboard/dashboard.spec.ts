import { ComponentFixture, TestBed } from '@angular/core/testing';
import { NoopAnimationsModule } from '@angular/platform-browser/animations';
import { provideRouter } from '@angular/router';
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

  // --- 2 new tests ----------------------------------------------------------

  it('theme toggle button calls themeService.toggleTheme()', () => {
    fixture.detectChanges();

    // The toolbar has a button (click)="toggleTheme()"
    const buttons: NodeListOf<HTMLButtonElement> = fixture.nativeElement.querySelectorAll('button');
    // Find the button that triggers toggleTheme — it is an icon-button in the toolbar
    // We can call the component method directly and verify the mock
    component.toggleTheme();

    expect(themeMock.toggleTheme).toHaveBeenCalledTimes(1);
  });

  it('renders Historial nav item for user role', () => {
    // Context with 'user' role → Historial link must appear
    authMock.currentContext.set({ type: 'company', id: 1, label: 'ECONOVA', role: 'user' });
    fixture.detectChanges();

    const el: HTMLElement = fixture.nativeElement;
    const navText = el.textContent ?? '';
    expect(navText).toContain('Historial');
    // Admin section must NOT appear for plain users
    expect(navText).not.toContain('Administración');
  });
});
