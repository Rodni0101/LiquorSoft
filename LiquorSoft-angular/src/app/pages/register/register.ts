import { Component, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ReactiveFormsModule, Validators, FormBuilder } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';

@Component({
  imports: [ReactiveFormsModule, RouterLink],
  selector: 'app-register',
  styleUrl: './register.css',
  templateUrl: './register.html',
})
export class Register {
  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);
  private readonly formBuilder = inject(FormBuilder);

  protected readonly registerForm = this.formBuilder.nonNullable.group({
    nombre: ['', [Validators.required, Validators.minLength(2), Validators.maxLength(100)]],
    apellido: ['', [Validators.required, Validators.minLength(2), Validators.maxLength(100)]],
    correo: ['', [Validators.required, Validators.email, Validators.maxLength(150)]],
    password: ['', [Validators.required, Validators.minLength(8), Validators.maxLength(72)]],
    confirmPassword: ['', Validators.required],
    terms: [false, Validators.requiredTrue],
  });

  protected submitting = false;
  protected errorMessage = '';
  protected successMessage = '';

  protected get passwordsMatch(): boolean {
    const { password, confirmPassword } = this.registerForm.getRawValue();
    return password === confirmPassword;
  }

  protected submit(): void {
    this.errorMessage = '';
    this.successMessage = '';

    if (this.registerForm.invalid || !this.passwordsMatch) {
      this.registerForm.markAllAsTouched();
      this.errorMessage = this.passwordsMatch
        ? 'Revisa los campos marcados e intenta de nuevo.'
        : 'Las contraseñas no coinciden.';
      return;
    }

    this.submitting = true;
    const { nombre, apellido, correo, password } = this.registerForm.getRawValue();

    this.http.post<{ message?: string }>('/api/auth/Registro/Register.php', {
      nombre,
      apellido,
      correo,
      password,
    }).subscribe({
      next: (response) => {
        this.submitting = false;
        this.successMessage = response.message ?? 'Cuenta creada correctamente.';
        this.registerForm.reset({ nombre: '', apellido: '', correo: '', password: '', confirmPassword: '', terms: false });
        setTimeout(() => this.router.navigate(['/inicio']), 1200);
      },
      error: (error) => {
        this.submitting = false;
        this.errorMessage = error.error?.message ?? 'No fue posible crear la cuenta. Intenta de nuevo.';
      },
    });
  }

  protected fieldInvalid(field: 'nombre' | 'apellido' | 'correo' | 'password' | 'confirmPassword' | 'terms'): boolean {
    const control = this.registerForm.controls[field];
    return control.invalid && (control.dirty || control.touched);
  }
}
