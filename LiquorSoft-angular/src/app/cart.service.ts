import { Injectable, signal } from '@angular/core';

export interface CartItem {
  id?: number;
  name: string;
  price: number;
  icon: string;
  stock?: number;
  quantity: number;
}

@Injectable({ providedIn: 'root' })
export class CartService {
  readonly items = signal<CartItem[]>(this.read());
  readonly count = () => this.items().reduce((total, item) => total + item.quantity, 0);
  add(product: Omit<CartItem, 'quantity'>): void {
    const items = [...this.items()];
    const current = items.find(item => (item.id ?? item.name) === (product.id ?? product.name));
    if (current) {
      const availableStock = product.stock ?? current.stock;
      if (availableStock !== undefined && current.quantity >= availableStock) return;
      current.quantity += 1;
      if (product.stock !== undefined) current.stock = product.stock;
    } else {
      if (product.stock !== undefined && product.stock < 1) return;
      items.push({ ...product, quantity: 1 });
    }
    this.items.set(items); localStorage.setItem('liquorsoft-cart', JSON.stringify(items));
  }
  remove(product: CartItem): void { this.persist(this.items().filter(item => (item.id ?? item.name) !== (product.id ?? product.name))); }
  clear(): void { this.persist([]); }
  decrease(product: CartItem): void {
    const items = this.items().map(item => item === product ? { ...item, quantity: item.quantity - 1 } : item).filter(item => item.quantity > 0);
    this.persist(items);
  }
  total(): number { return this.items().reduce((total, item) => total + item.price * item.quantity, 0); }
  private persist(items: CartItem[]): void { this.items.set(items); localStorage.setItem('liquorsoft-cart', JSON.stringify(items)); }
  private read(): CartItem[] { try { return JSON.parse(localStorage.getItem('liquorsoft-cart') ?? '[]') as CartItem[]; } catch { return []; } }
}
