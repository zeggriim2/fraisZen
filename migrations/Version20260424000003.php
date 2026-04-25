<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add meal_amount column to expense table (STI for MealExpense)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expense ADD meal_amount DECIMAL(10,2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expense DROP COLUMN meal_amount');
    }
}
