# api-pedidos

- **Responsable:** Victor
- **Stack:** PHP 8.3 + PostgreSQL (`pedidos_db`)
- **Puerto:** 8006

## Endpoints
| Método | Ruta | Body / Respuesta |
|---|---|---|
| POST | `/api/pedidos` | `{usuario_id, items:[{producto_id, cantidad, precio_unitario}], direccion, codigo_postal, costo_envio}` → 201 (estado `pendiente`) |
| GET | `/api/pedidos/usuario/{usuario_id}` | Historial con `estado`, `total` y `guia` |
| PATCH | `/api/pedidos/{id}/estado` | `{estado: pendiente\|pagado\|enviado\|cancelado}` |

## Correr solo este servicio
```bash
docker compose up --build api-pedidos
curl http://localhost:8006/health
```

## Checklist
- [ ] Tablas `pedidos` y `pedido_items` (crear al arrancar con `CREATE TABLE IF NOT EXISTS`)
- [ ] Al pasar a `pagado`: reservar inventario, vaciar carrito y generar guía (ver el flujo en el README raíz)
- [ ] Guardar la `guia` en el pedido
