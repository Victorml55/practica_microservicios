import { Component, OnInit, inject, signal } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { AuthService } from '../core/auth.service';
import { EnviosService } from '../core/envios.service';
import { PedidosService } from '../core/pedidos.service';

@Component({
  selector: 'app-mis-pedidos',
  imports: [CurrencyPipe],
  template: `
    <h2>Mis pedidos</h2>
    @if (error()) { <p class="error">{{ error() }}</p> }
    <table>
      <tr><th>#</th><th>Estado</th><th>Total</th><th>Guía</th><th>Rastreo</th></tr>
      @for (p of pedidos(); track p.id) {
        <tr>
          <td>{{ p.id }}</td>
          <td>{{ p.estado }}</td>
          <td>{{ p.total | currency: 'MXN' }}</td>
          <td>{{ p.guia ?? '—' }}</td>
          <td>
            @if (p.guia) {
              <button class="btn secundario" (click)="rastrear(p.guia)">Consultar</button>
              {{ rastreo()[p.guia] }}
            }
          </td>
        </tr>
      } @empty {
        <tr><td colspan="5">Aún no tienes pedidos.</td></tr>
      }
    </table>
  `,
})
export class MisPedidosComponent implements OnInit {
  private auth = inject(AuthService);
  private pedidosSrv = inject(PedidosService);
  private enviosSrv = inject(EnviosService);

  pedidos = signal<any[]>([]);
  rastreo = signal<Record<string, string>>({});
  error = signal('');

  ngOnInit() {
    this.pedidosSrv.porUsuario(this.auth.usuario.id).subscribe({
      next: (r: any) => this.pedidos.set(Array.isArray(r) ? r : (r.pedidos ?? [])),
      error: (e) => this.error.set(e.error?.error ?? 'No se pudieron cargar los pedidos'),
    });
  }

  rastrear(guia: string) {
    this.enviosSrv.rastrear(guia).subscribe({
      next: (r) => this.rastreo.update((m) => ({ ...m, [guia]: r.estado })),
      error: () => this.rastreo.update((m) => ({ ...m, [guia]: 'sin información' })),
    });
  }
}
