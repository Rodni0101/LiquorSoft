import { ChangeDetectorRef, Component, inject, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { RouterLink } from '@angular/router';
import { AdminShell } from '../../admin-shell';

interface ManagedUser { id: number; name: string; email: string; role: string; active: boolean; }

@Component({ imports: [AdminShell, RouterLink], selector: 'app-usuarios', styleUrl: './usuarios.css', templateUrl: './usuarios.html' })
export class Usuarios implements OnInit {
  private readonly http = inject(HttpClient);
  private readonly changeDetector = inject(ChangeDetectorRef);
  protected users: ManagedUser[] = [];
  protected loading = true;
  protected errorMessage = '';

  ngOnInit(): void {
    this.http.get<{ users: ManagedUser[] }>('/api/admin/users.php', { withCredentials: true }).subscribe({
      next: response => { this.users = response.users; this.loading = false; this.changeDetector.markForCheck(); },
      error: error => { this.errorMessage = error.error?.message ?? 'No fue posible cargar los usuarios.'; this.loading = false; this.changeDetector.markForCheck(); },
    });
  }
}
