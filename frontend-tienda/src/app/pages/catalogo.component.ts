import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { ProductosService } from '../core/productos.service';
import { ResenasService } from '../core/resenas.service';

@Component({
  selector: 'app-catalogo',
  imports: [FormsModule, RouterLink, CurrencyPipe],
  template: `
    <h2>Catálogo</h2>
    <div class="filtros">
      <input placeholder="Buscar producto..." [ngModel]="busqueda()" (ngModelChange)="busqueda.set($event)" />
      <select [ngModel]="categoria()" (ngModelChange)="categoria.set($event)">
        <option value="">Todas las categorías</option>
        @for (c of categorias(); track c) { <option [value]="c">{{ c }}</option> }
      </select>
    </div>
    @if (error()) { <p class="error">{{ error() }}</p> }
    <div class="grid">
      @for (p of filtrados(); track p.id) {
        <article class="card">
          <img [src]="p.imagen_url" [alt]="p.nombre" />
          <h3>{{ p.nombre }}</h3>
          <p class="precio">{{ p.precio | currency: 'MXN' }}</p>
          <p>{{ estrellas(p.id) }}</p>
          <a class="btn" [routerLink]="['/producto', p.id]">Ver detalle</a>
        </article>
      }
    </div>
    @if (!filtrados().length && !error()) { <p>No hay productos para mostrar.</p> }
  `,
})
export class CatalogoComponent implements OnInit {
  private productosSrv = inject(ProductosService);
  private resenasSrv = inject(ResenasService);

  productos = signal<any[]>([]);
  promedios = signal<Record<number, number>>({});
  busqueda = signal('');
  categoria = signal('');
  error = signal('');

  categorias = computed(() => [...new Set(this.productos().map((p) => p.categoria).filter(Boolean))]);
  filtrados = computed(() =>
    this.productos().filter(
      (p) =>
        p.nombre?.toLowerCase().includes(this.busqueda().toLowerCase()) &&
        (!this.categoria() || p.categoria === this.categoria()),
    ),
  );

  ngOnInit() {
    this.productosSrv.listar().subscribe({
      next: (r: any) => {
        const lista: any[] = Array.isArray(r) ? r : (r.productos ?? []);
        this.productos.set(lista);
        lista.forEach((p) =>
          this.resenasSrv.porProducto(p.id).subscribe({
            next: (x: any) => this.promedios.update((m) => ({ ...m, [p.id]: x.promedio })),
            error: () => {},
          }),
        );
      },
      error: (e) => this.error.set(e.error?.error ?? 'No se pudo cargar el catálogo'),
    });
  }

  estrellas(id: number) {
    const prom = this.promedios()[id];
    if (!prom) return 'Sin reseñas';
    const n = Math.round(prom);
    return '★'.repeat(n) + '☆'.repeat(5 - n) + ` (${Number(prom).toFixed(1)})`;
  }
}
