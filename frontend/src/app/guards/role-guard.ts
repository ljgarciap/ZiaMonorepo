import { inject } from '@angular/core';
import { Router, CanActivateFn } from '@angular/router';
import { AuthService } from '../services/auth';

export const roleGuard: CanActivateFn = (route, state) => {
    const authService = inject(AuthService);
    const router = inject(Router);

    const expectedRoles = route.data['roles'] as Array<string>;

    const context = authService.currentContext();
    const user = authService.currentUser();

    const currentRole = context?.role || user?.role;

    if (currentRole && expectedRoles.includes(currentRole)) {
        return true;
    }

    // Redirect to dashboard if not authorized
    router.navigate(['/dashboard']);
    return false;
};
