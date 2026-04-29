<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260429000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add home_meal_value column to fiscal_config table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fiscal_config ADD home_meal_value DECIMAL(5,2) NOT NULL DEFAULT 5.35');
        $this->addSql("UPDATE fiscal_config SET home_meal_value = 4.85 WHERE year = 2023");
        $this->addSql("UPDATE fiscal_config SET home_meal_value = 5.35 WHERE year = 2024");
        $this->addSql("UPDATE fiscal_config SET home_meal_value = 5.45 WHERE year = 2025");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fiscal_config DROP COLUMN home_meal_value');
    }
}
