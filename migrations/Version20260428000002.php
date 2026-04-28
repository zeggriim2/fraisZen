<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add daily_allowance column to expense table for RemoteWorkExpense';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expense ADD daily_allowance DECIMAL(5,2) DEFAULT 2.70');
        $this->addSql("UPDATE expense SET daily_allowance = 2.70 WHERE type = 'remote_work'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expense DROP COLUMN daily_allowance');
    }
}
