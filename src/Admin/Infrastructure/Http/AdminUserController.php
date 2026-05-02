<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http;

use App\Auth\Domain\Entity\User;
use App\Auth\Domain\Repository\UserRepositoryInterface;
use App\Auth\Domain\ValueObject\UserId;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Person\Domain\Repository\PersonRepositoryInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Webmozart\Assert\Assert;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminUserController extends AbstractController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PersonRepositoryInterface $personRepository,
        private readonly ExpenseRepositoryInterface $expenseRepository,
        private readonly JWTEncoderInterface $jwtEncoder,
    ) {
    }

    #[Route('/users', methods: [Request::METHOD_GET])]
    public function list(Request $request): JsonResponse
    {
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;

        $users = $search
            ? $this->userRepository->findByEmailLike($search)
            : $this->userRepository->findAll();

        if ('' !== $status) {
            $users = array_values(array_filter($users, fn (User $u) => $u->subscriptionStatus() === $status));
        }

        $total = count($users);
        $offset = ($page - 1) * $limit;
        $paged = array_slice($users, $offset, $limit);

        $items = array_map(function (User $u) {
            $persons = $this->personRepository->findAllByUserId($u->id()->value());

            return array_merge($u->toArray(), ['personCount' => count($persons)]);
        }, $paged);

        return $this->json([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => (int) ceil($total / $limit),
        ]);
    }

    #[Route('/users/export', methods: [Request::METHOD_GET])]
    public function exportCsv(): StreamedResponse
    {
        $users = $this->userRepository->findAll();

        $response = new StreamedResponse(function () use ($users) {
            $handle = fopen('php://output', 'w');
            Assert::resource($handle);
            fputcsv($handle, ['ID', 'Email', 'Statut', 'Inscription', 'Nb personnes']);

            foreach ($users as $user) {
                $persons = $this->personRepository->findAllByUserId($user->id()->value());
                fputcsv($handle, [
                    $user->id()->value(),
                    $user->email(),
                    $user->subscriptionStatus() ?? '',
                    $user->createdAt()->format('Y-m-d H:i:s'),
                    count($persons),
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="users-'.date('Y-m-d').'.csv"');

        return $response;
    }

    #[Route('/users/{id}', requirements: ['id' => Requirement::UUID_V4], methods: [Request::METHOD_GET])]
    public function detail(string $id): JsonResponse
    {
        $user = $this->userRepository->findById(UserId::fromString($id));
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $persons = $this->personRepository->findAllByUserId($id);

        $personsData = array_map(function ($person) {
            return array_merge($person->toArray(), [
                'expenseCount' => $this->expenseRepository->countByPersonId($person->id()->value()),
            ]);
        }, $persons);

        return $this->json(array_merge($user->toArray(), [
            'persons' => $personsData,
        ]));
    }

    #[Route('/users/{id}/subscription', requirements: ['id' => Requirement::UUID_V4], methods: [Request::METHOD_PATCH])]
    public function updateSubscription(string $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->findById(UserId::fromString($id));
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $status = $data['status'] ?? '';

        if (!in_array($status, ['active', 'canceled', 'inactive'], true)) {
            return $this->json(['error' => 'Invalid status'], Response::HTTP_BAD_REQUEST);
        }

        $user->setSubscriptionStatus($status);
        $this->userRepository->save($user);

        return $this->json(['success' => true]);
    }

    #[Route('/users/{id}', requirements: ['id' => Requirement::UUID_V4], methods: [Request::METHOD_DELETE])]
    public function delete(string $id): JsonResponse
    {
        $user = $this->userRepository->findById(UserId::fromString($id));
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $this->userRepository->delete($user);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/users/{id}/impersonate', requirements: ['id' => Requirement::UUID_V4], methods: [Request::METHOD_POST])]
    public function impersonate(string $id): JsonResponse
    {
        $user = $this->userRepository->findById(UserId::fromString($id));
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $token = $this->jwtEncoder->encode([
            'username' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
        ]);

        return $this->json(['token' => $token]);
    }
}
