import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { API } from './api.config';

@Injectable({ providedIn: 'root' })
export class InventarioService {
  private http = inject(HttpClient);
  disponibilidad(productoId: number) { return this.http.get<any>(`${API.inventario}/${productoId}`); }
}
