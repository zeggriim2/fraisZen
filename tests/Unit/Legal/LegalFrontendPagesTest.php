<?php

declare(strict_types=1);

function frontendFile(string $relativePath): string
{
    $path = dirname(__DIR__, 3).'/frontend/'.$relativePath;

    if (!is_file($path)) {
        throw new RuntimeException(sprintf('Frontend file not found: %s', $path));
    }

    return file_get_contents($path) ?: '';
}

it('expose les pages légales en routes publiques', function () {
    $router = frontendFile('src/router/index.ts');

    expect($router)->toContain("path: '/privacy'");
    expect($router)->toContain("path: '/legal'");
    expect($router)->toContain("meta: { public: true }");
});

it('documente les données sensibles dans la politique de confidentialité', function () {
    $privacy = frontendFile('src/views/PrivacyPolicyView.vue');

    expect($privacy)->toContain('revenus');
    expect($privacy)->toContain('frais');
    expect($privacy)->toContain('justificatifs');
    expect($privacy)->toContain('Stripe');
    expect($privacy)->toContain('suppression de ses données');
    expect($privacy)->toContain('ne remplacent pas un conseil fiscal personnalisé');
});

it('affiche un squelette de mentions légales à compléter avant production', function () {
    $legal = frontendFile('src/views/LegalNoticeView.vue');

    expect($legal)->toContain('Mentions légales');
    expect($legal)->toContain('À compléter');
    expect($legal)->toContain('Éditeur du service');
    expect($legal)->toContain('Hébergement');
    expect($legal)->toContain('ne remplacent pas un conseil fiscal personnalisé');
});

it('corrige la promesse commerciale du tarif annuel', function () {
    $pricing = frontendFile('src/views/PricingView.vue');

    expect($pricing)->toContain('environ 10 % de réduction');
    expect($pricing)->not->toContain('2 mois offerts');
});
