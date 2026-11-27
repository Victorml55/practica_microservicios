import { Component, OnInit, inject, signal } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { AuthService } from '../core/auth.service';
import { CarritoService } from '../core/carrito.service';
import { InventarioService } from '../core/inventario.service';
import { ProductosService } from '../core/productos.service';
import { ResenasService } from '../core/resenas.service';

@Component({
  selector: 'app-detalle-producto',
  imports: [FormsModule, CurrencyPipe],
  template: `
    @if (producto(); as p) {
      <div class="panel">
        <h2>{{ p.nombre }}</h2>
        <img [src]="p.imagen_url" [alt]="p.nombre" style="max-width:280px;border-radius:4px" />
        <p>{{ p.descripcion }}</p>
        <p class="precio">{{ p.precio | currency: 'MXN' }}</p>
        <p>Categoría: {{ p.categoria }}</p>
        <p>
          Disponibilidad:
          @if (stock() === null) { consultando... }
          @else if (stock()! > 0) { {{ stock() }} unidades }
          @else { Agotado }
        </p>
        <input type="number" min="1" [(ngModel)]="cantidad" style="width:80px" />
        <button class="btn" [disabled]="stock() === 0" (click)="agregar()">Agregar al carrito</button>
        @if (mensaje()) { <p class="ok">{{ mensaje() }}</p> }
        @if (error()) { <p class="error">{{ error() }}</p> }
      </div>

      <div class="panel">
        <h3>Reseñas @if (promedio() !== null) { (promedio {{ promedio() }}) }</h3>
        @for (r of resenas(); track $index) {
          <p><strong>{{ '★'.repeat(r.calificacion) }}</strong> {{ r.comentario }}</p>
        } @empty { <p>Aún no hay reseñas.</p> }

        @if (auth.autenticado) {
          <h4>Deja tu reseña</h4>
          <select [(ngModel)]="calificacion">
            @for (n of [5, 4, 3, 2, 1]; track n) { <option [value]="n">{{ n }} estrellas</option> }
          </select>
          <input placeholder="Comentario" [(ngModel)]="comentario" style="width:60%" />
          <button class="btn" (click)="enviarResena()">Publicar</button>
        } @else {
          <p>Inicia sesión para dejar una reseña.</p>
        }
      </div>
    } @else if (error()) {
      <p class="error">{{ error() }}</p>
    }
  `,
})
export class DetalleProductoComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  auth = inject(AuthService);
  private productosSrv = inject(ProductosService);
  private inventarioSrv = inject(InventarioService);
  private resenasSrv = inject(ResenasService);
  private carritoSrv = inject(CarritoService);

  id = 0;
  producto = signal<any>(null);
  stock = signal<number | null>(null);
  resenas = signal<any[]>([]);
  promedio = signal<number | null>(null);
  mensaje = signal('');
  error = signal('');
  cantidad = 1;
  calificacion = 5;
  comentario = '';

  ngOnInit() {
    this.id = Number(this.route.snapshot.paramMap.get('id'));
    this.productosSrv.obtener(this.id).subscribe({
      next: (p) => this.producto.set(p),
      error: (e) => this.error.set(e.error?.error ?? 'Producto no encontrado'),
    });
    this.inventarioSrv.disponibilidad(this.id).subscribe({
      next: (i) => this.stock.set(i.disponibles ?? 0),
      error: () => this.stock.set(null),
    });
    this.cargarResenas();
  }

  cargarResenas() {
    this.resenasSrv.porProducto(this.id).subscribe({
      next: (r) => {
        this.resenas.set(r.resenas ?? []);
        this.promedio.set(r.promedio ?? null);
      },
      error: () => {},
    });
  }

  agregar() {
    if (!this.auth.autenticado) return void this.router.navigate(['/login']);
    this.mensaje.set('');
    this.error.set('');
    this.carritoSrv.agregar(this.auth.usuario.id, this.id, Number(this.cantidad)).subscribe({
      next: () => this.mensaje.set('Producto agregado al carrito'),
      error: (e) => this.error.set(e.error?.error ?? 'No se pudo agregar'),
    });
  }

  enviarResena() {
    this.resenasSrv
      .crear({
        producto_id: this.id,
        usuario_id: this.auth.usuario.id,
        calificacion: Number(this.calificacion),
        comentario: this.comentario,
      })
      .subscribe({
        next: () => { this.comentario = ''; this.cargarResenas(); },
        error: (e) => this.error.set(e.error?.error ?? 'No se pudo guardar la reseña'),
      });
  }
}
