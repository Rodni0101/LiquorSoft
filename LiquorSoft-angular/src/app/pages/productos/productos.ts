
import {
  ChangeDetectorRef,
  Component,
  inject,
  OnInit
} from '@angular/core';

import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AdminShell } from '../../admin-shell';

interface Product {
  id: number;
  name: string;
  category: string;
  categoryId?: number | null;
  description: string;
  sku?: string | null;
  brand?: string | null;
  presentation?: string | null;
  alcoholicGraduation?: number | null;
  purchasePrice?: number;
  price: number;
  stock: number;
  minStock?: number;
  image?: string | null;
  featured: boolean;
  active?: boolean;
}

interface Category {
  id: number;
  name: string;
}

interface ProductForm {
  name: string;
  sku: string;
  brand: string;
  presentation: string;
  description: string;
  categoryId: number | null;
  alcoholicGraduation: number | null;
  purchasePrice: number;
  price: number;
  stock: number;
  minStock: number;
  image: string;
  featured: boolean;
}

@Component({
  selector: 'app-productos',
  standalone: true,
  imports: [RouterLink, AdminShell, FormsModule],
  styleUrl: './productos.css',
  templateUrl: './productos.html'
})
export class Productos implements OnInit {
  private readonly http = inject(HttpClient);
  private readonly changeDetector = inject(ChangeDetectorRef);

  protected products: Product[] = [];
  protected categories: Category[] = [];

  protected loading = true;
  protected saving = false;
  protected showForm = false;
  protected editingId: number | null = null;
  protected errorMessage = '';
  protected successMessage = '';

  protected form: ProductForm = this.emptyForm();

  ngOnInit(): void {
    this.loadCategories();
    this.loadProducts();
  }

  private emptyForm(): ProductForm {
    return {
      name: '',
      sku: '',
      brand: '',
      presentation: '',
      description: '',
      categoryId: null,
      alcoholicGraduation: null,
      purchasePrice: 0,
      price: 0,
      stock: 0,
      minStock: 5,
      image: '',
      featured: false
    };
  }

  protected loadCategories(): void {
    this.http
      .get<{ categories: Category[] }>('/api/categories.php')
      .subscribe({
        next: (response) => {
          this.categories = response.categories ?? [];
          this.changeDetector.markForCheck();
        },
        error: (error) => {
          console.error('Error al cargar categorías:', error);
          this.errorMessage = 'No fue posible cargar las categorías.';
          this.changeDetector.markForCheck();
        }
      });
  }

  protected loadProducts(): void {
    this.loading = true;

    this.http
      .get<{ products: Product[] }>('/api/admin/products.php')
      .subscribe({
        next: (response) => {
          this.products = response.products ?? [];
          this.loading = false;
          this.changeDetector.markForCheck();
        },
        error: (error) => {
          console.error('Error al cargar productos:', error);
          this.loading = false;
          this.errorMessage = 'No fue posible cargar los productos.';
          this.changeDetector.markForCheck();
        }
      });
  }

  protected openForm(): void {
    this.form = this.emptyForm();
    this.editingId = null;
    this.errorMessage = '';
    this.successMessage = '';
    this.showForm = true;
  }

  protected editProduct(product: Product): void {
    this.form = {
      name: product.name ?? '',
      sku: product.sku ?? '',
      brand: product.brand ?? '',
      presentation: product.presentation ?? '',
      description: product.description ?? '',
      categoryId: product.categoryId ?? null,
      alcoholicGraduation: product.alcoholicGraduation ?? null,
      purchasePrice: product.purchasePrice ?? 0,
      price: product.price ?? 0,
      stock: product.stock ?? 0,
      minStock: product.minStock ?? 5,
      image: product.image ?? '',
      featured: Boolean(product.featured)
    };

    this.editingId = product.id;
    this.errorMessage = '';
    this.successMessage = '';
    this.showForm = true;
  }

  protected cancelForm(): void {
    this.showForm = false;
    this.editingId = null;
    this.form = this.emptyForm();
    this.errorMessage = '';
  }

  protected saveProduct(): void {
    this.errorMessage = '';
    this.successMessage = '';

    if (!this.form.name.trim()) {
      this.errorMessage = 'Escribe el nombre del producto.';
      return;
    }

    if (!this.form.categoryId) {
      this.errorMessage = 'Selecciona una categoría.';
      return;
    }

    if (
      !Number.isFinite(this.form.price) ||
      !Number.isFinite(this.form.purchasePrice) ||
      this.form.price < 0 ||
      this.form.purchasePrice < 0
    ) {
      this.errorMessage = 'Los precios deben ser válidos y no negativos.';
      return;
    }

    if (
      !Number.isInteger(this.form.stock) ||
      !Number.isInteger(this.form.minStock) ||
      this.form.stock < 0 ||
      this.form.minStock < 0
    ) {
      this.errorMessage = 'El stock debe ser un entero no negativo.';
      return;
    }

    if (
      this.form.alcoholicGraduation !== null &&
      (
        !Number.isFinite(this.form.alcoholicGraduation) ||
        this.form.alcoholicGraduation < 0 ||
        this.form.alcoholicGraduation > 100
      )
    ) {
      this.errorMessage = 'La graduación debe estar entre 0 y 100 %.';
      return;
    }

    const payload = {
      name: this.form.name.trim(),
      sku: this.form.sku.trim() || null,
      brand: this.form.brand.trim() || null,
      presentation: this.form.presentation.trim() || null,
      description: this.form.description.trim(),
      categoryId: this.form.categoryId,
      alcoholicGraduation: this.form.alcoholicGraduation,
      purchasePrice: this.form.purchasePrice,
      price: this.form.price,
      stock: this.form.stock,
      minStock: this.form.minStock,
      image: this.form.image.trim() || null,
      featured: this.form.featured,
      active: true
    };

    this.saving = true;

    const request = this.editingId !== null
      ? this.http.put<{ message?: string }>(
          '/api/admin/products.php?id=' + this.editingId,
          payload
        )
      : this.http.post<{ message?: string }>(
          '/api/admin/products.php',
          payload
        );

    request.subscribe({
      next: (response) => {
        this.saving = false;
        this.showForm = false;
        this.editingId = null;
        this.form = this.emptyForm();
        this.successMessage =
          response.message ||
          'Producto guardado correctamente.';
        this.loadProducts();
        this.changeDetector.markForCheck();
      },
      error: (error) => {
        console.error('Error al guardar producto:', error);
        this.saving = false;
        this.errorMessage =
          error.error?.message ||
          'No fue posible guardar el producto. Verifica el backend PHP.';
        this.changeDetector.markForCheck();
      }
    });
  }

  protected deleteProduct(product: Product): void {
    const confirmed = window.confirm(
      `¿Seguro que deseas eliminar "${product.name}"?`
    );

    if (!confirmed) {
      return;
    }

    this.errorMessage = '';
    this.successMessage = '';
    this.saving = true;

    this.http
      .delete<{ message?: string }>(
        '/api/admin/products.php?id=' + product.id
      )
      .subscribe({
        next: (response) => {
          this.saving = false;
          this.successMessage =
            response.message || 'Producto eliminado correctamente.';
          this.loadProducts();
          this.changeDetector.markForCheck();
        },
        error: (error) => {
          console.error('Error al eliminar producto:', error);
          this.saving = false;
          this.errorMessage =
            error.error?.message ||
            'No fue posible eliminar el producto. Revisa el backend PHP.';
          this.changeDetector.markForCheck();
        }
      });
  }

  protected formatPrice(value: number): string {
    return new Intl.NumberFormat('es-CO', {
      style: 'currency',
      currency: 'COP',
      maximumFractionDigits: 0
    }).format(value);
  }
}
