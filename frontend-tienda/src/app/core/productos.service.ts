import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { API } from './api.config';

@Injectable({ providedIn: 'root' })
export class ProductosService {
  private http = inject(HttpClient);
  listar() { return this.http.get<any[]>(API.productos); }
  obtener(id: number) { return this.http.get<any>(`${API.productos}/${id}`); }
}
