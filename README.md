# tienda-microservicios

Tienda en línea construida con **arquitectura de microservicios multi-stack**: 8 APIs en 5 lenguajes, 2 bases de datos y un frontend en Angular, todo orquestado con **Docker Compose** en la red `red-tienda`.

```
                         ┌──────────────────────┐
                         │  frontend-tienda     │  Angular · :4200
                         │  (navegador)         │
                         └──────────┬───────────┘
                                    │ HTTP + JWT (Authorization: Bearer)
 ┌──────────────┬──────────────┬────┴─────────┬──────────────┬──────────────┬──────────────┬──────────────┐
 │ api-usuarios │ api-productos│ api-envios   │ api-inventario│ api-carrito │ api-pedidos  │ api-pagos    │ api-resenas
 │ PHP · 8001   │ PHP · 8002   │ Python · 8003│ Go · 8004    │ Node · 8005  │ PHP · 8006   │ Java · 8007  │ Python · 8008
 │ SQLite       │ SQLite       │ memoria      │ memoria      │ memoria      │ PostgreSQL   │ PostgreSQL   │ MongoDB
 └──────────────┴──────────────┴──────────────┴──────────────┴──────────────┴──────────────┴──────────────┘
```

## Equipo

| Integrante | Servicios | Stack | Puerto |
|---|---|---|---|
| **Victor** | `api-usuarios` | PHP 8.3 + SQLite (JWT) | 8001 |
| | `api-pedidos` | PHP 8.3 + PostgreSQL (`pedidos_db`) | 8006 |
| | `docker-compose.yml`, integración | Docker | — |
| **Dennis** | `api-productos` | PHP 8.3 + SQLite | 8002 |
| | `api-pagos` | Java 21 + Spring Boot 3.4 + PostgreSQL (`pagos_db`) | 8007 |
| **Ricardo** | `api-envios` | Python 3.12 + FastAPI | 8003 |
| | `api-inventario` | Go 1.23 + Gin | 8004 |
| | `api-resenas` | Python 3.12 + FastAPI + MongoDB (`resenas_db`) | 8008 |
| **Omar** | `frontend-tienda` | Angular 20 + Nginx | 4200 |
| | `api-carrito` | Node.js 22 + Express | 8005 |

Cada carpeta tiene su propio `README.md` con el detalle de sus endpoints.

---

## Requisitos

Solo **Docker Desktop** (con Docker Compose). No hace falta instalar PHP, Python, Go, Java ni Node: cada servicio se compila dentro de su contenedor.

## Cómo levantar el proyecto

```bash
cp .env.example .env          # y cambia JWT_SECRET e INTERNAL_KEY (ver abajo)
docker compose up --build
```

La primera vez tarda varios minutos (descarga imágenes y compila Java, Go y Angular). Cuando termine:

- Tienda: **http://localhost:4200**
- Documentación interactiva: http://localhost:8003/docs (envíos) y http://localhost:8008/docs (reseñas)
- Salud de cada servicio: `GET http://localhost:<puerto>/health`

Para detener todo: `Ctrl + C` y luego `docker compose down`.
Para borrar también las bases de datos (Postgres y Mongo): `docker compose down -v`.

Levantar solo un servicio (y lo que necesite):
```bash
docker compose up --build api-pedidos
```

### Variables de entorno (`.env`)

| Variable | Para qué sirve |
|---|---|
| `JWT_SECRET` | Firma y valida los tokens de usuario. La usan todos los servicios que revisan tokens. |
| `INTERNAL_KEY` | Clave para llamadas **servicio → servicio** (header `X-Internal-Key`). Sin ella el flujo de pago no funciona. |
| `POSTGRES_USER`, `POSTGRES_PASSWORD` | Usuario de PostgreSQL (`pedidos_db` y `pagos_db`). |
| `MONGO_INITDB_ROOT_USERNAME`, `MONGO_INITDB_ROOT_PASSWORD` | Usuario de MongoDB (`resenas_db`). |

Genera valores seguros con `openssl rand -hex 32`. **Nunca subas `.env` al repositorio.**

> Si ya tenías un `.env` de una versión anterior, vuelve a copiarlo desde `.env.example`: ahora incluye `INTERNAL_KEY`.

---

## Usuarios de prueba

| Correo | Contraseña | Rol |
|---|---|---|
| `demo@tienda.com` | `demo123` | cliente (id 1) |
| `admin@tienda.com` | `admin123` | admin (id 2) |

Datos semilla compartidos: productos **1 a 10** (api-productos) con stock en api-inventario, un carrito inicial para el usuario 1 y algunas reseñas.

---

## Seguridad

Cada endpoint pide una de tres cosas:

| Nivel | Cómo se identifica | Quién lo usa |
|---|---|---|
| **Público** | nada | catálogo, inventario (consulta), cotizar envío, rastreo, leer reseñas, login/registro |
| **Usuario** | `Authorization: Bearer <token>` (JWT HS256, dura 2 h) | carrito, crear pedido, mis pedidos, publicar reseña, pagar |
| **Interno / admin** | `X-Internal-Key: <INTERNAL_KEY>` o token con `rol: admin` | cambiar estado de pedidos, reservar/reabastecer inventario, generar guías, crear productos |

Reglas importantes:
- **El servidor no confía en el navegador.** api-pedidos toma el precio de cada producto de api-productos, valida el stock en api-inventario y calcula el envío con api-envios.
- **api-pagos verifica el pedido antes de cobrar:** que exista, que esté `pendiente` y que el monto sea exactamente su `total`.
- Un usuario solo puede ver o modificar **su** carrito y **sus** pedidos (un admin puede todo).
- El `usuario_id` de una reseña sale del token, no del body. Una reseña por usuario y producto.
- Los errores 500 no muestran detalles internos al cliente; el detalle queda en el log del contenedor.

---

## Acuerdos del equipo

### Formato de datos
- Respuestas en **JSON**, campos en **`snake_case`** (`producto_id`, `costo_envio`).
- IDs **enteros**. En MongoDB el `_id` se expone como texto en el campo `id`.
- Precios y montos como número decimal (`199.90`), nunca como texto.
- Errores siempre con la forma `{ "error": "mensaje" }`.

### Códigos HTTP
| Código | Cuándo |
|---|---|
| 200 | Consulta o actualización correcta |
| 201 | Recurso creado (registro, producto, pedido, pago, reseña, guía) |
| 400 | Datos faltantes o inválidos / stock insuficiente / pago rechazado |
| 401 | Token inválido o ausente |
| 403 | Token válido pero sin permiso (otro usuario o no es admin) |
| 404 | No existe el recurso |
| 500 | Error interno |

### URLs entre servicios
- **Servicio → servicio** (dentro de Docker): por nombre del contenedor, p. ej. `http://api-inventario:8004`. Vienen en variables de entorno del compose.
- **Frontend → servicios**: `http://localhost:<puerto>` (ver `frontend-tienda/src/app/core/api.config.ts`), porque Angular corre en el navegador.

### CORS
Todos los servicios permiten el origen `http://localhost:4200` (`CORS_ORIGIN`), los métodos `GET, POST, PATCH, DELETE, OPTIONS` y el header `Authorization`.

---

## Endpoints

### api-usuarios · 8001
| Método | Ruta | Auth | Body → Respuesta |
|---|---|---|---|
| POST | `/api/usuarios/registro` | — | `{nombre, correo, contrasena}` → 201 usuario |
| POST | `/api/usuarios/login` | — | `{correo, contrasena}` → `{token, usuario:{id, nombre, correo, rol, creado_en}}` |
| GET | `/api/usuarios/{id}` | Usuario (el mismo) / admin | perfil sin contraseña |

### api-productos · 8002
| Método | Ruta | Auth | Body → Respuesta |
|---|---|---|---|
| GET | `/api/productos?q=&categoria=` | — | lista de `{id, nombre, descripcion, precio, categoria, imagen_url}` |
| GET | `/api/productos/{id}` | — | un producto o 404 |
| POST | `/api/productos` | Admin | `{nombre, descripcion, precio, categoria, imagen_url}` → 201 |

### api-envios · 8003
| Método | Ruta | Auth | Body → Respuesta |
|---|---|---|---|
| POST | `/api/envios/cotizar` | — | `{codigo_postal: "06600", cantidad: 3}` → `{costo_envio, dias_estimados}` |
| POST | `/api/envios/generar` | Interno / admin | `{pedido_id}` → 201 `{guia: "TM-000001", pedido_id, estado: "preparando"}` |
| GET | `/api/envios/rastreo/{guia}` | — | `{guia, pedido_id, estado}` (`preparando` → `en_transito` → `entregado`) |
| PATCH | `/api/envios/rastreo/{guia}/avanzar` | Admin | pasa al siguiente estado (para la demo) |

Tarifa: costo base por zona (primer dígito del CP, de $79 a $129) + $15 por pieza extra.

### api-inventario · 8004
| Método | Ruta | Auth | Body → Respuesta |
|---|---|---|---|
| GET | `/api/inventario/{producto_id}` | — | `{producto_id, disponibles}` o 404 |
| POST | `/api/inventario/reservar` | Interno / admin | `{producto_id, cantidad}` → descuenta; 400 si no alcanza |
| POST | `/api/inventario/reabastecer` | Interno / admin | `{producto_id, cantidad}` → suma (da de alta si no existía) |

### api-carrito · 8005
| Método | Ruta | Auth | Body → Respuesta |
|---|---|---|---|
| GET | `/api/carrito/{usuario_id}` | Usuario (dueño) / admin | `{usuario_id, items:[{producto_id, cantidad, nombre, precio}]}` |
| POST | `/api/carrito/{usuario_id}/agregar` | Usuario (dueño) / admin | `{producto_id, cantidad}` → 201 (suma si ya estaba) |
| DELETE | `/api/carrito/{usuario_id}/vaciar` | Interno / dueño | vacía el carrito |

### api-pedidos · 8006
| Método | Ruta | Auth | Body → Respuesta |
|---|---|---|---|
| POST | `/api/pedidos` | Usuario (dueño) | `{usuario_id, direccion, codigo_postal, items:[{producto_id, cantidad}]}` → 201 pedido `pendiente` |
| GET | `/api/pedidos/usuario/{usuario_id}` | Usuario (dueño) / admin | historial con `estado` y `guia` |
| GET | `/api/pedidos/{id}` | Usuario (dueño) / admin / interno | un pedido |
| PATCH | `/api/pedidos/{id}/estado` | Interno / admin | `{estado}`; transiciones `pendiente → pagado → enviado`, o `→ cancelado` |

Forma del pedido:
```json
{
  "id": 1, "usuario_id": 1, "direccion": "Calle 1", "codigo_postal": "06600",
  "items": [{ "producto_id": 2, "cantidad": 2, "precio_unitario": 549.0 }],
  "subtotal": 1098.0, "costo_envio": 94.0, "total": 1192.0,
  "estado": "pagado", "guia": "TM-000001",
  "creado_en": "...", "actualizado_en": "..."
}
```

### api-pagos · 8007
| Método | Ruta | Auth | Body → Respuesta |
|---|---|---|---|
| POST | `/api/pagos/procesar` | Usuario | `{pedido_id, monto, metodo_pago}` → 201 comprobante / 400 rechazado |
| GET | `/api/pagos/pedido/{pedido_id}` | — | `{pedido_id, monto, metodo_pago, estado, referencia, fecha}` |

`metodo_pago` acepta cualquier texto (`tarjeta`, `paypal`, `transferencia`). Simulación de rechazo: `metodo_pago = "rechazar"` o **monto mayor a $10,000** (por eso la Laptop, de $18,999.90, siempre se rechaza: sirve para mostrar un pago rechazado en la demo).

### api-resenas · 8008
| Método | Ruta | Auth | Body → Respuesta |
|---|---|---|---|
| GET | `/api/resenas/producto/{producto_id}` | — | `{producto_id, promedio, total, resenas:[{id, usuario_id, calificacion, comentario, fecha}]}` |
| POST | `/api/resenas` | Usuario | `{producto_id, calificacion (1-5), comentario}` → 201 (una por usuario y producto) |

---

## Flujo de compra (quién llama a quién)

```
Frontend ──POST /api/envios/cotizar────────────────▶ api-envios          (muestra el costo)
Frontend ──POST /api/pedidos {items, cp, dirección}─▶ api-pedidos
              api-pedidos ──GET precio──────────────▶ api-productos
              api-pedidos ──GET stock───────────────▶ api-inventario
              api-pedidos ──POST cotizar────────────▶ api-envios
              → pedido "pendiente" con el total calculado por el servidor
Frontend ──POST /api/pagos/procesar {pedido_id, monto = total}──▶ api-pagos
              api-pagos ──GET /api/pedidos/{id} (X-Internal-Key)──▶ api-pedidos   (valida estado y total)
              si se aprueba:
              api-pagos ──PATCH /api/pedidos/{id}/estado {"pagado"} (X-Internal-Key)──▶ api-pedidos
                  api-pedidos orquesta (todas con X-Internal-Key):
                    ├─ POST   api-inventario /reservar   (por item; si uno falla, devuelve lo reservado → 400)
                    ├─ DELETE api-carrito    /{usuario_id}/vaciar
                    └─ POST   api-envios     /generar     → guarda la guía
              si pedidos no puede confirmar (p. ej. ya no hay stock) → el pago queda "rechazado"
```

## Caso de prueba para la demo

1. Entrar a http://localhost:4200 con `demo@tienda.com` / `demo123`.
2. Catálogo → abrir un producto: se ve precio, stock (api-inventario) y reseñas (api-resenas).
3. Agregar al carrito (api-carrito).
4. Carrito → código postal `06600` → **Cotizar envío** (api-envios) → dirección → **Pagar**.
5. Se crea el pedido (api-pedidos) y se cobra (api-pagos). Al aprobarse: baja el inventario, se vacía el carrito, el pedido pasa a `pagado` y se genera la guía `TM-…`.
6. **Mis pedidos**: estado, total y guía; botón **Consultar** para el rastreo.
7. Pago rechazado: elegir el método "Rechazar (prueba)" o comprar la Laptop (más de $10,000).

### Prueba rápida por terminal
```bash
TOKEN=$(curl -s -X POST localhost:8001/api/usuarios/login -H "Content-Type: application/json" \
  -d '{"correo":"demo@tienda.com","contrasena":"demo123"}' | python3 -c 'import sys,json;print(json.load(sys.stdin)["token"])')

# Crear pedido (el servidor calcula precios y envío)
curl -s -X POST localhost:8006/api/pedidos -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"usuario_id":1,"direccion":"Calle 1","codigo_postal":"06600","items":[{"producto_id":2,"cantidad":1}]}'

# Pagar con el total que regresó el pedido (ej. pedido 1)
curl -s -X POST localhost:8007/api/pagos/procesar -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"pedido_id":1,"monto":<total>,"metodo_pago":"tarjeta"}'

curl -s localhost:8006/api/pedidos/usuario/1 -H "Authorization: Bearer $TOKEN"
```

---

## Estructura del repositorio

```
├── api-usuarios/      PHP     · login, registro, JWT
├── api-productos/     PHP     · catálogo
├── api-envios/        Python  · cotización, guías, rastreo
├── api-inventario/    Go      · stock
├── api-carrito/       Node.js · carrito por usuario
├── api-pedidos/       PHP     · pedidos + orquestación del pago
├── api-pagos/         Java    · pagos simulados
├── api-resenas/       Python  · reseñas en MongoDB
├── frontend-tienda/   Angular · tienda web (Nginx)
├── db/postgres/init.sql      crea pedidos_db y pagos_db
├── docker-compose.yml
└── .env.example
```

## Datos y persistencia

| Servicio | Dónde guarda | ¿Sobrevive a reiniciar el contenedor? |
|---|---|---|
| api-pedidos, api-pagos | PostgreSQL (volumen `pg-data`) | Sí |
| api-resenas | MongoDB (volumen `mongo-data`) | Sí |
| api-usuarios, api-productos | SQLite dentro del contenedor | Solo mientras no se recree el contenedor |
| api-carrito, api-inventario, api-envios | Memoria | No: vuelven a los datos semilla |

Por eso, si reinicias solo algunos servicios, los datos pueden no cuadrar (p. ej. un pedido pagado con una guía que api-envios ya no recuerda). Para la demo, levanta todo junto.

## Git

- Monorepo. Cada quien trabaja en sus carpetas, en su rama `feature/<nombre>-...`.
- La rama **`compilacion-completa-sin-errores`** integra las cuatro ramas con las correcciones de seguridad e integración; se mergea a `main` por Pull Request cuando `docker compose up --build` levanta todo y la demo funciona.
- Cambios a `docker-compose.yml` o a este README: avisar a Victor.
- Nunca subir `.env`, `node_modules`, `vendor`, `target` ni bases de datos.

## Solución de problemas

| Síntoma | Causa probable |
|---|---|
| `command not found: docker` | Docker Desktop no está abierto o la Terminal se abrió antes de instalarlo: ciérrala y ábrela otra vez. |
| El pago falla con "No se pudo confirmar el pedido" | Falta `INTERNAL_KEY` en `.env` o no es igual en todos los servicios: vuelve a copiar `.env.example` y `docker compose up --build`. |
| 401 en todas las peticiones del frontend | El token venció (2 h): vuelve a iniciar sesión. |
| "Stock insuficiente" al pagar | Otra compra se llevó las unidades: el pedido queda `pendiente` y se puede reintentar con menos piezas. |
| Puerto ocupado | Otro programa usa ese puerto (p. ej. un Postgres local en 5432): deténlo o cambia el puerto en el compose. |
