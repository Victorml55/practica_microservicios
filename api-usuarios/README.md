# api-usuarios

- **Responsable:** Victor
- **Stack:** PHP 8.3 (servidor embebido) + SQLite
- **Puerto:** 8001

## Endpoints
| Método | Ruta | Body / Respuesta |
|---|---|---|
| POST | `/api/usuarios/registro` | `{nombre, correo, contrasena}` → 201 `{id, nombre, correo, rol}` |
| POST | `/api/usuarios/login` | `{correo, contrasena}` → `{token, usuario}` |
| GET | `/api/usuarios/{id}` | Perfil sin contraseña |

## Correr solo este servicio
```bash
docker compose up --build api-usuarios
curl http://localhost:8001/health
```

## Checklist
- [ ] Contraseñas con `password_hash` / `password_verify`
- [ ] JWT HS256 con `JWT_SECRET` (payload `sub`, `rol`, `exp`)
- [ ] Correo duplicado → 400
- [ ] Usuario semilla `demo@tienda.com` / `demo123` con id 1
