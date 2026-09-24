<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Http;

use App\Auth\Domain\Entity\User;
use App\Expense\Application\Command\DeleteReceipt\DeleteReceiptCommand;
use App\Expense\Application\Command\UploadReceipt\UploadReceiptCommand;
use App\Expense\Application\Query\GetReceipt\GetReceiptQuery;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use App\SharedKernel\Infrastructure\Security\OwnershipGuardInterface;
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
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly OwnershipGuardInterface $ownershipGuard,
    ) {
    }

    #[Route('', name: 'upload', methods: [Request::METHOD_POST])]
    public function upload(string $id, Request $request): JsonResponse
    {
        $this->ownershipGuard->assertExpenseBelongsToUser($id, $this->currentUserId());
        $file = $request->files->get('receipt');
        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->commandBus->dispatch(new UploadReceiptCommand($id, $file->getPathname(), $file->getClientOriginalName(), $file->getMimeType(), $file->getSize())));
    }

    #[Route('', name: 'download', methods: [Request::METHOD_GET])]
    public function download(string $id): BinaryFileResponse
    {
        $this->ownershipGuard->assertExpenseBelongsToUser($id, $this->currentUserId());

        /** @var array{ path: string, filename: string, mimeType: string} $file */
        $file = $this->queryBus->ask(new GetReceiptQuery($id));

        return new BinaryFileResponse($file['path'], Response::HTTP_OK, ['Content-Type' => $file['mimeType'], 'Content-Disposition' => 'inline; filename="'.addslashes($file['filename']).'"']);
    }

    #[Route('', name: 'delete', methods: [Request::METHOD_DELETE])]
    public function delete(string $id): JsonResponse
    {
        $this->ownershipGuard->assertExpenseBelongsToUser($id, $this->currentUserId());
        $this->commandBus->dispatch(new DeleteReceiptCommand($id));

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function currentUserId(): string
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user->id()->value();
    }
}
