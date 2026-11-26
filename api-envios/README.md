# api-envios

- **Responsable:** Ricardo
- **Stack:** Python 3.12 + FastAPI
- **Puerto:** 8003

## Endpoints
| Método | Ruta | Body / Respuesta |
|---|---|---|
| POST | `/api/envios/cotizar` | `{codigo_postal, cantidad}` → `{costo_envio, dias_estimados}` |
| POST | `/api/envios/generar` | `{pedido_id}` → 201 `{guia, pedido_id, estado}` |
| GET | `/api/envios/rastreo/{guia}` | `{guia, estado: preparando\|en_transito\|entregado}` |

## Correr solo este servicio
```bash
docker compose up --build api-envios
curl http://localhost:8003/health
```

## Checklist
- [ ] Regla de tarifa simple (p. ej. por el primer dígito del CP y la cantidad)
- [ ] Guía única (p. ej. `TM-000123`)
- [ ] Documentación automática en `http://localhost:8003/docs`
