<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create person_favorite_route table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE person_favorite_route (
            id VARCHAR(36) NOT NULL,
            person_id VARCHAR(36) NOT NULL,
            name VARCHAR(100) NOT NULL,
            departure VARCHAR(255) NOT NULL,
            arrival VARCHAR(255) NOT NULL,
            vehicle_type VARCHAR(20) NOT NULL,
            vehicle_power INT DEFAULT NULL,
            is_electric TINYINT(1) NOT NULL,
            round_trip TINYINT(1) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id),
            INDEX IDX_PERSON (person_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE person_favorite_route');
    }
}
