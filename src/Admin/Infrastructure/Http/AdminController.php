<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http;

use App\Admin\Domain\Entity\FiscalConfig;
use App\Admin\Domain\Repository\FiscalConfigRepositoryInterface;
use App\Auth\Domain\Entity\User;
use App\Auth\Domain\Repository\UserRepositoryInterface;
use App\Auth\Domain\ValueObject\UserId;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Person\Domain\Repository\PersonRepositoryInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Stripe\StripeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PersonRepositoryInterface $personRepository,
        private readonly ExpenseRepositoryInterface $expenseRepository,
        private readonly FiscalConfigRepositoryInterface $fiscalConfigRepository,
        private readonly StripeClient $stripe,
        private readonly JWTEncoderInterface $jwtEncoder,
    ) {}

    #[Route('/stats', methods: [Request::METHOD_GET])]
    public function stats(): JsonResponse
    {
        $totalUsers = $this->userRepository->count();
        $allUsers   = $this->userRepository->findAll();

        $activeUsers    = count(array_filter($allUsers, fn (User $u) => $u->subscriptionStatus() === 'active'));
        $inactiveUsers  = $totalUsers - $activeUsers;

        $mrr = 0.0;
        $arr = 0.0;

        try {
            $subscriptions = $this->stripe->subscriptions->all(['status' => 'active', 'limit' => 100]);
            foreach ($subscriptions->data as $sub) {
                foreach ($sub->items->data as $item) {
                    $price = $item->price;
                    $amount = $price->unit_amount / 100;
                    if ($price->recurring->interval === 'month') {
                        $mrr += $amount * $item->quantity;
                    } elseif ($price->recurring->interval === 'year') {
                        $mrr += ($amount / 12) * $item->quantity;
                    }
                }
            }
            $arr = $mrr * 12;
        } catch (\Throwable) {
            // Stripe not configured or unreachable — return zeros
        }

        return $this->json([
            'totalUsers'   => $totalUsers,
            'activeUsers'  => $activeUsers,
            'inactiveUsers' => $inactiveUsers,
            'mrr'          => round($mrr, 2),
            'arr'          => round($arr, 2),
        ]);
    }

    #[Route('/users', methods: [Request::METHOD_GET])]
    public function users(Request $request): JsonResponse
    {
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $page   = max(1, (int) $request->query->get('page', 1));
        $limit  = 20;

        $users = $search
            ? $this->userRepository->findByEmailLike($search)
            : $this->userRepository->findAll();

        if ($status !== '') {
            $users = array_values(array_filter($users, fn (User $u) => $u->subscriptionStatus() === $status));
        }

        $total  = count($users);
        $offset = ($page - 1) * $limit;
        $paged  = array_slice($users, $offset, $limit);

        $items = array_map(function (User $u) {
            $persons = $this->personRepository->findAllByUserId($u->id()->value());
            return array_merge($u->toArray(), ['personCount' => count($persons)]);
        }, $paged);

        return $this->json([
            'items' => $items,
            'total' => $total,
            'page'  => $page,
            'pages' => (int) ceil($total / $limit),
        ]);
    }

    #[Route('/users/export', methods: [Request::METHOD_GET])]
    public function exportCsv(Request $request): StreamedResponse
    {
        $users = $this->userRepository->findAll();

        $response = new StreamedResponse(function () use ($users) {
            $handle = fopen('php://output', 'w');
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
        $response->headers->set('Content-Disposition', 'attachment; filename="users-' . date('Y-m-d') . '.csv"');

        return $response;
    }

    #[Route('/users/{id}', methods: [Request::METHOD_GET])]
    public function userDetail(string $id): JsonResponse
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

    #[Route('/users/{id}/subscription', methods: [Request::METHOD_PATCH])]
    public function updateSubscription(string $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->findById(UserId::fromString($id));
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $data   = json_decode($request->getContent(), true) ?? [];
        $status = $data['status'] ?? '';

        if (!in_array($status, ['active', 'canceled', 'inactive'], true)) {
            return $this->json(['error' => 'Invalid status'], Response::HTTP_BAD_REQUEST);
        }

        $user->setSubscriptionStatus($status);
        $this->userRepository->save($user);

        return $this->json(['success' => true]);
    }

    #[Route('/users/{id}', methods: [Request::METHOD_DELETE])]
    public function deleteUser(string $id): JsonResponse
    {
        $user = $this->userRepository->findById(UserId::fromString($id));
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $this->userRepository->delete($user);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/users/{id}/impersonate', methods: [Request::METHOD_POST])]
    public function impersonate(string $id): JsonResponse
    {
        $user = $this->userRepository->findById(UserId::fromString($id));
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $token = $this->jwtEncoder->encode([
            'username' => $user->getUserIdentifier(),
            'roles'    => $user->getRoles(),
        ]);

        return $this->json(['token' => $token]);
    }

    #[Route('/fiscal-config', methods: [Request::METHOD_GET])]
    public function listFiscalConfigs(): JsonResponse
    {
        $configs = $this->fiscalConfigRepository->findAll();

        return $this->json(array_map(fn (FiscalConfig $c) => $c->toArray(), $configs));
    }

    #[Route('/fiscal-config/{year}', methods: [Request::METHOD_GET])]
    public function getFiscalConfig(int $year): JsonResponse
    {
        $config = $this->fiscalConfigRepository->findByYear($year);
        if (!$config) {
            return $this->json(['error' => 'No config for this year'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($config->toArray());
    }

    #[Route('/fiscal-config/{year}', methods: [Request::METHOD_PUT])]
    public function upsertFiscalConfig(int $year, Request $request): JsonResponse
    {
        $data          = json_decode($request->getContent(), true) ?? [];
        $allowance     = $data['remoteWorkDailyAllowance'] ?? null;
        $homeMealValue = $data['homeMealValue'] ?? null;

        if (!is_numeric($allowance) || $allowance <= 0) {
            return $this->json(['error' => 'Invalid remoteWorkDailyAllowance'], Response::HTTP_BAD_REQUEST);
        }
        if ($homeMealValue !== null && (!is_numeric($homeMealValue) || $homeMealValue <= 0)) {
            return $this->json(['error' => 'Invalid homeMealValue'], Response::HTTP_BAD_REQUEST);
        }

        $config = $this->fiscalConfigRepository->findByYear($year);
        if ($config) {
            $config->setRemoteWorkDailyAllowance((float) $allowance);
            if ($homeMealValue !== null) $config->setHomeMealValue((float) $homeMealValue);
        } else {
            $config = new FiscalConfig($year, (float) $allowance, $homeMealValue ? (float) $homeMealValue : 5.35);
        }

        $this->fiscalConfigRepository->save($config);

        return $this->json($config->toArray());
    }
}
