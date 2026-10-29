import { Injectable, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';

interface SiteConfigResponse {
  businessName?: string;
  whatsappNumber?: string | null;
  whatsappMessage?: string;
  supportEmail?: string;
}

@Injectable({ providedIn: 'root' })
export class SiteConfigService {
  private readonly http = inject(HttpClient);
  private readonly config = signal<SiteConfigResponse>({});
  private requested = false;

  constructor() {
    this.load();
  }

  whatsappUrl(): string | null {
    const number = this.config().whatsappNumber?.replace(/\D+/g, '') ?? '';
    if (number.length < 10) return null;
    const message = this.config().whatsappMessage?.trim() || 'Hola LiquorSoft, quiero información sobre sus productos.';
    return `https://wa.me/${number}?text=${encodeURIComponent(message)}`;
  }

  private load(): void {
    if (this.requested) return;
    this.requested = true;
    this.http.get<SiteConfigResponse>('/api/site-config.php').subscribe({
      next: (response) => this.config.set(response ?? {}),
      error: () => this.config.set({}),
    });
  }
}
