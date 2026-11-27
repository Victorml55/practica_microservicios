# api-pedidos

- **Responsable:** Victor
- **Stack:** PHP 8.3 + PostgreSQL (`pedidos_db`)
- **Puerto:** 8006

Las tablas `pedidos` y `pedido_items` se crean solas al primer request.

## Endpoints

| Método | Ruta | Auth | Body → Respuesta |
|---|---|---|---|
| POST | `/api/pedidos` | Bearer | `{usuario_id, items:[{producto_id, cantidad}], direccion, codigo_postal}` → 201 pedido en `pendiente` |
| GET | `/api/pedidos/usuario/{usuario_id}` | Bearer | Historial (más reciente primero) con `items`, `total`, `estado` y `guia` |
| GET | `/api/pedidos/{id}` | Bearer (dueño/admin) o `X-Internal-Key` | Un pedido (api-pagos lo consulta para verificar el `total`) |
| PATCH | `/api/pedidos/{id}/estado` | `X-Internal-Key` (api-pagos) o admin | `{estado}` → pedido actualizado |

- Con token, un usuario solo ve o crea **sus** pedidos (admin puede todo).
- El `PATCH` solo lo acepta **api-pagos** (header `X-Internal-Key`) o un admin: un cliente no puede marcar su pedido como pagado.
- **El servidor no confía en el navegador:** toma el `precio_unitario` de api-productos, valida el stock en api-inventario y calcula `costo_envio` con api-envios. `total = subtotal + costo_envio`, y api-pagos exige que el monto sea exactamente ese `total`.
- Si al pagar no alcanza el stock de algún producto, se devuelven las unidades ya reservadas, el pedido sigue en `pendiente` y el `PATCH` responde 400.

### Forma del pedido
```json
{
  "id": 1, "usuario_id": 1,
  "direccion": "Av. Tecnológico 100", "codigo_postal": "38010",
  "items": [{ "producto_id": 3, "cantidad": 2, "precio_unitario": 199.9 }],
  "subtotal": 399.8, "costo_envio": 89.5, "total": 489.3,
  "estado": "pagado", "guia": "TM-000001",
  "creado_en": "...", "actualizado_en": "..."
}
```

## Estados y transiciones
```
pendiente ─▶ pagado ─▶ enviado
    │           │
    └───────────┴─▶ cancelado
```
Otra transición → 400. Repetir el mismo estado → 200 sin volver a ejecutar nada.

## Orquestación al pasar a `pagado`
1. `POST {INVENTARIO_URL}/api/inventario/reservar` por cada item `{producto_id, cantidad}`
2. `DELETE {CARRITO_URL}/api/carrito/{usuario_id}/vaciar`
3. `POST {ENVIOS_URL}/api/envios/generar {pedido_id}` → se guarda `guia` (se espera el campo `guia` en la respuesta)

La respuesta incluye `integracion` con el resultado de cada paso. Si un servicio falla, el pedido **sí queda en `pagado`** (el cobro ya se hizo) y el error se reporta en `integracion`.

## Correr solo este servicio
```bash
docker compose up --build api-pedidos      # también levanta postgres
curl http://localhost:8006/health
```
