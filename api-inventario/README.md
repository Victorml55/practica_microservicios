# api-inventario

- **Responsable:** Ricardo
- **Stack:** Go 1.23 + Gin
- **Puerto:** 8004

## Endpoints
| Método | Ruta | Body / Respuesta |
|---|---|---|
| GET | `/api/inventario/{producto_id}` | `{producto_id, disponibles}` |
| POST | `/api/inventario/reservar` | `{producto_id, cantidad}` → 400 si no hay suficiente stock |
| POST | `/api/inventario/reabastecer` | `{producto_id, cantidad}` |

## Correr solo este servicio
```bash
docker compose up --build api-inventario
curl http://localhost:8004/health
```

## Checklist
- [ ] Stock semilla para productos 1–10 (map en memoria con `sync.Mutex`)
- [ ] `go.sum` se genera en el build con `go mod tidy`; en local correr `go mod tidy` y subir el `go.sum`
