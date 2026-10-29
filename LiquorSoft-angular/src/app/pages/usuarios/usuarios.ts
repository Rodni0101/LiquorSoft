import { ChangeDetectorRef, Component, inject, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { AdminShell } from '../../admin-shell';
import { NotificationService } from '../../notification.service';

interface ManagedUser { id: number; name: string; email: string; phone: string; username: string; role: string; roleId: number; active: boolean; }
interface RoleOption { id: number; name: string; }

type ModalMode = 'create' | 'edit';

@Component({
  standalone: true,
  imports: [FormsModule, AdminShell],
  selector: 'app-usuarios',
  styleUrl: './usuarios.css',
  templateUrl: './usuarios.html'
})
export class Usuarios implements OnInit {
  private readonly http = inject(HttpClient);
  private readonly changeDetector = inject(ChangeDetectorRef);
  private readonly notification = inject(NotificationService);

  protected users: ManagedUser[] = [];
  protected roles: RoleOption[] = [];
  protected loading = true;
  protected errorMessage = '';
  protected successMessage = '';

  // Modal de crear/editar
  protected modalOpen = false;
  protected modalMode: ModalMode = 'create';
  protected editingUserId: number | null = null;
  protected saving = false;

  protected formName = '';
  protected formLastName = '';
  protected formEmail = '';
  protected formPhone = '';
  protected formPassword = '';
  protected formRoleId: number | null = null;

  ngOnInit(): void {
    this.loadUsers();
  }

  protected loadUsers(): void {
    this.loading = true;
    this.http.get<{ users: ManagedUser[]; roles: RoleOption[] }>('/api/admin/users.php', { withCredentials: true }).subscribe({
      next: response => {
        this.users = response.users ?? [];
        this.roles = response.roles ?? [];
        this.loading = false;
        this.changeDetector.markForCheck();
      },
      error: error => {
        this.errorMessage = error.error?.message ?? 'No fue posible cargar los usuarios.';
        this.loading = false;
        this.changeDetector.markForCheck();
      },
    });
  }

  protected openCreateModal(): void {
    this.modalMode = 'create';
    this.editingUserId = null;
    this.resetForm();
    this.errorMessage = '';
    this.successMessage = '';
    this.modalOpen = true;
  }

  protected openEditModal(user: ManagedUser): void {
    this.modalMode = 'edit';
    this.editingUserId = user.id;
    const [first, ...rest] = user.name.trim().split(/\s+/);
    this.formName = first ?? '';
    this.formLastName = rest.join(' ');
    this.formEmail = user.email;
    this.formPhone = user.phone;
    this.formRoleId = user.roleId;
    this.formPassword = '';
    this.errorMessage = '';
    this.successMessage = '';
    this.modalOpen = true;
  }

  protected closeModal(): void {
    this.modalOpen = false;
    this.editingUserId = null;
    this.resetForm();
    this.errorMessage = '';
    this.successMessage = '';
  }

  private resetForm(): void {
    this.formName = '';
    this.formLastName = '';
    this.formEmail = '';
    this.formPhone = '';
    this.formPassword = '';
    const preferred = this.roles.find(role => role.name.toLowerCase() === 'vendedor') ?? this.roles.find(role => role.name.toLowerCase() !== 'administrador') ?? this.roles[0];
    this.formRoleId = preferred ? preferred.id : null;
  }

  protected saveUser(): void {
    this.errorMessage = '';
    this.successMessage = '';

    if (!this.formName.trim() || !this.formLastName.trim()) {
      this.errorMessage = 'Nombre y apellido son obligatorios.';
      return;
    }
    if (!this.formEmail.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.formEmail)) {
      this.errorMessage = 'Ingresa un correo válido.';
      return;
    }
    if (this.modalMode === 'create' && (!this.formPassword || this.formPassword.length < 8)) {
      this.errorMessage = 'La contraseña debe tener al menos 8 caracteres.';
      return;
    }
    if (!this.formRoleId) {
      this.errorMessage = 'Selecciona un rol.';
      return;
    }

    this.saving = true;

    if (this.modalMode === 'create') {
      this.http.post('/api/admin/users.php', {
        nombre: this.formName.trim(),
        apellido: this.formLastName.trim(),
        correo: this.formEmail.trim(),
        telefono: this.formPhone.trim(),
        password: this.formPassword,
        rol_id: this.formRoleId,
      }, { withCredentials: true }).subscribe({
        next: () => {
          this.saving = false;
          this.successMessage = 'Usuario creado correctamente.';
          this.notification.showSuccess('Usuario creado correctamente.');
          this.closeModal();
          this.loadUsers();
        },
        error: (err) => {
          this.saving = false;
          this.errorMessage = err.error?.message ?? 'No fue posible crear el usuario.';
          this.notification.showError(this.errorMessage);
          this.changeDetector.markForCheck();
        },
      });
    } else if (this.editingUserId) {
      const updates: Record<string, unknown> = {
        userId: this.editingUserId,
        roleId: this.formRoleId,
        nombre: this.formName.trim(),
        apellido: this.formLastName.trim(),
        correo: this.formEmail.trim(),
        telefono: this.formPhone.trim(),
      };
      if (this.formPassword) updates['password'] = this.formPassword;

      this.http.patch('/api/admin/users.php', updates, { withCredentials: true }).subscribe({
        next: () => {
          this.saving = false;
          this.successMessage = 'Usuario actualizado correctamente.';
          this.notification.showSuccess('Usuario actualizado correctamente.');
          this.closeModal();
          this.loadUsers();
        },
        error: (err) => {
          this.saving = false;
          this.errorMessage = err.error?.message ?? 'No fue posible actualizar el usuario.';
          this.notification.showError(this.errorMessage);
          this.changeDetector.markForCheck();
        },
      });
    }
  }

  protected async deleteUser(user: ManagedUser): Promise<void> {
    const confirmado = await this.notification.confirm({
      title: 'Dar de baja usuario',
      message: `¿Desactivar la cuenta de "${user.name}" (${user.email})? El historial se conserva y podrás reactivarla cuando quieras.`,
      acceptLabel: 'Sí, dar de baja',
    });
    if (!confirmado) return;

    this.http.delete('/api/admin/users.php', { body: { userId: user.id }, withCredentials: true }).subscribe({
      next: () => {
        this.notification.showSuccess('Usuario desactivado correctamente.');
        this.loadUsers();
      },
      error: (err) => {
        this.errorMessage = err.error?.message ?? 'No fue posible desactivar el usuario.';
        this.notification.showError(this.errorMessage);
        this.changeDetector.markForCheck();
      },
    });
  }

  protected toggleActive(user: ManagedUser): void {
    const nuevoEstado = !user.active;
    this.http.patch('/api/admin/users.php', { userId: user.id, active: nuevoEstado }, { withCredentials: true }).subscribe({
      next: () => {
        user.active = nuevoEstado;
        this.notification.showSuccess(`Usuario ${nuevoEstado ? 'activado' : 'desactivado'}.`);
        this.changeDetector.markForCheck();
      },
      error: (err) => {
        this.notification.showError(err.error?.message ?? 'No fue posible cambiar el estado del usuario.');
      },
    });
  }

  protected formatRole(role: string): string {
    return role.charAt(0).toUpperCase() + role.slice(1).toLowerCase();
  }

  protected roleBadgeClass(role: string): string {
    const r = role.toLowerCase();
    if (r === 'administrador') return 'role-admin';
    if (r === 'vendedor') return 'role-vendedor';
    if (r === 'cliente') return 'role-cliente';
    if (r === 'bodega') return 'role-bodega';
    if (r === 'trabajador') return 'role-trabajador';
    if (r === 'supervisor') return 'role-supervisor';
    return 'role-default';
  }
}
