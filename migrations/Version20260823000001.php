<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260823000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add receipt mime type, fingerprint, OCR metadata and upload date';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expense ADD receipt_mime_type VARCHAR(100) DEFAULT NULL, ADD receipt_sha256 VARCHAR(64) DEFAULT NULL, ADD receipt_ocr_data JSON DEFAULT NULL, ADD receipt_uploaded_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX IDX_EXPENSE_RECEIPT_SHA256 ON expense (receipt_sha256)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_EXPENSE_RECEIPT_SHA256 ON expense');
        $this->addSql('ALTER TABLE expense DROP receipt_mime_type, DROP receipt_sha256, DROP receipt_ocr_data, DROP receipt_uploaded_at');
    }
}
