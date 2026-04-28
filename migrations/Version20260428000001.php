<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create fiscal_config table with default remote work daily allowance per year';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE fiscal_config (
            year INT NOT NULL,
            remote_work_daily_allowance DECIMAL(5,2) NOT NULL,
            PRIMARY KEY (year)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $this->addSql("INSERT INTO fiscal_config (year, remote_work_daily_allowance) VALUES
            (2023, 2.50),
            (2024, 2.70),
            (2025, 2.70)
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE fiscal_config');
    }
}