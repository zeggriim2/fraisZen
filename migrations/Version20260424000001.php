<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user_id to person table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE person ADD user_id CHAR(36) DEFAULT NULL');
        $this->addSql('UPDATE person SET user_id = (SELECT id FROM `user` LIMIT 1)');
        $this->addSql('ALTER TABLE person MODIFY user_id CHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE person ADD INDEX IDX_PERSON_USER (user_id)');
        $this->addSql('ALTER TABLE person ADD CONSTRAINT FK_PERSON_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE person DROP FOREIGN KEY FK_PERSON_USER');
        $this->addSql('ALTER TABLE person DROP INDEX IDX_PERSON_USER');
        $this->addSql('ALTER TABLE person DROP COLUMN user_id');
    }
}
