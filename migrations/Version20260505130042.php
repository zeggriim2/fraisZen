<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260505130042 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Soft delete person : ajout colonne deleted_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE person ADD deleted_at DATETIME DEFAULT NULL, CHANGE id id VARCHAR(36) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE user_id user_id VARCHAR(36) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE person ADD CONSTRAINT `FK_PERSON_USER` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE');
    }
}
