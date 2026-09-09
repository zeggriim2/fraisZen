# Agent Dev FraisZen

## Mission

Transformer une issue GitHub prête en branche de développement, tests, code, contrôles Docker et PR draft vers `develop`.

## Déclencheur

Issue ouverte avec le label :

```text
ready-for-dev
```

Ignorer toute issue avec :

```text
do-not-ai
blocked
needs-spec
```

## Règles Git

- Toujours partir de `origin/develop`.
- Ne jamais modifier directement `develop`.
- Ne jamais modifier directement `main`.
- Ne jamais merger.
- Ne jamais force-push.
- Ne jamais supprimer de branche distante sans demande explicite.
- Une issue = une branche = une PR.

## Convention de branche

- Feature : `feat/issue-XX-description-courte`
- Fix : `fix/issue-XX-description-courte`
- Chore : `chore/issue-XX-description-courte`
- Docs : `docs/issue-XX-description-courte`

## Auteur Git

```text
Zeggriim <lilian.dorazio@hotmail.fr>
```

## Workflow obligatoire

1. Lire l’issue et les critères d’acceptation.
2. Déterminer le type de branche.
3. Créer la branche depuis `origin/develop`.
4. Ajouter ou adapter les tests.
5. Implémenter le code.
6. Lancer les checks Docker.
7. Corriger jusqu’à un état acceptable.
8. Committer.
9. Pousser la branche.
10. Créer une PR draft vers `develop`.
11. Documenter les tests exécutés dans la PR.

## Checks Docker

Utiliser les commandes du `makefile` :

```bash
make up
make test
make analyse
```

Si nécessaire :

```bash
make playwright-install
make test-browser
```

Ne jamais prétendre qu’un test a été exécuté si la commande n’a pas réellement tourné.

## Sécurité et RGPD

Vérifier systématiquement :

- ownership utilisateur ;
- accès multi-tenant ;
- absence de secret ;
- données personnelles ;
- justificatifs ;
- revenus et données fiscales ;
- validation des entrées utilisateur.

## Blocage

Si l’agent ne peut pas continuer :

1. poster un commentaire sur l’issue ou la PR ;
2. expliquer le blocage ;
3. ajouter `blocked` ou `needs-spec` ;
4. arrêter sans inventer de résultat.
