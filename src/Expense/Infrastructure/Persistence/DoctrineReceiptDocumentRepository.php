<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Persistence;

use App\Expense\Domain\Entity\ReceiptDocument;
use App\Expense\Domain\Entity\ReceiptLine;
use App\Expense\Domain\Entity\ReceiptMatch;
use App\Expense\Domain\Repository\ReceiptDocumentRepositoryInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineReceiptDocumentRepository implements ReceiptDocumentRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function saveDocument(ReceiptDocument $document): void
    {
        $this->em->persist($document);
    }

    public function findDocument(string $id): ?ReceiptDocument
    {
        return $this->em->find(ReceiptDocument::class, $id);
    }

    public function findByFingerprint(string $personId, string $sha256): ?ReceiptDocument
    {
        return $this->em->getRepository(ReceiptDocument::class)
            ->findOneBy(['personId' => $personId, 'sha256' => $sha256])
        ;
    }

    public function deleteDocument(ReceiptDocument $document): void
    {
        $this->em->remove($document);
    }

    public function flush(): void
    {
        $this->em->flush();
    }

    public function findPage(string $personId, int $year, int $offset, int $limit): array
    {
        return $this->em->createQueryBuilder()
            ->select('d')
            ->from(ReceiptDocument::class, 'd')
            ->where('d.personId=:person')
            ->andWhere('d.createdAt>=:from')
            ->andWhere('d.createdAt<:to')
            ->orderBy('d.createdAt', 'DESC')
            ->setParameter('person', $personId)
            ->setParameter('from', new \DateTimeImmutable("$year-01-01"), Types::DATETIME_IMMUTABLE)
            ->setParameter('to', new \DateTimeImmutable(($year + 1).'-01-01'), Types::DATETIME_IMMUTABLE)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    public function count(string $personId, int $year): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(ReceiptDocument::class, 'd')
            ->where('d.personId=:person')
            ->andWhere('d.createdAt>=:from')
            ->andWhere('d.createdAt<:to')
            ->setParameter('person', $personId)
            ->setParameter('from', new \DateTimeImmutable("$year-01-01"), Types::DATETIME_IMMUTABLE)
            ->setParameter('to', new \DateTimeImmutable(($year + 1).'-01-01'), Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function saveLine(ReceiptLine $line): void
    {
        $this->em->persist($line);
    }

    public function findLines(string $documentId): array
    {
        return $this->em->getRepository(ReceiptLine::class)
            ->findBy(['documentId' => $documentId], ['lineNumber' => 'ASC'])
        ;
    }

    public function findLine(string $id): ?ReceiptLine
    {
        return $this->em->find(ReceiptLine::class, $id);
    }

    public function saveMatch(ReceiptMatch $match): void
    {
        $this->em->persist($match);
    }

    public function findMatch(string $id): ?ReceiptMatch
    {
        return $this->em->find(ReceiptMatch::class, $id);
    }

    /**
     * @param list<string> $lineIds
     *
     * @return ReceiptMatch[]
     */
    public function findMatchesForLines(array $lineIds): array
    {
        return [] === $lineIds
            ? []
            : $this->em->createQueryBuilder()
                ->select('m')
                ->from(ReceiptMatch::class, 'm')
                ->where('m.lineId IN (:ids)')
                ->setParameter('ids', $lineIds)
                ->getQuery()
                ->getResult()
        ;
    }
}
