-- Marca de producto destacado para la tienda.
ALTER TABLE productos
    ADD COLUMN destacado TINYINT(1) NOT NULL DEFAULT 0 AFTER imagen;
