import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { map } from 'rxjs/operators';
import { AuthService } from './auth.service';

export const permissionGuard: CanActivateFn = (route) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const permission = String(route.data['permission'] ?? '');
  return auth.refresh().pipe(map((user) => user && auth.can(permission) ? true : router.createUrlTree([user ? '/inicio' : '/login'])));
};
