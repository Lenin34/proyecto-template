<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Create messenger_messages table for Symfony Messenger
 */
final class Version20260109120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create messenger_messages table for Symfony Messenger multi-tenant support';
    }

    public function up(Schema $schema): void
    {
        // Create messenger_messages table if not exists
        $this->addSql("
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
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ");
    }

    public function down(Schema $schema): void
    {
        // Drop messenger_messages table
        $this->addSql('DROP TABLE IF EXISTS `messenger_messages`');
    }
}

