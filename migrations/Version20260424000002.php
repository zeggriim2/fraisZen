<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add default_year and default_fiscal_power to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD default_year SMALLINT DEFAULT NULL, ADD default_fiscal_power SMALLINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP COLUMN default_year, DROP COLUMN default_fiscal_power');
    }
}
