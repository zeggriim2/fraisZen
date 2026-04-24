<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add vehicle_type and is_electric columns to expense table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE expense ADD vehicle_type VARCHAR(20) NOT NULL DEFAULT 'car'");
        $this->addSql('ALTER TABLE expense ADD is_electric TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expense DROP COLUMN vehicle_type');
        $this->addSql('ALTER TABLE expense DROP COLUMN is_electric');
    }
}