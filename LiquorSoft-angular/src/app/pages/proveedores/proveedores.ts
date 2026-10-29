import { ChangeDetectorRef, Component, inject, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { AdminShell } from '../../admin-shell';
import { NotificationService } from '../../notification.service';

interface Provider {
  id: number;
  name: string;
  identification: string;
  contact: string;
  phone: string;
  email: string;
  address: string;
  active: boolean;
}

interface ProviderForm {
  name: string;
  identification: string;
  contact: string;
  phone: string;
  email: string;
  address: string;
}

@Component({
  selector: 'app-proveedores',
  standalone: true,
  imports: [AdminShell, FormsModule],
  templateUrl: './proveedores.html',
  styleUrl: './proveedores.css',
})
export class Proveedores implements OnInit {
  private readonly http = inject(HttpClient);
  private readonly changeDetector = inject(ChangeDetectorRef);
  private readonly notifications = inject(NotificationService);

  protected providers: Provider[] = [];
  protected loading = true;
  protected saving = false;
  protected errorMessage = '';
  protected successMessage = '';
  protected editingId: number | null = null;
  protected form: ProviderForm = this.emptyForm();

  ngOnInit(): void {
    this.load();
  }

  private emptyForm(): ProviderForm {
    return { name: '', identification: '', contact: '', phone: '', email: '', address: '' };
  }

  protected load(): void {
    this.loading = true;
    this.http.get<{ providers: Provider[] }>('/api/admin/providers.php', { withCredentials: true }).subscribe({
      next: (response) => {
        this.providers = response.providers ?? [];
        this.loading = false;
        this.changeDetector.markForCheck();
      },
      error: (error) => {
        this.errorMessage = error.error?.message ?? 'No fue posible cargar los proveedores.';
        this.loading = false;
        this.changeDetector.markForCheck();
      },
    });
  }

  protected save(): void {
    this.errorMessage = '';
    this.successMessage = '';
    if (!this.form.name.trim()) {
      this.errorMessage = 'El nombre del proveedor es obligatorio.';
      return;
    }
    if (this.form.email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.form.email.trim())) {
      this.errorMessage = 'Ingresa un correo válido.';
      return;
    }

    this.saving = true;
    const payload = {
      name: this.form.name.trim(),
      identification: this.form.identification.trim(),
      contact: this.form.contact.trim(),
      phone: this.form.phone.trim(),
      email: this.form.email.trim(),
      address: this.form.address.trim(),
    };

    const request = this.editingId === null
      ? this.http.post<{ message?: string }>('/api/admin/providers.php', payload, { withCredentials: true })
      : this.http.patch<{ message?: string }>('/api/admin/providers.php', { id: this.editingId, ...payload }, { withCredentials: true });

    request.subscribe({
      next: (response) => {
        this.saving = false;
        this.successMessage = response.message ?? 'Proveedor guardado correctamente.';
        this.cancelEdit();
        this.load();
        this.changeDetector.markForCheck();
      },
      error: (error) => {
        this.saving = false;
        this.errorMessage = error.error?.message ?? 'No fue posible guardar el proveedor.';
        this.changeDetector.markForCheck();
      },
    });
  }

  protected edit(provider: Provider): void {
    this.editingId = provider.id;
    this.form = {
      name: provider.name,
      identification: provider.identification,
      contact: provider.contact,
      phone: provider.phone,
      email: provider.email,
      address: provider.address,
    };
    this.errorMessage = '';
    this.successMessage = '';
  }

  protected cancelEdit(): void {
    this.editingId = null;
    this.form = this.emptyForm();
  }

  protected toggle(provider: Provider): void {
    this.errorMessage = '';
    this.http.patch<{ message?: string }>('/api/admin/providers.php', {
      id: provider.id,
      name: provider.name,
      identification: provider.identification,
      contact: provider.contact,
      phone: provider.phone,
      email: provider.email,
      address: provider.address,
      active: !provider.active,
    }, { withCredentials: true }).subscribe({
      next: () => {
        provider.active = !provider.active;
        this.successMessage = `Proveedor ${provider.active ? 'activado' : 'desactivado'}.`;
        this.changeDetector.markForCheck();
      },
      error: (error) => {
        this.errorMessage = error.error?.message ?? 'No fue posible cambiar el estado del proveedor.';
        this.changeDetector.markForCheck();
      },
    });
  }

  protected async deactivate(provider: Provider): Promise<void> {
    const confirmed = await this.notifications.confirm({
      title: 'Desactivar proveedor',
      message: `¿Desactivar a "${provider.name}"? No aparecerá en nuevas compras, pero su historial se conserva.`,
      acceptLabel: 'Sí, desactivar',
    });
    if (!confirmed) return;

    this.http.delete<{ message?: string }>('/api/admin/providers.php', {
      body: { id: provider.id },
      withCredentials: true,
    }).subscribe({
      next: (response) => {
        this.successMessage = response.message ?? 'Proveedor desactivado.';
        if (this.editingId === provider.id) this.cancelEdit();
        this.load();
        this.changeDetector.markForCheck();
      },
      error: (error) => {
        this.errorMessage = error.error?.message ?? 'No fue posible desactivar el proveedor.';
        this.changeDetector.markForCheck();
      },
    });
  }

  protected get activeCount(): number {
    return this.providers.filter(provider => provider.active).length;
  }
}
