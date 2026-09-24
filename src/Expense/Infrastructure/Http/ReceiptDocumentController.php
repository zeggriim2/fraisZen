<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Http;

use App\Auth\Domain\Entity\User;
use App\Expense\Application\Command\AttachReceiptLine\AttachReceiptLineCommand;
use App\Expense\Application\Command\CreateTollExpenseFromReceiptLine\CreateTollExpenseFromReceiptLineCommand;
use App\Expense\Application\Command\DeleteReceiptDocument\DeleteReceiptDocumentCommand;
use App\Expense\Application\Command\ReviewReceiptMatch\ReviewReceiptMatchCommand;
use App\Expense\Application\Command\UploadReceiptDocument\UploadReceiptDocumentCommand;
use App\Expense\Application\Query\GetReceiptDocument\GetReceiptDocumentQuery;
use App\Expense\Application\Query\GetReceiptDocumentFile\GetReceiptDocumentFileQuery;
use App\Expense\Application\Query\GetReceiptDocuments\GetReceiptDocumentsQuery;
use App\Expense\Application\Query\GetReceiptLineCandidates\GetReceiptLineCandidatesQuery;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use App\SharedKernel\Infrastructure\Security\OwnershipGuardInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReceiptDocumentController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commands,
        private readonly QueryBusInterface $queries,
        private readonly OwnershipGuardInterface $guard,
    ) {
    }

    #[Route('/api/receipt-documents', methods: [Request::METHOD_GET])]
    public function list(Request $request): JsonResponse
    {
        $personId = $request->query->getString('personId');
        $this->guard->assertPersonBelongsToUser($personId, $this->userId());

        return $this->json(
            $this->queries->ask(
                new GetReceiptDocumentsQuery(
                    $personId,
                    $request->query->getInt('year', (int) date('Y')),
                    $request->query->getInt('page', 1),
                    $request->query->getInt('pageSize', 6)
                )
            )
        );
    }

    #[Route('/api/receipt-documents', methods: [Request::METHOD_POST])]
    public function upload(Request $request): JsonResponse
    {
        $personId = $request->getPayload()->getString('personId');
        $this->guard->assertPersonBelongsToUser($personId, $this->userId());

        $file = $request->files->get('receipt');

        if (null === $file) {
            throw new \DomainException('Aucun fichier reçu.');
        }

        return $this->json(
            $this->commands->dispatch(
                new UploadReceiptDocumentCommand(
                    $personId,
                    $file->getPathname(),
                    $file->getClientOriginalName(),
                    $file->getMimeType(),
                    $file->getSize())),
            Response::HTTP_CREATED
        );
    }

    #[Route('/api/receipt-documents/{id}', methods: [Request::METHOD_GET])]
    public function show(string $id, Request $request): JsonResponse
    {
        $personId = $request->query->getString('personId');
        $this->guard->assertPersonBelongsToUser($personId, $this->userId());

        return $this->json($this->queries->ask(new GetReceiptDocumentQuery($id, $personId)));
    }

    #[Route('/api/receipt-documents/{id}/file', methods: [Request::METHOD_GET])]
    public function download(string $id, Request $request): BinaryFileResponse
    {
        $personId = $request->query->getString('personId');
        $this->guard->assertPersonBelongsToUser($personId, $this->userId());

        return parent::file($this->queries->ask(new GetReceiptDocumentFileQuery($id, $personId)));
    }

    #[Route('/api/receipt-documents/{id}', methods: [Request::METHOD_DELETE])]
    public function delete(string $id, Request $request): Response
    {
        $personId = $request->query->getString('personId');
        $this->guard->assertPersonBelongsToUser($personId, $this->userId());

        $this->commands->dispatch(new DeleteReceiptDocumentCommand($id, $personId));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/receipt-documents/{documentId}/matches/{matchId}/{decision}', methods: [Request::METHOD_POST])]
    public function review(string $documentId, string $matchId, string $decision, Request $request): JsonResponse
    {
        $personId = $request->getPayload()->getString('personId');
        $this->guard->assertPersonBelongsToUser($personId, $this->userId());

        if (!in_array($decision, ['confirm', 'reject'], true)) {
            throw new \DomainException('Décision invalide.');
        }

        return $this->json(
            $this->commands->dispatch(
                new ReviewReceiptMatchCommand($matchId, $documentId, $personId, 'confirm' === $decision)
            )
        );
    }

    #[Route('/api/receipt-documents/{documentId}/lines/{lineId}/candidates', methods: [Request::METHOD_GET])]
    public function candidates(string $documentId, string $lineId, Request $request): JsonResponse
    {
        $personId = $request->query->getString('personId');
        $this->guard->assertPersonBelongsToUser($personId, $this->userId());

        return $this->json(
            $this->queries->ask(
                new GetReceiptLineCandidatesQuery($documentId, $lineId, $personId)
            )
        );
    }

    #[Route('/api/receipt-documents/{documentId}/lines/{lineId}/attach', methods: [Request::METHOD_POST])]
    public function attach(string $documentId, string $lineId, Request $request): JsonResponse
    {
        $personId = $request->getPayload()->getString('personId');
        $expenseId = $request->getPayload()->getString('expenseId');
        $this->guard->assertPersonBelongsToUser($personId, $this->userId());

        return $this->json(
            $this->commands->dispatch(
                new AttachReceiptLineCommand($documentId, $lineId, $expenseId, $personId)
            )
        );
    }

    #[Route('/api/receipt-documents/{documentId}/lines/{lineId}/create-expense', methods: [Request::METHOD_POST])]
    public function createExpense(string $documentId, string $lineId, Request $request): JsonResponse
    {
        $personId = $request->getPayload()->getString('personId');
        $this->guard->assertPersonBelongsToUser($personId, $this->userId());

        return $this->json(
            $this->commands->dispatch(new CreateTollExpenseFromReceiptLineCommand($documentId, $lineId, $personId)),
            Response::HTTP_CREATED,
        );
    }

    private function userId(): string
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user->id()->value();
    }
}
