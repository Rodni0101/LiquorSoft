-- Migración 012: Sistema de notificaciones integrado para clientes y administradores
USE liquorsoft;

CREATE TABLE IF NOT EXISTS notificaciones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  tipo VARCHAR(50) NOT NULL,
  titulo VARCHAR(150) NOT NULL,
  mensaje TEXT NOT NULL,
  pedido_id INT UNSIGNED NULL,
  leida TINYINT(1) NOT NULL DEFAULT 0,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  leida_en TIMESTAMP NULL,
  CONSTRAINT fk_notificaciones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_notificaciones_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE SET NULL,
  INDEX idx_notificaciones_usuario_leida (usuario_id, leida, creado_en),
  INDEX idx_notificaciones_pedido (pedido_id)
) ENGINE=InnoDB;

