# Checklist Review IA FraisZen

## Git / PR

- [ ] La PR cible `develop`.
- [ ] La branche ne modifie pas directement `develop` ou `main`.
- [ ] Le nom de branche correspond à l’issue.
- [ ] La PR référence l’issue avec `Closes #XX` si applicable.

## Tests

- [ ] Tests unitaires ajoutés ou adaptés.
- [ ] Tests browser ajoutés si le parcours utilisateur est touché.
- [ ] `make test` exécuté ou CI équivalente vérifiée.
- [ ] `make analyse` exécuté ou CI équivalente vérifiée.
- [ ] Les résultats sont documentés dans la PR.

## Sécurité

- [ ] Ownership utilisateur vérifié.
- [ ] Pas de fuite multi-tenant.
- [ ] Aucun secret commit.
- [ ] Validation des entrées utilisateur.
- [ ] Contrôle d’accès côté backend, pas seulement frontend.

## RGPD / données sensibles

- [ ] Données personnelles minimisées.
- [ ] Justificatifs protégés.
- [ ] Revenus/frais traités comme données sensibles.
- [ ] Suppression/export des données non régressés.
- [ ] Pas de tracking ou transfert externe non documenté.

## Fiscalité

- [ ] Pas d’affirmation fiscale trop forte sans disclaimer.
- [ ] Les calculs sont couverts par tests.
- [ ] Les cas limites sont traités : zéro, plafond, minimum, arrondis.

## Architecture Symfony

- [ ] Domain/Application/Infrastructure respectés.
- [ ] Pas de logique métier lourde dans Controller.
- [ ] Services testables.
- [ ] Entités cohérentes avec Doctrine.
- [ ] Migrations présentes si schéma modifié.

## Frontend

- [ ] TypeScript passe.
- [ ] API errors gérées.
- [ ] États loading/empty/error traités si nécessaire.
- [ ] Pas de duplication excessive de logique métier backend.
