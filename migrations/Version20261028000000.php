<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tax comparison inputs.
 *
 * Schema adaptation: the existing fiscal_config table (one row per year) is reused and extended with
 * nullable, versioned comparison parameters so that existing rows stay valid; a row without them is
 * reported as configuration_missing by the comparison engine.
 */
final class Version20261028000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add versioned fiscal comparison parameters, net taxable salaries and employer reimbursements';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fiscal_config ADD forfait_rate_percent INT DEFAULT NULL, ADD forfait_minimum_cents INT DEFAULT NULL, ADD forfait_maximum_cents INT DEFAULT NULL, ADD version VARCHAR(100) DEFAULT NULL, ADD source VARCHAR(255) DEFAULT NULL, ADD effective_from DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\'');
        $this->addSql("CREATE TABLE net_taxable_salary (id VARCHAR(36) NOT NULL, person_id VARCHAR(36) NOT NULL, employer VARCHAR(255) NOT NULL, year INT NOT NULL, period_start DATE NOT NULL COMMENT '(DC2Type:date_immutable)', period_end DATE NOT NULL COMMENT '(DC2Type:date_immutable)', amount_cents INT NOT NULL, INDEX IDX_NET_TAXABLE_SALARY_PERSON_YEAR (person_id, year), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE employer_reimbursement (id VARCHAR(36) NOT NULL, person_id VARCHAR(36) NOT NULL, employer VARCHAR(255) NOT NULL, year INT NOT NULL, date DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)', amount_cents INT NOT NULL, category VARCHAR(50) NOT NULL, to_reintegrate TINYINT(1) DEFAULT NULL, expense_id VARCHAR(36) DEFAULT NULL, INDEX IDX_EMPLOYER_REIMBURSEMENT_PERSON_YEAR (person_id, year), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE employer_reimbursement');
        $this->addSql('DROP TABLE net_taxable_salary');
        $this->addSql('ALTER TABLE fiscal_config DROP forfait_rate_percent, DROP forfait_minimum_cents, DROP forfait_maximum_cents, DROP version, DROP source, DROP effective_from');
    }
}
