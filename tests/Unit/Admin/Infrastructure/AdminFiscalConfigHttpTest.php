<?php

declare(strict_types=1);

namespace App\Tests\Unit\Admin\Infrastructure;

use App\Admin\Application\Command\UpsertFiscalConfig\UpsertFiscalConfigCommand;
use App\Admin\Application\Command\UpsertFiscalConfig\UpsertFiscalConfigCommandHandler;
use App\Admin\Application\Query\GetFiscalConfig\GetFiscalConfigQuery;
use App\Admin\Application\Query\GetFiscalConfig\GetFiscalConfigQueryHandler;
use App\Admin\Application\Query\ListFiscalConfig\ListFiscalConfigQuery;
use App\Admin\Application\Query\ListFiscalConfig\ListFiscalConfigQueryHandler;
use App\Admin\Domain\Entity\FiscalConfig;
use App\Admin\Infrastructure\Http\AdminFiscal\GetController;
use App\Admin\Infrastructure\Http\AdminFiscal\ListController;
use App\Admin\Infrastructure\Http\AdminFiscal\UpsertController;
use App\Expense\Domain\Repository\BaremeKilometriqueRepositoryInterface;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\Service\KilometricAllowanceCalculator;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use App\SharedKernel\Infrastructure\Http\ExceptionListener;
use App\Tax\Application\Query\GetTaxComparison\GetTaxComparisonQuery;
use App\Tax\Application\Query\GetTaxComparison\GetTaxComparisonQueryHandler;
use App\Tax\Domain\Entity\NetTaxableSalary;
use App\Tests\Unit\Tax\Support\InMemoryEmployerReimbursements;
use App\Tests\Unit\Tax\Support\InMemoryFiscalConfigs;
use App\Tests\Unit\Tax\Support\InMemoryNetTaxableSalaries;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

/**
 * HTTP integration of the admin fiscal configuration endpoints without database: real attribute routes
 * (with the `/api/admin` prefix of config/routes/api/admin.yaml), real HttpKernel, real shared
 * ExceptionListener, real command/query handlers on an in-memory repository. The ROLE_ADMIN guard
 * (#[IsGranted] on AbstractAdminController) is enforced by the security bundle and is not exercised here.
 *
 * Reference values used as fixtures (revenus 2025 : 509 € / 14 555 €, 2024 : 504 € / 14 426 €).
 */
final class AdminFiscalConfigHttpTest extends TestCase
{
    private const COMPLETE_2025 = [
        'remoteWorkDailyAllowance' => 2.70,
        'homeMealValue' => 5.45,
        'forfaitRatePercent' => 10,
        'forfaitMinimumCents' => 50_900,
        'forfaitMaximumCents' => 1_455_500,
        'version' => '2025.1',
        'source' => 'BOFiP-IR-BASE-2025',
        'effectiveFrom' => '2025-01-01',
    ];

    private InMemoryFiscalConfigs $configs;
    private InMemoryNetTaxableSalaries $salaries;
    private GetTaxComparisonQueryHandler $comparison;
    private HttpKernel $kernel;

    protected function setUp(): void
    {
        $this->configs = new InMemoryFiscalConfigs();
        $this->salaries = new InMemoryNetTaxableSalaries();

        $upsertHandler = new UpsertFiscalConfigCommandHandler($this->configs);
        $getHandler = new GetFiscalConfigQueryHandler($this->configs);
        $listHandler = new ListFiscalConfigQueryHandler($this->configs);
        $expenses = $this->createStub(ExpenseRepositoryInterface::class);
        $expenses->method('findByPersonAndYear')->willReturn([]);
        $this->comparison = new GetTaxComparisonQueryHandler(
            $this->salaries,
            new InMemoryEmployerReimbursements(),
            $expenses,
            $this->configs,
            new KilometricAllowanceCalculator($this->createStub(BaremeKilometriqueRepositoryInterface::class)),
        );

        $commandBus = new class($upsertHandler) implements CommandBusInterface {
            public function __construct(private UpsertFiscalConfigCommandHandler $handler)
            {
            }

            public function dispatch(object $command): mixed
            {
                \assert($command instanceof UpsertFiscalConfigCommand);
                ($this->handler)($command);

                return null;
            }
        };
        $queryBus = new class($getHandler, $listHandler) implements QueryBusInterface {
            public function __construct(private GetFiscalConfigQueryHandler $get, private ListFiscalConfigQueryHandler $list)
            {
            }

            public function ask(object $query): mixed
            {
                return $query instanceof GetFiscalConfigQuery ? ($this->get)($query) : ($this->list)($query instanceof ListFiscalConfigQuery ? $query : throw new \LogicException());
            }
        };

        $container = new Container();
        $controllers = [
            GetController::class => new GetController($queryBus),
            ListController::class => new ListController($queryBus),
            UpsertController::class => new UpsertController($commandBus, $queryBus),
        ];
        $routes = new RouteCollection();
        $loader = new AttributeRouteControllerLoader();
        foreach ($controllers as $class => $controller) {
            $controller->setContainer($container);
            $routes->addCollection($loader->load($class));
        }
        $routes->addPrefix('/api/admin');

        $requestStack = new RequestStack();
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RouterListener(new UrlMatcher($routes, new RequestContext()), $requestStack));
        $dispatcher->addListener(KernelEvents::EXCEPTION, new ExceptionListener());
        $resolver = new class($controllers) implements ControllerResolverInterface {
            /** @param array<string, object> $controllers */
            public function __construct(private array $controllers)
            {
            }

            public function getController(Request $request): callable|false
            {
                $controller = $request->attributes->get('_controller');
                \assert(\is_string($controller));
                $callable = [$this->controllers[$controller], '__invoke'];
                \assert(\is_callable($callable));

                return $callable;
            }
        };
        $this->kernel = new HttpKernel($dispatcher, $resolver, $requestStack, new ArgumentResolver());
    }

    public function testGetAndListExposeTheSixParametersWithAmountsInCents(): void
    {
        $this->configs->save(new FiscalConfig(2024, '2.70', '5.35', 10, 50_400, 1_442_600, '2024.1', 'BOFiP-IR-BASE-2024', new \DateTimeImmutable('2024-01-01')));
        $this->configs->save(new FiscalConfig(2023, '2.50', '4.85'));

        $complete = $this->json('GET', '/api/admin/fiscal-config/2024');
        $partial = $this->json('GET', '/api/admin/fiscal-config/2023');
        $list = $this->json('GET', '/api/admin/fiscal-config');

        self::assertSame(200, $complete['status']);
        self::assertSame([
            'year' => 2024,
            'remoteWorkDailyAllowance' => 2.70,
            'homeMealValue' => 5.35,
            'forfaitRatePercent' => 10,
            'forfaitMinimumCents' => 50_400,
            'forfaitMaximumCents' => 1_442_600,
            'version' => '2024.1',
            'source' => 'BOFiP-IR-BASE-2024',
            'effectiveFrom' => '2024-01-01',
            'taxComparisonComplete' => true,
            'missingTaxComparisonFields' => [],
        ], $complete['body']);

        self::assertSame(200, $partial['status']);
        self::assertNull($partial['body']['forfaitRatePercent']);
        self::assertNull($partial['body']['forfaitMaximumCents']);
        self::assertNull($partial['body']['effectiveFrom']);
        self::assertFalse($partial['body']['taxComparisonComplete']);
        self::assertSame(['forfait_rate_percent', 'forfait_minimum_cents', 'forfait_maximum_cents', 'version', 'source', 'effective_from'], $partial['body']['missingTaxComparisonFields']);

        self::assertSame(200, $list['status']);
        self::assertSame([2024, 2023], array_column($list['body'], 'year'));
        self::assertSame(404, $this->json('GET', '/api/admin/fiscal-config/2022')['status']);
    }

    public function testACompleteUpsertStoresTheSixParametersAndMakesTheComparisonAvailable(): void
    {
        $this->salaries->save(new NetTaxableSalary('s-1', 'person-a', 'ACME', 2025, new \DateTimeImmutable('2025-01-01'), new \DateTimeImmutable('2025-12-31'), 3_000_000));
        $before = ($this->comparison)(new GetTaxComparisonQuery('person-a', 2025, new \DateTimeImmutable('2026-05-01')));
        self::assertSame('configuration_missing', $before['status']);

        $created = $this->json('PUT', '/api/admin/fiscal-config/2025', self::COMPLETE_2025);

        self::assertSame(200, $created['status']);
        self::assertSame(1_455_500, $created['body']['forfaitMaximumCents'], '14 555,00 € are stored as 1455500 cents');
        self::assertSame(50_900, $created['body']['forfaitMinimumCents']);
        self::assertSame('2025-01-01', $created['body']['effectiveFrom']);
        self::assertTrue($created['body']['taxComparisonComplete']);
        self::assertTrue($this->configs->findByYear(2025)?->isComplete());

        $after = ($this->comparison)(new GetTaxComparisonQuery('person-a', 2025, new \DateTimeImmutable('2026-05-01')));
        self::assertSame('available', $after['status']);
        self::assertSame(300_000, $after['amounts']['theoreticalForfaitCents']);
        self::assertSame(300_000, $after['amounts']['forfaitCents']);
        self::assertSame(['year' => 2025, 'version' => '2025.1', 'source' => 'BOFiP-IR-BASE-2025', 'effectiveFrom' => '2025-01-01'], $after['configuration']);
    }

    public function testThePartialConfigurationStaysAllowedAndTheLegacyFieldsKeepWorking(): void
    {
        $created = $this->json('PUT', '/api/admin/fiscal-config/2023', ['remoteWorkDailyAllowance' => 2.50, 'homeMealValue' => 4.85]);
        self::assertSame(200, $created['status']);
        self::assertSame(2.50, $created['body']['remoteWorkDailyAllowance']);
        self::assertSame(4.85, $created['body']['homeMealValue']);
        self::assertFalse($created['body']['taxComparisonComplete']);

        // Updating only the legacy fields of a complete year keeps its forfait parameters.
        self::assertSame(200, $this->json('PUT', '/api/admin/fiscal-config/2025', self::COMPLETE_2025)['status']);
        $updated = $this->json('PUT', '/api/admin/fiscal-config/2025', ['remoteWorkDailyAllowance' => 2.80, 'homeMealValue' => 5.50]);
        self::assertSame(200, $updated['status']);
        self::assertSame(2.80, $updated['body']['remoteWorkDailyAllowance']);
        self::assertSame(1_455_500, $updated['body']['forfaitMaximumCents']);
        self::assertTrue($updated['body']['taxComparisonComplete']);

        self::assertSame(400, $this->json('PUT', '/api/admin/fiscal-config/2023', ['remoteWorkDailyAllowance' => 0])['status']);
        self::assertSame(400, $this->json('PUT', '/api/admin/fiscal-config/2023', ['remoteWorkDailyAllowance' => 2.5, 'homeMealValue' => -1])['status']);
    }

    public function testDomainViolationsAreRejectedWith422WithoutPartialSave(): void
    {
        $this->configs->save(new FiscalConfig(2025, '2.70', '5.45'));

        foreach ([
            'rate 0' => ['forfaitRatePercent' => 0],
            'rate 101' => ['forfaitRatePercent' => 101],
            'negative minimum' => ['forfaitMinimumCents' => -1],
            'negative maximum' => ['forfaitMaximumCents' => -100],
            'minimum above maximum' => ['forfaitMinimumCents' => 1_455_501],
            'blank version' => ['version' => ' '],
            'blank source' => ['source' => ''],
            'invalid date' => ['effectiveFrom' => '01/01/2025'],
            'incomplete group' => ['source' => null, 'effectiveFrom' => null],
            'rate as string' => ['forfaitRatePercent' => '10'],
            'non integer cents' => ['forfaitMaximumCents' => 14555.5],
        ] as $case => $overrides) {
            $response = $this->json('PUT', '/api/admin/fiscal-config/2025', array_merge(self::COMPLETE_2025, $overrides));
            self::assertSame(422, $response['status'], $case);
            self::assertIsString($response['body']['error'], $case);
            self::assertNotSame('', $response['body']['error'], $case);
        }

        $stored = $this->configs->findByYear(2025);
        self::assertNotNull($stored);
        self::assertFalse($stored->isComplete(), 'no partial save');
        self::assertSame(2.70, $stored->remoteWorkDailyAllowance());
        self::assertSame('configuration_missing', ($this->comparison)(new GetTaxComparisonQuery('person-a', 2025, new \DateTimeImmutable('2026-05-01')))['status']);
    }

    /**
     * @param array<string, mixed>|null $payload
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    private function json(string $method, string $path, ?array $payload = null): array
    {
        $request = Request::create($path, $method, server: ['CONTENT_TYPE' => 'application/json'], content: null === $payload ? null : (string) json_encode($payload));
        $response = $this->kernel->handle($request);
        \assert($response instanceof Response);
        $content = (string) $response->getContent();
        /** @var array<string, mixed> $body */
        $body = '' === $content || 'null' === $content ? [] : json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        return ['status' => $response->getStatusCode(), 'body' => $body];
    }
}
