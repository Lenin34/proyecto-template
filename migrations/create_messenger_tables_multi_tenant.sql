-- Script para crear tablas messenger_messages en todas las bases de datos tenant
-- Ejecutar este script manualmente en cada base de datos

-- Para base de datos: msc-app-ts
USE `msc-app-ts`;
CREATE TABLE IF NOT EXISTS `messenger_messages` (
    `id` BIGINT AUTO_INCREMENT NOT NULL,
    `body` LONGTEXT NOT NULL,
    `headers` LONGTEXT NOT NULL,
    `queue_name` VARCHAR(190) NOT NULL,
    `created_at` DATETIME NOT NULL,
    `available_at` DATETIME NOT NULL,
    `delivered_at` DATETIME DEFAULT NULL,
    INDEX `IDX_75EA56E0FB7336F0` (`queue_name`),
    INDEX `IDX_75EA56E0E3BD61CE` (`available_at`),
    INDEX `IDX_75EA56E016BA31DB` (`delivered_at`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

-- Para base de datos: msc-app-rs
USE `msc-app-rs`;
CREATE TABLE IF NOT EXISTS `messenger_messages` (
    `id` BIGINT AUTO_INCREMENT NOT NULL,
    `body` LONGTEXT NOT NULL,
    `headers` LONGTEXT NOT NULL,
    `queue_name` VARCHAR(190) NOT NULL,
    `created_at` DATETIME NOT NULL,
    `available_at` DATETIME NOT NULL,
    `delivered_at` DATETIME DEFAULT NULL,
    INDEX `IDX_75EA56E0FB7336F0` (`queue_name`),
    INDEX `IDX_75EA56E0E3BD61CE` (`available_at`),
    INDEX `IDX_75EA56E016BA31DB` (`delivered_at`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

-- Para base de datos: msc-app-snt
USE `msc-app-snt`;
CREATE TABLE IF NOT EXISTS `messenger_messages` (
    `id` BIGINT AUTO_INCREMENT NOT NULL,
    `body` LONGTEXT NOT NULL,
    `headers` LONGTEXT NOT NULL,
    `queue_name` VARCHAR(190) NOT NULL,
    `created_at` DATETIME NOT NULL,
    `available_at` DATETIME NOT NULL,
    `delivered_at` DATETIME DEFAULT NULL,
    INDEX `IDX_75EA56E0FB7336F0` (`queue_name`),
    INDEX `IDX_75EA56E0E3BD61CE` (`available_at`),
    INDEX `IDX_75EA56E016BA31DB` (`delivered_at`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

-- Para base de datos: msc-app-issemym
USE `msc-app-issemym`;
CREATE TABLE IF NOT EXISTS `messenger_messages` (
    `id` BIGINT AUTO_INCREMENT NOT NULL,
    `body` LONGTEXT NOT NULL,
    `headers` LONGTEXT NOT NULL,
    `queue_name` VARCHAR(190) NOT NULL,
    `created_at` DATETIME NOT NULL,
    `available_at` DATETIME NOT NULL,
    `delivered_at` DATETIME DEFAULT NULL,
    INDEX `IDX_75EA56E0FB7336F0` (`queue_name`),
    INDEX `IDX_75EA56E0E3BD61CE` (`available_at`),
    INDEX `IDX_75EA56E016BA31DB` (`delivered_at`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

