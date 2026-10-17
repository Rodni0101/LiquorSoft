import { TestBed } from '@angular/core/testing';
import { Router, RouterStateSnapshot, UrlTree, provideRouter } from '@angular/router';
import { firstValueFrom, Observable, of } from 'rxjs';
import { authGuard } from './auth.guard';
import { AuthService } from './auth.service';

describe('authGuard', () => {
  it('preserves the checkout URL when redirecting an unauthenticated user to login', async () => {
    await TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        { provide: AuthService, useValue: { refresh: () => of(null) } },
      ],
    }).compileComponents();

    const result = TestBed.runInInjectionContext(() =>
      authGuard({} as never, { url: '/checkout' } as RouterStateSnapshot),
    );
    const destination = await firstValueFrom(result as Observable<boolean | UrlTree>);

    expect(destination).toBeInstanceOf(UrlTree);
    expect(TestBed.inject(Router).serializeUrl(destination as UrlTree)).toBe('/login?returnUrl=%2Fcheckout');
  });
});
