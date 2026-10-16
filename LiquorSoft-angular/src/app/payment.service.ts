import { Injectable } from '@angular/core';

export type PaymentMethod = 'nequi_simulado' | 'paypal';
export type PaymentStatus = 'pending' | 'processing' | 'success' | 'rejected' | 'error' | 'cancelled';

/**
 * Métodos disponibles en el checkout. El resultado real siempre lo determina el backend.
 */
@Injectable({ providedIn: 'root' })
export class PaymentService {
  getPendingMessage(method: PaymentMethod): string {
    return method === 'nequi_simulado'
      ? 'Nequi simulado solo para desarrollo. No mueve dinero real.'
      : 'PayPal Sandbox requiere credenciales de prueba y aún no está habilitado.';
  }
}
