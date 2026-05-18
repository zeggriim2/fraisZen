<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260518000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Insert FiscalConfig 2026 (télétravail 2.70 €/j, repas 5.45 €) — reconduit sur 2025 en attendant l\'arrêté officiel';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'INSERT INTO fiscal_config (year, remote_work_daily_allowance, home_meal_value) VALUES (2026, \'2.70\', \'5.45\')'
            .' ON DUPLICATE KEY UPDATE remote_work_daily_allowance = VALUES(remote_work_daily_allowance), home_meal_value = VALUES(home_meal_value)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM fiscal_config WHERE year = 2026');
    }
}
