import { Injectable, signal, NgZone } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { tap } from 'rxjs/operators';
import { Observable } from 'rxjs';
import { ContextService } from './context.service';
import { environment } from '../../environments/environment';

export interface AuthContext {
  type: 'global' | 'company';
  role: string;
  id?: number;
  name?: string;
  label: string;
  logo_url?: string;
}

@Injectable({
  providedIn: 'root',
})
export class AuthService {
  private apiUrl = environment.apiUrl; // Laravel API URL
  currentUser = signal<any>(null);
  currentContext = signal<AuthContext | null>(null);
  availableContexts = signal<AuthContext[]>([]);

  constructor(
    private http: HttpClient,
    private router: Router,
    private ngZone: NgZone,
    private contextService: ContextService
  ) {
    const user = localStorage.getItem('user');
    const context = localStorage.getItem('context');
    const available = localStorage.getItem('available_contexts');

    if (user) {
      try {
        this.currentUser.set(JSON.parse(user));
        if (context) {
          this.currentContext.set(JSON.parse(context));
        }
        if (available) {
          this.availableContexts.set(JSON.parse(available));
        }
      } catch (e) {
        console.error('[AuthService] Corrupted session data:', e);
        this.logout();
      }
    }
  }

  login(credentials: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/login`, credentials).pipe(
      tap((response: any) => {
        if (!response.require_selection) {
          this.setSession(response);
          // If contexts are returned even if selection not required (single context), save them
          if (response.contexts) {
            this.availableContexts.set(response.contexts);
            localStorage.setItem('available_contexts', JSON.stringify(response.contexts));
          } else if (response.context) {
            // If single context, wrap it
            const ctxs = [response.context];
            this.availableContexts.set(ctxs);
            localStorage.setItem('available_contexts', JSON.stringify(ctxs));
          }
        } else {
          // Store temporarily for selection step
          this.currentUser.set(response.user);
          localStorage.setItem('token', response.token);
          localStorage.setItem('user', JSON.stringify(response.user)); // FIX: Persist user for reload
          this.availableContexts.set(response.contexts);
          localStorage.setItem('available_contexts', JSON.stringify(response.contexts));
        }
      })
    );
  }

  selectContext(context: AuthContext, navigate: boolean = true) {
    this.contextService.reset();
    this.currentContext.set(context);
    localStorage.setItem('context', JSON.stringify(context));

    if (!navigate) return;

    this.ngZone.run(() => {
      if (this.router.url.includes('/dashboard')) {
        window.location.href = '/dashboard';
      } else {
        this.router.navigateByUrl('/dashboard');
      }
    });
  }

  private setSession(response: any) {
    localStorage.setItem('token', response.token);
    localStorage.setItem('user', JSON.stringify(response.user));
    if (response.context) {
      localStorage.setItem('context', JSON.stringify(response.context));
      this.currentContext.set(response.context);
    }
    this.currentUser.set(response.user);
    this.router.navigate(['/dashboard']);
  }

  register(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/register`, data);
  }

  logout() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    localStorage.removeItem('context');
    localStorage.removeItem('available_contexts');

    this.contextService.reset(); // Clear dashboard selections

    this.currentUser.set(null);
    this.currentContext.set(null);
    this.availableContexts.set([]);
    this.router.navigate(['/login']);
  }

  getToken(): string | null {
    return localStorage.getItem('token');
  }

  isLoggedIn(): boolean {
    return !!this.getToken();
  }
}
