<?php

declare(strict_types=1);

use App\Admin\Domain\Entity\FiscalConfig;
use App\Admin\Domain\Repository\FiscalConfigRepositoryInterface;
use App\Expense\Infrastructure\Http\FiscalConfig\GetController;
use Symfony\Component\HttpFoundation\Request;

function makeFiscalContainer(): \Psr\Container\ContainerInterface
{
    return new class implements \Psr\Container\ContainerInterface {
        public function get(string $id): mixed { return null; }
        public function has(string $id): bool { return false; }
    };
}

it('retourne les valeurs de la config fiscale quand l\'année existe', function () {
    $config = new FiscalConfig(2025, '2.70', '5.45');

    $repo = new class($config) implements FiscalConfigRepositoryInterface {
        public function __construct(private FiscalConfig $config) {}
        public function findByYear(int $year): ?FiscalConfig { return $this->config; }
        public function findAll(): array { return [$this->config]; }
        public function save(FiscalConfig $c): void {}
    };

    $controller = new GetController($repo);
    $controller->setContainer(makeFiscalContainer());

    $response = ($controller)(2025);
    $body = json_decode($response->getContent(), true);

    expect($response->getStatusCode())->toBe(200);
    expect($body['year'])->toBe(2025);
    expect($body['remoteWorkDailyAllowance'])->toBe(2.70);
    expect($body['homeMealValue'])->toBe(5.45);
});

it('retourne les valeurs par défaut quand l\'année est absente en base', function () {
    $repo = new class implements FiscalConfigRepositoryInterface {
        public function findByYear(int $year): ?FiscalConfig { return null; }
        public function findAll(): array { return []; }
        public function save(FiscalConfig $c): void {}
    };

    $controller = new GetController($repo);
    $controller->setContainer(makeFiscalContainer());

    $response = ($controller)(2099);
    $body = json_decode($response->getContent(), true);

    expect($response->getStatusCode())->toBe(200);
    expect($body['year'])->toBe(2099);
    expect($body['remoteWorkDailyAllowance'])->toBe(2.70);
    expect($body['homeMealValue'])->toBe(5.35);
});

it('retourne l\'année demandée dans le body', function () {
    $repo = new class implements FiscalConfigRepositoryInterface {
        public function findByYear(int $year): ?FiscalConfig { return null; }
        public function findAll(): array { return []; }
        public function save(FiscalConfig $c): void {}
    };

    $controller = new GetController($repo);
    $controller->setContainer(makeFiscalContainer());

    $response = ($controller)(2024);

    expect(json_decode($response->getContent(), true)['year'])->toBe(2024);
});
