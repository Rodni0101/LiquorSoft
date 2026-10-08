import { HttpInterceptorFn } from '@angular/common/http';

function readCookie(name: string): string | null {
  if (typeof document === 'undefined') return null;
  const prefix = `${name}=`;
  const cookie = document.cookie.split('; ').find((value) => value.startsWith(prefix));
  return cookie ? decodeURIComponent(cookie.slice(prefix.length)) : null;
}

export const credentialsInterceptor: HttpInterceptorFn = (req, next) => {
  const csrfToken = readCookie('liquorsoft_csrf');
  let request = req.clone({ withCredentials: true });
  if (csrfToken && !request.headers.has('X-CSRF-Token')) {
    request = request.clone({ setHeaders: { 'X-CSRF-Token': csrfToken } });
  }
  return next(request);
};
