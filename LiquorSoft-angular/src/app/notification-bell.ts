import { Component, inject, ViewChild, ElementRef, AfterViewInit, OnDestroy, effect } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterLink } from '@angular/router';
import { UserNotificationsService, UserNotification } from './user-notifications.service';
import { AuthService } from './auth.service';

@Component({
  selector: 'app-notification-bell',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="bell-container" (keydown.escape)="service.closePanel()">
      <button
        type="button"
        #bellBtn
        class="bell-button"
        [attr.aria-expanded]="service.isOpen()"
        [attr.aria-label]="service.unreadCount() > 0 ? 'Notificaciones: ' + service.unreadCount() + ' sin leer' : 'Notificaciones'"
        (click)="service.togglePanel()"
      >
        <svg class="bell-icon" viewBox="0 0 16 16" aria-hidden="true" fill="currentColor">
          <path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2zM8 1.918l-.797.161A4.002 4.002 0 0 0 4 6c0 .628-.134 2.197-.459 3.742-.16.767-.376 1.566-.663 2.258h10.244c-.287-.692-.502-1.49-.663-2.258C12.134 8.197 12 6.628 12 6a4.002 4.002 0 0 0-3.203-3.92L8 1.917zM14.22 12c.223.447.481.801.78 1H1c.299-.199.557-.553.78-1C2.68 10.2 3 6.88 3 6c0-2.42 1.72-4.44 4.005-4.901a1 1 0 1 1 1.99 0A5.002 5.002 0 0 1 13 6c0 .88.32 4.2 1.22 6z"/>
        </svg>

        @if (service.unreadCount() > 0) {
          <span class="badge" aria-hidden="true">
            {{ service.unreadCount() > 9 ? '9+' : service.unreadCount() }}
          </span>
        }
      </button>

      @if (service.isOpen()) {
        <!-- Backdrop para cerrar al hacer clic afuera -->
        <div class="bell-backdrop" (click)="service.closePanel()" role="presentation"></div>

        <section
          class="dropdown-panel"
          [class.position-left]="panelPosition === 'left'"
          [class.position-right]="panelPosition === 'right'"
          [style.top.px]="panelTop"
          role="region"
          aria-label="Panel de notificaciones"
        >
          <header class="dropdown-header">
            <div class="header-title">
              <h3>Notificaciones</h3>
              @if (service.unreadCount() > 0) {
                <span class="unread-pill">{{ service.unreadCount() }} nueva{{ service.unreadCount() === 1 ? '' : 's' }}</span>
              }
            </div>
            <div class="header-actions">
              @if (service.unreadCount() > 0) {
                <button type="button" class="action-btn" (click)="service.markAllAsRead()">
                  Marcar todas leídas
                </button>
              }
              <button type="button" class="close-btn" aria-label="Cerrar panel" (click)="service.closePanel()">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
              </button>
            </div>
          </header>

          <div class="dropdown-body">
            @if (service.loading() && service.notifications().length === 0) {
              <div class="panel-state">
                <span class="spinner" aria-hidden="true"></span>
                <p>Cargando avisos…</p>
              </div>
            } @else if (service.notifications().length === 0) {
              <div class="panel-state empty-state">
                <span class="empty-icon" aria-hidden="true"><i class="bi bi-bell"></i></span>
                <h4>Sin notificaciones</h4>
                <p>Te avisaremos cuando haya novedades sobre tus pedidos o compras.</p>
              </div>
            } @else {
              <ul class="notification-list" role="list">
                @for (item of service.notifications(); track item.id) {
                  <li
                    class="notification-item"
                    [class.unread]="!item.read"
                    (click)="handleItemClick(item)"
                  >
                    <div class="item-icon-wrapper" aria-hidden="true">
                      <i class="type-icon {{ getIcon(item.type) }}"></i>
                    </div>

                    <div class="item-content">
                      <div class="item-top">
                        <strong class="item-title">{{ item.title }}</strong>
                        @if (!item.read) {
                          <span class="unread-dot" title="No leída" aria-hidden="true"></span>
                        }
                      </div>

                      <p class="item-message">{{ item.message }}</p>

                      <div class="item-footer">
                        <time class="item-time">{{ formatTime(item.createdAt) }}</time>

                        @if (item.orderId || item.orderNumber) {
                          <span class="order-link-hint">
                            Ver detalle →
                          </span>
                        }

                        @if (!item.read) {
                          <button type="button" class="item-mark-btn"
                            (click)="$event.stopPropagation(); service.markAsRead(item.id)"
                            title="Marcar como leída"
                          >
                            <i class="bi bi-check-lg"></i> Leída
                          </button>
                        }
                      </div>
                    </div>
                  </li>
                }
              </ul>
            }
          </div>

          <footer class="dropdown-footer">
            <a
              [routerLink]="auth.can('ventas') ? '/admin/ventas' : auth.can('dashboard') ? '/admin/dashboard' : '/pedidos'"
              (click)="service.closePanel()"
              class="footer-link"
            >
              {{ auth.can('ventas') ? 'Ver panel de pedidos' : auth.can('dashboard') ? 'Ver resumen' : 'Ver todos mis pedidos' }} →
            </a>
          </footer>
        </section>
      }
    </div>
  `,
  styleUrl: './notification-bell.css'
})
export class NotificationBell implements AfterViewInit, OnDestroy {
  protected readonly service = inject(UserNotificationsService);
  protected readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  @ViewChild('bellBtn') bellBtn!: ElementRef<HTMLButtonElement>;
  protected panelPosition: 'left' | 'right' = 'right';
  protected panelTop = 0;
  private readonly resizeHandler = () => this.updatePanelPosition();

  constructor() {
    effect(() => {
      if (this.service.isOpen()) {
        this.updatePanelPosition();
      }
    });
  }

  ngAfterViewInit(): void {
    // Calculamos la posición inicial del panel
    this.updatePanelPosition();
    // Recalculamos si cambia el tamaño de la ventana
    window.addEventListener('resize', this.resizeHandler);
  }

  ngOnDestroy(): void {
    window.removeEventListener('resize', this.resizeHandler);
  }

  private updatePanelPosition(): void {
    if (!this.bellBtn) return;
    const btnRect = this.bellBtn.nativeElement.getBoundingClientRect();
    const panelWidth = 420; // ancho máximo del panel
    const spaceRight = window.innerWidth - btnRect.right - 14;
    const spaceLeft = btnRect.left - 14;
    this.panelPosition = spaceRight >= panelWidth ? 'right' : 'left';
    // Posicionar el panel justo debajo del botón de la campana
    this.panelTop = btnRect.bottom + 10;
  }

  protected getIcon(type: string): string {
    switch (type) {
      case 'COMPRA_REGISTRADA':
        return 'bi bi-bag';
      case 'ESTADO_PEDIDO':
        return 'bi bi-truck';
      case 'NUEVA_COMPRA_ADMIN':
        return 'bi bi-bell';
      default:
        return 'bi bi-chat';
    }
  }

  protected formatTime(timestamp: string): string {
    if (!timestamp) return '';
    try {
      const date = new Date(timestamp.replace(' ', 'T'));
      const now = new Date();
      const diffMs = now.getTime() - date.getTime();
      const diffMins = Math.floor(diffMs / 60000);

      if (diffMins < 1) return 'Hace un momento';
      if (diffMins < 60) return `Hace ${diffMins} min`;
      const diffHours = Math.floor(diffMins / 60);
      if (diffHours < 24) return `Hace ${diffHours} h`;
      const diffDays = Math.floor(diffHours / 24);
      if (diffDays === 1) return 'Ayer';
      if (diffDays < 7) return `Hace ${diffDays} días`;

      return new Intl.DateTimeFormat('es-CO', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
      }).format(date);
    } catch {
      return timestamp;
    }
  }

  protected handleItemClick(item: UserNotification): void {
    if (!item.read) {
      this.service.markAsRead(item.id);
    }
    this.service.closePanel();

    if (this.auth.can('ventas')) {
      void this.router.navigate(['/admin/ventas']);
    } else if (this.auth.can('dashboard')) {
      void this.router.navigate(['/admin/dashboard']);
    } else {
      void this.router.navigate(['/pedidos']);
    }
  }
}

