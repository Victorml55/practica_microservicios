package com.tienda.pagos;

import java.util.Map;

import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

@RestController
public class PagosController {

    @GetMapping("/health")
    public Map<String, Object> health() {
        return Map.of("servicio", "api-pagos", "ok", true);
    }

    // Body: { pedido_id, monto, metodo_pago }
    // TODO: simular aprobado/rechazado, guardar Transaccion en PostgreSQL (crear @Entity),
    //       y si es aprobado -> PATCH ${pedidos.url}/api/pedidos/{pedido_id}/estado { "estado": "pagado" }
    @PostMapping("/api/pagos/procesar")
    public ResponseEntity<?> procesar(@RequestBody Map<String, Object> body) {
        return ResponseEntity.status(HttpStatus.NOT_IMPLEMENTED).body(Map.of("error", "No implementado"));
    }

    // TODO: comprobante { pedido_id, monto, metodo_pago, estado, referencia, fecha } o 404
    @GetMapping("/api/pagos/pedido/{pedidoId}")
    public ResponseEntity<?> porPedido(@PathVariable Long pedidoId) {
        return ResponseEntity.status(HttpStatus.NOT_IMPLEMENTED).body(Map.of("error", "No implementado"));
    }
}
