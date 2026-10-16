
import { Injectable, signal } from '@angular/core';

export interface CartItem {
  id?: number;
  name: string;
  price: number;
  icon: string;
  image?: string | null;
  category?: string;
  stock?: number;
  quantity: number;
}

@Injectable({ providedIn: 'root' })
export class CartService {
  private readonly storageKey = 'liquorsoft-cart';

  readonly items = signal<CartItem[]>(this.read());
  readonly count = () =>
    this.items().reduce((total, item) => total + item.quantity, 0);

  readonly total = () =>
    this.items().reduce(
      (total, item) => total + item.price * item.quantity,
      0
    );

  add(product: Omit<CartItem, 'quantity'>): boolean {
    if (!Number.isFinite(product.price) || product.price < 0) {
      return false;
    }

    const items = this.items().map(item => ({ ...item }));
    const key = product.id ?? product.name;

    const index = items.findIndex(
      item => (item.id ?? item.name) === key
    );

    if (index >= 0) {
      const current = items[index];
      const stock = product.stock ?? current.stock;

      if (stock !== undefined && current.quantity >= stock) {
        return false;
      }

      items[index] = {
        ...current,
        stock,
        image: product.image ?? current.image,
        category: product.category ?? current.category,
        quantity: current.quantity + 1
      };
    } else {
      if (product.stock !== undefined && product.stock < 1) {
        return false;
      }

      items.push({
        ...product,
        quantity: 1
      });
    }

    this.persist(items);
    return true;
  }

  decrease(product: CartItem): void {
    const key = product.id ?? product.name;

    const items = this.items()
      .map(item =>
        (item.id ?? item.name) === key
          ? { ...item, quantity: item.quantity - 1 }
          : { ...item }
      )
      .filter(item => item.quantity > 0);

    this.persist(items);
  }

  remove(product: CartItem): void {
    const key = product.id ?? product.name;

    this.persist(
      this.items().filter(
        item => (item.id ?? item.name) !== key
      )
    );
  }

  clear(): void {
    this.persist([]);
  }

  private persist(items: CartItem[]): void {
    this.items.set(items);

    try {
      localStorage.setItem(this.storageKey, JSON.stringify(items));
    } catch {
      console.warn(
        'No se pudo guardar el carrito en el almacenamiento local.'
      );
    }
  }

  private read(): CartItem[] {
    try {
      const stored = localStorage.getItem(this.storageKey);

      if (!stored) return [];

      const parsed: unknown = JSON.parse(stored);

      if (!Array.isArray(parsed)) return [];

      return parsed.filter(
        (item): item is CartItem =>
          item !== null &&
          typeof item === 'object' &&
          typeof item.name === 'string' &&
          typeof item.price === 'number' &&
          Number.isFinite(item.price) &&
          item.price >= 0 &&
          typeof item.quantity === 'number' &&
          Number.isInteger(item.quantity) &&
          item.quantity > 0 &&
          typeof item.icon === 'string'
      );
    } catch {
      return [];
    }
  }
}
