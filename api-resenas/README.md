# api-resenas

- **Responsable:** Ricardo
- **Stack:** Python 3.12 + FastAPI + MongoDB (`resenas_db`)
- **Puerto:** 8008

## Endpoints
| Método | Ruta | Body / Respuesta |
|---|---|---|
| GET | `/api/resenas/producto/{producto_id}` | `{producto_id, promedio, total, resenas:[...]}` |
| POST | `/api/resenas` | **Bearer**. `{producto_id, calificacion (1-5), comentario}` → 201. El `usuario_id` se toma del token; una reseña por usuario y producto (400 si se repite) |

## Correr solo este servicio
```bash
docker compose up --build api-resenas
curl http://localhost:8008/health
```

## Checklist
- [x] Promedio con `aggregate` o calculado en Python (redondeado a 1 decimal)
- [x] No regresar el `ObjectId` crudo (da error de serialización)
- [x] Algunas reseñas semilla

## Notas de implementación
- Cada reseña se regresa como `{id, producto_id, usuario_id, calificacion, comentario, fecha}` (`id` es el ObjectId en texto).
- Las reseñas semilla se insertan al arrancar solo si la colección está vacía.
- Un producto sin reseñas regresa `{promedio: 0.0, total: 0, resenas: []}`.
