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
  get autenticado(): boolean { return !!this.token; }
}
