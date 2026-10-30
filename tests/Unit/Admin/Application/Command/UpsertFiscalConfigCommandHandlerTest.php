<?php

declare(strict_types=1);

use App\Admin\Application\Command\UpsertFiscalConfig\UpsertFiscalConfigCommand;
use App\Admin\Application\Command\UpsertFiscalConfig\UpsertFiscalConfigCommandHandler;
use App\Admin\Application\Exception\InvalidFiscalConfigException;
use App\Admin\Domain\Entity\FiscalConfig;
use App\Tests\Unit\Tax\Support\InMemoryFiscalConfigs;

// Reference values (revenus 2025) used as fixtures only: 509 € minimum, 14 555 € maximum.
const FORFAIT_2025 = ['forfaitRatePercent' => 10, 'forfaitMinimumCents' => 50_900, 'forfaitMaximumCents' => 1_455_500, 'version' => '2025.1', 'source' => 'BOFiP-IR-BASE-2025', 'effectiveFrom' => '2025-01-01'];

function upsertCommand(int $year = 2025, array $overrides = []): UpsertFiscalConfigCommand
{
    $fields = array_merge(FORFAIT_2025, $overrides);

    return new UpsertFiscalConfigCommand(
        year: $year,
        remoteWorkDailyAllowance: 2.70,
        homeMealValue: 5.45,
        forfaitRatePercent: $fields['forfaitRatePercent'],
        forfaitMinimumCents: $fields['forfaitMinimumCents'],
        forfaitMaximumCents: $fields['forfaitMaximumCents'],
        version: $fields['version'],
        source: $fields['source'],
        effectiveFrom: $fields['effectiveFrom'],
    );
}

it('crée une configuration complète avec les six paramètres du forfait via defineTaxComparisonRules', function () {
    $repo = new InMemoryFiscalConfigs();
    (new UpsertFiscalConfigCommandHandler($repo))(upsertCommand());

    $saved = $repo->findByYear(2025);
    expect($saved)->not->toBeNull();
    expect($saved->isComplete())->toBeTrue();
    expect($saved->remoteWorkDailyAllowance())->toBe(2.70);
    expect($saved->homeMealValue())->toBe(5.45);
    expect($saved->toArray())->toBe([
        'year' => 2025,
        'remoteWorkDailyAllowance' => 2.70,
        'homeMealValue' => 5.45,
        'forfaitRatePercent' => 10,
        'forfaitMinimumCents' => 50_900,
        'forfaitMaximumCents' => 1_455_500,
        'version' => '2025.1',
        'source' => 'BOFiP-IR-BASE-2025',
        'effectiveFrom' => '2025-01-01',
        'taxComparisonComplete' => true,
        'missingTaxComparisonFields' => [],
    ]);
});

it('met à jour les six paramètres d\'une configuration existante sans toucher aux champs historiques', function () {
    $repo = new InMemoryFiscalConfigs();
    $repo->save(new FiscalConfig(2024, '2.70', '5.35'));

    (new UpsertFiscalConfigCommandHandler($repo))(new UpsertFiscalConfigCommand(2024, 2.70, null, 10, 50_400, 1_442_600, '2024.1', 'BOFiP-IR-BASE-2024', '2024-01-01'));

    $saved = $repo->findByYear(2024);
    expect($saved->isComplete())->toBeTrue();
    expect($saved->forfaitMinimumCents())->toBe(50_400);
    expect($saved->forfaitMaximumCents())->toBe(1_442_600);
    expect($saved->effectiveFrom()?->format('Y-m-d'))->toBe('2024-01-01');
    expect($saved->homeMealValue())->toBe(5.35);
});

it('accepte une configuration partielle : sans les six paramètres, seuls les champs historiques sont enregistrés', function () {
    $repo = new InMemoryFiscalConfigs();
    (new UpsertFiscalConfigCommandHandler($repo))(new UpsertFiscalConfigCommand(2023, 2.50, 4.85));

    $saved = $repo->findByYear(2023);
    expect($saved->isComplete())->toBeFalse();
    expect($saved->missingTaxComparisonFields())->toBe(['forfait_rate_percent', 'forfait_minimum_cents', 'forfait_maximum_cents', 'version', 'source', 'effective_from']);
    expect($saved->toArray()['taxComparisonComplete'])->toBeFalse();
});

it('conserve les paramètres du forfait déjà définis quand une mise à jour ne les fournit pas', function () {
    $repo = new InMemoryFiscalConfigs();
    (new UpsertFiscalConfigCommandHandler($repo))(upsertCommand());

    (new UpsertFiscalConfigCommandHandler($repo))(new UpsertFiscalConfigCommand(2025, 2.80, 5.50));

    $saved = $repo->findByYear(2025);
    expect($saved->remoteWorkDailyAllowance())->toBe(2.80);
    expect($saved->isComplete())->toBeTrue();
    expect($saved->forfaitMaximumCents())->toBe(1_455_500);
});

it('rejette un jeu de paramètres incomplet et n\'enregistre rien', function () {
    $repo = new InMemoryFiscalConfigs();
    $handler = new UpsertFiscalConfigCommandHandler($repo);

    expect(fn () => $handler(upsertCommand(overrides: ['source' => null, 'effectiveFrom' => null])))
        ->toThrow(InvalidFiscalConfigException::class, 'missing: source, effectiveFrom');
    expect($repo->findAll())->toBe([]);
});

it('transforme chaque violation du domaine en InvalidFiscalConfigException sans enregistrement partiel', function (array $overrides, string $message) {
    $repo = new InMemoryFiscalConfigs();
    $repo->save(new FiscalConfig(2025, '2.70', '5.45'));
    $handler = new UpsertFiscalConfigCommandHandler($repo);

    expect(fn () => $handler(upsertCommand(overrides: $overrides)))->toThrow(InvalidFiscalConfigException::class, $message);

    $untouched = $repo->findByYear(2025);
    expect($untouched->isComplete())->toBeFalse();
    expect($untouched->remoteWorkDailyAllowance())->toBe(2.70);
})->with([
    'taux 0 %' => [['forfaitRatePercent' => 0], 'between 1 and 100'],
    'taux 101 %' => [['forfaitRatePercent' => 101], 'between 1 and 100'],
    'minimum négatif' => [['forfaitMinimumCents' => -1], 'cannot be negative'],
    'plafond négatif' => [['forfaitMaximumCents' => -1], 'cannot be negative'],
    'minimum > plafond' => [['forfaitMinimumCents' => 1_455_501], 'cannot exceed the maximum'],
    'version vide' => [['version' => '  '], 'cannot be blank'],
    'source vide' => [['source' => ''], 'cannot be blank'],
    'date invalide' => [['effectiveFrom' => '2025-13-40'], 'expected format YYYY-MM-DD'],
]);
