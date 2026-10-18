import { inject, Injectable, signal, effect, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { AuthService } from './auth.service';

export interface UserNotification {
  id: number;
  type: string;
  title: string;
  message: string;
  orderId: number | null;
  orderNumber: string | null;
  read: boolean;
  createdAt: string;
  readAt: string | null;
}

interface NotificationsResponse {
  notifications: UserNotification[];
  unreadCount: number;
}

@Injectable({ providedIn: 'root' })
export class UserNotificationsService implements OnDestroy {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AuthService);

  readonly notifications = signal<UserNotification[]>([]);
  readonly unreadCount = signal<number>(0);
  readonly loading = signal<boolean>(false);
  readonly isOpen = signal<boolean>(false);

  private pollTimer: ReturnType<typeof setInterval> | null = null;

  constructor() {
    // Escucha cambios en el usuario autenticado
    effect(() => {
      const user = this.auth.user();
      if (user) {
        this.fetch();
        this.startPolling();
      } else {
        this.stopPolling();
        this.notifications.set([]);
        this.unreadCount.set(0);
        this.isOpen.set(false);
      }
    });
  }

  ngOnDestroy(): void {
    this.stopPolling();
  }

  togglePanel(): void {
    this.isOpen.update(open => !open);
    if (this.isOpen() && this.auth.user()) {
      this.fetch();
    }
  }

  closePanel(): void {
    this.isOpen.set(false);
  }

  fetch(): void {
    if (!this.auth.user()) return;
    this.loading.set(true);
    this.http.get<NotificationsResponse>('/api/notifications.php', { withCredentials: true }).subscribe({
      next: (response) => {
        this.notifications.set(response.notifications ?? []);
        this.unreadCount.set(response.unreadCount ?? 0);
        this.loading.set(false);
      },
      error: () => {
        this.loading.set(false);
      },
    });
  }

  markAsRead(id: number): void {
    // Actualización optimista local
    this.notifications.update(items =>
      items.map(item => item.id === id ? { ...item, read: true, readAt: new Date().toISOString() } : item)
    );
    this.unreadCount.update(count => Math.max(0, count - 1));

    this.http.patch<{ unreadCount: number }>('/api/notifications.php', { id }, { withCredentials: true }).subscribe({
      next: (res) => {
        if (typeof res.unreadCount === 'number') {
          this.unreadCount.set(res.unreadCount);
        }
      },
      error: () => {
        // En caso de fallo, recargar estado real
        this.fetch();
      },
    });
  }

  markAllAsRead(): void {
    // Actualización optimista local
    this.notifications.update(items =>
      items.map(item => ({ ...item, read: true, readAt: new Date().toISOString() }))
    );
    this.unreadCount.set(0);

    this.http.patch<{ unreadCount: number }>('/api/notifications.php', { all: true }, { withCredentials: true }).subscribe({
      next: (res) => {
        if (typeof res.unreadCount === 'number') {
          this.unreadCount.set(res.unreadCount);
        }
      },
      error: () => {
        this.fetch();
      },
    });
  }

  private startPolling(): void {
    this.stopPolling();
    // Consulta periódica cada 25 segundos
    this.pollTimer = setInterval(() => {
      if (this.auth.user() && !document.hidden) {
        this.fetch();
      }
    }, 25000);
  }

  private stopPolling(): void {
    if (this.pollTimer) {
      clearInterval(this.pollTimer);
      this.pollTimer = null;
    }
  }
}

