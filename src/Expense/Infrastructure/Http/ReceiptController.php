<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Http;

use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\ValueObject\ExpenseId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/expenses/{id}/receipt', name: 'receipt_', requirements: ['id' => Requirement::UUID_V4])]
final class ReceiptController extends AbstractController
{
    private string $receiptsDir;

    public function __construct(
        private readonly ExpenseRepositoryInterface $repository,
        string $shareDir,
    ) {
        $this->receiptsDir = rtrim($shareDir, '/').'/receipts';
    }

    #[Route('', name: 'upload', methods: [Request::METHOD_POST])]
    public function upload(string $id, Request $request): JsonResponse
    {
        $expense = $this->repository->findById(ExpenseId::fromString($id));
        if (null === $expense) {
            return $this->json(['error' => 'Expense not found'], Response::HTTP_NOT_FOUND);
        }

        $file = $request->files->get('receipt');
        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
        }
        $mimeType = $file->getMimeType();
        $allowedMimeTypes = [
            'application/pdf' => 'pdf',
            'application/x-pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!is_string($mimeType) || !array_key_exists($mimeType, $allowedMimeTypes)) {
            return $this->json(['error' => 'Only PDF, JPG, PNG and WEBP files are accepted'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($file->getSize() > 10 * 1024 * 1024) {
            return $this->json(['error' => 'File too large (max 10 MB)'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!is_dir($this->receiptsDir)) {
            if (!mkdir($concurrentDirectory = $this->receiptsDir, 0755, true) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }

        foreach (glob($this->receiptsDir.'/'.$id.'.*') ?: [] as $previousFile) {
            if (is_file($previousFile)) {
                unlink($previousFile);
            }
        }

        $extension = $allowedMimeTypes[$mimeType];
        $file->move($this->receiptsDir, $id.'.'.$extension);
        $expense->setReceipt($file->getClientOriginalName(), $mimeType);
        $this->repository->save($expense);

        return $this->json([
            'receiptFilename' => $expense->receiptFilename(),
            'receiptMimeType' => $expense->receiptMimeType(),
        ]);
    }

    #[Route('', name: 'download', methods: [Request::METHOD_GET])]
    public function download(string $id): Response
    {
        $expense = $this->repository->findById(ExpenseId::fromString($id));
        if (null === $expense) {
            return $this->json(['error' => 'Expense not found'], Response::HTTP_NOT_FOUND);
        }

        if (null === $expense->receiptFilename()) {
            return $this->json(['error' => 'No receipt found'], Response::HTTP_NOT_FOUND);
        }

        $matches = glob($this->receiptsDir.'/'.$id.'.*') ?: [];
        $path = $matches[0] ?? null;
        if (!is_string($path) || !file_exists($path)) {
            return $this->json(['error' => 'File not found on disk'], Response::HTTP_NOT_FOUND);
        }

        return new BinaryFileResponse($path, Response::HTTP_OK, [
            'Content-Type' => $expense->receiptMimeType() ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($expense->receiptFilename()).'"',
        ]);
    }

    #[Route('', name: 'delete', methods: [Request::METHOD_DELETE])]
    public function delete(string $id): JsonResponse
    {
        $expense = $this->repository->findById(ExpenseId::fromString($id));
        if (null === $expense) {
            return $this->json(['error' => 'Expense not found'], Response::HTTP_NOT_FOUND);
        }

        foreach (glob($this->receiptsDir.'/'.$id.'.*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $expense->setReceipt(null, null);
        $this->repository->save($expense);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
