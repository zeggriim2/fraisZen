# Export Pattern — Expense Summary

**Date:** 2026-05-07
**Scope:** Refactor PDF and CSV export to remove HTTP concerns from the Application layer and introduce an extensible registry pattern.

---

## Context

The current export code leaks HTTP into the Application layer: `SummaryPdfExporterInterface` returns `Symfony\Component\HttpFoundation\Response` and `SummaryCsvExporterInterface` returns `StreamedResponse`. The controller delegates the full HTTP response construction to the exporter, which prevents reuse outside HTTP contexts and makes adding new formats require a new interface, a new constructor injection, and a new controller method.

---

## Design

### 1. Value Object — `ExportResult`

**Location:** `src/Expense/Application/Export/ExportResult.php`

A simple readonly DTO carrying the three pieces the controller needs to build a `Response`:

```php
final readonly class ExportResult {
    public function __construct(
        public string $content,   // raw bytes (PDF, CSV, …)
        public string $mimeType,  // e.g. 'application/pdf'
        public string $filename,  // e.g. 'frais-reels-2025.pdf'
    ) {}
}
```

No HTTP dependency. Trivially testable.

---

### 2. Unified Interface — `SummaryExporterInterface`

**Location:** `src/Expense/Application/Export/SummaryExporterInterface.php`

Replaces both `SummaryPdfExporterInterface` and `SummaryCsvExporterInterface`, which are deleted.

```php
interface SummaryExporterInterface {
    public function format(): string;

    /** @param array<string, mixed> $data */
    public function export(array $data, int $year): ExportResult;
}
```

`format()` returns a lowercase string identifier (`'pdf'`, `'csv'`). Adding a new format requires only a new class implementing this interface — no other changes.

---

### 3. Registry — `SummaryExporterRegistry`

**Location:** `src/Expense/Application/Export/SummaryExporterRegistry.php`

```php
final class SummaryExporterRegistry {
    /** @param iterable<SummaryExporterInterface> $exporters */
    public function __construct(private iterable $exporters) {}

    public function get(string $format): SummaryExporterInterface {
        foreach ($this->exporters as $exporter) {
            if ($exporter->format() === $format) {
                return $exporter;
            }
        }
        throw new \InvalidArgumentException("No exporter for format '$format'");
    }
}
```

**Symfony DI wiring** (`services.yaml`):

```yaml
App\Expense\Application\Export\SummaryExporterRegistry:
    arguments:
        $exporters: !tagged_iterator app.summary_exporter

_instanceof:
    App\Expense\Application\Export\SummaryExporterInterface:
        tags: ['app.summary_exporter']
```

`autoconfigure` handles tagging automatically; no manual tag needed per implementation.

---

### 4. Infrastructure Implementations

#### `DompdfSummaryPdfExporter`

- Adds `format(): string { return 'pdf'; }`
- `export()` returns `new ExportResult($dompdf->output(), 'application/pdf', "frais-reels-$year.pdf")`
- Twig rendering stays unchanged (added in previous refactor)

#### `CsvSummaryExporter` (renamed from `StreamedSummaryCsvExporter`)

- Drops `StreamedResponse`; writes CSV into a `php://memory` buffer, rewinds, reads with `stream_get_contents()`
- Returns `new ExportResult($csv, 'text/csv; charset=UTF-8', "frais-reels-$year.csv")`
- `format(): string { return 'csv'; }`
- Memory footprint is negligible for this app's dataset size

---

### 5. Controller Changes

`ExpenseController` drops both `SummaryPdfExporterInterface` and `SummaryCsvExporterInterface` injections, replaces them with a single `SummaryExporterRegistry`.

Routes stay separate. Both actions follow the same pattern:

```php
$result = $this->exporters->get('pdf')->export($data, $year);
return new Response($result->content, Response::HTTP_OK, [
    'Content-Type'        => $result->mimeType,
    'Content-Disposition' => 'attachment; filename="'.$result->filename.'"',
]);
```

`summaryCsv()` return type changes from `StreamedResponse` to `Response`.

---

### 6. Tests

| Test file | What it covers |
|---|---|
| `tests/Unit/Expense/Application/Export/ExportResultTest.php` | VO exposes the 3 fields correctly |
| `tests/Unit/Expense/Application/Export/SummaryExporterRegistryTest.php` | `get()` returns right exporter; throws on unknown format |
| `tests/Unit/Expense/Infrastructure/Export/SummaryPdfTemplateTest.php` | Twig template renders (already exists, unchanged) |
| `tests/Unit/Expense/Infrastructure/Export/CsvSummaryExporterTest.php` | `export()` returns ExportResult with correct mimeType, filename, non-empty content |

---

## Files Affected

| Action | File |
|---|---|
| Create | `src/Expense/Application/Export/ExportResult.php` |
| Create | `src/Expense/Application/Export/SummaryExporterInterface.php` |
| Create | `src/Expense/Application/Export/SummaryExporterRegistry.php` |
| Delete | `src/Expense/Application/Export/SummaryPdfExporterInterface.php` |
| Delete | `src/Expense/Application/Export/SummaryCsvExporterInterface.php` |
| Update | `src/Expense/Infrastructure/Export/DompdfSummaryPdfExporter.php` |
| Rename+update | `src/Expense/Infrastructure/Export/StreamedSummaryCsvExporter.php` → `CsvSummaryExporter.php` |
| Update | `src/Expense/Infrastructure/Http/ExpenseController.php` |
| Update | `config/services.yaml` |
| Create | `tests/Unit/Expense/Application/Export/ExportResultTest.php` |
| Create | `tests/Unit/Expense/Application/Export/SummaryExporterRegistryTest.php` |
| Create | `tests/Unit/Expense/Infrastructure/Export/CsvSummaryExporterTest.php` |