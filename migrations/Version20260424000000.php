<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user table for authentication';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE `user` (
                id                  CHAR(36)     NOT NULL,
                email               VARCHAR(255) NOT NULL,
                password            VARCHAR(255) NOT NULL,
                roles               JSON         NOT NULL,
                stripe_customer_id  VARCHAR(255) DEFAULT NULL,
                subscription_status VARCHAR(50)  DEFAULT NULL,
                created_at          DATETIME     NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY (id),
                UNIQUE KEY UNIQ_USER_EMAIL (email)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE `user`');
    }
}
