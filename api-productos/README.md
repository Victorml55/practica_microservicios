# api-productos

- **Responsable:** Dennis
- **Stack:** PHP 8.3 (servidor embebido) + SQLite o JSON
- **Puerto:** 8002

## Endpoints
| Método | Ruta | Body / Respuesta |
|---|---|---|
| GET | `/api/productos` | Lista. Opcional `?q=` y `?categoria=` |
| GET | `/api/productos/{id}` | Detalle o 404 |
| POST | `/api/productos` | **Solo admin (Bearer)**. `{nombre, descripcion, precio, categoria, imagen_url}` → 201 |

## Correr solo este servicio
```bash
docker compose up --build api-productos
curl http://localhost:8002/health
```

## Checklist
- [ ] 10 productos semilla (ids 1–10) con varias categorías e imágenes
- [ ] Validar campos obligatorios → 400
