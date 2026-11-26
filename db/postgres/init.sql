-- Se ejecuta solo la primera vez que se crea el volumen de Postgres.
-- Cada servicio tiene su propia base (un servicio = una base).
CREATE DATABASE pedidos_db;
CREATE DATABASE pagos_db;
