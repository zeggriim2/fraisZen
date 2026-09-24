<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\UploadReceipt;

use App\Expense\Application\Ocr\ReceiptOcrExtractorInterface;
use App\Expense\Application\Receipt\ReceiptStorageInterface;
use App\Expense\Domain\Exception\DuplicateReceiptException;
use App\Expense\Domain\Exception\ExpenseNotFoundException;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\Service\ReceiptEligibility;
use App\Expense\Domain\ValueObject\ExpenseId;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;

final readonly class UploadReceiptCommandHandler implements CommandHandlerInterface
{
    private const MAX_SIZE = 10 * 1024 * 1024;
    private const MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'application/x-pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private ExpenseRepositoryInterface $repository,
        private ReceiptStorageInterface $storage,
        private ReceiptOcrExtractorInterface $ocrExtractor,
    ) {
    }

    /** @return array{receiptFilename: ?string, receiptMimeType: ?string, ocr: array<string, mixed>|null} */
    public function __invoke(UploadReceiptCommand $command): array
    {
        $expense = $this->repository->findById(ExpenseId::fromString($command->expenseId));
        if (null === $expense) {
            throw new ExpenseNotFoundException($command->expenseId);
        }
        if (!ReceiptEligibility::supports($expense)) {
            throw new \DomainException('Cette catégorie de dépense ne nécessite pas de justificatif.');
        }
        if (null === $command->mimeType || !isset(self::MIME_EXTENSIONS[$command->mimeType])) {
            throw new \DomainException('Only PDF, JPG, PNG and WEBP files are accepted');
        }
        if ($command->size > self::MAX_SIZE) {
            throw new \DomainException('File too large (max 10 MB)');
        }
        $sha256 = hash_file('sha256', $command->sourcePath);
        if (!is_string($sha256)) {
            throw new \RuntimeException('Unable to fingerprint receipt');
        }
        foreach ($this->repository->findByPersonAndYear($expense->personId(), (int) $expense->date()->format('Y')) as $candidate) {
            if ($candidate->id()->value() !== $command->expenseId && $candidate->receiptSha256() === $sha256) {
                throw new DuplicateReceiptException($candidate->id()->value());
            }
        }

        $path = $this->storage->store($command->expenseId, $command->sourcePath, self::MIME_EXTENSIONS[$command->mimeType]);
        try {
            $ocrData = $this->ocrExtractor->extract($path, $command->mimeType);
            $expense->setReceipt($command->originalName, $command->mimeType);
            $expense->setReceiptMetadata($sha256, $ocrData, new \DateTimeImmutable());
            $this->repository->save($expense);
        } catch (\Throwable $exception) {
            $this->storage->delete($command->expenseId);
            throw $exception;
        }

        return ['receiptFilename' => $expense->receiptFilename(), 'receiptMimeType' => $expense->receiptMimeType(), 'ocr' => $expense->receiptOcrData()];
    }
}
