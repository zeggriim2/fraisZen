<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Http\Billing;

use App\Billing\Application\Webhook\StripeWebhookHandler;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Invoice;
use Stripe\Subscription;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/webhook', name: 'Webhook', methods: [Request::METHOD_POST])]
class WebhookController
{
    public function __construct(
        private readonly StripeWebhookHandler $webhookHandler,
        private readonly string $webhookSecret,
    ) {
    }

    public function __invoke(Request $request): Response
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
