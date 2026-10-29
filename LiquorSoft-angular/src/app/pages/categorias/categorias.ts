import { ChangeDetectorRef, Component, inject, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { AdminShell } from '../../admin-shell';
import { NotificationService } from '../../notification.service';

interface Category {
  id: number;
  name: string;
  description: string;
  active: boolean;
}

@Component({
  selector: 'app-categorias',
  standalone: true,
  imports: [AdminShell, FormsModule],
  templateUrl: './categorias.html',
  styleUrl: './categorias.css',
})
export class Categorias implements OnInit {
  private readonly http = inject(HttpClient);
  private readonly changeDetector = inject(ChangeDetectorRef);
  private readonly notifications = inject(NotificationService);

  protected categories: Category[] = [];
  protected name = '';
  protected description = '';
  protected editingId: number | null = null;
  protected editingCategoryActive = true;
  protected loading = true;
  protected saving = false;
  protected message = '';
  protected errorMessage = '';

  ngOnInit(): void {
    this.load();
  }

  protected load(): void {
    this.loading = true;
    this.http.get<{ categories: Category[] }>('/api/categories.php?all=1', { withCredentials: true }).subscribe({
      next: (response) => {
        this.categories = response.categories ?? [];
        this.loading = false;
        this.changeDetector.markForCheck();
      },
      error: (error) => {
        this.errorMessage = error.error?.message ?? 'No fue posible cargar las categorías.';
        this.loading = false;
        this.changeDetector.markForCheck();
      },
    });
  }

  protected save(): void {
    this.message = '';
    this.errorMessage = '';
    if (this.name.trim().length < 2) {
      this.errorMessage = 'El nombre es obligatorio (mínimo 2 caracteres).';
      return;
    }

    this.saving = true;
    const payload = {
      name: this.name.trim(),
      description: this.description.trim(),
      active: this.editingId === null ? true : undefined,
    };

    const request = this.editingId === null
      ? this.http.post<{ message?: string }>('/api/categories.php', payload, { withCredentials: true })
      : this.http.patch<{ message?: string }>('/api/categories.php', {
          id: this.editingId,
          name: this.name.trim(),
          description: this.description.trim(),
          active: this.editingCategoryActive,
        }, { withCredentials: true });

    request.subscribe({
      next: (response) => {
        this.saving = false;
        this.message = response.message ?? 'Categoría guardada correctamente.';
        this.cancelEdit();
        this.load();
        this.changeDetector.markForCheck();
      },
      error: (error) => {
        this.saving = false;
        this.errorMessage = error.error?.message ?? 'No fue posible guardar la categoría.';
        this.changeDetector.markForCheck();
      },
    });
  }

  protected edit(category: Category): void {
    this.editingId = category.id;
    this.editingCategoryActive = category.active;
    this.name = category.name;
    this.description = category.description;
    this.message = '';
    this.errorMessage = '';
  }

  protected cancelEdit(): void {
    this.editingId = null;
    this.editingCategoryActive = true;
    this.name = '';
    this.description = '';
  }

  protected toggle(category: Category): void {
    this.errorMessage = '';
    this.http.patch<{ message?: string }>('/api/categories.php', {
      id: category.id,
      name: category.name,
      description: category.description,
      active: !category.active,
    }, { withCredentials: true }).subscribe({
      next: () => {
        category.active = !category.active;
        this.message = `Categoría ${category.active ? 'activada' : 'desactivada'}.`;
        this.changeDetector.markForCheck();
      },
      error: (error) => {
        this.errorMessage = error.error?.message ?? 'No fue posible cambiar el estado de la categoría.';
        this.changeDetector.markForCheck();
      },
    });
  }

  protected async deactivate(category: Category): Promise<void> {
    const confirmed = await this.notifications.confirm({
      title: 'Desactivar categoría',
      message: `¿Desactivar "${category.name}"? Dejará de aparecer en el catálogo público, pero los productos existentes se conservan.`,
      acceptLabel: 'Sí, desactivar',
    });
    if (!confirmed) return;

    this.http.delete<{ message?: string }>('/api/categories.php', {
      body: { id: category.id },
      withCredentials: true,
    }).subscribe({
      next: (response) => {
        this.message = response.message ?? 'Categoría desactivada.';
        this.load();
        this.changeDetector.markForCheck();
      },
      error: (error) => {
        this.errorMessage = error.error?.message ?? 'No fue posible desactivar la categoría.';
        this.changeDetector.markForCheck();
      },
    });
  }

  protected get activeCount(): number {
    return this.categories.filter(category => category.active).length;
  }
}
