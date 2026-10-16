import { ChangeDetectorRef, Component, inject, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { AdminShell } from '../../admin-shell';

interface Provider { id: number; name: string; identification: string; contact: string; phone: string; email: string; address: string; active: boolean; }
@Component({ selector: 'app-proveedores', standalone: true, imports: [AdminShell, FormsModule], templateUrl: './proveedores.html', styleUrl: './proveedores.css' })
export class Proveedores implements OnInit {
  private readonly http = inject(HttpClient); private readonly changeDetector = inject(ChangeDetectorRef);
  protected providers: Provider[] = []; protected loading = true; protected saving = false; protected errorMessage = ''; protected successMessage = '';
  protected form: Partial<Provider> = { name: '', identification: '', contact: '', phone: '', email: '', address: '' };
  ngOnInit(): void { this.load(); }
  protected load(): void { this.http.get<{ providers: Provider[] }>('/api/admin/providers.php', { withCredentials: true }).subscribe({ next: response => { this.providers = response.providers; this.loading = false; this.changeDetector.markForCheck(); }, error: error => { this.errorMessage = error.error?.message ?? 'No fue posible cargar los proveedores.'; this.loading = false; this.changeDetector.markForCheck(); } }); }
  protected save(): void { this.errorMessage = ''; this.successMessage = ''; if (!this.form.name?.trim()) { this.errorMessage = 'El nombre del proveedor es obligatorio.'; return; } this.saving = true; this.http.post('/api/admin/providers.php', this.form, { withCredentials: true }).subscribe({ next: () => { this.saving = false; this.successMessage = 'Proveedor creado correctamente.'; this.form = { name: '', identification: '', contact: '', phone: '', email: '', address: '' }; this.load(); }, error: error => { this.saving = false; this.errorMessage = error.error?.message ?? 'No fue posible guardar el proveedor.'; this.changeDetector.markForCheck(); } }); }
}
