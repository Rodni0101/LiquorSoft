import { Component, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
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
  private readonly route = inject(ActivatedRoute);
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
      next: () => {
        this.auth.refresh().subscribe({
          next: user => {
            this.submitting = false;
            if (!user) {
              this.errorMessage = 'No fue posible confirmar tu sesión. Intenta iniciar sesión nuevamente.';
              return;
            }
            const returnUrl = this.route.snapshot.queryParamMap.get('returnUrl');
            if (returnUrl?.startsWith('/') && !returnUrl.startsWith('//')) {
              void this.router.navigateByUrl(returnUrl);
              return;
            }
            void this.router.navigateByUrl(this.auth.homeUrl());
          },
          error: () => {
            this.submitting = false;
            this.errorMessage = 'No fue posible confirmar tu sesión. Intenta iniciar sesión nuevamente.';
          },
        });
      },
      error: (error) => {
        this.submitting = false;
        this.errorMessage = error.error?.message ?? 'No fue posible iniciar sesión.';
      },
    });
  }
}
