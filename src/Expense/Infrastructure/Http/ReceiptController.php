<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Http;

use App\Expense\Domain\Entity\ParkingExpense;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\ValueObject\ExpenseId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/expenses/{id}/receipt')]
final class ReceiptController extends AbstractController
{
    private string $receiptsDir;

    public function __construct(
        private readonly ExpenseRepositoryInterface $repository,
        string $shareDir,
    ) {
        $this->receiptsDir = rtrim($shareDir, '/') . '/receipts';
    }

    #[Route('', methods: [Request::METHOD_POST])]
    public function upload(string $id, Request $request): JsonResponse
    {
        $expense = $this->repository->findById(ExpenseId::fromString($id));

        if (!$expense instanceof ParkingExpense) {
            return $this->json(['error' => 'Receipt upload only available for parking expenses'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $file = $request->files->get('receipt');
        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
        }
        if (!in_array($file->getMimeType(), ['application/pdf', 'application/x-pdf'], true)) {
            return $this->json(['error' => 'Only PDF files are accepted'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($file->getSize() > 10 * 1024 * 1024) {
            return $this->json(['error' => 'File too large (max 10 MB)'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!is_dir($this->receiptsDir)) {
            mkdir($this->receiptsDir, 0755, true);
        }

        $file->move($this->receiptsDir, $id . '.pdf');
        $expense->setReceiptFilename($file->getClientOriginalName());
        $this->repository->save($expense);

        return $this->json(['receiptFilename' => $expense->receiptFilename()]);
    }

    #[Route('', methods: [Request::METHOD_GET])]
    public function download(string $id): Response
    {
        $expense = $this->repository->findById(ExpenseId::fromString($id));

        if (!$expense instanceof ParkingExpense || $expense->receiptFilename() === null) {
            return $this->json(['error' => 'No receipt found'], Response::HTTP_NOT_FOUND);
        }

        $path = $this->receiptsDir . '/' . $id . '.pdf';
        if (!file_exists($path)) {
            return $this->json(['error' => 'File not found on disk'], Response::HTTP_NOT_FOUND);
        }

        return new BinaryFileResponse($path, Response::HTTP_OK, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . addslashes($expense->receiptFilename()) . '"',
        ]);
    }

    #[Route('', methods: [Request::METHOD_DELETE])]
    public function delete(string $id): JsonResponse
    {
        $expense = $this->repository->findById(ExpenseId::fromString($id));

        if (!$expense instanceof ParkingExpense) {
            return $this->json(['error' => 'Not a parking expense'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $path = $this->receiptsDir . '/' . $id . '.pdf';
        if (file_exists($path)) {
            unlink($path);
        }

        $expense->setReceiptFilename(null);
        $this->repository->save($expense);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
