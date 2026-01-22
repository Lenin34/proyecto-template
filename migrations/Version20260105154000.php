<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Safe add region_id to Event and Benefit tables
 */
final class Version20260105154000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Safe add region_id column to Event and Benefit tables with correct capitalization';
    }

    public function up(Schema $schema): void
    {
        // Add region_id column to Event table if not exists
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'Event';
            SET @columnname = 'region_id';
            SET @preparedStatement = (SELECT IF(
              (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
              'SELECT 1',
              'ALTER TABLE Event ADD region_id INT DEFAULT NULL'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        // Add FK constraint to Event table if not exists
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'Event';
            SET @constraintname = 'FK_3BAE0AA798260155';
            SET @preparedStatement = (SELECT IF(
              (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = @dbname AND TABLE_NAME = @tablename AND CONSTRAINT_NAME = @constraintname) > 0,
              'SELECT 1',
              'ALTER TABLE Event ADD CONSTRAINT FK_3BAE0AA798260155 FOREIGN KEY (region_id) REFERENCES Region (id)'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        // Create index for Event region_id
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'Event';
            SET @indexname = 'IDX_3BAE0AA798260155';
            SET @preparedStatement = (SELECT IF(
              (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = @indexname) > 0,
              'SELECT 1',
              'CREATE INDEX IDX_3BAE0AA798260155 ON Event (region_id)'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        // Add region_id column to Benefit table if not exists
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'Benefit';
            SET @columnname = 'region_id';
            SET @preparedStatement = (SELECT IF(
              (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
              'SELECT 1',
              'ALTER TABLE Benefit ADD region_id INT DEFAULT NULL'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        // Add FK constraint to Benefit table if not exists
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'Benefit';
            SET @constraintname = 'FK_4CF72D0298260155';
            SET @preparedStatement = (SELECT IF(
              (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = @dbname AND TABLE_NAME = @tablename AND CONSTRAINT_NAME = @constraintname) > 0,
              'SELECT 1',
              'ALTER TABLE Benefit ADD CONSTRAINT FK_4CF72D0298260155 FOREIGN KEY (region_id) REFERENCES Region (id)'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        // Create index for Benefit region_id
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'Benefit';
            SET @indexname = 'IDX_4CF72D0298260155';
            SET @preparedStatement = (SELECT IF(
              (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = @indexname) > 0,
              'SELECT 1',
              'CREATE INDEX IDX_4CF72D0298260155 ON Benefit (region_id)'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");
    }

    public function down(Schema $schema): void
    {
        // Remove foreign keys and columns from Event table
        $this->addSql('ALTER TABLE Event DROP FOREIGN KEY IF EXISTS FK_3BAE0AA798260155');
        $this->addSql('DROP INDEX IF EXISTS IDX_3BAE0AA798260155 ON Event');
        $this->addSql('ALTER TABLE Event DROP COLUMN IF EXISTS region_id');

        // Remove foreign keys and columns from Benefit table
        $this->addSql('ALTER TABLE Benefit DROP FOREIGN KEY IF EXISTS FK_4CF72D0298260155');
        $this->addSql('DROP INDEX IF EXISTS IDX_4CF72D0298260155 ON Benefit');
        $this->addSql('ALTER TABLE Benefit DROP COLUMN IF EXISTS region_id');
    }
}
