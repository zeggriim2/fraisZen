<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Infrastructure;

use App\Admin\Domain\Entity\FiscalConfig;
use App\Admin\Domain\Repository\FiscalConfigRepositoryInterface;
use App\Auth\Domain\Entity\User;
use App\Auth\Domain\ValueObject\UserId;
use App\Expense\Domain\Entity\Expense;
use App\Expense\Domain\Entity\ParkingExpense;
use App\Expense\Domain\Repository\BaremeKilometriqueRepositoryInterface;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\Service\KilometricAllowanceCalculator;
use App\Expense\Domain\ValueObject\ExpenseId;
use App\Person\Domain\Entity\Person;
use App\Person\Domain\Repository\FavoriteRouteRepositoryInterface;
use App\Person\Domain\Repository\PersonRepositoryInterface;
use App\Person\Domain\ValueObject\PersonId;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use App\SharedKernel\Infrastructure\Http\ExceptionListener;
use App\SharedKernel\Infrastructure\Security\OwnershipGuard;
use App\Tax\Application\Query\GetTaxComparison\GetTaxComparisonQuery;
use App\Tax\Application\Query\GetTaxComparison\GetTaxComparisonQueryHandler;
use App\Tax\Application\Service\TaxInputsService;
use App\Tax\Domain\Entity\EmployerReimbursement;
use App\Tax\Domain\Entity\NetTaxableSalary;
use App\Tax\Domain\Repository\EmployerReimbursementRepositoryInterface;
use App\Tax\Domain\Repository\NetTaxableSalaryRepositoryInterface;
use App\Tax\Infrastructure\Http\TaxComparisonController;
use App\Tax\Infrastructure\Http\TaxInputsController;
use App\Tax\Infrastructure\Http\TaxYearClosureController;
use App\Tax\Application\Command\CloseTaxYear\CloseTaxYearCommand;
use App\Tax\Application\Command\CloseTaxYear\CloseTaxYearCommandHandler;
use App\Tax\Application\Command\ReopenTaxYear\ReopenTaxYearCommand;
use App\Tax\Application\Command\ReopenTaxYear\ReopenTaxYearCommandHandler;
use App\Tax\Application\Service\TaxComparisonCalculator;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use App\Tests\Unit\Tax\Support\InMemoryTaxYearClosures;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * HTTP integration of the Tax endpoints without database: real attribute routes and requirements,
 * real HttpKernel argument resolution, real OwnershipGuard and shared ExceptionListener, real query
 * handler and input service on in-memory repositories. Only persistence and JWT parsing are replaced
 * (the authenticated user is placed in the token storage as the JWT firewall would do).
 */
final class TaxHttpTest extends TestCase
{
    private const ALICE = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const BOB = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private const ALICE_PERSON = '11111111-1111-4111-8111-111111111111';
    private const ALICE_PARTNER = '22222222-2222-4222-8222-222222222222';
    private const BOB_PERSON = '33333333-3333-4333-8333-333333333333';

    private InMemorySalaries $salaries;
    private InMemoryReimbursements $reimbursements;
    private TokenStorage $tokenStorage;
    private InMemoryTaxYearClosures $closures;
    /** @var \ArrayObject<int, object> */
    private \ArrayObject $dispatchedCommands;
    /** @var \ArrayObject<int, GetTaxComparisonQuery> */
    private \ArrayObject $askedQueries;
    private HttpKernel $kernel;

    protected function setUp(): void
    {
        $this->salaries = new InMemorySalaries();
        $this->reimbursements = new InMemoryReimbursements();
        $this->tokenStorage = new TokenStorage();
        $this->askedQueries = new \ArrayObject();
        $this->closures = new InMemoryTaxYearClosures();
        $this->dispatchedCommands = new \ArrayObject();

        $persons = [
            self::ALICE_PERSON => new Person(PersonId::fromString(self::ALICE_PERSON), self::ALICE, 'Alice', 'Martin'),
            self::ALICE_PARTNER => new Person(PersonId::fromString(self::ALICE_PARTNER), self::ALICE, 'Paul', 'Martin'),
            self::BOB_PERSON => new Person(PersonId::fromString(self::BOB_PERSON), self::BOB, 'Bob', 'Durand'),
        ];
        $personRepository = $this->createStub(PersonRepositoryInterface::class);
        $personRepository->method('findById')->willReturnCallback(static fn (PersonId $id): ?Person => $persons[$id->value()] ?? null);
        $expenses = [
            new ParkingExpense(ExpenseId::generate(), self::ALICE_PERSON, new \DateTimeImmutable('2026-03-01'), null, 3_000.00),
            new ParkingExpense(ExpenseId::generate(), self::ALICE_PARTNER, new \DateTimeImmutable('2026-03-01'), null, 100.00),
        ];
        $expenseRepository = $this->createStub(ExpenseRepositoryInterface::class);
        $expenseRepository->method('findByPersonAndYear')->willReturnCallback(
            static fn (string $personId, int $year): array => array_values(array_filter($expenses, static fn (Expense $e): bool => $e->personId() === $personId)),
        );
        $expenseRepository->method('findById')->willReturn(null);
        $guard = new OwnershipGuard($personRepository, $expenseRepository, $this->createStub(FavoriteRouteRepositoryInterface::class));

        $config = new FiscalConfig(2026, '2.70', '5.35', 10, 50_000, 1_400_000, '2026.1', 'BOFiP-IR-BASE', new \DateTimeImmutable('2026-01-01'));
        $configs = $this->createStub(FiscalConfigRepositoryInterface::class);
        $configs->method('findByYear')->willReturnCallback(static fn (int $year): ?FiscalConfig => 2026 === $year ? $config : null);
        $kilometric = new KilometricAllowanceCalculator($this->createStub(BaremeKilometriqueRepositoryInterface::class));
        $handler = new GetTaxComparisonQueryHandler(
            $this->salaries,
            $this->reimbursements,
            $expenseRepository,
            $configs,
            $kilometric,
            closures: $this->closures,
        );
        $closeHandler = new CloseTaxYearCommandHandler(new TaxComparisonCalculator($this->salaries, $this->reimbursements, $expenseRepository, $configs, $kilometric), $this->closures);
        $reopenHandler = new ReopenTaxYearCommandHandler($this->closures);
        $commandBus = new class($closeHandler, $reopenHandler, $this->dispatchedCommands) implements CommandBusInterface {
            /** @param \ArrayObject<int, object> $dispatched */
            public function __construct(private CloseTaxYearCommandHandler $close, private ReopenTaxYearCommandHandler $reopen, private \ArrayObject $dispatched)
            {
            }

            public function dispatch(object $command): mixed
            {
                $this->dispatched[] = $command;

                return $command instanceof CloseTaxYearCommand ? ($this->close)($command) : ($this->reopen)($command instanceof ReopenTaxYearCommand ? $command : throw new \LogicException());
            }
        };
        $queryBus = new class($handler, $this->askedQueries) implements QueryBusInterface {
            /** @param \ArrayObject<int, GetTaxComparisonQuery> $asked */
            public function __construct(private GetTaxComparisonQueryHandler $handler, private \ArrayObject $asked)
            {
            }

            public function ask(object $query): mixed
            {
                \assert($query instanceof GetTaxComparisonQuery);
                $this->asked[] = $query;

                return ($this->handler)($query);
            }
        };

        $container = new Container();
        $container->set('security.token_storage', $this->tokenStorage);
        $comparison = new TaxComparisonController($queryBus, $guard, new MockClock('2026-10-30 12:00:00'));
        $comparison->setContainer($container);
        $inputs = new TaxInputsController(new TaxInputsService($this->salaries, $this->reimbursements, $expenseRepository), $guard);
        $inputs->setContainer($container);
        $closure = new TaxYearClosureController($commandBus, $guard, new MockClock('2027-02-10 09:30:00'));
        $closure->setContainer($container);

        $routes = new RouteCollection();
        $loader = new AttributeRouteControllerLoader();
        $routes->addCollection($loader->load(TaxComparisonController::class));
        $routes->addCollection($loader->load(TaxInputsController::class));
        $routes->addCollection($loader->load(TaxYearClosureController::class));

        $requestStack = new RequestStack();
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RouterListener(new UrlMatcher($routes, new RequestContext()), $requestStack));
        $dispatcher->addListener(KernelEvents::EXCEPTION, new ExceptionListener());
        $controllers = [TaxComparisonController::class => $comparison, TaxInputsController::class => $inputs, TaxYearClosureController::class => $closure];
        $resolver = new class($controllers) implements ControllerResolverInterface {
            /** @param array<string, object> $controllers */
            public function __construct(private array $controllers)
            {
            }

            public function getController(Request $request): callable|false
            {
                $controller = $request->attributes->get('_controller');
                \assert(\is_string($controller));
                [$class, $method] = str_contains($controller, '::') ? explode('::', $controller, 2) : [$controller, '__invoke'];
                $callable = [$this->controllers[$class], $method];
                \assert(\is_callable($callable));

                return $callable;
            }
        };
        $this->kernel = new HttpKernel($dispatcher, $resolver, $requestStack, new ArgumentResolver());
    }

    public function testComparisonEndpointReturnsOnlyTheRequestedPersonOfTheCurrentUser(): void
    {
        $this->loginAs(self::ALICE);
        $this->salaries->save(new NetTaxableSalary('s-alice', self::ALICE_PERSON, 'ACME', 2026, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'), 2_000_000));
        $this->salaries->save(new NetTaxableSalary('s-paul', self::ALICE_PARTNER, 'Beta', 2026, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'), 9_000_000));

        $alice = $this->json('GET', '/api/persons/'.self::ALICE_PERSON.'/tax-comparison/2026');
        $paul = $this->json('GET', '/api/persons/'.self::ALICE_PARTNER.'/tax-comparison/2026');

        self::assertSame(200, $alice['status']);
        self::assertSame(self::ALICE_PERSON, $alice['body']['personId']);
        self::assertSame('available', $alice['body']['status']);
        self::assertSame(2_000_000, $alice['body']['amounts']['netTaxableSalaryCents']);
        self::assertSame(300_000, $alice['body']['amounts']['realExpensesCents']);
        self::assertSame(100_000, $alice['body']['amounts']['differenceCents']);
        self::assertTrue($alice['body']['display']['showBanner']);
        self::assertSame(1_000, $alice['body']['display']['roundedDifferenceEuros']);
        self::assertSame('provisional_estimate', $alice['body']['display']['temporalQualification']);

        self::assertSame(self::ALICE_PARTNER, $paul['body']['personId']);
        self::assertSame(9_000_000, $paul['body']['amounts']['netTaxableSalaryCents']);
        self::assertSame(10_000, $paul['body']['amounts']['realExpensesCents']);
        self::assertFalse($paul['body']['display']['showBanner']);
        self::assertSame('less_advantageous', $paul['body']['display']['outcome']);
    }

    public function testComparisonOfAnotherUsersPersonIsNotFoundAndNeverComputed(): void
    {
        $this->loginAs(self::BOB);
        $this->salaries->save(new NetTaxableSalary('s-alice', self::ALICE_PERSON, 'ACME', 2026, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'), 2_000_000));

        $response = $this->json('GET', '/api/persons/'.self::ALICE_PERSON.'/tax-comparison/2026');
        $unknown = $this->json('GET', '/api/persons/44444444-4444-4444-8444-444444444444/tax-comparison/2026');

        self::assertSame(404, $response['status']);
        self::assertSame(['error'], array_keys($response['body']));
        self::assertSame(404, $unknown['status']);
        self::assertSame([], $this->askedQueries->getArrayCopy(), 'the engine must not run for a person of another user');
    }

    public function testComparisonRequiresAnAuthenticatedUserAndValidIdentifiers(): void
    {
        try {
            $this->request('GET', '/api/persons/'.self::ALICE_PERSON.'/tax-comparison/2026');
            self::fail('anonymous access must be denied');
        } catch (AccessDeniedException) {
        }

        $this->loginAs(self::ALICE);
        foreach (['/api/persons/not-a-uuid/tax-comparison/2026', '/api/persons/'.self::ALICE_PERSON.'/tax-comparison/26'] as $path) {
            try {
                $this->request('GET', $path);
                self::fail($path.' must not match');
            } catch (NotFoundHttpException) {
            }
        }
        self::assertSame(400, $this->json('GET', '/api/persons/'.self::ALICE_PERSON.'/tax-comparison/1800')['status']);
        self::assertSame([], $this->askedQueries->getArrayCopy());
    }

    public function testIncompleteComparisonLinksTargetTheFieldsServedByTheTaxInputsEndpoint(): void
    {
        $this->loginAs(self::ALICE);
        $this->reimbursements->save(new EmployerReimbursement('r-1', self::ALICE_PERSON, 'ACME', 2026, 5_000, 'transport', null, null, '55555555-5555-4555-8555-555555555555'));

        $comparison = $this->json('GET', '/api/persons/'.self::ALICE_PERSON.'/tax-comparison/2026')['body'];

        self::assertSame('incomplete', $comparison['status']);
        self::assertSame(['net_taxable_salary[2026]', 'employer_reimbursement[r-1].to_reintegrate', 'employer_reimbursement[r-1].expense[55555555-5555-4555-8555-555555555555]'], $comparison['missing']);
        self::assertSame(
            ['#tax-field-net-taxable-salary-'.self::ALICE_PERSON, '#tax-field-employer-reimbursement-r-1', '#tax-field-employer-reimbursement-expense-r-1'],
            array_map(static fn (array $f): string => $f['link']['hash'], $comparison['display']['missingFields']),
        );

        // The panel opened by those links lists the very inputs, with the state of the linked expense.
        $inputs = $this->json('GET', '/api/persons/'.self::ALICE_PERSON.'/tax-inputs/2026')['body'];
        self::assertSame([], $inputs['salaries']);
        self::assertSame('r-1', $inputs['reimbursements'][0]['id']);
        self::assertNull($inputs['reimbursements'][0]['toReintegrate']);
        self::assertFalse($inputs['reimbursements'][0]['linkedExpenseFound']);

        // Completing the three fields through the API makes the comparison available.
        $created = $this->json('POST', '/api/persons/'.self::ALICE_PERSON.'/tax-inputs/2026/salaries', ['employer' => 'ACME', 'periodStart' => '2026-01-01', 'periodEnd' => '2026-12-31', 'amountCents' => 2_000_000]);
        self::assertSame(201, $created['status']);
        self::assertSame(2_000_000, $created['body']['amountCents']);
        self::assertSame(200, $this->json('PATCH', '/api/persons/'.self::ALICE_PERSON.'/tax-inputs/reimbursements/r-1', ['toReintegrate' => true])['status']);
        $unlinked = $this->json('PATCH', '/api/persons/'.self::ALICE_PERSON.'/tax-inputs/reimbursements/r-1', ['unlinkExpense' => true]);
        self::assertNull($unlinked['body']['expenseId']);
        self::assertTrue($unlinked['body']['toReintegrate']);

        $after = $this->json('GET', '/api/persons/'.self::ALICE_PERSON.'/tax-comparison/2026')['body'];
        self::assertSame('available', $after['status']);
        self::assertSame(5_000, $after['amounts']['reintegratedReimbursementsCents']);
        self::assertSame(95_000, $after['amounts']['differenceCents']);
    }

    public function testTaxInputsCannotBeReadOrChangedThroughAnotherPerson(): void
    {
        $this->loginAs(self::ALICE);
        $this->reimbursements->save(new EmployerReimbursement('r-paul', self::ALICE_PARTNER, 'Beta', 2026, 5_000, 'transport', null));
        $this->reimbursements->save(new EmployerReimbursement('r-bob', self::BOB_PERSON, 'Gamma', 2026, 5_000, 'transport', null));
        $this->salaries->save(new NetTaxableSalary('66666666-6666-4666-8666-666666666666', self::ALICE_PARTNER, 'Beta', 2026, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'), 1_000));

        // Same household, but the identifier belongs to the partner: not found through Alice.
        self::assertSame(404, $this->json('PATCH', '/api/persons/'.self::ALICE_PERSON.'/tax-inputs/reimbursements/r-paul', ['toReintegrate' => true])['status']);
        self::assertSame(404, $this->json('DELETE', '/api/persons/'.self::ALICE_PERSON.'/tax-inputs/salaries/66666666-6666-4666-8666-666666666666')['status']);
        // Another user's person.
        self::assertSame(404, $this->json('GET', '/api/persons/'.self::BOB_PERSON.'/tax-inputs/2026')['status']);
        self::assertSame(404, $this->json('PATCH', '/api/persons/'.self::BOB_PERSON.'/tax-inputs/reimbursements/r-bob', ['toReintegrate' => true])['status']);
        self::assertSame(404, $this->json('POST', '/api/persons/'.self::BOB_PERSON.'/tax-inputs/2026/salaries', ['employer' => 'X', 'periodStart' => '2026-01-01', 'periodEnd' => '2026-12-31', 'amountCents' => 1])['status']);

        self::assertNull($this->reimbursements->findById('r-paul')?->toReintegrate());
        self::assertNull($this->reimbursements->findById('r-bob')?->toReintegrate());
        self::assertNotNull($this->salaries->findById('66666666-6666-4666-8666-666666666666'));
        self::assertSame([], $this->salaries->findByPersonAndYear(self::BOB_PERSON, 2026));
        self::assertSame(['r-paul'], array_column($this->json('GET', '/api/persons/'.self::ALICE_PARTNER.'/tax-inputs/2026')['body']['reimbursements'], 'id'));
    }

    public function testInvalidTaxInputsAreRejected(): void
    {
        $this->loginAs(self::ALICE);
        $base = '/api/persons/'.self::ALICE_PERSON.'/tax-inputs/2026';

        foreach ([
            ['employer' => 'ACME', 'periodStart' => '2025-01-01', 'periodEnd' => '2026-12-31', 'amountCents' => 1],
            ['employer' => 'ACME', 'periodStart' => '2026-12-31', 'periodEnd' => '2026-01-01', 'amountCents' => 1],
            ['employer' => ' ', 'periodStart' => '2026-01-01', 'periodEnd' => '2026-12-31', 'amountCents' => 1],
            ['employer' => 'ACME', 'periodStart' => '2026-02-30', 'periodEnd' => '2026-12-31', 'amountCents' => 1],
            ['employer' => 'ACME', 'periodStart' => '2026-01-01', 'periodEnd' => '2026-12-31', 'amountCents' => -1],
            ['employer' => 'ACME', 'periodStart' => '2026-01-01', 'periodEnd' => '2026-12-31', 'amountCents' => '100'],
        ] as $payload) {
            self::assertSame(422, $this->json('POST', $base.'/salaries', $payload)['status'], (string) json_encode($payload));
        }
        foreach ([
            ['employer' => 'ACME', 'amountCents' => 1, 'category' => 'unknown', 'toReintegrate' => null],
            ['employer' => 'ACME', 'amountCents' => 1, 'category' => 'meal', 'toReintegrate' => 'yes'],
            ['employer' => 'ACME', 'amountCents' => 1, 'category' => 'meal', 'toReintegrate' => true, 'date' => '2025-05-01'],
        ] as $payload) {
            self::assertSame(422, $this->json('POST', $base.'/reimbursements', $payload)['status'], (string) json_encode($payload));
        }
        self::assertSame([], $this->salaries->findByPersonAndYear(self::ALICE_PERSON, 2026));

        $created = $this->json('POST', $base.'/reimbursements', ['employer' => 'ACME', 'amountCents' => 1_000, 'category' => 'meal', 'toReintegrate' => null, 'date' => '2026-05-01']);
        self::assertSame(201, $created['status']);
        self::assertNull($created['body']['toReintegrate']);
        $id = $created['body']['id'];
        self::assertSame(422, $this->json('PATCH', '/api/persons/'.self::ALICE_PERSON.'/tax-inputs/reimbursements/'.$id, [])['status']);
        self::assertSame(204, $this->json('DELETE', '/api/persons/'.self::ALICE_PERSON.'/tax-inputs/reimbursements/'.$id)['status']);
        self::assertSame([], $this->reimbursements->findByPersonAndYear(self::ALICE_PERSON, 2026));
    }

    public function testTheOwnerValidatesClosesAndExplicitlyReopensAYearWithoutLosingTheSnapshot(): void
    {
        $this->loginAs(self::ALICE);
        $this->salaries->save(new NetTaxableSalary('s-alice', self::ALICE_PERSON, 'ACME', 2026, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'), 2_000_000));
        $comparisonPath = '/api/persons/'.self::ALICE_PERSON.'/tax-comparison/2026';
        $closurePath = $comparisonPath.'/closure';

        // 1. Review: the open calculation exposes the fingerprint to validate.
        $reviewed = $this->json('GET', $comparisonPath)['body'];
        self::assertSame('available', $reviewed['status']);
        self::assertSame('open', $reviewed['closure']['state']);
        self::assertTrue($reviewed['closure']['closable']);
        $fingerprint = $reviewed['closure']['calculationFingerprint'];

        // 2. Explicit confirmation and the reviewed fingerprint are required.
        self::assertSame(422, $this->json('POST', $closurePath, ['calculationFingerprint' => $fingerprint])['status']);
        self::assertSame(422, $this->json('POST', $closurePath, ['confirm' => true, 'calculationFingerprint' => 'abc'])['status']);
        self::assertSame(400, $this->json('POST', $closurePath, null)['status']);
        self::assertSame(409, $this->json('POST', $closurePath, ['confirm' => true, 'calculationFingerprint' => str_repeat('0', 64)])['status']);
        self::assertSame([], $this->closures->rows);

        // 3. Closing.
        $closed = $this->json('POST', $closurePath, ['confirm' => true, 'calculationFingerprint' => $fingerprint]);
        self::assertSame(201, $closed['status']);
        self::assertSame(['personId' => self::ALICE_PERSON, 'year' => 2026, 'version' => 1, 'state' => 'closed', 'closedAt' => '2027-02-10T09:30:00+00:00', 'reopenedAt' => null], $closed['body']);
        self::assertSame(self::ALICE, $this->closures->rows[0]->closedBy());

        $read = $this->json('GET', $comparisonPath)['body'];
        self::assertSame('closed', $read['status']);
        self::assertSame('closed_estimate', $read['display']['temporalQualification']);
        self::assertSame(100_000, $read['amounts']['differenceCents']);

        // 4. A modification after closing does not replace the snapshot: the difference is shown.
        self::assertSame(201, $this->json('POST', '/api/persons/'.self::ALICE_PERSON.'/tax-inputs/2026/salaries', ['employer' => 'Beta', 'periodStart' => '2026-07-01', 'periodEnd' => '2026-12-31', 'amountCents' => 500_000])['status']);
        $afterChange = $this->json('GET', $comparisonPath)['body'];
        self::assertSame('closed', $afterChange['status']);
        self::assertSame(2_000_000, $afterChange['amounts']['netTaxableSalaryCents']);
        self::assertTrue($afterChange['closure']['afterClosure']['dataChanged']);
        self::assertSame(50_000, $afterChange['closure']['afterClosure']['currentDifferenceCents']);
        self::assertSame(409, $this->json('POST', $closurePath, ['confirm' => true, 'calculationFingerprint' => $afterChange['closure']['calculationFingerprint']])['status']);

        // 5. Explicit reopening of the version in force.
        self::assertSame(422, $this->json('POST', $closurePath.'/reopen', ['version' => 1])['status']);
        self::assertSame(409, $this->json('POST', $closurePath.'/reopen', ['confirm' => true, 'version' => 2])['status']);
        $reopened = $this->json('POST', $closurePath.'/reopen', ['confirm' => true, 'version' => 1]);
        self::assertSame(200, $reopened['status']);
        self::assertSame('reopened', $reopened['body']['state']);

        $open = $this->json('GET', $comparisonPath)['body'];
        self::assertSame('available', $open['status']);
        self::assertSame(50_000, $open['amounts']['differenceCents']);
        self::assertSame([['version' => 1, 'state' => 'reopened', 'closedAt' => '2027-02-10T09:30:00+00:00', 'reopenedAt' => '2027-02-10T09:30:00+00:00']], $open['closure']['history']);
        self::assertSame(100_000, $this->closures->rows[0]->closedResult()->amounts()['differenceCents'] ?? null);
    }

    public function testClosingRequiresTheOwnerOfThePersonAndACompleteCalculation(): void
    {
        $this->salaries->save(new NetTaxableSalary('s-alice', self::ALICE_PERSON, 'ACME', 2026, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'), 2_000_000));
        $closurePath = '/api/persons/'.self::ALICE_PERSON.'/tax-comparison/2026/closure';

        try {
            $this->request('POST', $closurePath, ['confirm' => true, 'calculationFingerprint' => str_repeat('a', 64)]);
            self::fail('anonymous closing must be denied');
        } catch (AccessDeniedException) {
        }

        $this->loginAs(self::BOB);
        self::assertSame(404, $this->json('POST', $closurePath, ['confirm' => true, 'calculationFingerprint' => str_repeat('a', 64)])['status']);
        self::assertSame(404, $this->json('POST', $closurePath.'/reopen', ['confirm' => true, 'version' => 1])['status']);
        self::assertSame([], $this->dispatchedCommands->getArrayCopy(), 'nothing is dispatched for a person of another user');

        // Alice's partner has no salary: incomplete, not closable.
        $this->loginAs(self::ALICE);
        $partner = $this->json('GET', '/api/persons/'.self::ALICE_PARTNER.'/tax-comparison/2026')['body'];
        self::assertSame('incomplete', $partner['status']);
        self::assertFalse($partner['closure']['closable']);
        self::assertSame(422, $this->json('POST', '/api/persons/'.self::ALICE_PARTNER.'/tax-comparison/2026/closure', ['confirm' => true, 'calculationFingerprint' => $partner['closure']['calculationFingerprint']])['status']);
        self::assertSame(409, $this->json('POST', '/api/persons/'.self::ALICE_PARTNER.'/tax-comparison/2026/closure/reopen', ['confirm' => true, 'version' => 1])['status']);
        self::assertSame([], $this->closures->rows);
    }

    private function loginAs(string $userId): void
    {
        $user = new User(UserId::fromString($userId), $userId.'@example.com', 'hash');
        $this->tokenStorage->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));
    }

    /** @param array<string, mixed>|null $payload */
    private function request(string $method, string $path, ?array $payload = null): Response
    {
        $request = Request::create($path, $method, server: ['CONTENT_TYPE' => 'application/json'], content: null === $payload ? null : (string) json_encode($payload));

        return $this->kernel->handle($request);
    }

    /**
     * @param array<string, mixed>|null $payload
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    private function json(string $method, string $path, ?array $payload = null): array
    {
        $response = $this->request($method, $path, $payload);
        $content = (string) $response->getContent();
        /** @var array<string, mixed> $body */
        $body = '' === $content || 'null' === $content || '{}' === $content ? [] : json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        return ['status' => $response->getStatusCode(), 'body' => $body];
    }
}

final class InMemorySalaries implements NetTaxableSalaryRepositoryInterface
{
    /** @var array<string, NetTaxableSalary> */
    private array $rows = [];

    public function save(NetTaxableSalary $salary): void
    {
        $this->rows[$salary->id()] = $salary;
    }

    public function remove(NetTaxableSalary $salary): void
    {
        unset($this->rows[$salary->id()]);
    }

    public function findById(string $id): ?NetTaxableSalary
    {
        return $this->rows[$id] ?? null;
    }

    public function findByPersonAndYear(string $personId, int $year): array
    {
        return array_values(array_filter($this->rows, static fn (NetTaxableSalary $s): bool => $s->personId() === $personId && $s->year() === $year));
    }
}

final class InMemoryReimbursements implements EmployerReimbursementRepositoryInterface
{
    /** @var array<string, EmployerReimbursement> */
    private array $rows = [];

    public function save(EmployerReimbursement $reimbursement): void
    {
        $this->rows[$reimbursement->id()] = $reimbursement;
    }

    public function remove(EmployerReimbursement $reimbursement): void
    {
        unset($this->rows[$reimbursement->id()]);
    }

    public function findById(string $id): ?EmployerReimbursement
    {
        return $this->rows[$id] ?? null;
    }

    public function findByPersonAndYear(string $personId, int $year): array
    {
        return array_values(array_filter($this->rows, static fn (EmployerReimbursement $r): bool => $r->personId() === $personId && $r->year() === $year));
    }
}
