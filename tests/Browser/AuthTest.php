<?php

declare(strict_types=1);

it('affiche la page de connexion', function (): void {
    visit(APP_URL.'/login')
        ->assertSee('Frais Réels')
        ->assertPresent('input[type="email"]')
        ->assertPresent('input[type="password"]')
        ->assertSee('Se connecter');
});

it('affiche une erreur avec des identifiants invalides', function (): void {
    visit(APP_URL.'/login')
        ->type('input[type="email"]', 'invalide@test.com')
        ->type('input[type="password"]', 'mauvaismdp')
        ->press('Se connecter')
        ->waitForText('Email ou mot de passe incorrect.')
        ->assertSee('Email ou mot de passe incorrect.');
});

it('redirige vers le calendrier après connexion réussie', function (): void {
    visit(APP_URL.'/login')
        ->type('input[type="email"]', TEST_EMAIL)
        ->type('input[type="password"]', TEST_PASSWORD)
        ->press('Se connecter')
        ->waitForText('Calendrier')
        ->assertPathIs('/calendar');
});

it('ne génère pas d\'erreurs JavaScript sur la page de connexion', function (): void {
    visit(APP_URL.'/login')
        ->assertNoSmoke();
});
