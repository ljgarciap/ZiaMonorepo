import { Component, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { MatCardModule } from '@angular/material/card';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { AuthService } from '../../services/auth';
import { Router, RouterModule } from '@angular/router';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, MatCardModule, MatInputModule, MatButtonModule, RouterModule],
  templateUrl: './login.html',
  styleUrls: ['./login.css']
})
export class LoginComponent {
  loginForm: FormGroup;
  error: string | null = null;
  isContextSelection = false;

  constructor(
    private fb: FormBuilder,
    public authService: AuthService,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) {
    this.loginForm = this.fb.group({
      email: ['', [Validators.required, Validators.email]],
      password: ['', [Validators.required]]
    });
  }

  onSubmit() {
    if (this.loginForm.valid) {
      console.log('Login submitted...');
      this.authService.login(this.loginForm.value).subscribe({
        next: () => {
          console.log('Login success, contexts:', this.authService.availableContexts().length);
          if (this.authService.availableContexts().length > 0) {
            this.isContextSelection = true;
            this.cdr.detectChanges(); // Force view update
            console.log('Context selection enabled');
          }
        },
        error: (err) => {
          this.error = 'Credenciales inválidas o error del servidor.';
          console.error(err);
          this.cdr.detectChanges();
        }
      });
    }
  }

  onSelectContext(context: any) {
    console.log('Context selected:', context);
    // Update state without auto-navigating from service
    this.authService.selectContext(context, false);

    // Force a hard navigation to ensure clean environment (fixes "double click" issue)
    console.log('Navigating to dashboard...');
    window.location.replace('/dashboard');
  }
}
