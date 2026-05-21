Prépare et pousse un tag de release SemVer sur `main` pour déclencher le workflow CD GitHub Actions.

## Étape 1 — Vérification de l'état du dépôt

Exécute en parallèle :
- `rtk git status`
- `rtk git branch`
- `rtk git tag --sort=-v:refname | head -5`

Si des fichiers sont modifiés (non commités), **arrête** et informe l'utilisateur qu'il faut commiter ou stasher avant de releaser.

## Étape 2 — Rebase de `main` sur `develop`

L'objectif est de ramener tous les commits de `develop` dans `main` avant de tagger.

Exécute dans cet ordre (séquentiel) :

```bash
rtk git checkout main
rtk git pull origin main
rtk git rebase origin/develop
```

Si le rebase échoue (conflits), **arrête** et informe l'utilisateur des fichiers en conflit. Ne pas résoudre automatiquement.

## Étape 3 — Détermination de la version SemVer

Récupère le dernier tag existant :

```bash
rtk git tag --sort=-v:refname | head -1
```

Le tag courant est de la forme `vX.Y.Z` (ou `VX.Y.Z` — normalise en minuscule). Si aucun tag n'existe, pars de `v0.0.0`.

Récupère les commits depuis ce tag :

```bash
rtk git log <dernier-tag>..HEAD --oneline
```

**Règles SemVer basées sur les Conventional Commits :**

| Commits trouvés | Bump suggéré |
|----------------|-------------|
| Au moins un commit avec `BREAKING CHANGE` dans le footer ou `!` après le type (ex: `feat!:`) | **MAJOR** → `vX+1.0.0` |
| Au moins un `feat(...)` sans breaking change | **MINOR** → `vX.Y+1.0` |
| Uniquement `fix`, `perf`, `refactor`, `chore`, `docs`, `ci`, `style`, `test` | **PATCH** → `vX.Y.Z+1` |

Calcule les 3 versions possibles à partir du tag courant :
- `vX.Y.Z+1` (patch)
- `vX.Y+1.0` (minor)
- `vX+1.0.0` (major)

Affiche la liste des commits depuis le dernier tag (titre uniquement, sans hachage).

## Étape 4 — Proposition du tag à l'utilisateur

Propose via `AskUserQuestion` :

**Question** — Quelle version veux-tu releaser ? (version suggérée en premier)

- `vX.Y.Z+1` — patch : corrections de bugs (Recommandé si uniquement des fix)
- `vX.Y+1.0` — minor : nouvelles fonctionnalités sans breaking change (Recommandé si des feat)
- `vX+1.0.0` — major : breaking change

**Important :** place la version suggérée par l'analyse SemVer en première option avec `(Recommandé)` à la fin du label.

## Étape 5 — Création et push du tag

Une fois la version confirmée :

```bash
rtk git tag <version-choisie>
rtk git push origin main
rtk git push origin <version-choisie>
```

Affiche la confirmation :
- URL du workflow GitHub Actions : `https://github.com/<owner>/<repo>/actions`
- Tag créé et poussé
- Branche `main` mise à jour

Termine par un récapitulatif : tag créé, commits inclus depuis le tag précédent, workflow CD déclenché.
