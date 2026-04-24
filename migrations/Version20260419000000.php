<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create person and expense tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE person (
                id          CHAR(36)     NOT NULL,
                first_name  VARCHAR(100) NOT NULL,
                last_name   VARCHAR(100) NOT NULL,
                email       VARCHAR(255) DEFAULT NULL,
                created_at  DATETIME     NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE expense (
                id            CHAR(36)      NOT NULL,
                person_id     CHAR(36)      NOT NULL,
                type          VARCHAR(20)   NOT NULL,
                date          DATE          NOT NULL COMMENT '(DC2Type:date_immutable)',
                description   VARCHAR(255)  DEFAULT NULL,
                distance_km   DECIMAL(8,2)  DEFAULT NULL,
                vehicle_power SMALLINT      DEFAULT NULL,
                departure     VARCHAR(255)  DEFAULT NULL,
                arrival       VARCHAR(255)  DEFAULT NULL,
                toll_amount   DECIMAL(10,2) DEFAULT NULL,
                created_at    DATETIME      NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at    DATETIME      NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY (id),
                INDEX IDX_EXPENSE_PERSON (person_id),
                INDEX IDX_EXPENSE_DATE (date),
                CONSTRAINT FK_EXPENSE_PERSON FOREIGN KEY (person_id) REFERENCES person (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE expense');
        $this->addSql('DROP TABLE person');
    }
}
