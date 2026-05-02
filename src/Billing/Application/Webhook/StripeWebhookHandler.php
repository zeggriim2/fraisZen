<?php

declare(strict_types=1);

namespace App\Billing\Application\Webhook;

use App\Auth\Domain\Repository\UserRepositoryInterface;
use App\Auth\Domain\ValueObject\UserId;
use Stripe\Checkout\Session;
use Stripe\Invoice;
use Stripe\Subscription;

final readonly class StripeWebhookHandler
{
    public function __construct(private UserRepositoryInterface $userRepository)
    {
    }

    public function onCheckoutCompleted(Session $session): void
    {
        $userId = $session->metadata->user_id ?? null;
        if (!$userId) {
            return;
        }

        $user = $this->userRepository->findById(UserId::fromString($userId));
        if (!$user) {
            return;
        }

        if (is_string($session->customer)) {
            $user->setStripeCustomerId($session->customer);
        }
        $user->setSubscriptionStatus('active');
        $this->userRepository->save($user);
    }

    public function onPaymentFailed(Invoice $invoice): void
    {
        if (is_string($invoice->customer)) {
            $this->updateStatusByCustomer($invoice->customer, 'past_due');
        }
    }

    public function onSubscriptionDeleted(Subscription $subscription): void
    {
        if (is_string($subscription->customer)) {
            $this->updateStatusByCustomer($subscription->customer, 'canceled');
        }
    }

    private function updateStatusByCustomer(string $customerId, string $status): void
    {
        $user = $this->userRepository->findByStripeCustomerId($customerId);
        if (!$user) {
            return;
        }

        $user->setSubscriptionStatus($status);
        $this->userRepository->save($user);
    }
}
