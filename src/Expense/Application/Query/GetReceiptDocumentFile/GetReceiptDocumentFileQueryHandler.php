<?php

declare(strict_types=1);

namespace App\Expense\Application\Query\GetReceiptDocumentFile;

use App\Expense\Application\Receipt\ReceiptStorageInterface;
use App\Expense\Domain\Repository\ReceiptDocumentRepositoryInterface;
use App\SharedKernel\Application\Bus\QueryHandlerInterface;

final readonly class GetReceiptDocumentFileQueryHandler implements QueryHandlerInterface
{
    public function __construct(private ReceiptDocumentRepositoryInterface $repository, private ReceiptStorageInterface $storage)
    {
    }

    public function __invoke(GetReceiptDocumentFileQuery $query): string
    {
        $document = $this->repository->findDocument($query->documentId);
        if (null === $document || $document->personId() !== $query->personId) {
            throw new \DomainException('Facture introuvable.');
        }

        return $this->storage->find($document->id()) ?? throw new \DomainException('Fichier introuvable.');
    }
}
