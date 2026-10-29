USE liquorsoft;

CREATE TABLE IF NOT EXISTS paypal_checkout_intents (
  paypal_order_id VARCHAR(64) PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  clave_idempotencia CHAR(36) NOT NULL,
  amount_cop DECIMAL(12,2) NOT NULL,
  amount_usd DECIMAL(12,2) NOT NULL,
  items_json JSON NOT NULL,
  direccion_entrega VARCHAR(255) NOT NULL,
  notas VARCHAR(500) NOT NULL DEFAULT '',
  estado ENUM('CREATED', 'COMPLETED') NOT NULL DEFAULT 'CREATED',
  capture_id VARCHAR(64) NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_paypal_intent_usuario_idempotencia (usuario_id, clave_idempotencia),
  INDEX idx_paypal_intent_estado (estado, creado_en)
) ENGINE=InnoDB;
