<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add round_trip column to expense table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expense ADD round_trip TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expense DROP COLUMN round_trip');
    }
}