package com.tienda.pagos;

import java.math.BigDecimal;
import java.util.LinkedHashMap;
import java.util.Map;
import java.util.UUID;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.http.HttpStatus;
import org.springframework.http.MediaType;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.client.RestClient;

@RestController
public class PagosController {

    private final TransaccionRepository repository;
    private final RestClient restClient;
    private final String pedidosUrl;

    public PagosController(
            TransaccionRepository repository,
            @Value("${pedidos.url}") String pedidosUrl) {
        this.repository = repository;
        this.pedidosUrl = pedidosUrl;
        this.restClient = RestClient.create();
    }

    @GetMapping("/health")
    public Map<String, Object> health() {
        return Map.of("servicio", "api-pagos", "ok", true);
    }

    public record PagoRequest(Long pedidoId, BigDecimal monto, String metodoPago) {}

    @PostMapping("/api/pagos/procesar")
    public ResponseEntity<?> procesar(@RequestBody PagoRequest body) {
        if (body.pedidoId() == null || body.monto() == null
                || body.metodoPago() == null || body.metodoPago().isBlank()) {
            return ResponseEntity.status(HttpStatus.BAD_REQUEST)
                    .body(Map.of("error", "Faltan campos obligatorios: pedido_id, monto, metodo_pago"));
        }

        if (body.monto().compareTo(BigDecimal.ZERO) <= 0) {
            return ResponseEntity.status(HttpStatus.BAD_REQUEST)
                    .body(Map.of("error", "El monto debe ser mayor a 0"));
        }

        boolean rechazado = body.monto().compareTo(new BigDecimal("10000")) > 0
                || "rechazar".equalsIgnoreCase(body.metodoPago().trim());

        Transaccion tx = new Transaccion();
        tx.setPedidoId(body.pedidoId());
        tx.setMonto(body.monto());
        tx.setMetodoPago(body.metodoPago().trim());
        tx.setReferencia("PAY-" + UUID.randomUUID().toString().substring(0, 8).toUpperCase());
        tx.setEstado(rechazado ? "rechazado" : "aprobado");

        Transaccion guardada = repository.save(tx);

        if (rechazado) {
            return ResponseEntity.status(HttpStatus.BAD_REQUEST)
                    .body(Map.of(
                            "error", "Pago rechazado (monto mayor a 10000 o metodo rechazado)",
                            "referencia", guardada.getReferencia(),
                            "estado", guardada.getEstado()
                    ));
        }

        // Notificar a api-pedidos si el pago fue aprobado
        try {
            restClient.patch()
                    .uri(pedidosUrl + "/api/pedidos/" + body.pedidoId() + "/estado")
                    .contentType(MediaType.APPLICATION_JSON)
                    .body(Map.of("estado", "pagado"))
                    .retrieve()
                    .toBodilessEntity();
        } catch (Exception e) {
            System.err.println("Aviso: no se pudo notificar a api-pedidos (" + pedidosUrl + "): " + e.getMessage());
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
