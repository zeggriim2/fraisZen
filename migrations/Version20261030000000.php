<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Annual closing of the forfait / real expenses comparison: one row per closure version of a
 * person and year, holding the immutable snapshot. Reopening only fills reopened_at/reopened_by,
 * so the history is never deleted; the unique (person_id, year, version) key rejects concurrent
 * closings and `revision` is the optimistic lock of concurrent reopenings.
 */
final class Version20261030000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tax year closures with immutable comparison snapshots';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE tax_year_closure (id VARCHAR(36) NOT NULL, person_id VARCHAR(36) NOT NULL, year INT NOT NULL, version INT NOT NULL, closed_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', closed_by VARCHAR(36) NOT NULL, fingerprint VARCHAR(64) NOT NULL, snapshot JSON NOT NULL, reopened_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', reopened_by VARCHAR(36) DEFAULT NULL, revision INT DEFAULT 1 NOT NULL, INDEX IDX_TAX_YEAR_CLOSURE_PERSON_YEAR (person_id, year), UNIQUE INDEX UNIQ_TAX_YEAR_CLOSURE_PERSON_YEAR_VERSION (person_id, year, version), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tax_year_closure');
    }
}
