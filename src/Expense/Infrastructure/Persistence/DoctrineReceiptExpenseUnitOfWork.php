<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Persistence;

use App\Expense\Application\Persistence\ReceiptExpenseUnitOfWorkInterface;
use App\Expense\Domain\Entity\Expense;
use App\Expense\Domain\Entity\ReceiptMatch;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineReceiptExpenseUnitOfWork implements ReceiptExpenseUnitOfWorkInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function persist(Expense $expense, ReceiptMatch $match): void
    {
        $this->entityManager->persist($expense);
        $this->entityManager->persist($match);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
