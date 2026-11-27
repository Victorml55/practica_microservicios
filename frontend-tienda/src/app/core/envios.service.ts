import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { API } from './api.config';

@Injectable({ providedIn: 'root' })
export class EnviosService {
  private http = inject(HttpClient);
  cotizar(body: any) { return this.http.post<any>(`${API.envios}/cotizar`, body); }
  rastrear(guia: string) { return this.http.get<any>(`${API.envios}/rastreo/${guia}`); }
}
