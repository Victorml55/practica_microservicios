# api-pagos

- **Responsable:** Dennis
- **Stack:** Java 21 + Spring Boot 3 + PostgreSQL (`pagos_db`)
- **Puerto:** 8007

## Endpoints
| Método | Ruta | Body / Respuesta |
|---|---|---|
| POST | `/api/pagos/procesar` | `{pedido_id, monto, metodo_pago}` → 201 aprobado / 400 rechazado |
| GET | `/api/pagos/pedido/{pedido_id}` | Comprobante `{pedido_id, monto, metodo_pago, estado, referencia, fecha}` |

## Correr solo este servicio
```bash
docker compose up --build api-pagos
curl http://localhost:8007/health
```

## Checklist
- [ ] `@Entity Transaccion` + `JpaRepository` (las tablas se crean solas con `ddl-auto=update`)
- [ ] Regla de simulación clara (p. ej. rechazar si el monto es mayor a 10000 o si `metodo_pago = "rechazar"`)
- [ ] Si se aprueba: `PATCH ${PEDIDOS_URL}/api/pedidos/{id}/estado {"estado":"pagado"}` con `RestClient`
- [ ] Usar `@JsonProperty` o `spring.jackson.property-naming-strategy=SNAKE_CASE` para `snake_case`
