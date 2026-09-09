# Workflow agents IA FraisZen

Ce document décrit le workflow cible pour faire travailler des agents Hermes sur FraisZen via GitHub Issues, labels, branches et pull requests.

## Objectifs

- Automatiser le développement depuis des issues GitHub prêtes.
- Créer une branche dédiée depuis `origin/develop`.
- Ajouter ou modifier les tests avec le code.
- Exécuter les contrôles via Docker uniquement.
- Ouvrir une PR vers `develop`.
- Lancer une review IA sous forme de commentaire Markdown.
- Garder le merge final manuel par Lilian.

## Principes non négociables

- `develop` est la branche d’intégration.
- `main` reste la branche stable/production.
- Aucun agent ne push directement sur `develop` ou `main`.
- Aucun agent ne merge une PR.
- Une issue traitée par l’IA = une branche = une PR.
- Les outils projet sont exécutés dans Docker, pas installés directement sur la machine.
- Les résultats de tests doivent être réels : l’agent ne doit jamais inventer une sortie de test.

## Labels GitHub

| Label | Usage |
|---|---|
| `ready-for-dev` | Issue prête pour l’Agent Dev |
| `ai-in-progress` | Issue en cours de traitement par l’Agent Dev |
| `ai-pr-opened` | Une PR a été créée par l’agent |
| `ai-review` | PR prête pour review IA |
| `ai-reviewed` | Review IA effectuée |
| `need-rework` | Corrections nécessaires |
| `ready-for-human-review` | PR relisible par Lilian |
| `blocked` | Agent bloqué |
| `needs-spec` | Issue trop floue ou critères insuffisants |
| `do-not-ai` | L’IA ne doit pas traiter cette issue/PR |

## Cycle Agent Dev

Déclencheur : issue ouverte avec le label `ready-for-dev`.

1. Lire l’issue et ses critères d’acceptation.
2. Si l’issue est floue, commenter l’issue, ajouter `needs-spec`, retirer `ready-for-dev`, puis arrêter.
3. Retirer `ready-for-dev`, ajouter `ai-in-progress`.
4. Créer une branche depuis `origin/develop`.
5. Ajouter ou modifier les tests nécessaires.
6. Implémenter la fonctionnalité ou le correctif.
7. Démarrer l’environnement Docker.
8. Lancer les tests et contrôles qualité via `make`/Docker.
9. Corriger jusqu’à obtention d’un état vert ou documenter le blocage.
10. Pousser la branche.
11. Créer une PR **draft** vers `develop`.
12. Ajouter `ai-pr-opened` sur l’issue et `ai-review` sur la PR.
13. Poster un résumé avec les changements et les tests exécutés.

## Cycle Agent Review

Déclencheur : PR ouverte vers `develop` avec le label `ai-review`.

1. Lire la PR, son issue liée et le diff.
2. Vérifier architecture Symfony, sécurité, tests, RGPD, fiscalité et frontend/backend.
3. Poster un commentaire Markdown de review.
4. Si tout est acceptable : retirer `ai-review`, ajouter `ai-reviewed` et `ready-for-human-review`.
5. Si des corrections sont nécessaires : retirer `ai-review`, ajouter `need-rework`.
6. Ne jamais merger et ne jamais pousser de code depuis l’Agent Review.

## Cycle Rework

Déclencheur : PR avec le label `need-rework`.

- Rework automatique autorisé uniquement pour problèmes techniques clairs :
  - test cassé ;
  - PHPStan/Psalm fail ;
  - CS fixer ;
  - erreur typographique évidente ;
  - bug technique bien décrit par la review.
- Validation humaine obligatoire si la correction touche :
  - règle métier fiscale ;
  - RGPD ou données personnelles ;
  - UX importante ;
  - décision produit ;
  - changement d’architecture significatif.

Après correction : relancer les checks Docker, pousser, retirer `need-rework`, ajouter `ai-review`.

## Commandes Docker de référence

Les commandes officielles du repo passent par le `makefile` et Docker Compose :

```bash
make up
make test
make test-browser
make lint
make phpstan
make psalm
make typecheck
make actionlint
make analyse
```

La commande globale actuelle est :

```bash
make analyse
```

Elle exécute :

```text
lint → phpstan → psalm → typecheck → hadolint → actionlint
```

Pour les tests backend :

```bash
make test
```

Pour les tests browser :

```bash
make playwright-install
make test-browser
```

## GitHub Actions

La CI GitHub actuelle expose les jobs :

- `Tests`
- `Code Quality`
- `Lint`

La protection de branche `develop` doit demander une PR, interdire le force-push et exiger une validation humaine. Les checks ci-dessus peuvent être rendus obligatoires une fois confirmés comme stables côté GitHub.

## Merge

Le merge final reste manuel par Lilian, même si la PR porte `ready-for-human-review`.
