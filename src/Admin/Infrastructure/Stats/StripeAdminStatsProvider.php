<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Stats;

use App\Admin\Application\Stats\AdminStatsProviderInterface;
use App\Auth\Domain\Entity\User;
use App\Auth\Domain\Repository\UserRepositoryInterface;
use Stripe\StripeClient;

final readonly class StripeAdminStatsProvider implements AdminStatsProviderInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private StripeClient $stripe,
    ) {
    }

    public function getStats(): array
    {
        $totalUsers = $this->userRepository->count();
        $allUsers = $this->userRepository->findAll();

        $activeUsers = count(array_filter($allUsers, fn (User $u) => 'active' === $u->subscriptionStatus()));

        $mrr = 0.0;
        $arr = 0.0;

        try {
            $subscriptions = $this->stripe->subscriptions->all(['status' => 'active', 'limit' => 100]);
            foreach ($subscriptions->data as $sub) {
                foreach ($sub->items->data as $item) {
                    $price = $item->price;
                    $amount = (float) ($price->unit_amount ?? 0) / 100.0;
                    if (null !== $price->recurring && 'month' === $price->recurring->interval) {
                        $mrr += $amount * (float) ($item->quantity ?? 1);
                    } elseif (null !== $price->recurring && 'year' === $price->recurring->interval) {
                        $mrr += ($amount / 12.0) * (float) ($item->quantity ?? 1);
                    }
                }
            }
            $arr = $mrr * 12.0;
        } catch (\Throwable) {
            // Stripe not configured or unreachable — return zeros
        }

        return [
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'inactiveUsers' => $totalUsers - $activeUsers,
            'mrr' => round($mrr, 2),
            'arr' => round($arr, 2),
        ];
    }
}
