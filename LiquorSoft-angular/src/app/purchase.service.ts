import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { CartItem } from './cart.service';
import { PaymentMethod } from './payment.service';

export interface PurchaseResponse {
  success: boolean;
  saleId: number;
  orderNumber?: string;
  total: number;
  paymentMethod: PaymentMethod;
  message: string;
}

@Injectable({ providedIn: 'root' })
export class PurchaseService {
  private readonly http = inject(HttpClient);

  checkout(items: CartItem[], paymentMethod: PaymentMethod, notes = ''): Observable<PurchaseResponse> {
    return this.http.post<PurchaseResponse>('/api/purchase.php', {
      paymentMethod,
      notes,
      items: items.map(item => ({ id: item.id, quantity: item.quantity })),
    });
  }
}
