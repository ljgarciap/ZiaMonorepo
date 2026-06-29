import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { vi } from 'vitest';

import { authGuard } from './auth-guard';
import { AuthService } from '../services/auth';

describe('authGuard', () => {
  let mockAuth: Partial<AuthService>;
  let mockRouter: Partial<Router>;

  function runGuard() {
    return TestBed.runInInjectionContext(() =>
      authGuard({} as any, {} as any)
    );
  }

  beforeEach(() => {
    mockAuth   = { isLoggedIn: vi.fn() };
    mockRouter = { navigate: vi.fn().mockResolvedValue(true) };

    TestBed.configureTestingModule({
      providers: [
        { provide: AuthService, useValue: mockAuth },
        { provide: Router,      useValue: mockRouter },
      ],
    });
  });

  it('returns true when the user is logged in', () => {
    (mockAuth.isLoggedIn as ReturnType<typeof vi.fn>).mockReturnValue(true);
    expect(runGuard()).toBe(true);
    expect(mockRouter.navigate).not.toHaveBeenCalled();
  });

  it('redirects to /login and returns false when not logged in', () => {
    (mockAuth.isLoggedIn as ReturnType<typeof vi.fn>).mockReturnValue(false);
    expect(runGuard()).toBe(false);
    expect(mockRouter.navigate).toHaveBeenCalledWith(['/login']);
  });
});
