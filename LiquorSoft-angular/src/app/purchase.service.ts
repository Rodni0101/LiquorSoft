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
  paymentStatus: 'APROBADO' | 'RECHAZADO' | 'PENDIENTE' | 'CANCELADO' | 'ERROR';
  orderStatus: string;
  invoiceAvailable: boolean;
  message: string;
}

@Injectable({ providedIn: 'root' })
export class PurchaseService {
  private readonly http = inject(HttpClient);

  checkout(items: CartItem[], paymentMethod: PaymentMethod, simulationOutcome: string, idempotencyKey: string, deliveryAddress: string, birthDate: string, notes = ''): Observable<PurchaseResponse> {
    return this.http.post<PurchaseResponse>('/api/purchase.php', {
      paymentMethod,
      simulationOutcome,
      idempotencyKey,
      deliveryAddress,
      birthDate,
      notes,
      items: items.map(item => ({ id: item.id, quantity: item.quantity })),
    });
  }
}
