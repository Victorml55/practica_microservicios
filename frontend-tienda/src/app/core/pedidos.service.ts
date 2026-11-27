import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { API } from './api.config';

@Injectable({ providedIn: 'root' })
export class PedidosService {
  private http = inject(HttpClient);
  crear(body: any) { return this.http.post<any>(API.pedidos, body); }
  porUsuario(usuarioId: number) { return this.http.get<any[]>(`${API.pedidos}/usuario/${usuarioId}`); }
}
