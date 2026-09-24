<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\DeleteReceiptDocument;

use App\Expense\Application\Receipt\ReceiptStorageInterface;
use App\Expense\Domain\Repository\ReceiptDocumentRepositoryInterface;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;

final readonly class DeleteReceiptDocumentCommandHandler implements CommandHandlerInterface
{
    public function __construct(private ReceiptDocumentRepositoryInterface $repository, private ReceiptStorageInterface $storage)
    {
    }

    public function __invoke(DeleteReceiptDocumentCommand $command): void
    {
        $document = $this->repository->findDocument($command->documentId);
        if (null === $document || $document->personId() !== $command->personId) {
            throw new \DomainException('Facture introuvable.');
        }$this->storage->delete($document->id());
        $this->repository->deleteDocument($document);
        $this->repository->flush();
    }
}
