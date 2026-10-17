import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { CartItem } from './cart.service';
import { PaymentMethod } from './payment.service';

export interface PurchaseResponse {
  success: boolean;
  saleId: number | null;
  orderId: number;
  orderNumber?: string;
  total: number;
  paymentMethod: PaymentMethod;
  paymentStatus: 'APROBADO' | 'CAPTURADO' | 'RECHAZADO' | 'PENDIENTE' | 'CANCELADO' | 'ERROR';
  orderStatus: string;
  invoiceAvailable: boolean;
  message: string;
}

export interface PayPalOrderResponse {
  orderId: string;
  currency: 'USD';
  amount: string;
}

@Injectable({ providedIn: 'root' })
export class PurchaseService {
  private readonly http = inject(HttpClient);

  createPayPalOrder(items: CartItem[], idempotencyKey: string, deliveryAddress: string, birthDate: string, notes = ''): Observable<PayPalOrderResponse> {
    return this.http.post<PayPalOrderResponse>('/api/paypal/create-order.php', {
      idempotencyKey,
      deliveryAddress,
      birthDate,
      notes,
      items: items.map(item => ({ id: item.id, quantity: item.quantity })),
    });
  }

  checkout(items: CartItem[], paymentMethod: PaymentMethod, simulationOutcome: string, idempotencyKey: string, deliveryAddress: string, birthDate: string, notes = '', paypalOrderId = ''): Observable<PurchaseResponse> {
    return this.http.post<PurchaseResponse>('/api/purchase.php', {
      paymentMethod,
      simulationOutcome,
      paypalOrderId,
      idempotencyKey,
      deliveryAddress,
      birthDate,
      notes,
      items: items.map(item => ({ id: item.id, quantity: item.quantity })),
    });
  }
}
