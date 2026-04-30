<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260430000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add parking_amount and parking_location columns to expense table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expense ADD COLUMN parking_amount DECIMAL(10,2) NULL, ADD COLUMN parking_location VARCHAR(255) NULL, ADD COLUMN receipt_filename VARCHAR(255) NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expense DROP COLUMN parking_amount, DROP COLUMN parking_location, DROP COLUMN receipt_filename');
    }
}
