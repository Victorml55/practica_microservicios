# api-resenas

- **Responsable:** Ricardo
- **Stack:** Python 3.12 + FastAPI + MongoDB (`resenas_db`)
- **Puerto:** 8008

## Endpoints
| Método | Ruta | Body / Respuesta |
|---|---|---|
| GET | `/api/resenas/producto/{producto_id}` | `{producto_id, promedio, total, resenas:[...]}` |
| POST | `/api/resenas` | `{producto_id, usuario_id, calificacion (1-5), comentario}` → 201 |

## Correr solo este servicio
```bash
docker compose up --build api-resenas
curl http://localhost:8008/health
```

## Checklist
- [ ] Promedio con `aggregate` o calculado en Python (redondeado a 1 decimal)
- [ ] No regresar el `ObjectId` crudo (da error de serialización)
- [ ] Algunas reseñas semilla
