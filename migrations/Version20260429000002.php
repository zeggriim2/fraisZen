<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260429000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add home_meal_value, employer_ticket_contribution, without_receipt columns to expense table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expense
            ADD home_meal_value DECIMAL(5,2) DEFAULT 5.35,
            ADD employer_ticket_contribution DECIMAL(5,2) NOT NULL DEFAULT 0,
            ADD without_receipt TINYINT(1) NOT NULL DEFAULT 0
        ');
        $this->addSql("UPDATE expense SET home_meal_value = 5.35 WHERE type = 'meal'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expense
            DROP COLUMN home_meal_value,
            DROP COLUMN employer_ticket_contribution,
            DROP COLUMN without_receipt
        ');
    }
}
