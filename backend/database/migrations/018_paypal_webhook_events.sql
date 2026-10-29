-- LiquorSoft - auditoría de webhooks de PayPal
-- Permite conciliar pagos capturados aunque la respuesta HTTP al cliente se pierda.
CREATE TABLE IF NOT EXISTS `paypal_webhook_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resource_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paypal_order_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` json NOT NULL,
  `processed` tinyint(1) NOT NULL DEFAULT 0,
  `error` text COLLATE utf8mb4_unicode_ci,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `procesado_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_paypal_webhook_event` (`event_id`),
  KEY `idx_paypal_webhook_order` (`paypal_order_id`),
  KEY `idx_paypal_webhook_processed` (`processed`,`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
