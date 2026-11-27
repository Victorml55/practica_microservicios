import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { API } from './api.config';

@Injectable({ providedIn: 'root' })
export class PagosService {
  private http = inject(HttpClient);
  procesar(body: { pedido_id: number; monto: number; metodo_pago: string }) {
    return this.http.post<any>(`${API.pagos}/procesar`, body);
  }
  porPedido(pedidoId: number) { return this.http.get<any>(`${API.pagos}/pedido/${pedidoId}`); }
}
