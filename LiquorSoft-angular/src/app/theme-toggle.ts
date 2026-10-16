import { Component, inject } from '@angular/core';
import { ThemeService } from './theme.service';

@Component({
  selector: 'app-theme-toggle',
  standalone: true,
  template: `<button class="theme-toggle" type="button" (click)="theme.toggle()" [attr.aria-label]="theme.darkMode() ? 'Activar modo claro' : 'Activar modo oscuro'" [attr.title]="theme.darkMode() ? 'Modo claro' : 'Modo oscuro'"><span aria-hidden="true">{{ theme.darkMode() ? '☀' : '☾' }}</span><span class="theme-label">{{ theme.darkMode() ? 'Claro' : 'Oscuro' }}</span></button>`,
  styles: [`:host { display:block; } .theme-toggle { display:inline-flex; align-items:center; gap:.45rem; min-height:38px; padding:.5rem .75rem; border:1px solid var(--theme-button-border, #334155); border-radius:9px; color:var(--theme-button-text, #e2e8f0); background:var(--theme-button-bg, #172238); font:inherit; font-size:.78rem; cursor:pointer; } .theme-toggle:hover { border-color:#fbbf24; } .theme-toggle:focus-visible { outline:3px solid #fbbf24; outline-offset:3px; } @media (max-width:460px) { .theme-label { display:none; } }`],
})
export class ThemeToggle {
  protected readonly theme = inject(ThemeService);
}
