import { DOCUMENT } from '@angular/common';
import { effect, inject, Injectable, signal } from '@angular/core';

@Injectable({ providedIn: 'root' })
export class ThemeService {
  private readonly document = inject(DOCUMENT);
  readonly darkMode = signal(this.readPreference());

  constructor() {
    effect(() => {
      const theme = this.darkMode() ? 'dark' : 'light';
      this.document.documentElement.dataset['theme'] = theme;
      localStorage.setItem('liquorsoft-theme', theme);
    });
  }

  toggle(): void { this.darkMode.update((dark) => !dark); }

  private readPreference(): boolean {
    return localStorage.getItem('liquorsoft-theme') !== 'light';
  }
}
