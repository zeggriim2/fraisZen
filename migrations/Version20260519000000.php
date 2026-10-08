<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260519000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add atomic per-issue leases for agent workflow';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE issue_lease (id CHAR(36) NOT NULL, repository_full_name VARCHAR(255) NOT NULL, issue_number INT NOT NULL, token_hash VARCHAR(64) NOT NULL, owner_id VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_ISSUE_LEASE_ISSUE (repository_full_name, issue_number), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE issue_lease');
    }
}
