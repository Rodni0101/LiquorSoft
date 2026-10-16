import { Injectable, signal } from '@angular/core';

export type NoticeKind = 'success' | 'error' | 'warning' | 'info';
export interface Notice { id: number; kind: NoticeKind; message: string; }
export interface Confirmation { title: string; message: string; acceptLabel: string; }

@Injectable({ providedIn: 'root' })
export class NotificationService {
  readonly notices = signal<Notice[]>([]);
  readonly confirmation = signal<Confirmation | null>(null);
  private nextId = 0;
  private resolveConfirmation: ((accepted: boolean) => void) | null = null;

  showSuccess(message: string): void { this.show('success', message); }
  showError(message: string): void { this.show('error', message); }
  showWarning(message: string): void { this.show('warning', message); }
  showInfo(message: string): void { this.show('info', message); }

  confirm(options: Confirmation): Promise<boolean> {
    if (this.resolveConfirmation) this.resolveConfirmation(false);
    this.confirmation.set(options);
    return new Promise(resolve => { this.resolveConfirmation = resolve; });
  }

  close(id: number): void { this.notices.update(items => items.filter(item => item.id !== id)); }

  answerConfirmation(accepted: boolean): void {
    this.confirmation.set(null);
    const resolve = this.resolveConfirmation;
    this.resolveConfirmation = null;
    resolve?.(accepted);
  }

  private show(kind: NoticeKind, message: string): void {
    const id = ++this.nextId;
    this.notices.update(items => [...items, { id, kind, message }].slice(-4));
    setTimeout(() => this.close(id), kind === 'error' ? 8000 : 5000);
  }
}
