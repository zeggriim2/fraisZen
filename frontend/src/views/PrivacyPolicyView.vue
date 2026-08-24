<template>
  <main class="min-h-screen bg-gray-50 p-6">
    <section class="mx-auto max-w-3xl bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-5">
      <div>
        <RouterLink to="/login" class="text-sm text-indigo-600 hover:underline">← Retour</RouterLink>
        <h1 class="text-2xl font-bold text-gray-900 mt-4">Politique de confidentialité</h1>
        <p class="text-sm text-gray-500 mt-1">Version de travail à valider avant mise en production.</p>
      </div>

      <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
        FraisZen manipule des données sensibles : revenus, frais, adresses, justificatifs et données fiscales.
        Cette page formalise les engagements minimums à finaliser avant lancement public.
      </div>

      <section class="space-y-2 text-sm text-gray-700">
        <h2 class="text-lg font-semibold text-gray-900">Données collectées</h2>
        <ul class="list-disc pl-5 space-y-1">
          <li>compte utilisateur : email, mot de passe chiffré, paramètres ;</li>
          <li>profils du foyer fiscal : nom, prénom, email optionnel ;</li>
          <li>frais professionnels : dates, montants, trajets, descriptions ;</li>
          <li>justificatifs téléversés ;</li>
          <li>données d’abonnement traitées par Stripe.</li>
        </ul>
      </section>

      <section class="space-y-2 text-sm text-gray-700">
        <h2 class="text-lg font-semibold text-gray-900">Finalités</h2>
        <p>Les données servent à calculer, organiser et exporter un récapitulatif des frais réels.</p>
      </section>

      <section class="space-y-2 text-sm text-gray-700">
        <h2 class="text-lg font-semibold text-gray-900">Droits utilisateur</h2>
        <p>
          L’utilisateur doit pouvoir demander l’accès, la rectification, l’export et la suppression de ses données.
          La suppression du compte doit entraîner la suppression des frais et justificatifs associés, sous réserve des obligations légales.
        </p>
      </section>

      <section class="space-y-2 text-sm text-gray-700">
        <h2 class="text-lg font-semibold text-gray-900">Avertissement fiscal</h2>
        <p>
          Les estimations fournies sont indicatives. L’utilisateur reste responsable des informations déclarées
          et doit conserver les justificatifs nécessaires en cas de contrôle fiscal.
        </p>
      </section>
    </section>
  </main>
</template>
