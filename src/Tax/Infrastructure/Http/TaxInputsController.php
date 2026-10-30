<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\Http;

use App\Auth\Domain\Entity\User;
use App\SharedKernel\Infrastructure\Security\OwnershipGuardInterface;
use App\Tax\Application\Exception\InvalidTaxInputException;
use App\Tax\Application\Service\TaxInputsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Comparison inputs entered by the person herself (net taxable salaries, employer reimbursements).
 * These are the fields targeted by the "complete this field" links of an incomplete comparison.
 * The person is always checked against the current user before any read or write.
 */
#[Route('/api/persons/{personId}/tax-inputs', name: 'tax_inputs_', requirements: ['personId' => Requirement::UUID_V4])]
final class TaxInputsController extends AbstractController
{
    public function __construct(
        private readonly TaxInputsService $service,
        private readonly OwnershipGuardInterface $ownershipGuard,
    ) {
    }

    #[Route('/{year}', name: 'list', requirements: ['year' => '\d{4}'], methods: [Request::METHOD_GET])]
    public function list(string $personId, int $year): JsonResponse
    {
        $this->assertOwnedPerson($personId);

        return $this->json($this->service->list($personId, $year));
    }

    #[Route('/{year}/salaries', name: 'salary_add', requirements: ['year' => '\d{4}'], methods: [Request::METHOD_POST])]
    public function addSalary(string $personId, int $year, Request $request): JsonResponse
    {
        $this->assertOwnedPerson($personId);
        $data = self::body($request);

        return $this->json($this->service->addSalary(
            $personId,
            $year,
            self::string($data, 'employer'),
            self::string($data, 'periodStart'),
            self::string($data, 'periodEnd'),
            self::int($data, 'amountCents'),
        ), Response::HTTP_CREATED);
    }

    #[Route('/salaries/{salaryId}', name: 'salary_remove', requirements: ['salaryId' => Requirement::UUID_V4], methods: [Request::METHOD_DELETE])]
    public function removeSalary(string $personId, string $salaryId): JsonResponse
    {
        $this->assertOwnedPerson($personId);
        $this->service->removeSalary($personId, $salaryId);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{year}/reimbursements', name: 'reimbursement_add', requirements: ['year' => '\d{4}'], methods: [Request::METHOD_POST])]
    public function addReimbursement(string $personId, int $year, Request $request): JsonResponse
    {
        $this->assertOwnedPerson($personId);
        $data = self::body($request);
        $date = $data['date'] ?? null;
        if (null !== $date && !\is_string($date)) {
            throw new InvalidTaxInputException('Invalid "date".');
        }

        return $this->json($this->service->addReimbursement(
            $personId,
            $year,
            self::string($data, 'employer'),
            self::int($data, 'amountCents'),
            self::string($data, 'category'),
            self::nullableBool($data, 'toReintegrate'),
            $date,
        ), Response::HTTP_CREATED);
    }

    /** Body: {"toReintegrate": true|false|null} and/or {"unlinkExpense": true}. */
    #[Route('/reimbursements/{reimbursementId}', name: 'reimbursement_update', requirements: ['reimbursementId' => '[^/]+'], methods: [Request::METHOD_PATCH])]
    public function updateReimbursement(string $personId, string $reimbursementId, Request $request): JsonResponse
    {
        $this->assertOwnedPerson($personId);
        $data = self::body($request);
        if (!\array_key_exists('toReintegrate', $data) && true !== ($data['unlinkExpense'] ?? null)) {
            throw new InvalidTaxInputException('Nothing to update.');
        }

        $view = null;
        if (\array_key_exists('toReintegrate', $data)) {
            $view = $this->service->indicateReintegration($personId, $reimbursementId, self::nullableBool($data, 'toReintegrate'));
        }
        if (true === ($data['unlinkExpense'] ?? null)) {
            $view = $this->service->unlinkExpense($personId, $reimbursementId);
        }

        return $this->json($view);
    }

    #[Route('/reimbursements/{reimbursementId}', name: 'reimbursement_remove', requirements: ['reimbursementId' => '[^/]+'], methods: [Request::METHOD_DELETE])]
    public function removeReimbursement(string $personId, string $reimbursementId): JsonResponse
    {
        $this->assertOwnedPerson($personId);
        $this->service->removeReimbursement($personId, $reimbursementId);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function assertOwnedPerson(string $personId): void
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $this->ownershipGuard->assertPersonBelongsToUser($personId, $user->id()->value());
    }

    /** @return array<array-key, mixed> */
    private static function body(Request $request): array
    {
        try {
            $data = json_decode($request->getContent(), true, 8, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidTaxInputException('Invalid JSON body.');
        }
        if (!\is_array($data)) {
            throw new InvalidTaxInputException('Invalid JSON body.');
        }

        return $data;
    }

    /** @param array<array-key, mixed> $data */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!\is_string($value)) {
            throw new InvalidTaxInputException(sprintf('"%s" must be a string.', $key));
        }

        return $value;
    }

    /** @param array<array-key, mixed> $data */
    private static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (!\is_int($value)) {
            throw new InvalidTaxInputException(sprintf('"%s" must be an integer number of cents.', $key));
        }

        return $value;
    }

    /** @param array<array-key, mixed> $data */
    private static function nullableBool(array $data, string $key): ?bool
    {
        $value = $data[$key] ?? null;
        if (null !== $value && !\is_bool($value)) {
            throw new InvalidTaxInputException(sprintf('"%s" must be true, false or null.', $key));
        }

        return $value;
    }
}
