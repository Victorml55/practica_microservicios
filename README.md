# tienda-microservicios

Plataforma de comercio electrónico con **arquitectura de microservicios multi-stack**: 8 APIs en distintos lenguajes y bases de datos, más un frontend en Angular, todo orquestado con Docker Compose en la red `red-tienda`.

## Equipo y responsabilidades

| Integrante | Servicios | Stack | Puerto |
|---|---|---|---|
| **Victor** | `api-usuarios` | PHP (JWT) | 8001 |
| | `api-pedidos` | PHP + PostgreSQL | 8006 |
| | `docker-compose.yml`, `README.md`, integración final | Docker | — |
| **Dennis** | `api-productos` | PHP | 8002 |
| | `api-pagos` | Java (Spring Boot) + PostgreSQL | 8007 |
| **Ricardo** | `api-envios` | Python (FastAPI) | 8003 |
| | `api-inventario` | Go (Gin) | 8004 |
| | `api-resenas` | Python (FastAPI) + MongoDB | 8008 |
| **Omar** | `frontend-tienda` | Angular | 4200 |
| | `api-carrito` | Node.js (Express) | 8005 |

Cada carpeta tiene su propio `README.md` con los endpoints que le tocan y su checklist.

## Cómo levantar el proyecto

```bash
cp .env.example .env
docker compose up --build
```

Levantar solo un servicio (y lo que necesite):

```bash
docker compose up --build api-pedidos      # también levanta postgres
```

Comprobar que un servicio está vivo: `GET http://localhost:<puerto>/health`

## Acuerdos del equipo (obligatorios)

### 1. Formato de datos
- Todas las respuestas en **JSON**, campos en **`snake_case`** (`producto_id`, `usuario_id`, `costo_envio`).
- IDs **enteros** en todos los servicios (también en MongoDB se guarda `producto_id` y `usuario_id` como número; el `_id` de Mongo no se expone o se convierte a string).
- Precios y montos como número decimal (`199.90`), nunca como texto.
- Errores siempre con la forma: `{ "error": "mensaje" }`.

### 2. Códigos HTTP
| Código | Cuándo |
|---|---|
| 200 | Consulta o actualización correcta |
| 201 | Recurso creado (registro, producto, pedido, reseña, guía) |
| 400 | Datos faltantes o inválidos / stock insuficiente / pago rechazado |
| 401 | Token inválido o ausente |
| 404 | No existe el recurso |
| 500 | Error interno |

### 3. Autenticación (JWT)
- Solo **api-usuarios** genera el token en `POST /api/usuarios/login`.
- Algoritmo **HS256**, firmado con la variable `JWT_SECRET` del `.env` (la misma para todos).
- Payload: `{ "sub": <usuario_id>, "rol": "cliente" | "admin", "exp": <timestamp> }`.
- El frontend lo manda en cada petición como `Authorization: Bearer <token>`.
- Respuesta del login: `{ "token": "...", "usuario": { "id", "nombre", "correo", "rol" } }`.

### 4. CORS
Todos los servicios permiten el origen `http://localhost:4200` (variable `CORS_ORIGIN`) y los métodos `GET, POST, PATCH, DELETE, OPTIONS`, con el header `Authorization`. El esqueleto de cada servicio ya lo trae configurado: **no lo borren**.

### 5. URLs entre servicios
- **Servicio → servicio** (dentro de Docker): por nombre del contenedor, p. ej. `http://api-inventario:8004`. Nunca `localhost`. Las URLs vienen en variables de entorno (`INVENTARIO_URL`, `PEDIDOS_URL`, etc.), ya definidas en el compose.
- **Frontend → servicios**: Angular corre en el navegador, así que usa `http://localhost:<puerto>` (ver `frontend-tienda/src/app/core/api.config.ts`).

### 6. Bases de datos
- Un solo contenedor `postgres` con **dos bases separadas**: `pedidos_db` (Victor) y `pagos_db` (Dennis). Se crean con `db/postgres/init.sql`.
- `mongo` para `resenas_db` (Ricardo).
- `api-usuarios` y `api-productos` pueden usar SQLite o un arreglo/JSON en memoria (el documento no exige una base de datos).
- `api-carrito` e `api-inventario` pueden guardar en memoria. Cada uno debe cargar **datos semilla** al arrancar (productos 1–10 con stock, por ejemplo).

### 7. Flujo de pago (quién llama a quién)
```
Frontend ──POST /api/pedidos──────────────▶ api-pedidos   (estado: pendiente)
Frontend ──POST /api/pagos/procesar───────▶ api-pagos
                     si aprobado:
api-pagos ──PATCH /api/pedidos/{id}/estado {"estado":"pagado"}──▶ api-pedidos
                     api-pedidos orquesta:
                       ├─ POST   api-inventario /api/inventario/reservar   (por cada item)
                       ├─ DELETE api-carrito    /api/carrito/{usuario_id}/vaciar
                       └─ POST   api-envios     /api/envios/generar  → guarda la guía
```
- **Dennis** solo hace una llamada HTTP desde pagos hacia pedidos.
- **Victor** concentra la orquestación en pedidos.
- **Ricardo** y **Omar** solo exponen sus endpoints.

### 8. Datos semilla compartidos
Para que todo cuadre en las pruebas, usamos los mismos IDs:
- Productos **1 a 10** (api-productos) → con stock en api-inventario.
- Usuario de prueba: `demo@tienda.com` / `demo123` (api-usuarios), con `id = 1`.

### 9. Git
- Un solo repo (monorepo). **Cada quien trabaja solo en sus carpetas.**
- Ramas: `feature/victor-usuarios-pedidos`, `feature/dennis-productos-pagos`, `feature/ricardo-envios-inventario-resenas`, `feature/omar-frontend-carrito`.
- Se hace merge a `main` por Pull Request cuando el servicio **levanta solo con su Dockerfile** y responde `/health`.
- Cambios a `docker-compose.yml` o a este README: avisar a Victor.
- Nunca subir `.env`, `node_modules`, `vendor`, `target`.

## Entregas por fases

1. **Fase 1:** cada servicio responde `/health` y sus endpoints con datos de prueba, levantado de forma aislada.
2. **Fase 2:** endpoints completos con persistencia, probados con Postman/Thunder Client.
3. **Fase 3:** integración con `docker compose up` y el flujo completo desde el frontend.

## Caso de prueba completo (para la demo)

1. Login → `api-usuarios`
2. Catálogo → `api-productos` + disponibilidad en `api-inventario` + reseñas en `api-resenas`
3. Agregar al carrito → `api-carrito`
4. Cotizar envío → `api-envios`, crear la orden → `api-pedidos`, pagar → `api-pagos`
5. Al aprobarse el pago: se descuenta el inventario, se vacía el carrito, el pedido pasa a `pagado` y se genera la guía de rastreo.
6. Ver "Mis Pedidos" con el estado y la guía.
