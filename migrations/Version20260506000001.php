<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260506000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create bareme_kilometrique table and seed 2023-2026 rates (arrêté du 27 mars 2023, reconduit)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE bareme_kilometrique (year INT NOT NULL, rates JSON NOT NULL, PRIMARY KEY(year)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $rates = json_encode([
            'car' => [
                3 => ['rate1' => 0.529, 'rate2' => 0.316, 'fixed2' => 1065, 'rate3' => 0.370],
                4 => ['rate1' => 0.606, 'rate2' => 0.340, 'fixed2' => 1330, 'rate3' => 0.407],
                5 => ['rate1' => 0.636, 'rate2' => 0.357, 'fixed2' => 1395, 'rate3' => 0.427],
                6 => ['rate1' => 0.665, 'rate2' => 0.374, 'fixed2' => 1457, 'rate3' => 0.447],
                7 => ['rate1' => 0.697, 'rate2' => 0.394, 'fixed2' => 1515, 'rate3' => 0.470],
            ],
            'motorcycle' => [
                1 => ['rate1' => 0.395, 'rate2' => 0.099, 'fixed2' => 891,  'rate3' => 0.248],
                3 => ['rate1' => 0.468, 'rate2' => 0.082, 'fixed2' => 1158, 'rate3' => 0.275],
                6 => ['rate1' => 0.606, 'rate2' => 0.079, 'fixed2' => 1583, 'rate3' => 0.343],
            ],
            'moped' => ['rate1' => 0.315, 'rate2' => 0.079, 'fixed2' => 711, 'rate3' => 0.198],
            'electricMultiplier' => 1.20,
        ], JSON_THROW_ON_ERROR);

        foreach ([2023, 2024, 2025, 2026] as $year) {
            $this->addSql(
                'INSERT INTO bareme_kilometrique (year, rates) VALUES (:year, :rates)',
                ['year' => $year, 'rates' => $rates]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE bareme_kilometrique');
    }
}
