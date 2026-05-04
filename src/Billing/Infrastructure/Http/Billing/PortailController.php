<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Http\Billing;

use App\Auth\Domain\Entity\User;
use Stripe\StripeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal', name: 'portail', methods: [Request::METHOD_POST])]
class PortailController extends AbstractController
{
    public function __construct(
        private readonly StripeClient $stripe,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->stripeCustomerId()) {
            return $this->json(['error' => 'Aucun abonnement actif.'], Response::HTTP_BAD_REQUEST);
        }

        $returnUrl = rtrim($request->headers->get('Origin', 'https://localhost:5173'), '/').'/settings';

        $session = $this->stripe->billingPortal->sessions->create([
            'customer' => $user->stripeCustomerId(),
            'return_url' => $returnUrl,
        ]);

        return $this->json(['url' => $session->url]);
    }
}
