export interface CatalogProduct {
  id: number;
  name: string;
  category: string;
  categoryId?: number | null;
  description: string;
  price: number;
  stock: number;
  minStock?: number;
  featured?: boolean;
  active?: boolean;
  icon: string;
}
