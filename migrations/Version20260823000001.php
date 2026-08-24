<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260823000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Generalize expense receipts by storing mime type on expense';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expense ADD receipt_mime_type VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expense DROP receipt_mime_type');
    }
}
