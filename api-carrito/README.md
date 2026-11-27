# api-carrito

- **Responsable:** Omar
- **Stack:** Node.js 22 + Express
- **Puerto:** 8005

## Endpoints
| Método | Ruta | Body / Respuesta |
|---|---|---|
| GET | `/api/carrito/{usuario_id}` (Bearer del dueño o admin) | `{usuario_id, items:[{producto_id, cantidad}]}` |
| POST | `/api/carrito/{usuario_id}/agregar` (Bearer del dueño o admin) | `{producto_id, cantidad}` → si el producto ya está, sumar la cantidad |
| DELETE | `/api/carrito/{usuario_id}/vaciar` | La llama api-pedidos al aprobarse el pago (`X-Internal-Key`), o el dueño con su token |

## Correr solo este servicio
```bash
docker compose up --build api-carrito
curl http://localhost:8005/health
```

## Checklist
- [ ] Guardar en memoria (`Map`); el carrito es temporal
- [ ] Opcional: enriquecer los items con nombre y precio consultando `PRODUCTOS_URL`
