# frontend-tienda

- **Responsable:** Omar
- **Stack:** Angular 20 (build servido con nginx)
- **Puerto:** 4200

## Endpoints
Consume los 8 servicios. Las URLs están en `src/app/core/api.config.ts`.

| Vista | Servicios que consume |
|---|---|
| Inicio / Catálogo (buscador, filtro por categoría, calificación promedio) | productos, resenas |
| Detalle de producto (stock en tiempo real, reseñas + formulario) | productos, inventario, resenas |
| Carrito y Checkout (cotizar envío, pagar, confirmar) | carrito, envios, pedidos, pagos |
| Mis Pedidos (estado de pago, guía de rastreo) | pedidos, pagos, envios |
| Login / Registro | usuarios |

Desarrollo local sin Docker: `npm install && npx ng serve` (http://localhost:4200)

## Correr solo este servicio
```bash
docker compose up --build frontend-tienda
curl http://localhost:4200/health
```

## Checklist
- [ ] Interceptor HTTP que agregue `Authorization: Bearer <token>`
- [ ] Guard para las rutas que requieren sesión (checkout, mis pedidos)
- [ ] Un servicio Angular por API en `src/app/core/`
