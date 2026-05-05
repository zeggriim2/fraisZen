<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260505125301 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY `FK_EXPENSE_PERSON`');
        $this->addSql('ALTER TABLE expense CHANGE id id VARCHAR(36) NOT NULL, CHANGE person_id person_id VARCHAR(36) NOT NULL, CHANGE type type VARCHAR(255) NOT NULL, CHANGE date date DATETIME NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE round_trip round_trip TINYINT DEFAULT 0, CHANGE vehicle_type vehicle_type VARCHAR(20) DEFAULT \'car\', CHANGE is_electric is_electric TINYINT DEFAULT 0, CHANGE daily_allowance daily_allowance NUMERIC(5, 2) DEFAULT NULL, CHANGE home_meal_value home_meal_value NUMERIC(5, 2) DEFAULT NULL, CHANGE employer_ticket_contribution employer_ticket_contribution NUMERIC(5, 2) DEFAULT NULL, CHANGE without_receipt without_receipt TINYINT DEFAULT NULL');
        $this->addSql('ALTER TABLE person DROP FOREIGN KEY `FK_PERSON_USER`');
        $this->addSql('ALTER TABLE person ADD deleted_at DATETIME DEFAULT NULL, CHANGE id id VARCHAR(36) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE user_id user_id VARCHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE person_favorite_route CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE id id VARCHAR(36) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE expense CHANGE id id CHAR(36) NOT NULL, CHANGE person_id person_id CHAR(36) NOT NULL, CHANGE date date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE type type VARCHAR(20) NOT NULL, CHANGE round_trip round_trip TINYINT DEFAULT 0 NOT NULL, CHANGE vehicle_type vehicle_type VARCHAR(20) DEFAULT \'car\' NOT NULL, CHANGE is_electric is_electric TINYINT DEFAULT 0 NOT NULL, CHANGE daily_allowance daily_allowance NUMERIC(5, 2) DEFAULT \'2.70\', CHANGE home_meal_value home_meal_value NUMERIC(5, 2) DEFAULT \'5.35\', CHANGE employer_ticket_contribution employer_ticket_contribution NUMERIC(5, 2) DEFAULT \'0.00\' NOT NULL, CHANGE without_receipt without_receipt TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT `FK_EXPENSE_PERSON` FOREIGN KEY (person_id) REFERENCES person (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE person DROP deleted_at, CHANGE id id CHAR(36) NOT NULL, CHANGE user_id user_id CHAR(36) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE person ADD CONSTRAINT `FK_PERSON_USER` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE person_favorite_route CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE `user` CHANGE id id CHAR(36) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
