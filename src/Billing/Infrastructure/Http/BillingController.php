<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Http;

use App\Auth\Domain\Entity\User;
use App\Billing\Application\Webhook\StripeWebhookHandler;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Invoice;
use Stripe\StripeClient;
use Stripe\Subscription;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/billing')]
final class BillingController extends AbstractController
{
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly StripeWebhookHandler $webhookHandler,
        private readonly string $webhookSecret,
        private readonly string $priceIdMonthly,
        private readonly string $priceIdYearly,
    ) {
    }

    #[Route('/checkout', methods: [Request::METHOD_POST])]
    public function checkout(Request $request): JsonResponse
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

    #[Route('/portal', methods: [Request::METHOD_POST])]
    public function portal(Request $request): JsonResponse
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

    #[Route('/webhook', methods: [Request::METHOD_POST])]
    public function webhook(Request $request): Response
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->headers->get('Stripe-Signature', ''),
                $this->webhookSecret,
            );
        } catch (SignatureVerificationException) {
            return new Response('Invalid signature', Response::HTTP_BAD_REQUEST);
        }

        $object = $event->data->object;
        if ('checkout.session.completed' === $event->type && $object instanceof Session) {
            $this->webhookHandler->onCheckoutCompleted($object);
        } elseif ('invoice.payment_failed' === $event->type && $object instanceof Invoice) {
            $this->webhookHandler->onPaymentFailed($object);
        } elseif ('customer.subscription.deleted' === $event->type && $object instanceof Subscription) {
            $this->webhookHandler->onSubscriptionDeleted($object);
        }

        return new Response('', Response::HTTP_OK);
    }
}
