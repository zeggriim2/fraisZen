<?php

declare(strict_types=1);

it('affiche le calendrier des dépenses après connexion', function (): void {
    visit(APP_URL.'/login')
        ->type('input[type="email"]', TEST_EMAIL)
        ->type('input[type="password"]', TEST_PASSWORD)
        ->press('Se connecter')
        ->waitForText('Calendrier')
        ->assertPathIs('/calendar')
        ->assertSee('Calendrier')
        ->assertNoSmoke();
});

it('navigue vers la page récapitulatif', function (): void {
    visit(APP_URL.'/login')
        ->type('input[type="email"]', TEST_EMAIL)
        ->type('input[type="password"]', TEST_PASSWORD)
        ->press('Se connecter')
        ->waitForText('Calendrier')
        ->click('a[href="/summary"]')
        ->waitForText('Récapitulatif fiscal')
        ->assertPathIs('/summary')
        ->assertNoSmoke();
});

it('ne génère pas d\'erreurs JavaScript sur la page calendrier', function (): void {
    visit(APP_URL.'/login')
        ->type('input[type="email"]', TEST_EMAIL)
        ->type('input[type="password"]', TEST_PASSWORD)
        ->press('Se connecter')
        ->waitForText('Calendrier')
        ->assertNoJavaScriptErrors();
});
