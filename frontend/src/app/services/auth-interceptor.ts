import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { AuthService } from './auth';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const authService = inject(AuthService);
  const token = authService.getToken();

  if (token) {
    let headers: any = {
      Authorization: `Bearer ${token}`
    };

    const context = authService.currentContext();
    if (context && context.type === 'company') {
      headers['X-Company-ID'] = context.id?.toString();
      headers['X-Context-Role'] = context.role;
    }

    const cloned = req.clone({
      setHeaders: headers
    });
    return next(cloned);
  }

  return next(req);
};
