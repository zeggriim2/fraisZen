# Export Pattern Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the two format-specific exporter interfaces (returning HTTP responses) with a unified `SummaryExporterInterface` returning an `ExportResult` value object, wired through a registry, so the controller owns HTTP construction and adding a new format requires only one new class.

**Architecture:** `ExportResult` (VO in Application layer) carries raw bytes + mime type + filename. `SummaryExporterInterface` defines `format(): string` and `export(): ExportResult`. `SummaryExporterRegistry` resolves the right exporter by format string using Symfony tagged services. The controller wraps `ExportResult` into a `Response`.

**Tech Stack:** PHP 8.5, Symfony 8, Pest (tests), Dompdf (PDF), Twig (PDF template), Symfony DI tagged iterators.

---

## File Map

| Action | File |
|---|---|
| Create | `src/Expense/Application/Export/ExportResult.php` |
| Create | `src/Expense/Application/Export/SummaryExporterInterface.php` |
| Create | `src/Expense/Application/Export/SummaryExporterRegistry.php` |
| Delete | `src/Expense/Application/Export/SummaryPdfExporterInterface.php` |
| Delete | `src/Expense/Application/Export/SummaryCsvExporterInterface.php` |
| Update | `src/Expense/Infrastructure/Export/DompdfSummaryPdfExporter.php` |
| Replace | `src/Expense/Infrastructure/Export/StreamedSummaryCsvExporter.php` → `CsvSummaryExporter.php` |
| Update | `src/Expense/Infrastructure/Http/ExpenseController.php` |
| Update | `config/services.yaml` |
| Create | `tests/Unit/Expense/Application/Export/ExportResultTest.php` |
| Create | `tests/Unit/Expense/Application/Export/SummaryExporterRegistryTest.php` |
| Create | `tests/Unit/Expense/Infrastructure/Export/DompdfSummaryPdfExporterTest.php` |
| Create | `tests/Unit/Expense/Infrastructure/Export/CsvSummaryExporterTest.php` |

---

### Task 1: ExportResult Value Object

**Files:**
- Create: `src/Expense/Application/Export/ExportResult.php`
- Create: `tests/Unit/Expense/Application/Export/ExportResultTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Unit/Expense/Application/Export/ExportResultTest.php
<?php

declare(strict_types=1);

use App\Expense\Application\Export\ExportResult;

it('exposes content, mimeType and filename', function () {
    $result = new ExportResult('raw-bytes', 'application/pdf', 'frais-reels-2025.pdf');

    expect($result->content)->toBe('raw-bytes');
    expect($result->mimeType)->toBe('application/pdf');
    expect($result->filename)->toBe('frais-reels-2025.pdf');
});
```

- [ ] **Step 2: Run test — verify it fails**

```bash
docker compose exec -e APP_ENV=test php vendor/bin/pest tests/Unit/Expense/Application/Export/ExportResultTest.php
```

Expected: FAIL — `Class "App\Expense\Application\Export\ExportResult" not found`

- [ ] **Step 3: Implement ExportResult**

```php
// src/Expense/Application/Export/ExportResult.php
<?php

declare(strict_types=1);

namespace App\Expense\Application\Export;

final readonly class ExportResult
{
    public function __construct(
        public string $content,
        public string $mimeType,
        public string $filename,
    ) {
    }
}
```

- [ ] **Step 4: Run test — verify it passes**

```bash
docker compose exec -e APP_ENV=test php vendor/bin/pest tests/Unit/Expense/Application/Export/ExportResultTest.php
```

Expected: PASS (1 test, 3 assertions)

- [ ] **Step 5: Commit**

```bash
git add src/Expense/Application/Export/ExportResult.php \
        tests/Unit/Expense/Application/Export/ExportResultTest.php
git commit -m "feat(export): add ExportResult value object"
```

---

### Task 2: SummaryExporterInterface

**Files:**
- Create: `src/Expense/Application/Export/SummaryExporterInterface.php`

No behavior to test — it is a pure contract. Create and commit.

- [ ] **Step 1: Create the interface**

```php
// src/Expense/Application/Export/SummaryExporterInterface.php
<?php

declare(strict_types=1);

namespace App\Expense\Application\Export;

interface SummaryExporterInterface
{
    public function format(): string;

    /** @param array<string, mixed> $data */
    public function export(array $data, int $year): ExportResult;
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Expense/Application/Export/SummaryExporterInterface.php
git commit -m "feat(export): add unified SummaryExporterInterface"
```

---

### Task 3: SummaryExporterRegistry

**Files:**
- Create: `src/Expense/Application/Export/SummaryExporterRegistry.php`
- Create: `tests/Unit/Expense/Application/Export/SummaryExporterRegistryTest.php`

- [ ] **Step 1: Write failing tests**

```php
// tests/Unit/Expense/Application/Export/SummaryExporterRegistryTest.php
<?php

declare(strict_types=1);

use App\Expense\Application\Export\ExportResult;
use App\Expense\Application\Export\SummaryExporterInterface;
use App\Expense\Application\Export\SummaryExporterRegistry;

function makeExporter(string $format): SummaryExporterInterface
{
    return new class($format) implements SummaryExporterInterface {
        public function __construct(private string $fmt) {}

        public function format(): string { return $this->fmt; }

        public function export(array $data, int $year): ExportResult
        {
            return new ExportResult('', 'text/plain', "file-$year.txt");
        }
    };
}

it('returns the exporter matching the requested format', function () {
    $registry = new SummaryExporterRegistry([
        makeExporter('pdf'),
        makeExporter('csv'),
    ]);

    expect($registry->get('pdf')->format())->toBe('pdf');
    expect($registry->get('csv')->format())->toBe('csv');
});

it('throws InvalidArgumentException when no exporter matches', function () {
    $registry = new SummaryExporterRegistry([makeExporter('pdf')]);

    expect(fn () => $registry->get('xlsx'))
        ->toThrow(\InvalidArgumentException::class, "No exporter for format 'xlsx'");
});
```

- [ ] **Step 2: Run tests — verify they fail**

```bash
docker compose exec -e APP_ENV=test php vendor/bin/pest tests/Unit/Expense/Application/Export/SummaryExporterRegistryTest.php
```

Expected: FAIL — `Class "App\Expense\Application\Export\SummaryExporterRegistry" not found`

- [ ] **Step 3: Implement the registry**

```php
// src/Expense/Application/Export/SummaryExporterRegistry.php
<?php

declare(strict_types=1);

namespace App\Expense\Application\Export;

final class SummaryExporterRegistry
{
    /** @param iterable<SummaryExporterInterface> $exporters */
    public function __construct(private iterable $exporters)
    {
    }

    public function get(string $format): SummaryExporterInterface
    {
        foreach ($this->exporters as $exporter) {
            if ($exporter->format() === $format) {
                return $exporter;
            }
        }

        throw new \InvalidArgumentException("No exporter for format '$format'");
    }
}
```

- [ ] **Step 4: Run tests — verify they pass**

```bash
docker compose exec -e APP_ENV=test php vendor/bin/pest tests/Unit/Expense/Application/Export/SummaryExporterRegistryTest.php
```

Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Expense/Application/Export/SummaryExporterRegistry.php \
        tests/Unit/Expense/Application/Export/SummaryExporterRegistryTest.php
git commit -m "feat(export): add SummaryExporterRegistry with tagged-iterator support"
```

---

### Task 4: Update DompdfSummaryPdfExporter

**Files:**
- Update: `src/Expense/Infrastructure/Export/DompdfSummaryPdfExporter.php`
- Create: `tests/Unit/Expense/Infrastructure/Export/DompdfSummaryPdfExporterTest.php`

- [ ] **Step 1: Write failing test**

```php
// tests/Unit/Expense/Infrastructure/Export/DompdfSummaryPdfExporterTest.php
<?php

declare(strict_types=1);

use App\Expense\Application\Export\ExportResult;
use App\Expense\Infrastructure\Export\DompdfSummaryPdfExporter;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

function makePdfExporter(): DompdfSummaryPdfExporter
{
    $twig = new Environment(new FilesystemLoader(__DIR__.'/../../../../../templates'));

    return new DompdfSummaryPdfExporter($twig);
}

$data = [
    'travel' => ['trips' => [], 'totalKm' => 0.0, 'deduction' => 0.0],
    'remoteWork' => ['days' => 0, 'dailyAllowance' => 0.0, 'deduction' => 0.0],
    'toll' => ['entries' => 0, 'deduction' => 0.0],
    'meal' => ['entries' => 0, 'homeMealValue' => 0.0, 'deduction' => 0.0],
    'parking' => ['entries' => 0, 'deduction' => 0.0],
    'total' => 0.0,
];

it('returns format pdf', function () {
    expect(makePdfExporter()->format())->toBe('pdf');
});

it('returns ExportResult with pdf mimeType and correct filename', function () use ($data) {
    $result = makePdfExporter()->export($data, 2025);

    expect($result)->toBeInstanceOf(ExportResult::class);
    expect($result->mimeType)->toBe('application/pdf');
    expect($result->filename)->toBe('frais-reels-2025.pdf');
    expect($result->content)->not->toBeEmpty();
});
```

- [ ] **Step 2: Run test — verify it fails**

```bash
docker compose exec -e APP_ENV=test php vendor/bin/pest tests/Unit/Expense/Infrastructure/Export/DompdfSummaryPdfExporterTest.php
```

Expected: FAIL — `DompdfSummaryPdfExporter::format()` does not exist, `export()` returns `Response` not `ExportResult`

- [ ] **Step 3: Update DompdfSummaryPdfExporter**

Full file replacement:

```php
// src/Expense/Infrastructure/Export/DompdfSummaryPdfExporter.php
<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Export;

use App\Expense\Application\Export\ExportResult;
use App\Expense\Application\Export\SummaryExporterInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

final class DompdfSummaryPdfExporter implements SummaryExporterInterface
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function format(): string
    {
        return 'pdf';
    }

    public function export(array $data, int $year): ExportResult
    {
        $html = $this->twig->render('expense/summary_pdf.html.twig', [
            'data' => $data,
            'year' => $year,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new ExportResult(
            $dompdf->output(),
            'application/pdf',
            sprintf('frais-reels-%s.pdf', $year),
        );
    }
}
```

- [ ] **Step 4: Run test — verify it passes**

```bash
docker compose exec -e APP_ENV=test php vendor/bin/pest tests/Unit/Expense/Infrastructure/Export/DompdfSummaryPdfExporterTest.php
```

Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Expense/Infrastructure/Export/DompdfSummaryPdfExporter.php \
        tests/Unit/Expense/Infrastructure/Export/DompdfSummaryPdfExporterTest.php
git commit -m "refactor(export): DompdfSummaryPdfExporter implements SummaryExporterInterface, returns ExportResult"
```

---

### Task 5: CsvSummaryExporter (replaces StreamedSummaryCsvExporter)

**Files:**
- Create: `src/Expense/Infrastructure/Export/CsvSummaryExporter.php`
- Create: `tests/Unit/Expense/Infrastructure/Export/CsvSummaryExporterTest.php`

The old `StreamedSummaryCsvExporter` will be deleted in Task 7. For now, both files can coexist.

- [ ] **Step 1: Write failing tests**

```php
// tests/Unit/Expense/Infrastructure/Export/CsvSummaryExporterTest.php
<?php

declare(strict_types=1);

use App\Expense\Application\Export\ExportResult;
use App\Expense\Infrastructure\Export\CsvSummaryExporter;

$data = [
    'travel' => [
        'trips' => [
            [
                'date' => '2025-03-15',
                'departure' => 'Paris',
                'arrival' => 'Lyon',
                'description' => null,
                'roundTrip' => true,
                'distanceKm' => 450,
                'vehiclePower' => 5,
            ],
        ],
        'totalKm' => 450.0,
        'deduction' => 286.20,
    ],
    'remoteWork' => ['days' => 10, 'dailyAllowance' => 2.50, 'deduction' => 25.0],
    'toll' => ['entries' => 3, 'deduction' => 18.90],
    'meal' => ['entries' => 5, 'homeMealValue' => 4.15, 'deduction' => 73.70],
    'parking' => ['entries' => 2, 'deduction' => 12.0],
    'total' => 415.80,
];

it('returns format csv', function () {
    expect((new CsvSummaryExporter())->format())->toBe('csv');
});

it('returns ExportResult with csv mimeType and correct filename', function () use ($data) {
    $result = (new CsvSummaryExporter())->export($data, 2025);

    expect($result)->toBeInstanceOf(ExportResult::class);
    expect($result->mimeType)->toBe('text/csv; charset=UTF-8');
    expect($result->filename)->toBe('frais-reels-2025.csv');
});

it('includes year and trip data in the csv content', function () use ($data) {
    $result = (new CsvSummaryExporter())->export($data, 2025);

    expect($result->content)->toContain('2025');
    expect($result->content)->toContain('Paris');
    expect($result->content)->toContain('Lyon');
});
```

- [ ] **Step 2: Run tests — verify they fail**

```bash
docker compose exec -e APP_ENV=test php vendor/bin/pest tests/Unit/Expense/Infrastructure/Export/CsvSummaryExporterTest.php
```

Expected: FAIL — `Class "App\Expense\Infrastructure\Export\CsvSummaryExporter" not found`

- [ ] **Step 3: Implement CsvSummaryExporter**

```php
// src/Expense/Infrastructure/Export/CsvSummaryExporter.php
<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Export;

use App\Expense\Application\Export\ExportResult;
use App\Expense\Application\Export\SummaryExporterInterface;
use Webmozart\Assert\Assert;

final class CsvSummaryExporter implements SummaryExporterInterface
{
    public function format(): string
    {
        return 'csv';
    }

    public function export(array $data, int $year): ExportResult
    {
        $handle = fopen('php://memory', 'r+');
        Assert::resource($handle);

        // BOM UTF-8 pour compatibilité Excel
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, ["Récapitulatif frais réels - $year"], ';');
        fputcsv($handle, [], ';');

        fputcsv($handle, ['Catégorie', 'Détail', 'Déduction (€)'], ';');
        fputcsv($handle, [
            'Trajets',
            count($data['travel']['trips']).' trajets — '.(int) round($data['travel']['totalKm']).' km',
            number_format($data['travel']['deduction'], 2, ',', ' '),
        ], ';');
        fputcsv($handle, [
            'Télétravail',
            $data['remoteWork']['days'].' jours × '.number_format($data['remoteWork']['dailyAllowance'], 2, ',', ' ').' €',
            number_format($data['remoteWork']['deduction'], 2, ',', ' '),
        ], ';');
        fputcsv($handle, [
            'Péages',
            $data['toll']['entries'].' entrées',
            number_format($data['toll']['deduction'], 2, ',', ' '),
        ], ';');
        fputcsv($handle, [
            'Repas',
            $data['meal']['entries'].' repas − '.number_format($data['meal']['homeMealValue'], 2, ',', ' ').' €/repas',
            number_format($data['meal']['deduction'], 2, ',', ' '),
        ], ';');
        fputcsv($handle, [
            'Parking',
            ($data['parking']['entries'] ?? 0).' entrées',
            number_format($data['parking']['deduction'] ?? 0, 2, ',', ' '),
        ], ';');
        fputcsv($handle, ['TOTAL DÉDUCTIBLE', '', number_format($data['total'], 2, ',', ' ')], ';');
        fputcsv($handle, [], ';');

        if (!empty($data['travel']['trips'])) {
            fputcsv($handle, ['Détail des trajets'], ';');
            fputcsv($handle, ['Date', 'Départ', 'Arrivée', 'Description', 'Distance (km)', 'Puissance (CV)', 'A/R'], ';');
            foreach ($data['travel']['trips'] as $t) {
                fputcsv($handle, [
                    $t['date'],
                    $t['departure'] ?? '',
                    $t['arrival'] ?? '',
                    $t['description'] ?? '',
                    $t['distanceKm'],
                    $t['vehiclePower'] ?? '',
                    $t['roundTrip'] ? 'Oui' : 'Non',
                ], ';');
            }
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);
        Assert::string($content);

        return new ExportResult(
            $content,
            'text/csv; charset=UTF-8',
            sprintf('frais-reels-%s.csv', $year),
        );
    }
}
```

- [ ] **Step 4: Run tests — verify they pass**

```bash
docker compose exec -e APP_ENV=test php vendor/bin/pest tests/Unit/Expense/Infrastructure/Export/CsvSummaryExporterTest.php
```

Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Expense/Infrastructure/Export/CsvSummaryExporter.php \
        tests/Unit/Expense/Infrastructure/Export/CsvSummaryExporterTest.php
git commit -m "feat(export): add CsvSummaryExporter implementing SummaryExporterInterface"
```

---

### Task 6: Wire DI and update controller

**Files:**
- Update: `config/services.yaml`
- Update: `src/Expense/Infrastructure/Http/ExpenseController.php`

- [ ] **Step 1: Update services.yaml**

Replace the `# Expense export services` block and extend `_instanceof`:

```yaml
# Expense export services
App\Expense\Application\Export\SummaryExporterRegistry:
    arguments:
        $exporters: !tagged_iterator app.summary_exporter
```

Remove these two lines entirely:
```yaml
App\Expense\Application\Export\SummaryPdfExporterInterface:
    alias: App\Expense\Infrastructure\Export\DompdfSummaryPdfExporter

App\Expense\Application\Export\SummaryCsvExporterInterface:
    alias: App\Expense\Infrastructure\Export\StreamedSummaryCsvExporter
```

Add to the `_instanceof` block (after the existing `QueryHandlerInterface` entry):
```yaml
App\Expense\Application\Export\SummaryExporterInterface:
    tags: ['app.summary_exporter']
```

Final `_instanceof` block:
```yaml
_instanceof:
    App\SharedKernel\Application\Bus\CommandHandlerInterface:
        tags: [{ name: messenger.message_handler, bus: command.bus }]
    App\SharedKernel\Application\Bus\QueryHandlerInterface:
        tags: [{ name: messenger.message_handler, bus: query.bus }]
    App\Expense\Application\Export\SummaryExporterInterface:
        tags: ['app.summary_exporter']
```

- [ ] **Step 2: Update ExpenseController**

Full file replacement:

```php
// src/Expense/Infrastructure/Http/ExpenseController.php
<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Http;

use App\Admin\Domain\Repository\FiscalConfigRepositoryInterface;
use App\Expense\Application\Command\CreateMealExpense\CreateMealExpenseCommand;
use App\Expense\Application\Command\CreateParkingExpense\CreateParkingExpenseCommand;
use App\Expense\Application\Command\CreateRemoteWorkExpense\CreateRemoteWorkExpenseCommand;
use App\Expense\Application\Command\CreateTollExpense\CreateTollExpenseCommand;
use App\Expense\Application\Command\CreateTravelExpense\CreateTravelExpenseCommand;
use App\Expense\Application\Command\DeleteExpense\DeleteExpenseCommand;
use App\Expense\Application\Command\UpdateExpense\UpdateExpenseCommand;
use App\Expense\Application\Export\SummaryExporterRegistry;
use App\Expense\Application\Query\GetExpensesByPeriod\GetExpensesByPeriodQuery;
use App\Expense\Application\Query\GetExpensesSummary\GetExpensesSummaryQuery;
use App\Expense\Domain\Exception\ExpenseNotFoundException;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/expenses', name: 'expense_')]
final class ExpenseController extends AbstractController
{
    private const FALLBACK_HOME_MEAL_VALUE = 5.35;
    private const FALLBACK_DAILY_ALLOWANCE = 2.70;

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly FiscalConfigRepositoryInterface $fiscalConfigRepository,
        private readonly SummaryExporterRegistry $exporters,
    ) {
    }

    #[Route('/fiscal-config/{year}', name: 'fiscalconfig', requirements: ['year' => '\d{4}'], methods: [Request::METHOD_GET])]
    public function fiscalConfig(int $year): JsonResponse
    {
        $config = $this->fiscalConfigRepository->findByYear($year);

        return $this->json([
            'year' => $year,
            'remoteWorkDailyAllowance' => $config?->remoteWorkDailyAllowance() ?? self::FALLBACK_DAILY_ALLOWANCE,
            'homeMealValue' => $config?->homeMealValue() ?? self::FALLBACK_HOME_MEAL_VALUE,
        ]);
    }

    #[Route('', name: 'list', methods: [Request::METHOD_GET])]
    public function list(Request $request): JsonResponse
    {
        $from = $request->query->get('from', date('Y-m-01'));
        $to = $request->query->get('to', date('Y-m-t'));
        $personId = $request->query->get('personId') ?: null;

        return $this->json($this->queryBus->ask(new GetExpensesByPeriodQuery($from, $to, $personId)));
    }

    #[Route('/summary/pdf', name: 'summaryPdf', methods: [Request::METHOD_GET])]
    public function summaryPdf(Request $request): Response
    {
        $personId = $request->query->get('personId', '');
        $year = (int) $request->query->get('year', (int) date('Y'));

        if (empty($personId)) {
            return new Response('personId is required', Response::HTTP_BAD_REQUEST);
        }

        $data = $this->queryBus->ask(new GetExpensesSummaryQuery($personId, $year));
        $result = $this->exporters->get('pdf')->export($data, $year);

        return new Response($result->content, Response::HTTP_OK, [
            'Content-Type' => $result->mimeType,
            'Content-Disposition' => 'attachment; filename="'.$result->filename.'"',
        ]);
    }

    #[Route('/summary/csv', name: 'summaryCsv', methods: [Request::METHOD_GET])]
    public function summaryCsv(Request $request): Response
    {
        $personId = $request->query->get('personId', '');
        $year = (int) $request->query->get('year', (int) date('Y'));

        if (empty($personId)) {
            return new Response('personId is required', Response::HTTP_BAD_REQUEST);
        }

        $data = $this->queryBus->ask(new GetExpensesSummaryQuery($personId, $year));
        $result = $this->exporters->get('csv')->export($data, $year);

        return new Response($result->content, Response::HTTP_OK, [
            'Content-Type' => $result->mimeType,
            'Content-Disposition' => 'attachment; filename="'.$result->filename.'"',
        ]);
    }

    #[Route('/summary', name: 'summary', methods: [Request::METHOD_GET])]
    public function summary(Request $request): JsonResponse
    {
        $personId = $request->query->get('personId', '');
        $year = (int) $request->query->get('year', (int) date('Y'));

        if (empty($personId)) {
            return $this->json(['error' => 'personId is required'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->queryBus->ask(new GetExpensesSummaryQuery($personId, $year)));
    }

    #[Route('', name: 'create', methods: [Request::METHOD_POST])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $type = $data['type'] ?? '';

        try {
            $command = match ($type) {
                'travel' => new CreateTravelExpenseCommand(
                    personId: $data['personId'],
                    date: $data['date'],
                    distanceKm: (float) $data['distanceKm'],
                    vehiclePower: isset($data['vehiclePower']) ? (int) $data['vehiclePower'] : null,
                    departure: $data['departure'] ?? null,
                    arrival: $data['arrival'] ?? null,
                    description: $data['description'] ?? null,
                    roundTrip: (bool) ($data['roundTrip'] ?? false),
                    vehicleType: $data['vehicleType'] ?? 'car',
                    isElectric: (bool) ($data['isElectric'] ?? false),
                ),
                'remote_work' => new CreateRemoteWorkExpenseCommand(
                    personId: $data['personId'],
                    date: $data['date'],
                    description: $data['description'] ?? null,
                ),
                'toll' => new CreateTollExpenseCommand(
                    personId: $data['personId'],
                    date: $data['date'],
                    amount: (float) $data['amount'],
                    departure: $data['departure'] ?? null,
                    arrival: $data['arrival'] ?? null,
                    description: $data['description'] ?? null,
                ),
                'meal' => new CreateMealExpenseCommand(
                    personId: $data['personId'],
                    date: $data['date'],
                    mealAmount: (float) ($data['mealAmount'] ?? 0),
                    description: $data['description'] ?? null,
                    employerTicketContribution: (float) ($data['employerTicketContribution'] ?? 0),
                    withoutReceipt: (bool) ($data['withoutReceipt'] ?? false),
                ),
                'parking' => new CreateParkingExpenseCommand(
                    personId: $data['personId'],
                    date: $data['date'],
                    amount: (float) ($data['amount'] ?? 0),
                    location: $data['location'] ?? null,
                    description: $data['description'] ?? null,
                ),
                default => throw new \InvalidArgumentException(sprintf('Unknown expense type "%s"', $type)),
            };
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $id = $this->commandBus->dispatch($command);

        return $this->json(['id' => $id], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => Requirement::UUID_V4], methods: [Request::METHOD_PATCH])]
    public function update(string $id, Request $request): JsonResponse
    {
        $fields = json_decode($request->getContent(), true) ?? [];

        try {
            $this->commandBus->dispatch(new UpdateExpenseCommand($id, $fields));

            return $this->json(['success' => true]);
        } catch (HandlerFailedException $e) {
            if ($e->getPrevious() instanceof ExpenseNotFoundException) {
                return $this->json(['error' => $e->getPrevious()->getMessage()], Response::HTTP_NOT_FOUND);
            }
            throw $e;
        }
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => Requirement::UUID_V4], methods: [Request::METHOD_DELETE])]
    public function delete(string $id): JsonResponse
    {
        try {
            $this->commandBus->dispatch(new DeleteExpenseCommand($id));

            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (HandlerFailedException $e) {
            if ($e->getPrevious() instanceof ExpenseNotFoundException) {
                return $this->json(['error' => $e->getPrevious()->getMessage()], Response::HTTP_NOT_FOUND);
            }
            throw $e;
        }
    }
}
```

- [ ] **Step 3: Run full test suite**

```bash
make test
```

Expected: all tests pass (the old interfaces still exist so no broken references yet)

- [ ] **Step 4: Commit**

```bash
git add config/services.yaml src/Expense/Infrastructure/Http/ExpenseController.php
git commit -m "refactor(export): wire SummaryExporterRegistry into controller, remove per-format injections"
```

---

### Task 7: Delete old files and final cleanup

**Files:**
- Delete: `src/Expense/Application/Export/SummaryPdfExporterInterface.php`
- Delete: `src/Expense/Application/Export/SummaryCsvExporterInterface.php`
- Delete: `src/Expense/Infrastructure/Export/StreamedSummaryCsvExporter.php`

- [ ] **Step 1: Delete the three obsolete files**

```bash
rm src/Expense/Application/Export/SummaryPdfExporterInterface.php
rm src/Expense/Application/Export/SummaryCsvExporterInterface.php
rm src/Expense/Infrastructure/Export/StreamedSummaryCsvExporter.php
```

- [ ] **Step 2: Run full test suite**

```bash
make test
```

Expected: all tests pass

- [ ] **Step 3: Run static analysis**

```bash
make analyse
```

Expected: no errors from phpstan or psalm

- [ ] **Step 4: Commit**

```bash
git add -u
git commit -m "refactor(export): delete obsolete SummaryPdfExporterInterface, SummaryCsvExporterInterface, StreamedSummaryCsvExporter"
```