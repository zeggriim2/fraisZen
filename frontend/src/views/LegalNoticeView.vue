<template>
  <main class="min-h-screen bg-gray-50 p-6">
    <section class="mx-auto max-w-3xl bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-5">
      <div>
        <RouterLink to="/login" class="text-sm text-indigo-600 hover:underline">← Retour</RouterLink>
        <h1 class="text-2xl font-bold text-gray-900 mt-4">Mentions légales</h1>
        <p class="text-sm text-gray-500 mt-1">Informations à compléter avant ouverture publique du service.</p>
      </div>

      <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
        <strong>À compléter :</strong> ces mentions constituent un squelette produit/RGPD. Les informations éditeur,
        hébergeur, contact et conditions commerciales doivent être validées avant mise en production.
      </div>

      <section class="space-y-2 text-sm text-gray-700">
        <h2 class="text-lg font-semibold text-gray-900">Éditeur du service</h2>
        <p>FraisZen — service d’aide au suivi des frais professionnels réels.</p>
        <p>Responsable de publication : à compléter.</p>
        <p>Email de contact : à compléter.</p>
      </section>

      <section class="space-y-2 text-sm text-gray-700">
        <h2 class="text-lg font-semibold text-gray-900">Hébergement</h2>
        <p>Hébergeur : à compléter selon l’infrastructure retenue.</p>
        <p>Localisation recommandée : Union européenne.</p>
      </section>

      <section class="space-y-2 text-sm text-gray-700">
        <h2 class="text-lg font-semibold text-gray-900">Nature du service</h2>
        <p>
          FraisZen fournit une aide à la saisie, au classement et au récapitulatif des frais professionnels.
          Les calculs sont fournis à titre indicatif et ne remplacent pas un conseil fiscal personnalisé.
        </p>
      </section>
    </section>
  </main>
</template>
