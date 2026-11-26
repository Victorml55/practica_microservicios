# api-usuarios

- **Responsable:** Victor
- **Stack:** PHP 8.3 (servidor embebido) + SQLite + JWT HS256 (sin librerías externas)
- **Puerto:** 8001

## Endpoints

| Método | Ruta | Auth | Body → Respuesta |
|---|---|---|---|
| POST | `/api/usuarios/registro` | — | `{nombre, correo, contrasena}` → 201 `{id, nombre, correo, rol, creado_en}` |
| POST | `/api/usuarios/login` | — | `{correo, contrasena}` → 200 `{token, usuario}` |
| GET | `/api/usuarios/{id}` | Bearer | Perfil sin contraseña. Solo el propio usuario o un admin (403 si no) |

Errores: 400 (campos faltantes, correo inválido o duplicado, contraseña < 6), 401 (credenciales o token), 403, 404.

## Token
- HS256 firmado con `JWT_SECRET`, dura 2 horas.
- Payload: `{ sub: <id>, rol: "cliente"|"admin", nombre, iat, exp }`.
- Para validarlo en otro servicio PHP se puede copiar `src/jwt.php` (`jwt_verificar($token)`).

## Usuarios semilla
| id | correo | contraseña | rol |
|---|---|---|---|
| 1 | demo@tienda.com | demo123 | cliente |
| 2 | admin@tienda.com | admin123 | admin |

## Correr solo este servicio
```bash
docker compose up --build api-usuarios
curl -X POST http://localhost:8001/api/usuarios/login \
  -H "Content-Type: application/json" \
  -d '{"correo":"demo@tienda.com","contrasena":"demo123"}'
```
