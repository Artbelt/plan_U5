-- Создание таблицы для аудита изменений в БД
-- Таблица будет отслеживать все операции с данными

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_name` varchar(100) NOT NULL COMMENT 'Название таблицы',
  `record_id` varchar(255) DEFAULT NULL COMMENT 'ID записи (может быть составным)',
  `operation` enum('INSERT','UPDATE','DELETE') NOT NULL COMMENT 'Тип операции',
  `old_values` longtext DEFAULT NULL COMMENT 'Старые значения (JSON)',
  `new_values` longtext DEFAULT NULL COMMENT 'Новые значения (JSON)',
  `changed_fields` text DEFAULT NULL COMMENT 'Список измененных полей',
  `user_ip` varchar(45) DEFAULT NULL COMMENT 'IP адрес пользователя',
  `user_agent` text DEFAULT NULL COMMENT 'User Agent браузера',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время изменения',
  `session_id` varchar(255) DEFAULT NULL COMMENT 'ID сессии',
  `additional_info` text DEFAULT NULL COMMENT 'Дополнительная информация',
  PRIMARY KEY (`id`),
  KEY `idx_table_name` (`table_name`),
  KEY `idx_operation` (`operation`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_record_id` (`record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Таблица аудита изменений в БД';

-- Создание индексов для быстрого поиска
CREATE INDEX `idx_table_operation` ON `audit_log` (`table_name`, `operation`);
CREATE INDEX `idx_table_record` ON `audit_log` (`table_name`, `record_id`);
CREATE INDEX `idx_created_at_desc` ON `audit_log` (`created_at` DESC);


