import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';
import { ActivatedRoute, Router, convertToParamMap, provideRouter } from '@angular/router';
import { vi } from 'vitest';
import { of } from 'rxjs';
import { Login } from './login';
import { AuthService } from '../../auth.service';

describe('Login', () => {
  let component: Login;
  let fixture: ComponentFixture<Login>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Login],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { queryParamMap: convertToParamMap({ returnUrl: '/checkout' }) } },
        },
        {
          provide: AuthService,
          useValue: {
            refresh: () => of({ id: 1, name: 'Usuario de prueba', email: 'test@example.invalid', role: 'Cliente', roleId: 2 }),
            isAdmin: () => false,
          },
        },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(Login);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('returns the user to checkout after successful login', async () => {
    const navigateByUrl = vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
    const checkout = fixture.nativeElement as HTMLElement;
    const email = checkout.querySelector<HTMLInputElement>('#email');
    const password = checkout.querySelector<HTMLInputElement>('#password');
    const form = checkout.querySelector('form');
    if (!email || !password || !form) throw new Error('Login form fields are missing.');

    email.value = 'test@example.invalid';
    email.dispatchEvent(new Event('input'));
    password.value = 'test-password';
    password.dispatchEvent(new Event('input'));
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    fixture.detectChanges();

    const request = TestBed.inject(HttpTestingController).expectOne('/api/auth/Login.php');
    expect(request.request.body.correo).toBe('test@example.invalid');
    request.flush({ message: 'Inicio de sesión correcto.' });
    await fixture.whenStable();

    expect(navigateByUrl).toHaveBeenCalledWith('/checkout');
  });

  afterEach(() => {
    TestBed.inject(HttpTestingController).verify();
  });
});
