# api-envios

- **Responsable:** Ricardo
- **Stack:** Python 3.12 + FastAPI
- **Puerto:** 8003

## Endpoints
| Método | Ruta | Body / Respuesta |
|---|---|---|
| POST | `/api/envios/cotizar` | `{codigo_postal, cantidad}` → `{costo_envio, dias_estimados}` |
| POST | `/api/envios/generar` | `{pedido_id}` → 201 `{guia, pedido_id, estado}`. **Solo `X-Internal-Key` o admin** |
| GET | `/api/envios/rastreo/{guia}` | `{guia, estado: preparando\|en_transito\|entregado}` |

## Correr solo este servicio
```bash
docker compose up --build api-envios
curl http://localhost:8003/health
```

## Checklist
- [x] Regla de tarifa simple (p. ej. por el primer dígito del CP y la cantidad)
- [x] Guía única (p. ej. `TM-000123`)
- [x] Documentación automática en `http://localhost:8003/docs`

## Notas de implementación
- **Tarifa:** costo base según la zona (primer dígito del CP) + $15 por cada pieza extra. Más de 10 piezas suma 1 día.
  - CP 0/1 → $79, 2 días · 2/3/4 → $99, 3 días · 5/6 → $109, 4 días · 7 → $119, 4 días · 8/9 → $129, 5 días
- `codigo_postal` debe tener 5 dígitos (texto, p. ej. `"06600"`).
- Si se llama `generar` dos veces con el mismo `pedido_id`, regresa la misma guía (no duplica).
- Extra para la demo (**solo admin**): `PATCH /api/envios/rastreo/{guia}/avanzar` pasa a `en_transito` y luego a `entregado`.
- Las guías viven en memoria: se pierden al reiniciar el contenedor.
