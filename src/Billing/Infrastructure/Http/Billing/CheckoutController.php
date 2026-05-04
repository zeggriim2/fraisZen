<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Http\Billing;

use App\Auth\Domain\Entity\User;
use Stripe\StripeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/checkout', name: 'checkout', methods: [Request::METHOD_POST])]
class CheckoutController extends AbstractController
{
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly string $priceIdMonthly,
        private readonly string $priceIdYearly,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true) ?? [];
        $plan = $data['plan'] ?? 'monthly';
        $priceId = 'yearly' === $plan ? $this->priceIdYearly : $this->priceIdMonthly;

        $successUrl = rtrim($request->headers->get('Origin', 'https://localhost:5173'), '/').'/?checkout=success';
        $cancelUrl = rtrim($request->headers->get('Origin', 'https://localhost:5173'), '/').'/pricing?checkout=cancel';

        $params = [
            'mode' => 'subscription',
            'line_items' => [['price' => $priceId, 'quantity' => 1]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'customer_email' => $user->email(),
            'metadata' => ['user_id' => $user->id()->value()],
            'subscription_data' => ['metadata' => ['user_id' => $user->id()->value()]],
        ];

        if ($user->stripeCustomerId()) {
            unset($params['customer_email']);
            $params['customer'] = $user->stripeCustomerId();
        }

        $session = $this->stripe->checkout->sessions->create($params);

        return $this->json(['url' => $session->url]);
    }
}
