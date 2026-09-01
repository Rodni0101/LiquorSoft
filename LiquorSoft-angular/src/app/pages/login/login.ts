import { Component, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../auth.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './login.html',
  styleUrl: './login.css'
})
export class Login {
  private readonly http = inject(HttpClient);
  private readonly formBuilder = inject(FormBuilder);
  private readonly router = inject(Router);
  private readonly auth = inject(AuthService);
  protected readonly loginForm = this.formBuilder.nonNullable.group({
    correo: ['', [Validators.required, Validators.email]],
    password: ['', Validators.required],
    remember: [false],
  });
  protected submitting = false;
  protected errorMessage = '';

  protected submit(): void {
    this.errorMessage = '';
    if (this.loginForm.invalid) {
      this.loginForm.markAllAsTouched();
      this.errorMessage = 'Ingresa un correo y una contraseña válidos.';
      return;
    }
    this.submitting = true;
    const { correo, password, remember } = this.loginForm.getRawValue();
    this.http.post('/api/auth/Login.php', { correo, password, remember }, { withCredentials: true }).subscribe({
      next: () => { this.submitting = false; this.auth.refresh().subscribe(() => this.router.navigate(['/inicio'])); },
      error: (error) => {
        this.submitting = false;
        this.errorMessage = error.error?.message ?? 'No fue posible iniciar sesión.';
      },
    });
  }
}
