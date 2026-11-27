import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { API } from './api.config';

@Injectable({ providedIn: 'root' })
export class CarritoService {
  private http = inject(HttpClient);
  obtener(usuarioId: number) { return this.http.get<any>(`${API.carrito}/${usuarioId}`); }
  agregar(usuarioId: number, producto_id: number, cantidad: number) {
    return this.http.post<any>(`${API.carrito}/${usuarioId}/agregar`, { producto_id, cantidad });
  }
  vaciar(usuarioId: number) { return this.http.delete<any>(`${API.carrito}/${usuarioId}/vaciar`); }
}
