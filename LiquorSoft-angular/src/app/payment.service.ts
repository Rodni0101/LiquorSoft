import { Injectable } from '@angular/core';

export type PaymentMethod = 'nequi' | 'paypal';
export type PaymentStatus = 'pending' | 'processing' | 'success' | 'rejected' | 'error';

export interface PaymentRequest {
  method: PaymentMethod;
  amount: number;
  items: Array<{ id?: number; quantity: number }>;
}

export interface PaymentResult {
  status: PaymentStatus;
  message: string;
}

/**
 * Punto único para conectar las pasarelas oficiales cuando exista su backend.
 * Mientras no haya credenciales/SDK configurados, nunca declara un pago exitoso.
 */
@Injectable({ providedIn: 'root' })
export class PaymentService {
  preparePayment(request: PaymentRequest): PaymentResult {
    const provider = request.method === 'nequi' ? 'Nequi' : 'PayPal';

    return {
      status: 'pending',
      message: `Pago con ${provider} pendiente de configuración. No se realizó ningún cobro.`,
    };
  }

  getPendingMessage(method: PaymentMethod): string {
    const provider = method === 'nequi' ? 'Nequi' : 'PayPal';
    return `Pago con ${provider} pendiente de configuración. No se realizó ningún cobro.`;
  }
}
