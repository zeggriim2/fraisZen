<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Persistence;

use App\Expense\Domain\Entity\Expense;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\ValueObject\ExpenseId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;

final readonly class DoctrineExpenseRepository implements ExpenseRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(Expense $expense): void
    {
        $this->em->persist($expense);
        $this->em->flush();
    }

    public function delete(Expense $expense): void
    {
        $this->em->remove($expense);
        $this->em->flush();
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function findById(ExpenseId $id): ?Expense
    {
        return $this->em->find(Expense::class, $id->value());
    }

    public function findByPersonAndPeriod(string $personId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->em->createQueryBuilder()
            ->select('e')->from(Expense::class, 'e')
            ->where('e.personId = :personId')
            ->andWhere('e.date >= :from')
            ->andWhere('e.date <= :to')
            ->orderBy('e.date', 'ASC')
            ->setParameter('personId', $personId)
            ->setParameter('from', $from, Types::DATETIME_IMMUTABLE)
            ->setParameter('to', $to, Types::DATETIME_IMMUTABLE)
            ->getQuery()->getResult();
    }

    public function findByPersonAndYear(string $personId, int $year): array
    {
        return $this->findByPersonAndPeriod(
            $personId,
            new \DateTimeImmutable("$year-01-01"),
            new \DateTimeImmutable("$year-12-31"),
        );
    }

    public function findReceiptPage(
        string $personId,
        int $year,
        int $offset,
        int $limit,
        string $status = 'all',
        string $search = '',
    ): array {
        [$where, $parameters] = $this->receiptSqlFilter($personId, $year, $status, $search);
        $ids = $this->em->getConnection()->executeQuery(
            'SELECT id FROM expense '.$where.' ORDER BY date DESC, id ASC LIMIT :limit OFFSET :offset',
            [...$parameters, 'limit' => $limit, 'offset' => $offset],
            ['limit' => Types::INTEGER, 'offset' => Types::INTEGER],
        )->fetchFirstColumn();
        if ([] === $ids) {
            return [];
        }

        return $this->em->createQueryBuilder()
            ->select('e')->from(Expense::class, 'e')
            ->where('e.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('e.date', 'DESC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function receiptVaultCounts(string $personId, int $year, string $status = 'all', string $search = ''): array
    {
        [$baseWhere, $baseParameters] = $this->receiptSqlFilter($personId, $year);
        $counts = $this->em->getConnection()->executeQuery(
            'SELECT COUNT(id) AS total, SUM(receipt_filename IS NOT NULL) AS with_receipt FROM expense '.$baseWhere,
            $baseParameters,
        )->fetchAssociative();
        [$filteredWhere, $filteredParameters] = $this->receiptSqlFilter($personId, $year, $status, $search);
        $filtered = $this->em->getConnection()->executeQuery(
            'SELECT COUNT(id) FROM expense '.$filteredWhere,
            $filteredParameters,
        )->fetchOne();
        if (false === $counts) {
            $counts = ['total' => 0, 'with_receipt' => 0];
        }
        $total = (int) $counts['total'];
        $withReceipt = (int) ($counts['with_receipt'] ?? 0);

        return [
            'total' => $total,
            'withReceipt' => $withReceipt,
            'missing' => $total - $withReceipt,
            'filtered' => (int) $filtered,
        ];
    }

    /** @return array{string, array<string, scalar>} */
    private function receiptSqlFilter(string $personId, int $year, string $status = 'all', string $search = ''): array
    {
        $where = 'WHERE person_id = :personId AND date >= :from AND date <= :to AND (type IN (\'toll\', \'parking\') OR (type = \'meal\' AND without_receipt = 1))';
        $parameters = ['personId' => $personId, 'from' => "$year-01-01", 'to' => "$year-12-31 23:59:59"];

        if ('missing' === $status) {
            $where .= ' AND receipt_filename IS NULL';
        } elseif ('archived' === $status) {
            $where .= ' AND receipt_filename IS NOT NULL';
        }

        $search = trim($search);
        if ('' !== $search) {
            $where .= ' AND (LOWER(description) LIKE :search OR LOWER(receipt_filename) LIKE :search)';
            $parameters['search'] = '%'.mb_strtolower($search).'%';
        }

        return [$where, $parameters];
    }

    public function findByPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->em->createQueryBuilder()
            ->select('e')->from(Expense::class, 'e')
            ->where('e.date >= :from')
            ->andWhere('e.date <= :to')
            ->orderBy('e.date', 'ASC')
            ->setParameter('from', $from, Types::DATETIME_IMMUTABLE)
            ->setParameter('to', $to, Types::DATETIME_IMMUTABLE)
            ->getQuery()->getResult();
    }

    public function countByPersonId(string $personId): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(e.id)')->from(Expense::class, 'e')
            ->where('e.personId = :personId')
            ->setParameter('personId', $personId)
            ->getQuery()->getSingleScalarResult();
    }
}
