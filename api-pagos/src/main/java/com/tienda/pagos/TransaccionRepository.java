package com.tienda.pagos;

import org.springframework.data.jpa.repository.JpaRepository;
import java.util.Optional;

public interface TransaccionRepository extends JpaRepository<Transaccion, Long> {
    Optional<Transaccion> findFirstByPedidoIdOrderByFechaDesc(Long pedidoId);
}