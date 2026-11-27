package com.tienda.pagos;

import java.math.BigDecimal;
import java.math.RoundingMode;
import java.util.LinkedHashMap;
import java.util.Map;
import java.util.UUID;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.http.HttpStatus;
import org.springframework.http.MediaType;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.client.RestClient;
import org.springframework.web.client.RestClientResponseException;

@RestController
public class PagosController {

    private final TransaccionRepository repository;
    private final RestClient restClient;
    private final String pedidosUrl;
    private final String internalKey;

    private static final BigDecimal LIMITE = new BigDecimal("10000");

    public PagosController(
            TransaccionRepository repository,
            @Value("${pedidos.url}") String pedidosUrl,
            @Value("${internal.key:}") String internalKey) {
        this.repository = repository;
        this.pedidosUrl = pedidosUrl;
        this.internalKey = internalKey;
        this.restClient = RestClient.create();
    }

    @GetMapping("/health")
    public Map<String, Object> health() {
        return Map.of("servicio", "api-pagos", "ok", true);
    }

    public record PagoRequest(Long pedidoId, BigDecimal monto, String metodoPago) {}

    private static ResponseEntity<?> error(HttpStatus status, String mensaje) {
        return ResponseEntity.status(status).body(Map.of("error", mensaje));
    }

    private static String mensajeDe(RestClientResponseException e) {
        try {
            Map<?, ?> cuerpo = e.getResponseBodyAs(Map.class);
            if (cuerpo != null && cuerpo.get("error") != null) return cuerpo.get("error").toString();
        } catch (Exception ignorada) { }
        return "HTTP " + e.getStatusCode().value();
    }

    @PostMapping("/api/pagos/procesar")
    public ResponseEntity<?> procesar(@RequestBody PagoRequest body) {
        if (body.pedidoId() == null || body.monto() == null
                || body.metodoPago() == null || body.metodoPago().isBlank()) {
            return error(HttpStatus.BAD_REQUEST, "Faltan campos obligatorios: pedido_id, monto, metodo_pago");
        }
        BigDecimal monto = body.monto().setScale(2, RoundingMode.HALF_UP);
        if (monto.compareTo(BigDecimal.ZERO) <= 0) {
            return error(HttpStatus.BAD_REQUEST, "El monto debe ser mayor a 0");
        }

        // 1) Consultar el pedido real en api-pedidos (no se confía en el monto del navegador)
        Map<?, ?> pedido;
        try {
            pedido = restClient.get()
                    .uri(pedidosUrl + "/api/pedidos/" + body.pedidoId())
                    .header("X-Internal-Key", internalKey)
                    .retrieve()
                    .body(Map.class);
        } catch (RestClientResponseException e) {
            if (e.getStatusCode().value() == 404) {
                return error(HttpStatus.NOT_FOUND, "Pedido " + body.pedidoId() + " no encontrado");
            }
            return error(HttpStatus.INTERNAL_SERVER_ERROR, "No se pudo consultar el pedido: " + mensajeDe(e));
        } catch (Exception e) {
            return error(HttpStatus.INTERNAL_SERVER_ERROR, "api-pedidos no responde");
        }

        String estadoPedido = String.valueOf(pedido.get("estado"));
        if (!"pendiente".equals(estadoPedido)) {
            return error(HttpStatus.BAD_REQUEST, "El pedido ya está en estado '" + estadoPedido + "'");
        }
        BigDecimal total = new BigDecimal(String.valueOf(pedido.get("total"))).setScale(2, RoundingMode.HALF_UP);
        if (monto.compareTo(total) != 0) {
            return error(HttpStatus.BAD_REQUEST, "El monto " + monto + " no coincide con el total del pedido " + total);
        }

        // 2) Regla de simulación: se rechaza si el monto supera 10000 o si metodo_pago = "rechazar"
        String motivoRechazo = null;
        if (monto.compareTo(LIMITE) > 0) motivoRechazo = "Pago rechazado: el monto supera el límite de 10000";
        else if ("rechazar".equalsIgnoreCase(body.metodoPago().trim())) motivoRechazo = "Pago rechazado por el método de pago";

        // 3) Si se aprueba, confirmar en api-pedidos (reserva inventario, vacía carrito, genera guía).
        //    Si pedidos no puede confirmar (p. ej. ya no hay stock), el pago queda rechazado.
        if (motivoRechazo == null) {
            try {
                restClient.patch()
                        .uri(pedidosUrl + "/api/pedidos/" + body.pedidoId() + "/estado")
                        .header("X-Internal-Key", internalKey)
                        .contentType(MediaType.APPLICATION_JSON)
                        .body(Map.of("estado", "pagado"))
                        .retrieve()
                        .toBodilessEntity();
            } catch (RestClientResponseException e) {
                motivoRechazo = "No se pudo confirmar el pedido: " + mensajeDe(e);
            } catch (Exception e) {
                motivoRechazo = "No se pudo confirmar el pedido: api-pedidos no responde";
            }
        }

        Transaccion tx = new Transaccion();
        tx.setPedidoId(body.pedidoId());
        tx.setMonto(monto);
        tx.setMetodoPago(body.metodoPago().trim());
        tx.setReferencia("PAY-" + UUID.randomUUID().toString().substring(0, 8).toUpperCase());
        tx.setEstado(motivoRechazo == null ? "aprobado" : "rechazado");
        Transaccion guardada = repository.save(tx);

        if (motivoRechazo != null) {
            Map<String, Object> res = new LinkedHashMap<>();
            res.put("error", motivoRechazo);
            res.put("referencia", guardada.getReferencia());
            res.put("estado", guardada.getEstado());
            return ResponseEntity.status(HttpStatus.BAD_REQUEST).body(res);
        }
        return ResponseEntity.status(HttpStatus.CREATED).body(formatearComprobante(guardada));
    }

    @GetMapping("/api/pagos/pedido/{pedidoId}")
    public ResponseEntity<?> porPedido(@PathVariable Long pedidoId) {
        return repository.findFirstByPedidoIdOrderByFechaDesc(pedidoId)
                .<ResponseEntity<?>>map(tx -> ResponseEntity.ok(formatearComprobante(tx)))
                .orElseGet(() -> ResponseEntity.status(HttpStatus.NOT_FOUND)
                        .body(Map.of("error", "Comprobante no encontrado para el pedido " + pedidoId)));
    }

    private Map<String, Object> formatearComprobante(Transaccion tx) {
        Map<String, Object> res = new LinkedHashMap<>();
        res.put("pedido_id", tx.getPedidoId());
        res.put("monto", tx.getMonto());
        res.put("metodo_pago", tx.getMetodoPago());
        res.put("estado", tx.getEstado());
        res.put("referencia", tx.getReferencia());
        res.put("fecha", tx.getFecha().toString());
        return res;
    }
}
