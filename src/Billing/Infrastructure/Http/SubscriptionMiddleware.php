<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Http;

use App\Auth\Domain\Entity\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 4)]
final readonly class SubscriptionMiddleware
{
    private const EXEMPT_PREFIXES = [
        '/api/auth/',
        '/api/billing/',
        '/api/admin/',
    ];

    public function __construct(private TokenStorageInterface $tokenStorage) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) return;

        $path = $event->getRequest()->getPathInfo();
        foreach (self::EXEMPT_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) return;
        }

        $token = $this->tokenStorage->getToken();
        $user  = $token?->getUser();

        if (!$user instanceof User) return;

        if ($user->subscriptionStatus() !== 'active') {
            $event->setResponse(new JsonResponse(
                ['error' => 'Abonnement requis.', 'code' => 'subscription_required'],
                Response::HTTP_PAYMENT_REQUIRED,
            ));
        }
    }
}
