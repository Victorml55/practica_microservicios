import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { API } from './api.config';

@Injectable({ providedIn: 'root' })
export class ResenasService {
  private http = inject(HttpClient);
  porProducto(productoId: number) { return this.http.get<any>(`${API.resenas}/producto/${productoId}`); }
  crear(r: { producto_id: number; usuario_id: number; calificacion: number; comentario: string }) {
    return this.http.post<any>(API.resenas, r);
  }
}
