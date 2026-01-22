<?php

declare(strict_types=1);

namespace DoctrineMigrations\Master;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251120195604 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cambio de columna description a tipo TEXT en la tabla Event';
    }

    public function up(Schema $schema): void
    {
        // Cambia a LONGTEXT si necesitas espacio de novela dramática
        $this->addSql('ALTER TABLE Event MODIFY description TEXT');
    }

    public function down(Schema $schema): void
    {
        // Revertimos a algo más modesto para que la migración sea reversible
        $this->addSql('ALTER TABLE Event MODIFY description VARCHAR(255)');
    }
}
