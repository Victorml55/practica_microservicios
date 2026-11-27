import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { tap } from 'rxjs';
import { API } from './api.config';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private http = inject(HttpClient);

  login(correo: string, contrasena: string) {
    return this.http.post<any>(`${API.usuarios}/login`, { correo, contrasena }).pipe(
      tap((r) => {
        localStorage.setItem('token', r.token);
        localStorage.setItem('usuario', JSON.stringify(r.usuario));
      }),
    );
  }

  registro(nombre: string, correo: string, contrasena: string) {
    return this.http.post<any>(`${API.usuarios}/registro`, { nombre, correo, contrasena });
  }

  logout() {
    localStorage.removeItem('token');
    localStorage.removeItem('usuario');
  }

  get token(): string | null { return localStorage.getItem('token'); }
  get usuario(): any { const u = localStorage.getItem('usuario'); return u ? JSON.parse(u) : null; }
  /** Hay token y todavía no expira (el JWT dura 2 horas). */
  get autenticado(): boolean {
    const t = this.token;
    if (!t) return false;
    try {
      const payload = JSON.parse(atob(t.split('.')[1].replace(/-/g, '+').replace(/_/g, '/')));
      if (typeof payload.exp === 'number' && payload.exp * 1000 > Date.now()) return true;
    } catch {
      /* token corrupto */
    }
    this.logout();
    return false;
  }

  get esAdmin(): boolean { return this.autenticado && this.usuario?.rol === 'admin'; }
}
