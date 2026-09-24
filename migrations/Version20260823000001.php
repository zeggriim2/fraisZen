<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260823000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add expense receipts and multi-line receipt document analysis';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expense ADD receipt_mime_type VARCHAR(100) DEFAULT NULL, ADD receipt_sha256 VARCHAR(64) DEFAULT NULL, ADD receipt_ocr_data JSON DEFAULT NULL, ADD receipt_uploaded_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX IDX_EXPENSE_RECEIPT_SHA256 ON expense (receipt_sha256)');
        $this->addSql("CREATE TABLE receipt_document (id VARCHAR(36) NOT NULL, person_id VARCHAR(36) NOT NULL, filename VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, sha256 VARCHAR(64) NOT NULL, status VARCHAR(255) NOT NULL, supplier VARCHAR(100) DEFAULT NULL, invoice_number VARCHAR(100) DEFAULT NULL, invoice_date DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)', period_start DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)', period_end DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)', total_amount NUMERIC(10, 2) DEFAULT NULL, error_message LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', analyzed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_RECEIPT_DOCUMENT_PERSON (person_id, created_at), UNIQUE INDEX UNIQ_RECEIPT_DOCUMENT_SHA (person_id, sha256), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE receipt_line (id VARCHAR(36) NOT NULL, document_id VARCHAR(36) NOT NULL, line_number INT NOT NULL, date DATE NOT NULL COMMENT '(DC2Type:date_immutable)', departure VARCHAR(255) DEFAULT NULL, arrival VARCHAR(255) DEFAULT NULL, amount_ht NUMERIC(10, 2) DEFAULT NULL, amount_ttc NUMERIC(10, 2) NOT NULL, distance_km NUMERIC(10, 1) DEFAULT NULL, raw_text LONGTEXT NOT NULL, INDEX IDX_RECEIPT_LINE_DOCUMENT (document_id, line_number), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE receipt_match (id VARCHAR(36) NOT NULL, line_id VARCHAR(36) NOT NULL, expense_id VARCHAR(36) NOT NULL, confidence INT NOT NULL, status VARCHAR(255) NOT NULL, reasons JSON NOT NULL, reviewed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_RECEIPT_MATCH_LINE (line_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE receipt_line ADD CONSTRAINT FK_RECEIPT_LINE_DOCUMENT FOREIGN KEY (document_id) REFERENCES receipt_document (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE receipt_match ADD CONSTRAINT FK_RECEIPT_MATCH_LINE FOREIGN KEY (line_id) REFERENCES receipt_line (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE receipt_match ADD CONSTRAINT FK_RECEIPT_MATCH_EXPENSE FOREIGN KEY (expense_id) REFERENCES expense (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE receipt_match');
        $this->addSql('DROP TABLE receipt_line');
        $this->addSql('DROP TABLE receipt_document');
        $this->addSql('DROP INDEX IDX_EXPENSE_RECEIPT_SHA256 ON expense');
        $this->addSql('ALTER TABLE expense DROP receipt_mime_type, DROP receipt_sha256, DROP receipt_ocr_data, DROP receipt_uploaded_at');
    }
}
