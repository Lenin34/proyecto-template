-- ========================================
-- MIGRACIÓN: Tabla user_notification_read
-- ========================================
-- Esta tabla rastrea qué notificaciones ha leído cada usuario
-- para implementar el sistema de badges de notificaciones no leídas

CREATE TABLE IF NOT EXISTS `user_notification_read` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `notification_id` INT NOT NULL,
    `read_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Índices para mejorar performance
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_notification_id` (`notification_id`),
    INDEX `idx_user_notification` (`user_id`, `notification_id`),
    
    -- Clave única para evitar duplicados
    UNIQUE KEY `unique_user_notification` (`user_id`, `notification_id`),
    
    -- Claves foráneas
    CONSTRAINT `fk_user_notification_read_user` 
        FOREIGN KEY (`user_id`) 
        REFERENCES `User` (`id`) 
        ON DELETE CASCADE,
    
    CONSTRAINT `fk_user_notification_read_notification` 
        FOREIGN KEY (`notification_id`) 
        REFERENCES `Notification` (`id`) 
        ON DELETE CASCADE
        
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- NOTAS DE IMPLEMENTACIÓN
-- ========================================
-- 1. Esta tabla debe crearse en CADA base de datos de tenant
-- 2. Ejecutar este script en:
--    - msc-app-issemym
--    - msc-app-ctm
--    - Cualquier otro tenant que uses
--
-- 3. Ejemplo de ejecución:
--    mysql -u root -p msc-app-issemym < migration_user_notification_read.sql
--    mysql -u root -p msc-app-ctm < migration_user_notification_read.sql
--
-- 4. Para verificar que se creó correctamente:
--    SHOW TABLES LIKE 'user_notification_read';
--    DESCRIBE user_notification_read;
