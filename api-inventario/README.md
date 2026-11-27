# api-inventario

- **Responsable:** Ricardo
- **Stack:** Go 1.23 + Gin
- **Puerto:** 8004

## Endpoints
| Método | Ruta | Body / Respuesta |
|---|---|---|
| GET | `/api/inventario/{producto_id}` | `{producto_id, disponibles}` |
| POST | `/api/inventario/reservar` | `{producto_id, cantidad}` → 400 si no hay suficiente stock. **Solo `X-Internal-Key` o admin** |
| POST | `/api/inventario/reabastecer` | `{producto_id, cantidad}`. **Solo `X-Internal-Key` o admin** |

## Correr solo este servicio
```bash
docker compose up --build api-inventario
curl http://localhost:8004/health
```

## Checklist
- [x] Stock semilla para productos 1–10 (map en memoria con `sync.Mutex`)
- [ ] `go.sum` se genera en el build con `go mod tidy`; en local correr `go mod tidy` y subir el `go.sum`

## Notas de implementación
- Stock semilla: 1→50, 2→30, 3→25, 4→40, 5→15, 6→60, 7→10, 8→35, 9→20, 10→45.
- `reservar` responde 200 `{producto_id, reservados, disponibles}`; 400 si no alcanza, 404 si el producto no existe.
- `reabastecer` responde 200 `{producto_id, agregados, disponibles}` (si el producto no existía, lo da de alta).
