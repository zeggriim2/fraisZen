Lance une analyse complète de la qualité du code et rapporte les résultats.

Exécute les commandes suivantes **séquentiellement** via le Bash tool (préfixe `rtk` selon les règles du projet) :

1. `rtk make lint` — PHP CS Fixer (style)
2. `rtk make phpstan` — PHPStan niveau 5
3. `rtk make psalm` — Psalm analyse statique
4. `rtk make typecheck` — TypeScript tsc --noEmit
5. `rtk make hadolint` — Dockerfile best practices
6. `rtk make actionlint` — GitHub Actions workflows

Pour chaque étape :
- Indique clairement si elle passe ✓ ou échoue ✗
- En cas d'échec, affiche les erreurs pertinentes
- Continue les étapes suivantes même si une échoue (ne t'arrête pas au premier échec)

À la fin, donne un **résumé en tableau** : outil | statut | nb d'erreurs.
Si des erreurs sont trouvées, propose de les corriger automatiquement (oui/non).
