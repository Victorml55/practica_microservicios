import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { forkJoin } from 'rxjs';
import { AuthService } from '../core/auth.service';
import { CarritoService } from '../core/carrito.service';
import { EnviosService } from '../core/envios.service';
import { PagosService } from '../core/pagos.service';
import { PedidosService } from '../core/pedidos.service';
import { ProductosService } from '../core/productos.service';

@Component({
  selector: 'app-checkout',
  imports: [FormsModule, RouterLink, CurrencyPipe],
  template: `
    <h2>Carrito y pago</h2>
    @if (confirmado()) {
      <div class="ok">
        <h3>¡Orden confirmada!</h3>
        <p>Pedido #{{ confirmado() }} pagado correctamente.</p>
        <a class="btn" routerLink="/mis-pedidos">Ver mis pedidos</a>
      </div>
    } @else {
      @if (error()) { <p class="error">{{ error() }}</p> }
      @if (!items().length) {
        <p>Tu carrito está vacío. <a routerLink="/">Ir al catálogo</a></p>
      } @else {
        <table>
          <tr><th>Producto</th><th>Cant.</th><th>Precio</th><th>Subtotal</th></tr>
          @for (i of items(); track i.producto_id) {
            <tr>
              <td>{{ i.nombre }}</td><td>{{ i.cantidad }}</td>
              <td>{{ i.precio | currency: 'MXN' }}</td>
              <td>{{ i.precio * i.cantidad | currency: 'MXN' }}</td>
            </tr>
          }
        </table>

        <div class="panel">
          <h3>Envío</h3>
          <input placeholder="Código postal (5 dígitos)" maxlength="5" [(ngModel)]="cp" (ngModelChange)="cpCambiado()" />
          <button class="btn secundario" (click)="cotizar()">Cotizar envío</button>
          @if (dias() !== null) {
            <p>Costo: {{ costoEnvio() | currency: 'MXN' }} · Llega en {{ dias() }} días</p>
          }
          <input placeholder="Dirección de entrega" [(ngModel)]="direccion" style="width:100%" />
        </div>

        <div class="panel">
          <h3>Pago</h3>
          <select [(ngModel)]="metodo">
            <option value="tarjeta">Tarjeta</option>
            <option value="paypal">PayPal</option>
            <option value="transferencia">Transferencia</option>
            <option value="rechazar">Rechazar (prueba)</option>
          </select>
          <p>Subtotal: {{ subtotal() | currency: 'MXN' }}</p>
          <p>Envío: {{ costoEnvio() | currency: 'MXN' }}</p>
          <h3>Total: {{ total() | currency: 'MXN' }}</h3>
          <button class="btn" [disabled]="procesando() || dias() === null || !direccion" (click)="pagar()">
            {{ procesando() ? 'Procesando...' : 'Pagar' }}
          </button>
        </div>
      }
    }
  `,
})
export class CheckoutComponent implements OnInit {
  private auth = inject(AuthService);
  private carritoSrv = inject(CarritoService);
  private productosSrv = inject(ProductosService);
  private enviosSrv = inject(EnviosService);
  private pedidosSrv = inject(PedidosService);
  private pagosSrv = inject(PagosService);

  items = signal<any[]>([]);
  costoEnvio = signal(0);
  dias = signal<number | null>(null);
  procesando = signal(false);
  confirmado = signal<number | null>(null);
  error = signal('');
  cp = '';
  direccion = '';
  metodo = 'tarjeta';
  private pedidoPendiente: { id: number; total: number } | null = null;

  subtotal = computed(() => this.items().reduce((s, i) => s + i.precio * i.cantidad, 0));
  total = computed(() => this.subtotal() + this.costoEnvio());

  ngOnInit() {
    this.carritoSrv.obtener(this.auth.usuario.id).subscribe({
      next: (c) => {
        const items: any[] = c.items ?? [];
        if (!items.length) return;
        forkJoin(items.map((i) => this.productosSrv.obtener(i.producto_id))).subscribe({
          next: (ps) =>
            this.items.set(
              items.map((i, idx) => ({ ...i, nombre: ps[idx].nombre, precio: Number(ps[idx].precio) })),
            ),
          error: () => this.error.set('No se pudieron cargar los productos del carrito'),
        });
      },
      error: (e) => this.error.set(e.error?.error ?? 'No se pudo cargar el carrito'),
    });
  }

  // Si cambia el código postal, la cotización anterior ya no vale
  cpCambiado() {
    this.dias.set(null);
    this.costoEnvio.set(0);
    this.pedidoPendiente = null;
  }

  cotizar() {
    this.error.set('');
    if (!/^\d{5}$/.test(this.cp)) {
      this.error.set('El código postal debe tener 5 dígitos');
      return;
    }
    const unidades = this.items().reduce((s, i) => s + i.cantidad, 0);
    this.enviosSrv.cotizar({ codigo_postal: this.cp, cantidad: unidades }).subscribe({
      next: (r) => { this.costoEnvio.set(Number(r.costo_envio)); this.dias.set(r.dias_estimados); },
      error: (e) => this.error.set(e.error?.error ?? 'No se pudo cotizar el envío'),
    });
  }

  pagar() {
    this.error.set('');
    this.procesando.set(true);

    // Si un intento anterior de pago falló, se reutiliza el pedido ya creado
    if (this.pedidoPendiente !== null) {
      this.cobrar(this.pedidoPendiente.id, this.pedidoPendiente.total);
      return;
    }

    // El servidor calcula precios, costo de envío y total; aquí solo se mandan producto y cantidad
    this.pedidosSrv
      .crear({
        usuario_id: this.auth.usuario.id,
        direccion: this.direccion,
        codigo_postal: this.cp,
        items: this.items().map((i) => ({ producto_id: i.producto_id, cantidad: i.cantidad })),
      })
      .subscribe({
        next: (p) => {
          this.pedidoPendiente = { id: p.id, total: Number(p.total) };
          this.costoEnvio.set(Number(p.costo_envio));
          this.cobrar(p.id, Number(p.total));
        },
        error: (e) => {
          this.error.set(e.error?.error ?? 'No se pudo crear el pedido');
          this.procesando.set(false);
        },
      });
  }

  // Se cobra el total que calculó api-pedidos (api-pagos lo vuelve a verificar)
  private cobrar(pedidoId: number, monto: number) {
    this.pagosSrv.procesar({ pedido_id: pedidoId, monto, metodo_pago: this.metodo }).subscribe({
      next: () => {
        this.confirmado.set(pedidoId);
        this.items.set([]);
        this.pedidoPendiente = null;
        this.procesando.set(false);
      },
      error: (e) => {
        this.error.set(e.error?.error ?? 'Pago rechazado');
        this.procesando.set(false);
      },
    });
  }
}
